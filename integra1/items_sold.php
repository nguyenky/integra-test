<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/sphinxapi.php');
require_once('system/acl.php');

$user = Login();

set_time_limit(120);

session_start();
ob_start();

$query = $_GET['q'];
$inFilter = "";

$fulfilmentCodes = array(
	0 => 'Unspecified',
	1 => 'Direct',
	2 => 'Pickup',
	3 => 'EOC',
);

$statusCodes = array(
	0 => 'Unspecified',
	1 => 'Scheduled',
	2 => 'Item Ordered / Waiting',
	3 => 'Ready for Dispatch',
	4 => 'Order Complete',
	90 => 'Cancelled',
    91 => 'Payment Pending',
	92 => 'Return Pending',
	93 => 'Return Complete',
	94 => 'Refund Pending',
	99 => 'Error',
);

if (!empty($query))
{
	$cl = new SphinxClient();
	$cl->SetServer(SPHINX_HOST, 3312);
	$cl->SetMatchMode(SPH_MATCH_EXTENDED);
	$cl->SetLimits(0, 500);
	$cl->SetRankingMode(SPH_RANK_PROXIMITY_BM25);

	$result = $cl->Query($query, 'sales');

	if ($result === false || empty($result[matches]))
		$inFilter = " AND 0 = 1";
	else $inFilter = " AND sales.id IN (" . implode(array_keys($result[matches]), ',') . ")";
}

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
		sales.id as id,
		sales.order_date as order_date,
		sales.store as store,
		sales.record_num as record_num,
		sales_items.sku as sku,
		sales_items.quantity as quantity,
		sales_items.total as total,
		REPLACE(sales.agent, '@eocenterprise.com', '') as agent,
		sales.status as status,
		sales.fulfilment as fulfilment,
		REPLACE(stamps.email, '@eocenterprise.com', '') as shipper,
		(SELECT oh.remarks
		FROM integra_prod.order_history oh, integra_prod.users u
		WHERE oh.order_id = sales.id
		AND u.email = '{$user}'
		AND NOT (u.group_name = 'Sales' AND oh.hide_sales = 1)
		AND NOT (u.group_name = 'Data' AND oh.hide_data = 1)
		AND NOT (u.group_name = 'Pricing' AND oh.hide_pricing = 1)
		AND NOT (u.group_name = 'Shipping' AND oh.hide_shipping = 1)
		AND oh.remarks > ''
		ORDER BY oh.ts DESC
		LIMIT 1) AS remarks
	FROM eoc.sales_items, eoc.sales sales LEFT JOIN eoc.stamps ON sales.id = stamps.sales_id
	WHERE (sales.id = sales_items.sales_id) " . $inFilter;

$columns = array(
	"id" => array(
		"type" => "label",
		"visible" => "false"),
	"order_date" => array(
		"header" => "Order Date",
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
	"store" => array(
		"header" => "Store",
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
	"record_num" => array(
		"header" => "Record #",
		"type" => "link",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"field_key" => "id", 
		"field_data" => "record_num", 
		"target" => "orderDetails",
		"href" => "http://integra2.eocenterprise.com/#/orders/view/{0}",
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
	"total" => array(
		"header" => "Total",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "true",
		"sort_by" => "",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "$",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"agent" => array(
		"header" => "Agent",
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
	"fulfilment" => array(
		"header" => "Fulfilment",
		"type" => "enum",
		"source" => $fulfilmentCodes,
		"readonly" => true,
		"align" => "center",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"shipper" => array(
		"header" => "Shipper",
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
	"status" => array(
		"header" => "Status",
		"type" => "enum",
		"source" => $statusCodes,
		"readonly" => true,
		"align" => "center",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"on_js_event" => ""),
	"remarks" => array(
		"header" => "Remarks",
		"type" => "link",
		"field_key" => "id",
		"field_data" => "remarks",
		"target" => "orderDetails",
		"href" => "http://integra2.eocenterprise.com/#/orders/view/{0}",
		"readonly" => true,
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "40",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
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
$dgSales->SetHttpGetVars(array("q"));
$dgSales->AllowFiltering(true, false);

$filtering_fields = array(
	"From" => array(
		"type" => "calendar",
		"table" => "sales",
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
		"table" => "sales",
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
	"Store" => array(
		"type" => "dropdownlist",
		"table" => "sales",
		"field" => "store",
		"filter_condition" => "",
		"show_operator" => "false",
		"default_operator" => "like%",
		"case_sensitive" => "false",
		"comparison_type" => "string",
		"width" => "",
		"on_js_event" => ""),
	"Agent" => array(
		"type" => "dropdownlist",
		"table" => "sales",
		"field" => "agent",
		"filter_condition" => "",
		"show_operator" => "false",
		"default_operator" => "like%",
		"case_sensitive" => "false",
		"comparison_type" => "string",
		"width" => "",
		"on_js_event" => ""),
	"Fulfilment" => array(
		"type" => "dropdownlist",
		"table" => "sales",
		"field" => "fulfilment",
		"source" => $fulfilmentCodes,
		"filter_condition" => "",
		"show_operator" => "false",
		"default_operator" => "=",
		"case_sensitive" => "false",
		"comparison_type" => "string",
		"width" => "",
		"on_js_event" => ""),
	"Status" => array(
		"type" => "dropdownlist",
		"table" => "sales",
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
    <title>Items Sold</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		input:hover#search
		{
			border: 2px inset !important;
		}
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
	if (@$_REQUEST['sh_export'] != 'true')
	{
?>

<form>
	<div class="x-blue_dg_caption" style="display: inline;">Search Orders:</div>
	<input id="search" name="q" type="text" value="<?=$query?>"/>
	<input class="x-blue_dg_button" type="submit" value="Search" />
	&nbsp;
	<input class="x-blue_dg_button" type="button" onclick="javascript:window.open('index.php','_blank');" value="Dashboard" />
	&nbsp;
	<input class="x-blue_dg_button" type="button" onclick="javascript:window.open('stats.php','_blank');" value="Statistics" />
</form>

	

<?php
		if (!empty($query))
		{
			if ($result['total'] > 500)
				echo '<span class="x-blue_dg_warning_message">' . $result['total'] . ' matching orders found. Only first 500 are displayed. Please refine your search.</span>&nbsp;&nbsp;';

			echo "<a href='items_sold.php' class='x-blue_dg_label'>Show all orders</a><br/><br/>";
		}
	}

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