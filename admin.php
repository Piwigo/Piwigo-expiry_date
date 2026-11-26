<?php

defined('EXPIRY_DATE_PATH') or die('Hacking attempt!');

global $template, $page, $conf;

include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');

define('EXPIRY_DATE_BASE_URL', get_root_url().'admin.php?page=plugin-expiry_date');

$tabs = array(
  'config' => l10n('Configuration'),
  'history' => l10n('History'),
);

// get current tab, only among known tabs because it is used to include a file
$page['tab'] = 'config';
if (isset($_GET['tab']) and isset($tabs[ $_GET['tab'] ]))
{
  $page['tab'] = $_GET['tab'];
}

//tabsheet
$tabsheet = new tabsheet();
$tabsheet->set_id('expiry_date');

foreach ($tabs as $tab_code => $tab_label)
{
  $tabsheet->add($tab_code, $tab_label, EXPIRY_DATE_ADMIN.'-'.$tab_code);
}
$tabsheet->select($page['tab']);
$tabsheet->assign();

// include page
include(EXPIRY_DATE_PATH . 'admin/' . $page['tab'] . '.php');

// send page content
$template->assign_var_from_handle('ADMIN_CONTENT', 'expiry_date_content');

?>