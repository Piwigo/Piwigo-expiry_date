<?php
/*
Plugin Name: Expiry Date
Version: auto
Description: Set an expiry date on photos. Apply an automatic action when photos expire.
Plugin URI: https://piwigo.org/ext/extension_view.php?eid=920
Author: HWFord
Author URI: https://github.com/HWFord
Has Settings: true
*/

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

if (basename(dirname(__FILE__)) != 'expiry_date')
{
  add_event_handler('init', 'expiry_date_error');
  function expiry_date_error()
  {
    global $page;
    $page['errors'][] = 'Expiry date folder name is incorrect, uninstall the plugin and rename it to "expiry_date"';
  }
  return;
}

// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+

global $prefixeTable;

define('EXPIRY_DATE_ID',      basename(dirname(__FILE__)));
define('EXPIRY_DATE_PATH' ,   PHPWG_PLUGINS_PATH . EXPIRY_DATE_ID . '/');
define('EXPIRY_DATE_DIR',     PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'expiry_date/');
define('EXPIRY_DATE_ADMIN',   get_root_url() . 'admin.php?page=plugin-expiry_date');
define('EXPIRY_DATE_NOTIFICATIONS_TABLE',   $prefixeTable.'expd_notifications');

include_once(EXPIRY_DATE_PATH.'include/admin_events.inc.php');
include_once(EXPIRY_DATE_PATH.'include/functions.inc.php');
include_once(EXPIRY_DATE_PATH.'include/functions_expiration.inc.php');
include_once(EXPIRY_DATE_PATH.'include/functions_prenotifications.inc.php');

// +-----------------------------------------------------------------------+
// | Add event handlers                                                    |
// +-----------------------------------------------------------------------+

// init the plugin
add_event_handler('init', 'expiry_date_init');

/**
 * plugin initialization
 *   - unserialize configuration
 *   - load language
 */
function expiry_date_init()
{
  global $conf;

  load_language('plugin.lang', EXPIRY_DATE_PATH);

  // prepare plugin configuration
  $conf['expiry_date'] = safe_unserialize($conf['expiry_date']);

  // set time between checks for expiring photos (1 day by default)
  $conf['expiry_date_check_period'] = conf_get_param('expiry_date_check_period', 24*60*60);

  $check_expiration_date = false;
  if (isset($conf['expd_last_check']))
  {
    if (strtotime($conf['expd_last_check']) < strtotime($conf['expiry_date_check_period'].' seconds ago'))
    {
      $check_expiration_date = true;
    }
  }
  else
  {
    $check_expiration_date = true;
  }

  if (!$check_expiration_date)
  {
    return;
  }

  $exec_id = expd_single_exec('expiry_date_running', 300);

  if (false === $exec_id)
  {
    return;
  }

  expiry_date_init_actions();
  
  //set time last action taken on photos
  conf_update_param('expd_last_check', date('c'));

  expd_single_exec_end('expiry_date_running', $exec_id);
}

/**
 * Take action on expired photos
 */
