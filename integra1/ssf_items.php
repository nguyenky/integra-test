<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/acl.php');

$user = Login();

set_time_limit(120);

session_start();
ob_start();

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
	"1000" => "1000"
	);

$paging_arrows = array(
	"first" => "|&lt;&lt;",
	"previous" => "&lt;&lt;",
	"next" => "&gt;&gt;",
	"last" => "&gt;&gt;|"
	);

$sql = "SELECT
		id,
		mpn,
		brand_id,
		brand,
		name,
		weight,
		core_unit_price,
		list_price,
		unit_price,
		qty_avail,
		timestamp
	FROM ssf_items
	WHERE inactive = 0";

$columns = array(
	"id" => array(
		"type" => "label",
		"visible" => "false"),
	"mpn" => array(
		"header" => "MPN",
		"type" => "label",
		"wrap" => "nowrap"),
	"brand_id" => array(
		"header" => "Brand ID",
		"type" => "label",
		"wrap" => "nowrap"),
	"brand" => array(
		"header" => "Brand",
		"type" => "label",
		"wrap" => "wrap"),
	"name" => array(
		"header" => "Name",
		"type" => "label",
		"wrap" => "wrap"),
	"weight" => array(
		"header" => "Weight",
		"type" => "label",
		"align" => "right",
		"wrap" => "nowrap"),
	"core_unit_price" => array(
		"header" => "Core Price",
		"type" => "label",
		"align" => "right",
		"wrap" => "nowrap"),
	"list_price" => array(
		"header" => "List Price",
		"type" => "label",
		"align" => "right",
		"wrap" => "nowrap"),
	"unit_price" => array(
		"header" => "Unit Price",
		"type" => "label",
		"align" => "right",
		"wrap" => "nowrap"),
	"qty_avail" => array(
		"header" => "Qty Available",
		"type" => "label",
		"align" => "right",
		"wrap" => "nowrap"),
	"timestamp" => array(
		"header" => "Last Updated",
		"type" => "label",
		"wrap" => "wrap"),
	);

$dgSales = new DataGrid(false, true, 'sh_');
$dgSales->SetColumnsInViewMode($columns);
$dgSales->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array('id' => 'ASC'));

$layouts = array(
	"view" => "0",
	"edit" => "0", 
	"details" => "1", 
	"filter" => "2"
	); 
$dgSales->setLayouts($layouts);
$dgSales->SetPostBackMethod('GET');
$dgSales->SetModes(array());
$dgSales->SetCssClass("x-blue");
$dgSales->AllowSorting(true);
$dgSales->AllowPrinting(false);
$dgSales->AllowExporting(true, true);
$dgSales->AllowExportingTypes(array('csv'=>'true', 'xls'=>'true', 'pdf'=>'false', 'xml'=>'false'));
$dgSales->SetPagingSettings($paging, array(), $pages_array, 50, $paging_arrows);
$dgSales->AllowFiltering(true, false);

$filtering_fields = array(
	"MPN" => array(
		"type" => "text",
		"table" => "ssf_items",
		"field" => "mpn",
		"show_operator" => "false", 
		"default_operator" => "=", 
		"case_sensitive" => "false", 
		"comparison_type" => "string"),
	"Name" => array(
		"type" => "text",
		"table" => "ssf_items",
		"field" => "name",
		"show_operator" => "false", 
		"default_operator" => "%like%", 
		"case_sensitive" => "false", 
		"comparison_type" => "string"),
	"Brand" => array(
		"type" => "text",
		"table" => "ssf_items",
		"field" => "brand",
		"show_operator" => "false", 
		"default_operator" => "%like%", 
		"case_sensitive" => "false", 
		"comparison_type" => "string"),
	);

$dgSales->SetFieldsFiltering($filtering_fields);
$dgSales->Bind(false);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>W2 Items</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		.default_dg_label, .default_dg_error_message
		{
			font-family: tahoma, verdana;
			font-size: 12px;
		}
		#sh_a_hide, #sh_a_unhide
		{
			display: none;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<center>
<br/>
<?php
$dgSales->Show();
ob_end_flush();
?>
</center>
<br/>
<br/>
<script>
$(document).ready(function()
{
	$('table.x-blue_dg_filter_table tr').append($('table.tblToolBar').contents().contents().contents());
	$('table.tblToolBar').remove();
	$('table.x-blue_dg_filter_table td[align=right]').attr('align','left');
});
</script>
</body>
</html>