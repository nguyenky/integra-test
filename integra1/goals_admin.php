<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');
require_once('system/acl.php');

$user = Login();

$metrics = array
(
	0 => 'Shipping: Shipments every 30 minutes',
    1 => 'eBay Editing: Daily Edits',
    2 => 'eBay Editing: Daily New Listings',
);

header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');    
session_start();    
ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'goal_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = 'SELECT id, email, metric, goal FROM goals';
$default_order = array('email' => 'ASC', 'metric' => 'ASC');
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

$emails[''] = '(Company-wide goal)';

$dSet = $dgrid->ExecuteSQL('SELECT email, first_name, last_name FROM integra_users ORDER BY first_name, last_name');
while ($row = $dSet->fetch())
	$emails[$row[0]] = $row[1] . ' ' . $row[2];

$vm_columns = array
(
	'metric'=>array('header'=>'Metric', 'type'=>'enum', 'source'=>$metrics, 'align'=>'left', 'wrap'=>'nowrap'),
	'email'=>array('header'=>'User', 'type'=>'enum', 'align'=>'left', 'wrap'=>'nowrap', 'source'=>$emails),
	'goal'=>array('header'=>'Goal', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$em_table_properties = array('width'=>'50%');
$dgrid->SetEditModeTableProperties($em_table_properties);
$table_name  = 'goals';
$primary_key = 'id';
$condition   = 'goals.id = ' . $_REQUEST['goal_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);
$em_columns = array
(
	'metric' =>array('header'=>'Metric', 'type'=>'enum', 'req_type'=>'rt', 'source'=>$metrics, 'view_type'=>'dropdownlist'),
	'email' =>array('header'=>'User', 'type'=>'enum', 'req_type'=>'st', 'width'=>'250px', 'maxlength'=>'50', 'source'=>$emails, 'view_type'=>'dropdownlist'),
	'goal'  =>array('header'=>'Goal', 'type'=>'textbox', 'req_type'=>'rn', 'width'=>'210px', 'maxlength'=>'10'),
);
$dgrid->SetColumnsInEditMode($em_columns);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: Goals Admin</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2 { font-family: tahoma, verdana; }
</style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>Goals Admin</h2>
<?php
$dgrid->Bind();
ob_end_flush();
?>
</center>
</body>
</html>