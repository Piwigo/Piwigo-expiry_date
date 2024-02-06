<?php
defined('EXPIRY_DATE_PATH') or die('Hacking attempt!');

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |Actions                                                                |
// +-----------------------------------------------------------------------

if (!empty($_POST))
{
  check_input_parameter('expd_actions', $_POST, false, '/^(nothing|delete|archive)$/');
  check_input_parameter('selected_category', $_POST, false, '/^[a-zA-Z\d_-]+$/');
  check_input_parameter('expd_notify', $_POST, false, '/^(notify|)$/');

  $conf['expiry_date'] = array(
    'expd_action' => $_POST['expd_actions'],
    'expd_archive_album' => $_POST['selected_category'],   
    'expd_notify' => isset($_POST["expd_notify"]) ? true : false,
    'expd_notify_before_option'=> $_POST['expd_notify_before_option'],
    'expd_notify_admin' => isset($_POST["expd_notify_admin"]) ? true : false,
    'expd_notify_admin_before_option'=> $_POST['expd_admin_notify_before_option'],   
  );

  $conf['expd_email_content'] = $_POST['expd_email_content'];
  $conf['expd_admin_email_content'] = $_POST['expd_admin_email_content'];
  
  if (null == $_POST['selected_category'] and 'archive' == $_POST['expd_actions'])
  {
    array_push($page['warnings'], l10n('You must select an album for archiving photos'));
  }
  else
  {
    conf_update_param('expiry_date',  $conf['expiry_date'], true);
    conf_update_param('expd_email_content',  trim($conf['expd_email_content']), true);
    conf_update_param('expd_admin_email_content',  trim($conf['expd_admin_email_content']), true);
    array_push($page['infos'], l10n('Expiry date configuration saved'));
  }
}

//Get default values for options from config
$template->assign('selectedAction', $conf['expiry_date']['expd_action']);
$template->assign('notifyAction', $conf['expiry_date']['expd_notify']);
$template->assign('notifyActionBeforeOption', $conf['expiry_date']['expd_notify_before_option']);
$template->assign('notifyActionAdmin', $conf['expiry_date']['expd_notify_admin']);
$template->assign('notifyActionAdminBeforeOption', $conf['expiry_date']['expd_notify_admin_before_option']);

$selected_category = array();
if (isset($conf['expiry_date']['expd_archive_album']))
{
  $selected_category = array($conf['expiry_date']['expd_archive_album']);
}
$template->assign('selected_category', $selected_category);
$template->assign('expd_email_content', stripslashes($conf['expd_email_content']));
$template->assign('expd_admin_email_content', stripslashes($conf['expd_admin_email_content']));


// +-----------------------------------------------------------------------+
// | template init                                                         |
// +-----------------------------------------------------------------------+

//assign value for select input
$template->assign(
  'expd_actions_options',
  array(
    'nothing' => l10n('Do nothing'),
    'delete' => l10n('Delete expired photos'),
    'archive' => l10n('Archive expired photos'),
  )
);

$template->assign(
  'expd_prenotification_options',
  array(
    'none' => l10n('No prenotification'),
    '7' => l10n('7 days before expiry'),
    '14' => l10n('14 days before expiry'),
    '30' => l10n('30 days before expiry'),
  )
);

$template->assign(array(
  'CACHE_KEYS' => get_admin_client_cache_keys(array('categories')),
  'PWG_TOKEN' => get_pwg_token(),
  ));


$template->set_filename('expiry_date_content', realpath(EXPIRY_DATE_PATH . 'admin/template/config.tpl'));
