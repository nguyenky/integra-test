<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');
require_once('system/acl.php');

$user = Login('egrid');

$now = date_create("now", new DateTimeZone('America/New_York'));
$date = date_format($now, 'Y-m-d');

header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');    
session_start();    
ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'ex_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = <<<EOD
SELECT id, competitor_item_id, our_item_id, variance, can_increase_price, keywords
FROM integra_prod.v_ebay_monitor_matrix emm
EOD;
$default_order = array('id' => 'DESC');
$dgrid->DataSource('PDO', 'mysql', DB_HOST, 'integra_prod', DB_USERNAME, DB_PASSWORD, $sql, $default_order);
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
$dgrid->SetPostBackMethod('GET');
$vm_table_properties = array('width'=>'50%');
$dgrid->SetViewModeTableProperties($vm_table_properties);  
$vm_columns = array
(
	'competitor_item_id'=>array('header'=>'Competitor Item', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
	'our_item_id'=>array('header'=>'Our Item', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
	'variance'=>array('header'=>'Variance', 'type'=>'label', 'align'=>'right', 'wrap'=>'nowrap'),
	'can_increase_price'=>array('header'=>'Can Increase Price', 'type'=>'checkbox', 'true_value'=>1, 'false_value'=>0, 'align'=>'center', 'wrap'=>'nowrap'),
	'keywords'=>array('header'=>'Keywords', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$em_table_properties = array('width'=>'50%');
$dgrid->SetEditModeTableProperties($em_table_properties);
$table_name  = 'ebay_monitor_matrix';
$primary_key = 'id';
$condition   = 'ebay_monitor_matrix.id = ' . $_REQUEST['ex_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);
$em_columns = array
(
	'competitor_item_id'  =>array('header'=>'Competitor Item', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'210px', 'maxlength'=>'30'),
	'our_item_id'  =>array('header'=>'Our Item', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'210px', 'maxlength'=>'30'),
	'variance'  =>array('header'=>'Variance (+ to go Over, - to go Under)', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'210px', 'maxlength'=>'5'),
	'can_increase_price'  =>array('header'=>'Can Increase Price', 'type'=>'checkbox', 'true_value'=>1, 'false_value'=>0),
);
$dgrid->SetColumnsInEditMode($em_columns);

$filtering_fields = array(
	"Competitor Item" => array(
		"type" => "textbox",
		"table" => "emm",
		"field" => "competitor_item_id",
		"default_operator" => "=",
		"show_operator" => "false",
		"case_sensitive" => "true",
		"comparison_type" => "string",
		"width" => "100px",
		"on_js_event" => ""),
	"Our Item" => array(
		"type" => "textbox",
		"table" => "emm",
		"field" => "our_item_id",
		"default_operator" => "=",
		"show_operator" => "false",
		"case_sensitive" => "true",
		"comparison_type" => "string",
		"width" => "100px",
		"on_js_event" => ""),
	"Keywords" => array(
		"type" => "textbox",
		"table" => "emm",
		"field" => "keywords",
		"default_operator" => "=",
		"show_operator" => "true",
		"case_sensitive" => "false",
		"comparison_type" => "string",
		"width" => "100px",
		"on_js_event" => "")
);

$dgrid->AllowFiltering(true, false);
$dgrid->SetFieldsFiltering($filtering_fields);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: eBay Monitor Matrix</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2, p, li { font-family: tahoma, verdana; }
</style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>eBay Monitor Matrix</h2>
</center>
<center>
<?php
$dgrid->Bind();
ob_end_flush();
?>
</center>
</body>
</html>