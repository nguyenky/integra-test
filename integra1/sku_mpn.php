<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');
require_once('system/acl.php');

$user = Login();

session_start();
ob_start();

$suppliers = array(
	"1" => "W1",
	"2" => "W2",
	"3" => "W3"
);

$sql = "SELECT id, sku, mpn, supplier, no_indiv_relist FROM sku_mpn";
$dgrid = new DataGrid(false, true, "sm_");
$dgrid->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array('sku' => 'ASC'));
$dgrid->AllowAjax(true);

$layouts = array(
	"view" => "0",
	"edit" => "0", 
	"details" => "1", 
	"filter" => "2"
	); 
$dgrid->SetLayouts($layouts);

$modes = array(
	"add" => array("view" => true, "edit" => false, "type" => "link", "show_add_button" => "inside"),
	"edit" => array("view" => true, "edit" => true, "type" => "link"),
	"details" => array("view" => false, "edit" => false),
	"delete" => array("view" => true, "edit" => true, "type" => "image")
);
$dgrid->SetModes($modes);
$dgrid->SetCaption("SKU-MPN Mapping");
 
$paging = array(
	"results" => true, 
	"results_align" => "left",
	"pages" => true,
	"pages_align" => "center",
	"page_size" => true,
	"page_size_align" => "right"
	);

$pages_array = array(
	"25" => "25",
	"50" => "50",
	"100" => "100",
	"200" => "200",
	"500" => "500",
	"1000" => "1000",
	);

$paging_arrows = array(
	"first" => "|&lt;&lt;",
	"previous" => "&lt;&lt;",
	"next" => "&gt;&gt;",
	"last" => "&gt;&gt;|"
	);

$dgrid->SetCssClass("x-blue");
$dgrid->AllowPrinting(false);
$dgrid->AllowExporting(true, true);
$dgrid->AllowExportingTypes(array('csv'=>'true', 'xls'=>'true', 'pdf'=>'false', 'xml'=>'false'));
$dgrid->SetPagingSettings($paging, $paging, $pages_array, 50, $paging_arrows);

$filtering_fields = array(
	"SKU" => array(
		"type" => "textbox",
		"table" => "sku_mpn",
		"field" => "sku",
		"filter_condition" => "", 
		"show_operator" => "false", 
		"default_operator" => "%like%", 
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "140px", 
		"on_js_event" => ""),
	"MPN" => array(
		"type" => "textbox",
		"table" => "sku_mpn",
		"field" => "mpn",
		"filter_condition" => "", 
		"show_operator" => "false", 
		"default_operator" => "%like%", 
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "140px", 
		"on_js_event" => ""),
	"Supplier" => array(
		"type" => "dropdownlist",
		"table" => "sku_mpn",
		"field" => "supplier",
		"filter_condition" => "",
		"show_operator" => "false",
		"default_operator" => "=",
		"comparison_type" => "numeric",
		"width" => "",
		"source" =>  $suppliers,
		"on_js_event" => ""),
	"Don't Relist Individually" => array(
		"type" => "dropdownlist",
		"table" => "sku_mpn",
		"field" => "no_indiv_relist",
		"filter_condition" => "",
		"show_operator" => "false",
		"default_operator" => "=",
		"comparison_type" => "numeric",
		"width" => "",
		"source" =>  array(1 => 'Yes', 0 => 'No'),
		"on_js_event" => ""),
	);

$dgrid->AllowFiltering(true, false);
$dgrid->SetFieldsFiltering($filtering_fields);

$dgrid->SetViewModeTableProperties(array("width"=>"50%"));
$vm_colimns = array(
	"id" => array("type" => "label", "visible" => "false"),
	"sku" => array("header" => "SKU", "type" => "label", "align" => "left"),
	"mpn" => array("header" => "MPN", "type" => "label", "align" => "left"),
	"supplier" => array("header" => "Supplier", "type" => "enum", "align" => "left", "source" => $suppliers),
	"no_indiv_relist" => array("header" => "Don't Relist Individually", "type" => "checkbox", "align" => "center", "true_value" => 1, "false_value" => 0),
);
$dgrid->SetColumnsInViewMode($vm_colimns);

 
$dgrid->SetEditModeTableProperties(array("width"=>"50%"));
$dgrid->SetTableEdit("sku_mpn", "id", "");
$em_columns = array(
	"id" => array(
		"type" => "label",
		"visible" => "false"),
	"sku" => array(
		"header" => "SKU",
		"type" => "textbox",
		"width" => "140px",
		"req_type" => "rt",
		"title" => "SKU"),
	"mpn" => array(
		"header" => "MPN",
		"type" => "textbox",
		"width" => "140px",
		"req_type" => "rt",
		"title" => "MPN"),
	"supplier" => array(
		"header" => "Supplier",
		"type" => "enum",
		"req_type" => "rt",
		"title" => "Supplier",
		"source" => $suppliers,
		"view_type" => "dropdownlist"),
	"no_indiv_relist" => array(
		"header" => "Don't Relist Individually",
		"type" => "checkbox",
		"title" => "Don't Relist Individually",
		"true_value" => 1,
		"false_value" => 0)
);
$dgrid->SetColumnsInEditMode($em_columns);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>SKU-MPN Mapping</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
	$dgrid->WriteCssClass();
?>
	<style>
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<center>
<br/>
<?php
	$dgrid->Bind();
    ob_end_flush();
?>
</center>
<br/>
<br/>
<script>
$(document).ready(function()
{
});
</script>
</body>
</html>