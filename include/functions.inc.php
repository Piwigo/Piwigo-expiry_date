<?php

/**
 * Add notification to expd_ntifications tabel
 * keep track of what notification sent to who
 */

function expd_add_notification_history($notifications)
{
  mass_inserts(
    EXPIRY_DATE_NOTIFICATIONS_TABLE,
    array_keys($notifications[0]),
    $notifications
  );
}

/**
 * Key identifying one email in the notification history: one recipient +
 * one notification type + one sending run
 *
 * @param array $line row of EXPIRY_DATE_NOTIFICATIONS_TABLE
 * @return string
 */
function expd_get_history_email_key($line)
{
  return implode('|', array($line['send_date'], (string)$line['email_uuid'], $line['type'], $line['user_id']));
}

/**
 * Recipient type and moment of a notification type, for display
 *
 * @param string $type value of EXPIRY_DATE_NOTIFICATIONS_TABLE.type
 * @return array (recipient type: 'user', 'admin' or null, recipient type
 *   label, moment label), labels are translated
 */
function expd_get_history_notification_type($type)
{
  $recipient = null;
  $when = $type;

  if (preg_match('/^prenotification_(user|admin)_(\d+)$/', $type, $matches))
  {
    $recipient = $matches[1];
    $when = l10n($matches[2].' days before expiry');
  }
  // "notification_admin" was used by old versions of the plugin
  elseif (preg_match('/^(?:expiration_)?notification_(user|admin)$/', $type, $matches))
  {
    $recipient = $matches[1];
    $when = l10n('On the expiry date');
  }

  $recipient_type = null;
  if ('admin' == $recipient)
  {
    $recipient_type = l10n('Administrator notifications');
  }
  elseif ('user' == $recipient)
  {
    $recipient_type = l10n('User notifications');
  }

  return array($recipient, $recipient_type, $when);
}

/**
 * Code copied from empty_lounge
 */
function expd_single_exec($token_name, $timeout = 60)
{
  global $conf, $logger;

  if (isset($conf[$token_name]))
  {
    list($running_exec_id, $running_exec_start_time) = explode('-', $conf[$token_name]);
    if (time() - $running_exec_start_time > $timeout)
    {
      $logger->debug(__FUNCTION__.', token_name='.$token_name.', exec='.$running_exec_id.', timeout stopped by another call to the function');
      conf_delete_param($token_name);
    }
  }

  $exec_id = generate_key(4);
  $logger->debug(__FUNCTION__.', token_name='.$token_name.', exec='.$exec_id.', begins');

  // if lounge is already being emptied, skip
  $query = '
INSERT IGNORE
  INTO '.CONFIG_TABLE.'
  SET param="'.$token_name.'"
    , value="'.$exec_id.'-'.time().'"
;';
  pwg_query($query);

  list($token_name) = pwg_db_fetch_row(pwg_query('SELECT value FROM '.CONFIG_TABLE.' WHERE param = "'.$token_name.'"'));
  list($running_exec_id,) = explode('-', $token_name);

  if ($running_exec_id != $exec_id)
  {
    $logger->debug(__FUNCTION__.', token_name='.$token_name.', exec='.$exec_id.', skip');
    return false;
  }
  $logger->debug(__FUNCTION__.', token_name='.$token_name.', exec='.$exec_id.' wins the race and gets the token!');

  return $exec_id;
}


function expd_single_exec_end($token_name, $exec_id)
{
  global $logger;

  conf_delete_param($token_name);
  $logger->debug(__FUNCTION__.', token_name='.$token_name.', exec='.$exec_id.' releases the token');
}

/**
 * Columns of IMAGES_TABLE needed to describe a photo in a notification email
 * (path, representative_ext, width, height and rotation are needed by
 * DerivativeImage to build the thumbnail URL)
 */
function expd_get_mail_image_columns()
{
  return 'id, file, name, author, expiry_date, path, representative_ext, width, height, rotation';
}

/**
 * Prepare the data of a photo for the email template
 *
 * @param array $image row of IMAGES_TABLE, see expd_get_mail_image_columns()
 * @param string $date_label "expires on" or "expired on", already translated
 * @param string|null $link_to 'gallery' (only if the photo is still visible
 *   in the gallery), 'admin' (only if the photo still exists) or null for no link
 * @param bool $add_thumbnail only if the photo file still exists
 * @return array
 */
