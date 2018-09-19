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
	"200" => "200"
	);

$paging_arrows = array(
	"first" => "|&lt;&lt;",
	"previous" => "&lt;&lt;",
	"next" => "&gt;&gt;",
	"last" => "&gt;&gt;|"
	);
	
$statusCodes = array(
	0 => 'Cancelled; Order Not Received',
	1 => 'Waiting for eBay Order',
	2 => 'Waiting for Item Delivery',
	3 => 'Ready for Pickup',
	4 => 'Pickup Completed',
	99 => 'Order Placement Error',
);

$sql = "SELECT
		pickups.id,
		buyer_id,
		sku,
		pickup_sites.name,
		status,
		added_date,
		(IF (status = 0, 'Cancelled; Order Not Received',
			(IF (order_date IS NULL, 'Waiting for eBay Order', CONCAT('<a href=\"pickup_order.php?sales_id=', sales_id, '\">', order_date, '</a>'))))) as order_date,
		(IF (order_date IS NULL, '',
			(IF (status = 99, '<b><font color=\"red\">Order Placement Error</font><b>',
				(IF (deliver_date IS NOT NULL, deliver_date, CONCAT('<a id=\"deliver_', pickups.id, '\" href=\"javascript:void(0)\" onclick=\"javascript:deliver(', pickups.id, ')\">Waiting for Item Delivery</a>'))))))) as deliver_date,
		(IF (deliver_date IS NULL, '',
			(IF (pickup_date IS NOT NULL, CONCAT('<a href=\"pickup_invoice.php?pickup_id=', pickups.id, '\">', pickup_date, '</a>'), CONCAT('<a href=\"pickup_invoice.php?pickup_id=', pickups.id, '\">Ready for Pickup</a>'))))) as pickup_date
	FROM pickups INNER JOIN pickup_sites ON pickups.site_id = pickup_sites.id";

$columns = array(
	"id" => array(
		"type" => "label",
		"visible" => "false"),
	"buyer_id" => array(
		"header" => "eBay ID",
		"type" => "label",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"sku" => array(
		"header" => "SKU",
		"type" => "label",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"name" => array(
		"header" => "Pickup Site",
		"type" => "label",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"added_date" => array(
		"header" => "Date Added",
		"type" => "label",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"order_date" => array(
		"header" => "Order Date",
		"type" => "data",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"deliver_date" => array(
		"header" => "Delivery Date",
		"type" => "data",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"pickup_date" => array(
		"header" => "Pickup Date",
		"type" => "data",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"status" => array(
		"header" => "Status",
		"type" => "enum",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"source" => $statusCodes,
		"sort_by" => "",
		"visible" => "false",
		"on_js_event" => ""),
	);

$dgSales = new DataGrid(false, true, 'sh_');
$dgSales->SetColumnsInViewMode($columns);
$dgSales->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array('order_date' => 'DESC'));

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
	"From" => array(
		"type" => "calendar",
		"table" => "pickups",
		"field" => "order_date",
		"field_type" => "from",
		"filter_condition" => "", 
		"show_operator" => "false", 
		"default_operator" => ">=", 
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "", 
		"on_js_event" => "", 
		"calendar_type" => "floating"),
	"To" => array(
		"type" => "calendar",
		"table" => "pickups",
		"field" => "order_date",
		"field_type" => "to",
		"filter_condition" => "", 
		"show_operator" => "false", 
		"default_operator" => "<=", 
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "", 
		"on_js_event" => "", 
		"calendar_type" => "floating"),
	"Status" => array(
		"type" => "dropdownlist",
		"table" => "pickups",
		"multiple" => "true",
		"field" => "status",
		"source" => $statusCodes,
		"filter_condition" => "",
		"show_operator" => "false",
		"default_operator" => "=",
		"case_sensitive" => "false",
		"comparison_type" => "string",
		"width" => "",
		"on_js_event" => ""),
	);

$dgSales->SetFieldsFiltering($filtering_fields);
$dgSales->Bind(false);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Local Pickup</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		input:hover#search
		{
			border: 2px inset !important;
		}
		h2
		{
			font-family: tahoma, verdana;
		}
		.default_dg_label, .default_dg_error_message, i
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
<h2>Local Pickup</h2>

<?php
	$dgSales->Show();
    ob_end_flush();
?>
<br/>
<i>Note: Date Added, Delivery Date, and Pickup Date are in Eastern Time Zone. Order Date is in Pacific Time Zone.</i>
</center>
<script src="js/jquery.min.js"></script>
<script>
$(document).ready(function()
{
	$('table.x-blue_dg_filter_table tr').append($('table.tblToolBar').contents().contents().contents());
	$('table.tblToolBar').remove();
	$('table.x-blue_dg_filter_table td[align=right]').attr('align','left');
});

function deliver(pickup_id)
{
	sku = $('#deliver_' + pickup_id.toString()).closest('td').prev().prev().prev().prev().text();

	if (confirm('Click OK to confirm that you have already received all the components for the SKU ' + sku + '.\n\n'
	+ 'The customer will then be notified by the system that the order is ready for pickup.'))
	{
		$.ajax('pickup_delivery.php?pickup_id=' + pickup_id).done(function(data)
		{
			window.location.reload();
		});
	}
}
</script>
</body>
</html>