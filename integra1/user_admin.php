<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');
require_once('system/acl.php');

$user = Login();

header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');    
session_start();    
ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'user_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = 'SELECT id, email, first_name, last_name, restrict_ip FROM integra_users';
$default_order = array('first_name' => 'ASC', 'last_name' => 'ASC');
$dgrid->DataSource('PDO', 'mysql', DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, $default_order);
$layouts = array('view'=>'0', 'edit'=>'1', 'details'=>'0', 'filter'=>'2');
$dgrid->SetLayouts($layouts);
$modes = array
(
	'add'	  =>array('view'=>true, 'edit'=>false, 'type'=>'link',  'show_button'=>true, 'show_add_button'=>'inside'),
	'edit'	  =>array('view'=>true, 'edit'=>true,  'type'=>'link',  'show_button'=>true, 'byFieldValue'=>''),
	'details' =>array('view'=>false, 'edit'=>false, 'type'=>'link',  'show_button'=>true),
	'delete'  =>array('view'=>true, 'edit'=>true,  'type'=>'image', 'show_button'=>true)
);
$dgrid->SetModes($modes);
$css_class = 'x-blue';
$dgrid->SetCssClass($css_class);
$dgrid->AllowPrinting(false);
$paging_option = false;
$rows_numeration = false;
$numeration_sign = '';
$dropdown_paging = false;
$dgrid->AllowPaging($paging_option, $rows_numeration, $numeration_sign, $dropdown_paging);
$dgrid->SetPagingSettings(null, null, null, 10000);
$vm_table_properties = array('width'=>'50%');
$dgrid->SetViewModeTableProperties($vm_table_properties);  
$vm_columns = array
(
	'email'=>array('header'=>'Email', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
	'first_name'=>array('header'=>'First Name', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
	'last_name'=>array('header'=>'Last Name', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
    'restrict_ip'=>array('header'=>'IP Address', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$em_table_properties = array('width'=>'50%');
$dgrid->SetEditModeTableProperties($em_table_properties);
$table_name  = 'integra_users';
$primary_key = 'id';
$condition   = 'integra_users.id = ' . $_REQUEST['user_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);
$em_columns = array
(
	'email' =>array('header'=>'Email', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'250px', 'maxlength'=>'50'),
	'first_name' =>array('header'=>'First Name', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'250px', 'maxlength'=>'100'),
	'last_name' =>array('header'=>'Last Name', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'250px', 'maxlength'=>'100'),
    'restrict_ip' =>array('header'=>'IP Address', 'type'=>'textbox', 'req_type'=>'st', 'width'=>'250px', 'maxlength'=>'100'),
);
$dgrid->SetColumnsInEditMode($em_columns);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: User Admin</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2 { font-family: tahoma, verdana; }
</style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>User Admin</h2>
<?php
$dgrid->Bind();
ob_end_flush();
?>
</center>
</body>
</html>