function expd_get_mail_image($image, $date_label, $link_to, $add_thumbnail)
{
  global $conf;

  $mail_image = array(
    'NAME' => render_element_name($image),
    'AUTHOR' => $image['author'],
    'FILE' => $image['file'],
    'DATE' => $date_label.' '.format_date($image['expiry_date']),
  );

  // we are building URLs for an email: they must be absolute
  set_make_full_url();

  if ('gallery' == $link_to)
  {
    $mail_image['URL'] = make_picture_url(
      array(
        'image_id' => $image['id'],
        'image_file' => $image['file'],
      )
    );
  }
  elseif ('admin' == $link_to)
  {
    $mail_image['URL'] = get_root_url().'admin.php?page=photo-'.$image['id'];
  }

  if ($add_thumbnail)
  {
    // force $conf['derivative_url_style'] to 2 (script) to make sure we use
    // i.php?/upload and not _data/i/upload, because the derivative cache may
    // be flushed before the email is read
    $previous_derivative_url_style = $conf['derivative_url_style'];
    $conf['derivative_url_style'] = 2;

    // square size: always enabled and same width for all photos, so that
    // texts are aligned in the email
    $mail_image['THUMB_URL'] = DerivativeImage::url(IMG_SQUARE, $image);

    $conf['derivative_url_style'] = $previous_derivative_url_style;
  }

  unset_make_full_url();

  return $mail_image;
}

/**
 * Template parameter of pwg_mail() for notification emails (HTML with
 * thumbnails + plain text), used for both users and admins.
 *
 * @param string $intro paragraph before the list of photos, translated
 * @param array $mail_images list of expd_get_mail_image() results
 * @param string $status_message paragraph after the list of photos, translated
 * @param string $custom_content free text set in the plugin configuration
 * @return array
 */
function expd_get_mail_tpl($intro, $mail_images, $status_message, $custom_content)
{
  return array(
    'dirname' => realpath(EXPIRY_DATE_PATH.'template/mail'),
    'filename' => 'expd_notification',
    'assign' => array(
      'EXPD_INTRO' => $intro,
      'EXPD_IMAGES' => $mail_images,
      'EXPD_STATUS' => $status_message,
      'EXPD_CUSTOM_CONTENT' => $custom_content,
    ),
  );
}

/**
 * Send a notification email to a user about expiring/expired photos.
 *
 * Must be called after switch_lang_to() the user language.
 *
 * @param string $email
 * @param string $subject
 * @param array $mail_images list of expd_get_mail_image() results
 * @param string $status_message "These photos will soon expire." or equivalent, translated
 */
function expd_send_user_mail($email, $subject, $mail_images, $status_message)
{
  global $conf;

  // the existing translation includes a "%s" for the list of photos, we
  // reuse it without the list to avoid requiring a new translation
  $intro = rtrim(l10n('You have received this email because you previously downloaded these photos: %s', ''));

  $custom_content = '';
  if (!empty($conf['expd_email_content']))
  {
    $custom_content = $conf['expd_email_content'];
  }

  return pwg_mail(
    $email,
    array(
      'subject' => $subject,
      'mail_title' => $conf['gallery_title'],
      'mail_subtitle' => $subject,
      'content_format' => 'text/html',
    ),
    expd_get_mail_tpl($intro, $mail_images, $status_message, $custom_content)
  );
}

/**
 * Send a notification email to all admins (including the current user, if
 * admin) about expiring/expired photos.
 *
 * Must be called after switch_lang_to() the default language.
 *
 * @param string $subject
 * @param array $mail_images list of expd_get_mail_image() results
 * @param string $intro paragraph before the list of photos, translated
 * @param string $status_message paragraph after the list of photos, translated
 * @param string $custom_content free text set in the plugin configuration
 */
function expd_send_admin_mail($subject, $mail_images, $intro, $status_message, $custom_content)
{
  global $conf;

  return pwg_mail_admins(
    array(
      'subject' => '['.$conf['gallery_title'].'] '.$subject,
      'mail_title' => $conf['gallery_title'],
      'mail_subtitle' => $subject,
      'content_format' => 'text/html',
    ),
    expd_get_mail_tpl($intro, $mail_images, $status_message, $custom_content),
    false // exclude_current_user: the check runs on any page visit, the visitor may be an admin
  );
}
