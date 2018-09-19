<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');

$salesId = $_REQUEST['sales_id'];
settype($salesId, 'integer');

if (empty($salesId))
{
	header('Location: sales.php');
	return;
}

$sql = "SELECT
	id,
	sku,
	IF (ebay_item_id > '', ebay_item_id, amazon_asin) as store_item_id,
	description,
	quantity
FROM sales_items
WHERE sales_id = ${salesId}";

$columns = array(
	"id" => array(
		"type" => "label",
		"visible" => "false"),
	"sku" => array(
		"header" => "SKU",
		"type" => "label",
		"align" => "center",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"store_item_id" => array(
		"header" => "Store Item ID",
		"type" => "label",
		"align" => "center",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"description" => array(
		"header" => "Description",
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
	"quantity" => array(
		"header" => "Quantity",
		"type" => "label",
		"align" => "right",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "true",
		"sort_by" => "",
		"visible" => "true",
		"sort_type" => "numeric",
		"on_js_event" => ""),
	);

$dgItems = new DataGrid(false, true, 'si_');
$dgItems->SetColumnsInViewMode($columns);
$dgItems->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql);
$dgItems->SetCssClass("x-blue");
$dgItems->SetModes(array());
$dgItems->AllowPrinting(false);
$dgItems->AllowSorting(false);
$dgItems->AllowPaging(false, false);
$dgItems->Bind(false);

$sql = "SELECT * FROM sales";

$columns = array(
	"id" => array(
		"type" => "label",
		"visible" => "false"),
	"store" => array(
		"header" => "Store",
		"type" => "label"),
	"record_num" => array(
		"header" => "Record #",
		"type" => "label"),
	"internal_id" => array(
		"header" => "Order #",
		"type" => "label"),
	"order_date" => array(
		"header" => "Order Date",
		"type" => "label"),
	"total" => array(
		"header" => "Total",
		"type" => "label"),
	"buyer_id" => array(
		"header" => "Buyer",
		"type" => "label"),
	"buyer_name" => array(
		"header" => "Ship To",
		"type" => "label"),
	"email" => array(
		"header" => "E-mail",
		"type" => "label"),
	"street" => array(
		"header" => "Street",
		"type" => "label"),
	"city" => array(
		"header" => "City",
		"type" => "label"),
	"state" => array(
		"header" => "State",
		"type" => "label"),
	"zip" => array(
		"header" => "Zip",
		"type" => "label"),
	"phone" => array(
		"header" => "Phone",
		"type" => "label"),
	"speed" => array(
		"header" => "Requested Shipping",
		"type" => "label"),
	"agent" => array(
		"header" => "Sales Agent",
		"type" => "label"),
	"tracking_num" => array(
		"header" => "Tracking #",
		"type" => "label"),
	"carrier" => array(
		"header" => "Carrier",
		"type" => "label"),
	"remarks" => array(
		"header" => "Remarks",
		"type" => "label"),
	);
	
if ($_REQUEST['sd_mode'] != 'update')
	$_REQUEST['sd_mode'] = 'edit';

$_REQUEST['sd_rid'] = $salesId;

$dgDetails = new DataGrid(false, true, 'sd_');
$dgDetails->SetHttpGetVars(array("sales_id"));
$dgDetails->SetColumnsInEditMode($columns);
$dgDetails->SetEditModeTableProperties(array("width" => "45%"));
$dgDetails->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql);
$dgDetails->SetCssClass("x-blue");
$dgDetails->SetCaption("Order Details");
$dgDetails->AllowPrinting(false);
$dgDetails->AllowSorting(false);
$dgDetails->AllowPaging(false, false);
$dgDetails->SetTableEdit('sales', 'id', '');

$modes = array(
	"add" => array(
		"view" => false, 
		"edit" => false),
	"edit" => array(
		"view" => false, 
		"edit" => false),
	"details" => array(
		"view" => false, 
		"edit" => false),
	"delete" => array(
		"view" => false, 
		"edit" => false)
	);

$dgDetails->SetModes($modes);

$dgDetails->Bind(false);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Order Details</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		.tblToolBar
		{
			display: none;
		}

		#printTbl
		{
			display: none;
		}
		
		#sd__contentTable_bottom
		{
			display: none;
		}
		
		#sd_frmEditRow br
		{
			display: none;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<br/>
<center>
<?php
	ob_start();

	$dgDetails->Show();
	echo "<br>";
	$dgItems->Show();

    ob_end_flush();
?>
</center>
<script src="js/jquery.min.js" type="text/javascript"></script>
</body>
</html>