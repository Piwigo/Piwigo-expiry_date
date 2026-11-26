<?php
defined('EXPIRY_DATE_PATH') or die('Hacking attempt!');

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | Filters                                                               |
// +-----------------------------------------------------------------------+

// empty values mean "no filter". check_input_parameter considers any
// empty() value as valid without checking it against the pattern, "0"
// included: no valid filter value is "0", so it is removed too
foreach (array('filter_user', 'filter_image', 'filter_type', 'filter_date_min', 'filter_date_max') as $param)
{
  // arrays are rejected by check_input_parameter below
  if (isset($_GET[$param]) and is_string($_GET[$param]))
  {
    $_GET[$param] = trim($_GET[$param]);
    if ('' === $_GET[$param] or '0' === $_GET[$param])
    {
      unset($_GET[$param]);
    }
  }
}

check_input_parameter('filter_user', $_GET, false, PATTERN_ID);
check_input_parameter('filter_image', $_GET, false, PATTERN_ID);
check_input_parameter('filter_type', $_GET, false, '/^(prenotification|expiration)$/');
check_input_parameter('filter_date_min', $_GET, false, '/^\d{4}-\d{2}-\d{2}$/');
check_input_parameter('filter_date_max', $_GET, false, '/^\d{4}-\d{2}-\d{2}$/');
check_input_parameter('start', $_GET, false, PATTERN_ID);

$filters = array();
$where = array('1 = 1');

if (isset($_GET['filter_user']))
{
  $filters['filter_user'] = intval($_GET['filter_user']);
  $where[] = 'user_id = '.$filters['filter_user'];
}

if (isset($_GET['filter_image']))
{
  $filters['filter_image'] = intval($_GET['filter_image']);
  $where[] = 'image_id = '.$filters['filter_image'];
}

if (isset($_GET['filter_type']))
{
  $filters['filter_type'] = $_GET['filter_type'];
  if ('prenotification' == $filters['filter_type'])
  {
    $where[] = 'type LIKE \'prenotification\\_%\'';
  }
  elseif ('expiration' == $filters['filter_type'])
  {
    // "notification_admin" was used by old versions of the plugin
    $where[] = 'type IN (\'expiration_notification_user\', \'expiration_notification_admin\', \'notification_admin\')';
  }
}

if (isset($_GET['filter_date_min']))
{
  $filters['filter_date_min'] = $_GET['filter_date_min'];
  $where[] = 'send_date >= \''.$filters['filter_date_min'].' 00:00:00\'';
}

if (isset($_GET['filter_date_max']))
{
  $filters['filter_date_max'] = $_GET['filter_date_max'];
  $where[] = 'send_date <= \''.$filters['filter_date_max'].' 23:59:59\'';
}

$history_url = EXPIRY_DATE_ADMIN.'-history';
$filtered_history_url = add_url_params($history_url, $filters);

// +-----------------------------------------------------------------------+
// | Filter options                                                        |
// +-----------------------------------------------------------------------+

// users who received notifications, with their number of emails (an email
// is identified by its sending date and type for a given user)
$query = '
SELECT
    user_id,
    COUNT(DISTINCT send_date, type) AS nb_emails
  FROM '.EXPIRY_DATE_NOTIFICATIONS_TABLE.'
  GROUP BY user_id
;';
$nb_emails_of_user = query2array($query, 'user_id', 'nb_emails');

$tpl_users = array();
if (count($nb_emails_of_user) > 0)
{
  $query = '
SELECT
    '.$conf['user_fields']['id'].' AS id,
    '.$conf['user_fields']['username'].' AS username
  FROM '.USERS_TABLE.'
  WHERE '.$conf['user_fields']['id'].' IN ('.implode(',', array_keys($nb_emails_of_user)).')
;';
  $username_of = query2array($query, 'id', 'username');

  foreach ($nb_emails_of_user as $user_id => $nb_emails)
  {
    $tpl_users[] = array(
      'ID' => $user_id,
      // a user may have been deleted since
      'USERNAME' => isset($username_of[$user_id]) ? $username_of[$user_id] : '#'.$user_id,
      'NB_EMAILS' => $nb_emails,
    );
  }

  usort($tpl_users, function ($a, $b) { return strcasecmp($a['USERNAME'], $b['USERNAME']); });
}

$query = '
SELECT
    DATE(MIN(send_date)),
    DATE(MAX(send_date))
  FROM '.EXPIRY_DATE_NOTIFICATIONS_TABLE.'
;';
list($history_date_min, $history_date_max) = pwg_db_fetch_row(pwg_query($query));

// +-----------------------------------------------------------------------+
// | Emails sent                                                           |
// +-----------------------------------------------------------------------+

