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
$unique_prefix = 'bul_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = 'SELECT id, content, sort_num, published, recipients FROM bulletins';
$default_order = array('sort_num' => 'ASC', 'id' => 'ASC');
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
$vm_table_properties = array('width'=>'70%');
$dgrid->SetViewModeTableProperties($vm_table_properties);  
$vm_columns = array
(
	'content'=>array('header'=>'Content', 'type'=>'label',      'align'=>'left', 'wrap'=>'wrap'),
	'recipients'=>array('header'=>'Recipients', 'type'=>'label',      'align'=>'left', 'width'=>'300px', 'wrap'=>'wrap'),
	'published'=>array('header'=>'Published', 'type'=>'checkbox',  'align'=>'center', 'true_value'=>1, 'false_value'=>0, 'width'=>'100px'),
	'sort_num'=>array('header'=>'Sort Number', 'type'=>'label',      'align'=>'right', 'wrap'=>'nowrap', 'width'=>'100px'),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$em_table_properties = array('width'=>'50%');
$dgrid->SetEditModeTableProperties($em_table_properties);
$table_name  = 'bulletins';
$primary_key = 'id';
$condition   = 'bulletins.id = ' . $_REQUEST['bul_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);
$em_columns = array
(
	'content'  =>array('header'=>'Content', 'type'=>'textarea',   'req_type'=>'rt', 'width'=>'210px', 'edit_type'=>'wysiwyg', 'resizable'=>'both', 'upload_images'=>'true', 'rows'=>'7', 'cols'=>'50'),
	'recipients'  =>array('header'=>'Recipients', 'type'=>'textarea',   'req_type'=>'stl', 'width'=>'210px', 'edit_type'=>'simple', 'title'=>'List of email addresses. If blank, bulletin is visible to everyone.', 'resizable'=>'both', 'upload_images'=>'false', 'rows'=>'7', 'cols'=>'50'),
	'sort_num'  =>array('header'=>'Sort Number', 'type'=>'textbox',    'req_type'=>'rn', 'width'=>'210px', 'maxlength'=>'5', 'default'=>'100'),
	'published' =>array('header'=>'Published', 'type'=>'checkbox',   'req_type'=>'rt', 'default'=>'1', 'true_value'=>1, 'false_value'=>0),
);
$dgrid->SetColumnsInEditMode($em_columns);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: Bulletin Admin</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2 { font-family: tahoma, verdana; }
</style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>Bulletin Admin</h2>
<?php
$dgrid->Bind();
ob_end_flush();
?>
</center>
</body>
</html>