<?php

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');
include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

/**
 * Send prenotifications to admins
 */
function send_prenotifications_admin()
{
  global $conf, $user;

  // Check if either admin prenotification is set
  if ('none' == $conf['expiry_date']['expd_notify_admin_before_option'])
  {
    return;
  }

  // select all images expiring in X days for admin prenotif
  $query = '
SELECT
    id,
    file,
    name,
    author,
    expiry_date
  FROM '.IMAGES_TABLE.'
  WHERE expiry_date BETWEEN ADDDATE(NOW(), INTERVAL 2 DAY) AND ADDDATE(NOW(), INTERVAL '.$conf['expiry_date']['expd_notify_admin_before_option'] . ' DAY)
;';
  $images = query2array($query, 'id');

  if (empty($images))
  {
    return;
  }

  //get list of admin ids and emails for notification history
  $admin_ids = get_admins(true);
  $admin_emails = array();

  if (empty($admin_ids))
  {
    return;
  }

  $query = '
SELECT 
    `'.$conf['user_fields']['id'].'` AS id,
    `'.$conf['user_fields']['email'].'` AS email
  FROM '.USERS_TABLE.'
  WHERE '.$conf['user_fields']['id'].' IN ('.implode(',',$admin_ids).')
    AND `'.$conf['user_fields']['email'].'` IS NOT NULL
;';

  $admin_emails = query2array($query, 'id', 'email');

  if (empty($admin_emails))
  {
    return;
  }

  $query = '
SELECT
    DISTINCT(image_id)
  FROM '.EXPIRY_DATE_NOTIFICATIONS_TABLE.'
  WHERE send_date > SUBDATE(NOW(), INTERVAL '.$conf['expiry_date']['expd_notify_admin_before_option'].' DAY)
    AND image_id IN ('.implode(',', array_keys($images)).')
    AND type = \'prenotification_admin_'.$conf['expiry_date']['expd_notify_admin_before_option'].'\'
  ;';
  
  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
  $email_uuid = generate_key(10);

  $notifications_sent = query2array($query, 'image_id');
  $notification_history = array();

  $image_info = "\n\n";

  switch_lang_to(get_default_language());
  foreach ($images as $image_id => $image)
  {
    if (isset($notifications_sent[$image['id']]))
    {
      continue;
    }
    $url_admin = get_absolute_root_url().'admin.php?page=photo-'.$image_id;

    $image_info .= '* '.$image["name"].' '.$image["author"].' ('.$image["file"]."), ".l10n("expires on")." ".format_date($image["expiry_date"])."\n ".$url_admin."\n\n";

    foreach ($admin_ids as $admin_id)
    {
      $notification_history[] = array(
        'type' => 'prenotification_admin_'.$conf['expiry_date']['expd_notify_admin_before_option'],
        'user_id' =>  $admin_id,
        'image_id' => $image['id'],
        'send_date' => $dbnow,
        'email_used' => $admin_emails[$admin_id],
        'email_uuid' => $email_uuid,
      );
    }
  }

  if (count($notification_history) > 0)
  {
    // notify admins on expiration
    $current_user_id = $user['id'];
    $user['id'] = -1; // make sure even the current user will get notified. Fake current user.

    $subject =l10n('Expiry date').", ".l10n('These photos will soon expire.');
    $keyargs_content = array(
      get_l10n_args("These photos will soon expire."),
      get_l10n_args("%s",$image_info),
      get_l10n_args($conf['expd_admin_email_content']),
    );

    pwg_mail_notification_admins($subject, $keyargs_content, false);
    expd_add_notification_history($notification_history);

    // unfake current user
    $user['id'] = $current_user_id;
  }
  switch_lang_back();
}

/**
 * Send prenotifications to users
 * $user_id user id for notification history
 * $user_email for notification history
 */