$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$nb_emails_per_page = 50;

// the table has one line per recipient and per photo. One email = one
// recipient + one notification type + one sending run (same send_date and
// email_uuid for the whole run, email_uuid is null for old notifications)
$email_group_by = 'send_date, email_uuid, type, user_id';

$query = '
SELECT
    COUNT(*)
  FROM (
    SELECT 1
      FROM '.EXPIRY_DATE_NOTIFICATIONS_TABLE.'
      WHERE '.implode(' AND ', $where).'
      GROUP BY '.$email_group_by.'
  ) AS emails
;';
list($nb_emails) = pwg_db_fetch_row(pwg_query($query));

$query = '
SELECT
    send_date,
    email_uuid,
    type,
    user_id,
    MAX(email_used) AS email_used
  FROM '.EXPIRY_DATE_NOTIFICATIONS_TABLE.'
  WHERE '.implode(' AND ', $where).'
  GROUP BY '.$email_group_by.'
  ORDER BY send_date DESC, type ASC, user_id ASC
  LIMIT '.$nb_emails_per_page.' OFFSET '.$start.'
;';
$emails = query2array($query);

$tpl_emails = array();

if (count($emails) > 0)
{
  // all photos of each email (not only the ones matching the filters), from
  // the lines of the displayed sending runs
  $send_dates = array_unique(array_column($emails, 'send_date'));

  $query = '
SELECT
    send_date,
    email_uuid,
    type,
    user_id,
    image_id
  FROM '.EXPIRY_DATE_NOTIFICATIONS_TABLE.'
  WHERE send_date IN (\''.implode("','", array_map('pwg_db_real_escape_string', $send_dates)).'\')
  ORDER BY image_id ASC
;';
  $lines = query2array($query);

  $image_ids_of_email = array();
  $image_ids = array();
  foreach ($lines as $line)
  {
    $email_key = expd_get_history_email_key($line);
    $image_ids_of_email[$email_key][] = $line['image_id'];
    $image_ids[ $line['image_id'] ] = 1;
  }

  // photos still in the database (deleted photos are missing)
  $images = array();
  if (count($image_ids) > 0)
  {
    $query = '
SELECT
    '.expd_get_mail_image_columns().'
  FROM '.IMAGES_TABLE.'
  WHERE id IN ('.implode(',', array_keys($image_ids)).')
;';
    $images = query2array($query, 'id');
  }

  foreach ($emails as $email)
  {
    list($recipient, $recipient_type, $when) = expd_get_history_notification_type($email['type']);

    $tpl_images = array();
    foreach ($image_ids_of_email[ expd_get_history_email_key($email) ] as $image_id)
    {
      $tpl_image = array(
        'ID' => $image_id,
        'FILTERED' => (isset($filters['filter_image']) and $filters['filter_image'] == $image_id),
      );

      if (isset($images[$image_id]))
      {
        $tpl_image['NAME'] = strip_tags(render_element_name($images[$image_id]));
        $tpl_image['THUMB_URL'] = DerivativeImage::url(IMG_SQUARE, $images[$image_id]);
        $tpl_image['URL'] = get_root_url().'admin.php?page=photo-'.$image_id;
      }
      else
      {
        $tpl_image['DELETED'] = true;
      }

      $tpl_images[] = $tpl_image;
    }

    $tpl_emails[] = array(
      'DAY' => format_date($email['send_date'], array('day', 'month', 'year')),
      'HOUR' => substr($email['send_date'], 11, 5),
      'RECIPIENT' => $recipient,
      'RECIPIENT_TYPE' => $recipient_type,
      'WHEN' => $when,
      'USERNAME' => isset($username_of[ $email['user_id'] ]) ? $username_of[ $email['user_id'] ] : null,
      'USER_ID' => $email['user_id'],
      'EMAIL' => $email['email_used'],
      'IMAGES' => $tpl_images,
    );
  }
}

// +-----------------------------------------------------------------------+
// | template init                                                         |
// +-----------------------------------------------------------------------+

$template->assign(
  array(
    'expd_emails' => $tpl_emails,
    'expd_history_is_empty' => (0 == count($nb_emails_of_user)),
    'expd_filters' => $filters,
    'expd_users' => $tpl_users,
    'expd_history_dates' => array(
      'min' => $history_date_min,
      'max' => $history_date_max,
    ),
    'U_EXPD_HISTORY' => $history_url,
    'navbar' => create_navigation_bar(
      $filtered_history_url,
      $nb_emails,
      $start,
      $nb_emails_per_page
    ),
  )
);

$template->set_filename('expiry_date_content', realpath(EXPIRY_DATE_PATH.'admin/template/history.tpl'));