function expiry_date_init_actions()
{
  global $conf, $user;

  $query = '
SELECT id, file
  FROM '.IMAGES_TABLE.'
  WHERE expiry_date
;';

  $expiry_date_images = query2array($query, 'id', 'file');

  //check if there is expiring photos with an expiry date else return
  if (empty($expiry_date_images))
  {
    return;
  }

  //BEFORE EXPIRY//

  //  If notifications for admin is set check if prentifications need to be sent
  if (isset($conf['expiry_date']['expd_notify_admin']))
  {
    //Prenotify admins of expiration,
    send_prenotifications_admin();
  }
 
  //If notifications is set check if prentifications need to be sent
  if (isset($conf['expiry_date']['expd_notify']))
  {
    //Prenotify user of expiration
    send_prenotifications_user();
  }      

  //ON EXPIRY//

  //Select all images with an expiry date before now (=expired)
    $query = '
SELECT id, file, name, author, expiry_date
  FROM '.IMAGES_TABLE.'
  WHERE id IN ('.implode(',', array_keys($expiry_date_images)).')
    AND expiry_date <= NOW()
;';

  $result = pwg_query($query);
  
  $images = array();
  $image_ids = array();

  while ($row = pwg_db_fetch_assoc($result))
  {
    array_push($images, $row);
    array_push($image_ids, $row['id']);
  }

  if (empty($image_ids))
  {
    return;
  }

  switch_lang_to(get_default_language());

  $image_details = "\n\n";
  foreach ($images as $image)
  {
    $image_details.= '* '.$image["name"].' '.$image["author"].' ('.$image["file"]."), ".l10n("expired on")." ".format_date($image["expiry_date"])."\n";
    if('delete' != $conf['expiry_date']['expd_action'])
    {
      $image_details.= get_absolute_root_url().'admin.php?page=photo-'.$image['id'];
    }
  }
  $image_details .= "\n";

  // Action taken on expiring images
  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));

  //set email content to notify admins expiration action has taken place
  $subject = get_l10n_args("Expiry date, action has been taken");
  $keyargs_content = array(
    get_l10n_args("These images have reached expiration: %s", $image_details),
  );

  if ('delete' == $conf['expiry_date']['expd_action'])
  {
    //Delete expired photos
    delete_elements($image_ids, true);

    // let's update activities to show it's a deletion on expiry date
    $query = '
SELECT
    *
  FROM '.ACTIVITY_TABLE.'
  WHERE action = \'delete\'
    AND object = \'photo\'
    AND object_id IN ('.implode(',', $image_ids).')
;';
    $activities = query2array($query);
    $updates = array();
    foreach ($activities as $activity)
    {
      $details = unserialize($activity['details']);
      $details['script'] = 'expiry_date';

      $updates[] = array(
        'activity_id' => $activity['activity_id'],
        'details' => serialize($details),
      );
    }

    if (count($updates) > 0)
    {
      mass_updates(
        ACTIVITY_TABLE,
        array(
          'primary' => array('activity_id'),
          'update' => array('details'),
        ),
        $updates
      );
    }

    array_push(
      $keyargs_content, array(
        get_l10n_args("\nTherefore these images have been deleted."),
        get_l10n_args("\n".$conf['expd_admin_email_content']),
      )
    );

  }
  else if ('archive' == $conf['expiry_date']['expd_action'] and isset($conf['expiry_date']['expd_archive_album']) )
  {
    $cat_info = get_cat_info($conf['expiry_date']['expd_archive_album']);
    $cat_names = array();
    foreach ($cat_info['upper_names'] as $upper_cat)
    {
      $cat_names[] = $upper_cat['name'];
    }
    $cat_fullname = implode($conf['level_separator'], $cat_names);

    //Move expired images
    move_images_to_categories($image_ids, array($conf['expiry_date']['expd_archive_album']));
    
    //remove expiry date so action is not done again, add action applied on date
    $datas = array();
    $count = 0;
    foreach ($image_ids as $image_id)
    {
      $datas[] = array(
        'id' => $image_id,
        'expiry_date' => null,
        'expd_action_applied_on' => $dbnow,
        'expd_expired_on' => $images[$count]['expiry_date'],
        );
      $count++;  
    }

    mass_updates(
      IMAGES_TABLE,
      array(
        'primary' => array('id'),
        'update' => array('expiry_date', 'expd_action_applied_on', 'expd_expired_on'),
      ),
      $datas
    );

    $random_key = generate_key(10);

    pwg_activity('photo', $image_ids, 'move', array('expiry_date_key'=>$random_key));

    // let's update activities to show it's archived on expiry date
    $query = '
SELECT
    *
  FROM '.ACTIVITY_TABLE.'
  WHERE action = \'move\'
    AND object = \'photo\'
    AND object_id IN ('.implode(',', $image_ids).')
;';
    $activities = query2array($query);
    $updates = array();
    foreach ($activities as $activity)
    {
      $details = unserialize($activity['details']);

      if (!isset($details['expiry_date_key']) or $details['expiry_date_key'] != $random_key)
      {
        continue;
      }
      $details['script'] = 'expiry_date';
      $details['archived_in'] = $conf['expiry_date']['expd_archive_album'];
      unset($details['expiry_date_key']);

      $updates[] = array(
        'activity_id' => $activity['activity_id'],
        'details' => serialize($details),
      );
    }

    if (count($updates) > 0)
    {
      mass_updates(
        ACTIVITY_TABLE,
        array(
          'primary' => array('activity_id'),
          'update' => array('details'),
        ),
        $updates
      );
    }

    array_push(
      $keyargs_content,
      array(
        get_l10n_args("Therefore these images have been archived in the album %s", $cat_fullname),
        get_l10n_args("\n".$conf['expd_admin_email_content']),
      )
    );

  }
  else
  {
    //remove expiry date so action is not done again, add archive date
    $datas = array();

    foreach ($image_ids as $image_id)
    {
      $datas[] = array(
        'id' => $image_id,
        'expiry_date' => null,
        'expd_action_applied_on' => $dbnow,
        'expd_expired_on' => $image['expiry_date'],
        );
    }

    mass_updates(
      IMAGES_TABLE,
      array(
        'primary' => array('id'),
        'update' => array('expiry_date', 'expd_action_applied_on','expd_expired_on'),
      ),
      $datas
    );

    array_push(
      $keyargs_content,
      array(
        get_l10n_args("No action was taken on these images"),
        get_l10n_args("\n".$conf['expd_admin_email_content']),
      )
    );

  }

  switch_lang_back();

  if (isset($conf['expiry_date']['expd_notify']))
  {
    notify_admins($images, $subject, $keyargs_content );
  }
  
  if (isset($conf['expiry_date']['expd_notify']))
  {
    notify_users($images, $image_ids);
  }
}

/*
 * event functions in admin
 */
if (defined('IN_ADMIN'))
{
  // file containing all admin handlers functions
  $admin_file = EXPIRY_DATE_PATH . 'include/admin_events.inc.php';
  
  //edit photo page
  add_event_handler('loc_end_picture_modify', 'expd_loc_end_picture_modify', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  //update photo information
  add_event_handler('picture_modify_before_update', 'expd_picture_modify_before_update', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // add predefined in Batch Manager
  add_event_handler('get_batch_manager_prefilters', 'expd_get_batch_manager_prefilters', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // add html for different options
  add_event_handler('loc_end_element_set_global', 'expd_loc_end_element_set_global', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

   //register predefined filter options
  add_event_handler('batch_manager_register_filters', 'expd_batch_manager_register_filters', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  //perform predefined filter
  add_event_handler('batch_manager_perform_filters', 'expd_batch_manager_perform_filters', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // batch manager apply action
  add_event_handler('element_set_global_action', 'expd_element_set_global_action', 50, $admin_file);

  // batch manager display action
  add_event_handler('loc_begin_element_set_global', 'expd_element_set_global_add_action', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file); 

  //watch delete categories to check if archive album is deleted
  add_event_handler('delete_categories', 'expd_delete_categories', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file); 

}

/**
 * Add expiry date or expired on date to image page in gallery
 */

// Add information to the picture's description (The copyright's name)
include_once(dirname(__FILE__).'/image.php');