function send_prenotifications_user()
{
  global $conf;

  //Check if either admin or user prenotification is set
  if ('none' == $conf['expiry_date']['expd_notify_before_option'])
  {
    return;
  }

  // select all images expiring in X days for user prenotif
  $query = '
SELECT
    id,
    file,
    name,
    author,
    expiry_date
  FROM '.IMAGES_TABLE.'
  WHERE expiry_date BETWEEN ADDDATE(NOW(), INTERVAL 2 DAY) AND ADDDATE(NOW(), INTERVAL '.$conf['expiry_date']['expd_notify_before_option'].' DAY);
;';

  $images = query2array($query, 'id');

  if (empty($images))
  {
    return;
  }

  //Get history of who downloaded which image
  $query = '
SELECT user_id, image_id
  FROM '.HISTORY_TABLE.'
  WHERE image_id IN ('.implode(',', array_keys($images)).')
    AND image_type = \'high\'
;';
        
  $history_lines = query2array($query);
  $user_history = array();
          
  foreach ($history_lines as $history_line)
  {
    @$user_history[$history_line['user_id']][$history_line['image_id']]++;
  }

  $user_ids = array_keys($user_history);
       
  if (empty($user_ids))
  {
    return;
  }
    
    $query = '
SELECT 
'.$conf['user_fields']['id'].' AS id,
'.$conf['user_fields']['email'].' AS email
FROM '.USERS_TABLE.'
WHERE '.$conf['user_fields']['id'].' IN ('.implode(',',$user_ids).')
  AND `'.$conf['user_fields']['email'].'` IS NOT NULL
;';

  $email_of_user = query2array($query, 'id', 'email');
      
  if (count($email_of_user) < 0)
  {
    return;
  }

  $query = '
SELECT
    user_id,
    language
  FROM '.USER_INFOS_TABLE.'
  WHERE user_id IN ('.implode(',', $user_ids).')
;';

  $language_of_user = query2array($query, 'user_id', 'language');

  $query = '
SELECT
    image_id,
    user_id,
    send_date
  FROM '.EXPIRY_DATE_NOTIFICATIONS_TABLE.'
  WHERE send_date > SUBDATE(NOW(), INTERVAL '.$conf['expiry_date']['expd_notify_before_option'].' DAY)
    AND image_id IN ('.implode(',', array_keys($images)).')
    AND type = \'prenotification_user_'.$conf['expiry_date']['expd_notify_before_option'].'\'
;';
  $result = pwg_query($query);

  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));

  $email_uuid = generate_key(10);
  $notifications_sent = array();
  $notification_history = array();

  while ($row = pwg_db_fetch_assoc($result)) {
    $notifications_sent[$row['image_id'].'_'.$row['user_id']] = $row['send_date'];
  }

  foreach ($user_history as $user_id => $user_image_ids)
  {
    if (!isset($email_of_user[$user_id]))
    {
      continue;
    }
      
    $recipient_language = get_default_language();
    if (isset($language_of_user[$user_id]))
    {
      $recipient_language = $language_of_user[$user_id];
    }

    switch_lang_to($recipient_language);

    $image_info = "\n\n";
    foreach (array_keys($user_image_ids) as $user_image_id)
    {
      if(in_array($user_image_id.'_'.$user_id,array_keys($notifications_sent)))
      {
        continue;
      }
      $url_gallery = get_absolute_root_url().'picture.php?/'.$user_image_id;
      foreach ($images as $image)
      {
        if ($user_image_id != $image["id"])
        {
          continue;
        }
        $image_info .= '* '.$image["name"].' '.$image["author"].' ('.$image["file"]."), ".l10n("expires on")." ".format_date($image["expiry_date"])."\n ".$url_gallery."\n\n";
         
        $notification_history[] = array(
          'type' => 'prenotification_user_'.$conf['expiry_date']['expd_notify_before_option'],
          'user_id' =>  $user_id,
          'image_id' => $user_image_id,
          'send_date' => $dbnow,
          'email_used' => $email_of_user[$user_id],
          'email_uuid' => $email_uuid,
        );
      }
    }

    if (count($notification_history) > 0)
    {
      $keyargs_content = array(
        get_l10n_args("You have received this email because you previously downloaded these photos: %s", $image_info),
        get_l10n_args("These photos will soon expire."),
        get_l10n_args("\n".$conf['expd_email_content']),
      );

      $subject =l10n('Expiry date').", ".l10n('These photos will soon expire.');
      $content = l10n_args($keyargs_content);
      
      pwg_mail(
        $email_of_user[$user_id],
        array(
          'subject' => $subject,
          'content' => $content,
          'content_format' => 'text/plain',
        )
      );
    }
    switch_lang_back();
  } 

  if (count($notification_history) > 0)
  {
    //add notification to notification history
    expd_add_notification_history($notification_history);
  }
}
