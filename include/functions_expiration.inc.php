<?php

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');
include_once(EXPIRY_DATE_PATH.'include/functions.inc.php');

/**
 * Notify admins of image expiration
 *
 * @param array $images rows of IMAGES_TABLE, see expd_get_mail_image_columns()
 * @param array $status_keyargs get_l10n_args() of the action taken on photos
 * @param bool $add_link add a link to the admin page of each photo (only
 *   makes sense if the photo was not deleted)
 * @param bool $add_thumbnail add a thumbnail of each photo (only makes sense
 *   if the photo file was not deleted)
 */
function expd_notify_admins($images, $status_keyargs, $add_link=false, $add_thumbnail=false)
{
  global $conf;

  if (!isset($conf['expiry_date']['expd_notify_admin']))
  {
    return;
  }

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
  '.$conf['user_fields']['id'].' AS id,
  '.$conf['user_fields']['email'].' AS email
  FROM '.USERS_TABLE.'
  WHERE '.$conf['user_fields']['id'].' IN ('.implode(',',$admin_ids).')
    AND `'.$conf['user_fields']['email'].'` IS NOT NULL
;';

  $admin_emails = query2array($query, 'id', 'email');

  if (empty($admin_emails))
  {
    return;
  }
    
  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
  $email_uuid = generate_key(10);

  $notification_history = array();
  $mail_images = array();

  switch_lang_to(get_default_language());

  foreach ($images as $image_id => $image)
  {
    $mail_images[] = expd_get_mail_image($image, l10n('expired on'), $add_link ? 'admin' : null, $add_thumbnail);

    // only admins with an email address get notified
    foreach ($admin_emails as $admin_id => $admin_email)
    {
      $notification_history[] = array(
        'type' => 'expiration_notification_admin',
        'user_id' =>  $admin_id,
        'image_id' => $image['id'],
        'send_date' => $dbnow,
        'email_used' => $admin_email,
        'email_uuid' => $email_uuid,
      );
    }
  }

  if (count($notification_history) > 0)
  {
    // the existing translation includes a "%s" for the list of photos, we
    // reuse it without the list to avoid requiring a new translation
    $intro = rtrim(l10n('These images have reached expiration: %s', ''));

    expd_send_admin_mail(
      l10n('Expiry date, action has been taken'),
      $mail_images,
      $intro,
      trim(l10n_args($status_keyargs)),
      ''
    );
    expd_add_notification_history($notification_history);
  }
  switch_lang_back();

}

/**
 * Notify users of photo expiration
 *
 * @param array $images rows of IMAGES_TABLE, see expd_get_mail_image_columns()
 * @param int[] $image_ids
 * @param bool $add_link add a link to each photo in the gallery (only makes
 *   sense if the photo was neither deleted nor archived)
 * @param bool $add_thumbnail add a thumbnail of each photo (only makes sense
 *   if the photo file was not deleted)
 */
function expd_notify_users($images, $image_ids, $add_link=false, $add_thumbnail=false)
{
  global $conf, $user;
  
  if (!isset($conf['expiry_date']['expd_notify']) or empty($images))
  {
    return;
  }

  //see what users downloaded which photo
  $query = '
SELECT user_id, image_id
  FROM '.HISTORY_TABLE.'
  WHERE image_id IN ('.implode(',',$image_ids).')
    AND image_type = \'high\'
;';

  $history_lines = query2array($query);
  $user_history = array();
  
  foreach ($history_lines as $history_line)
  {
    @$user_history[ $history_line['user_id'] ][ $history_line['image_id'] ]++;
  }
  
  $user_ids = array_keys($user_history);

  if (empty($user_ids))
  {
    return;
  }

  //Get user email
  $query = '
SELECT 
    '.$conf['user_fields']['id'].' AS id,
    '.$conf['user_fields']['email'].' AS email
  FROM '.USERS_TABLE.'
  WHERE '.$conf['user_fields']['id'].' IN ('.implode(',',$user_ids).')
    AND `'.$conf['user_fields']['email'].'` IS NOT NULL
;';
  $email_of_user = query2array($query, 'id', 'email');

  if (empty($email_of_user))
  {
    return;
  }

  //Get language for user
   $query = '
SELECT
    user_id,
    language
  FROM '.USER_INFOS_TABLE.'
  WHERE user_id IN ('.implode(',', $user_ids).')
;';

  $language_of_user = query2array($query, 'user_id', 'language');
  
  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
  
  $email_uuid = generate_key(10);
  $notification_history = array();
  
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

    $mail_images = array();
    foreach (array_keys($user_image_ids) as $user_image_id)
    {
      foreach ($images as $image)
      {
        if ($user_image_id != $image["id"])
        {
          continue;
        }
        $mail_images[] = expd_get_mail_image($image, l10n('expired on'), $add_link ? 'gallery' : null, $add_thumbnail);

        $notification_history[] = array(
          'type' => 'expiration_notification_user',
          'user_id' =>  $user_id,
          'image_id' => $user_image_id,
          'send_date' => $dbnow,
          'email_used' => $email_of_user[$user_id],
          'email_uuid' => $email_uuid,
        );

      }
    }

    // $notification_history is shared by all users, check this user has photos
    if (count($mail_images) > 0)
    {
      expd_send_user_mail(
        $email_of_user[$user_id],
        l10n('You have expiring photos'),
        $mail_images,
        l10n('These photo have reached their expiry date.')
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
 