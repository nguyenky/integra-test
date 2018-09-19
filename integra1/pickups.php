<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');

set_time_limit(120);

$item = $_GET[i];
$cust = $_GET[c];
$ok = false;

if (!empty($item))
{
	$ok = true;
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
	
	$item2 = mysql_real_escape_string(strtolower(trim($item)));

	$sql = sprintf("SELECT
		p.id,
		p.buyer_id,
		s.buyer_name,
		p.sku,
		si.description,
		ps.name,
		ds.order_id,
		p.status,
		p.added_date,
		(IF (p.status = 0, 'Cancelled; Order Not Received',
			(IF (p.order_date IS NULL, 'Waiting for eBay Order', CONCAT('<a href=\"pickup_order.php?sales_id=', p.sales_id, '\">', p.order_date, '</a>'))))) as order_date,
		(IF (p.order_date IS NULL, '',
			(IF (p.status = 99, '<b><font color=\"red\">Order Placement Error</font><b>',
				(IF (p.deliver_date IS NOT NULL, p.deliver_date, CONCAT('<a id=\"deliver_', p.id, '\" href=\"javascript:void(0)\" onclick=\"javascript:deliver(', p.id, ')\">Waiting for Item Delivery</a>'))))))) as deliver_date,
		(IF (p.deliver_date IS NULL, '',
			(IF (p.pickup_date IS NOT NULL, CONCAT('<a href=\"pickup_invoice.php?pickup_id=', p.id, '\">', p.pickup_date, '</a>'), CONCAT('<a href=\"pickup_invoice.php?pickup_id=', p.id, '\">Ready for Pickup</a>'))))) as pickup_date
	FROM pickups p, pickup_sites ps, sales_items si, sales s, direct_shipments ds
	WHERE p.sales_id = si.sales_id
	AND s.id = ds.sales_id
	AND p.site_id = ps.id
	AND s.id = p.sales_id
	AND p.status = 2
	AND (EXISTS
	(
		SELECT 1
		FROM sku_mpn s
		WHERE supplier = 1
		AND mpn = REPLACE(REPLACE('%s', ' ', ''), '-', '')
		AND p.sku LIKE CONCAT('%%', s.sku, '%%')
	) OR TRIM(LEADING '0' FROM ds.order_id) = TRIM(LEADING '0' FROM '%s'))",
	$item2, $item2);

	mysql_close();
}
else if (!empty($cust))
{
	$ok = true;
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
	
	$cust2 = mysql_real_escape_string(strtolower(trim($cust)));

	$sql = sprintf("SELECT
		p.id,
		p.buyer_id,
		s.buyer_name,
		p.sku,
		si.description,
		ps.name,
		ds.order_id,
		p.status,
		p.added_date,
		(IF (p.status = 0, 'Cancelled; Order Not Received',
			(IF (p.order_date IS NULL, 'Waiting for eBay Order', CONCAT('<a href=\"pickup_order.php?sales_id=', p.sales_id, '\">', p.order_date, '</a>'))))) as order_date,
		(IF (p.order_date IS NULL, '',
			(IF (p.status = 99, '<b><font color=\"red\">Order Placement Error</font><b>',
				(IF (p.deliver_date IS NOT NULL, p.deliver_date, CONCAT('<a id=\"deliver_', p.id, '\" href=\"javascript:void(0)\" onclick=\"javascript:deliver(', p.id, ')\">Waiting for Item Delivery</a>'))))))) as deliver_date,
		(IF (p.deliver_date IS NULL, '',
			(IF (p.pickup_date IS NOT NULL, CONCAT('<a href=\"pickup_invoice.php?pickup_id=', p.id, '\">', p.pickup_date, '</a>'), CONCAT('<a href=\"pickup_invoice.php?pickup_id=', p.id, '\">Ready for Pickup</a>'))))) as pickup_date
	FROM pickups p, pickup_sites ps, sales_items si, sales s, direct_shipments ds
	WHERE p.sales_id = si.sales_id
	AND s.id = ds.sales_id
	AND p.site_id = ps.id
	AND s.id = p.sales_id
	AND p.status IN (3, 4)
	AND ((LOWER(p.buyer_id) LIKE '%%%s%%') OR (LOWER(s.buyer_name) LIKE '%%%s%%'))",
	$cust2, $cust2);

	mysql_close();
}

if ($ok)
{
session_start();
ob_start();

$paging = array(
	"results" => false,
	"results_align" => "left",
	"pages" => false,
	"pages_align" => "center",
	"page_size" => false,
	"page_size_align" => "right"
	);
	
$statusCodes = array(
	0 => 'Cancelled; Order Not Received',
	1 => 'Waiting for eBay Order',
	2 => 'Waiting for Item Delivery',
	3 => 'Ready for Pickup',
	4 => 'Pickup Completed',
	99 => 'Order Placement Error',
);

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
	"buyer_name" => array(
		"header" => "Customer",
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
	"description" => array(
		"header" => "Item Description",
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
	"order_id" => array(
		"header" => "Invoice #",
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
	"filter" => "0"
	); 
$dgSales->setLayouts($layouts);
$dgSales->SetPostBackMethod('GET');
$dgSales->SetModes(array());
$dgSales->SetCssClass("x-blue");
$dgSales->AllowSorting(false);
$dgSales->AllowPrinting(false);
$dgSales->AllowExporting(false, false);
$dgSales->SetPagingSettings($paging, array(), array(), 100, array());
$dgSales->AllowFiltering(false, false);
$dgSales->Bind(false);
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Local Pickup</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		input[type=text]:hover
		{
			border: 2px inset !important;
		}
		input[type=submit]:hover
		{
			border: 2px inherit !important;
		}
		h2, h4, form, i
		{
			font-family: tahoma, verdana;
		}
		td
		{
			padding: 0 30px;
		}
		h4
		{
			margin-bottom: 10px;
			font-size: 16px;
		}
		form, i
		{
			font-size: 14px;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>Local Pickup</h2>

<table>
	<tr>
		<td>
			<form>
				<h4>Supplier Item Delivery</h4>
				Enter Part or Sales Order #:<br/>
				<input name="i" type="text" value="<?=$item?>"/>
				<input type="submit" value="Search" />
			</form>
		</td>
		<td>
			<form>
				<h4>Customer Pickup</h4>
				Enter eBay ID or Name:<br/>
				<input name="c" type="text" value="<?=$cust?>"/>
				<input type="submit" value="Search" />
			</form>
		</td>
	</tr>
</table>
<br/>

<?php
if ($ok)
{
	$dgSales->Show();
    ob_end_flush();
	echo "<br/><i>Note: Date Added, Delivery Date, and Pickup Date are in Eastern Time Zone. Order Date is in Pacific Time Zone.</i>";
}
?>
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
<script type="text/javascript">
  (function() {
    var se = document.createElement('script'); se.type = 'text/javascript'; se.async = true;
    se.src = '//commondatastorage.googleapis.com/code.snapengage.com/js/415ccca8-7dbb-4b19-b7f9-1e4fdd981e56.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(se, s);
  })();
</script>
</body>
</html>