<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/sphinxapi.php');
require_once('system/acl.php');

$user = Login('sales_profits');

set_time_limit(120);

session_start();
ob_start();

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

$query = $_GET['q'];
$inFilter = "";

if (!empty($query))
{
	$cl = new SphinxClient();
	$cl->SetServer(SPHINX_HOST, 3312);
	$cl->SetMatchMode(SPH_MATCH_EXTENDED);
	$cl->SetLimits(0, 500);
	$cl->SetRankingMode(SPH_RANK_PROXIMITY_BM25);

	$result = $cl->Query($query, 'sales');

	if ($result === false || empty($result[matches]))
		$inFilter = " WHERE 0 = 1";
	else $inFilter = " WHERE id IN (" . implode(array_keys($result[matches]), ',') . ")";
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
	"200" => "200"
	);

$paging_arrows = array(
	"first" => "|&lt;&lt;",
	"previous" => "&lt;&lt;",
	"next" => "&gt;&gt;",
	"last" => "&gt;&gt;|"
	);

$sql = "SELECT
		id,
		order_date,
		store,
		record_num,
		fulfilment,
		status,
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
		LIMIT 1) AS remarks,
		total,
		supplier_cost,
		supplier_tax,
		shipping_cost,
		listing_fee,
		IF (supplier_cost = 0, 0, profit) AS profit,
		loss_reason,
		loss_solution
	FROM eoc.sales sales " . $inFilter;

$columns = array(
	"id" => array(
		"type" => "label",
		"visible" => "true"),
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
		"field_key" => "id", 
		"field_data" => "record_num", 
		"target" => "orderDetails",
		"href" => "http://integra2.eocenterprise.com/#/orders/view/{0}",
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
	"total" => array(
		"header" => "Sales Price",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "true",
		"sort_by" => "",
		"visible" => "true",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"supplier_cost" => array(
		"header" => "Item Cost",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "true",
		"sort_by" => "",
		"visible" => "true",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"supplier_tax" => array(
		"header" => "Supplier Tax",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "true",
		"sort_by" => "",
		"visible" => "true",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"shipping_cost" => array(
		"header" => "Shipping",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "true",
		"sort_by" => "",
		"visible" => "true",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"listing_fee" => array(
		"header" => "Listing Fee",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "true",
		"sort_by" => "",
		"visible" => "true",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"profit" => array(
		"header" => "Profit",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "true",
		"sort_by" => "",
		"visible" => "true",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"loss_reason" => array(
		"header" => "Reason",
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
	"loss_solution" => array(
		"header" => "Solution",
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
		"width" => "100px", 
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
		"width" => "100px", 
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
	"Country" => array(
		"type" => "dropdownlist",
		"table" => "sales",
		"field" => "intl_country",
		"filter_condition" => "",
		"show_operator" => "true",
		"default_operator" => "!=",
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
    <title>Sales Profits</title>
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
		.no-data
		{
			background-color:rosybrown !important;
		}
		.loss
		{
			background-color:salmon !important;
		}
		.profit
		{
			background-color:lightgreen !important;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<center>
<br/>

<?php
	if ($_REQUEST['sh_export'] != 'true')
	{
?>

<form>
	<div class="x-blue_dg_caption" style="display: inline;">Search Orders:</div>
	<input id="search" name="q" type="text" value="<?=$query?>"/>
	<input class="x-blue_dg_button" type="submit" value="Search" />
</form>

<?php
		if (!empty($query))
		{
			if ($result[total] > 500)
				echo '<span class="x-blue_dg_warning_message">' . $result['total'] . ' matching orders found. Only first 500 are displayed. Please refine your search.</span>&nbsp;&nbsp;';

			echo "<a href='sales_profits.php' class='x-blue_dg_label'>Show all orders</a><br/><br/>";
		}
	}

	$dgSales->Show();
    ob_end_flush();
?>
</center>
<br/>
<br/>
<script src="js/jquery.min.js"></script>
<script src="js/jquery-ui.min.js" type="text/javascript"></script>
<script src="js/jquery.jeditable.js" type="text/javascript"></script>
<script>
$(document).ready(function()
{
	$("#sh__contentTable tr th:first-child").hide();
	$("#sh__contentTable tr td:first-child").hide();

	$('table.x-blue_dg_filter_table tr').append($('table.tblToolBar').contents().contents().contents());
	$('table.tblToolBar').remove();
	$('table.x-blue_dg_filter_table td[align=right]').attr('align','left');
	
	$('#sh__contentTable tr td:nth-child(6)').each(function()
	{
		content = $(this).text();
		if (content == 'Order Complete')
			$(this).addClass('profit');
		else if (content != '')
			$(this).addClass('no-data');
	});
	
	$('#sh__contentTable tr td:nth-child(9)').each(function()
	{
		content = $(this).text();
		if (content == '0.00')
			$(this).addClass('no-data');
	});
	
	$('#sh__contentTable tr td:nth-child(13)').each(function()
	{
		content = $(this).text().replace('$', '');
		if (content == '0.00')
			$(this).addClass('no-data');
		else
		{
			contentVal = parseFloat(content);
			if (contentVal < 0)
				$(this).addClass('loss');
			else
				$(this).addClass('profit');
		}
	});
	
	$("#sh__contentTable tr td:nth-child(8)").each(function()
	{
		$(this).text($(this).text());
		$(this).attr('id', 'total_' + $(this).parent().children().filter(':first-child').text());
	});
	
	$("#sh__contentTable tr td:nth-child(8)").each(function()
	{
		if ($(this).parent().children().filter(':nth-child(3)').text() != 'Manual')
			return true;

		$(this).editable("sales_price.php",
		{ 
			indicator : "<img src='img/ajax.gif'>",
			event     : "click",
			style	  : "inherit",
			placeholder   : '      '
		});
	});

    $("#sh__contentTable tr td:nth-child(9)").each(function()
    {
        $(this).text($(this).text());
        $(this).attr('id', 'cost_' + $(this).parent().children().filter(':first-child').text());
    });

    $("#sh__contentTable tr td:nth-child(9)").editable("supplier_cost.php",
    {
        indicator : "<img src='img/ajax.gif'>",
        event     : "click",
        style	  : "inherit",
        placeholder   : '      '
    });

	$("#sh__contentTable tr td:nth-last-child(2)").each(function()
	{
		$(this).text($(this).text());
		$(this).attr('id', 'reason_' + $(this).parent().children().filter(':first-child').text());
	});
	
	$("#sh__contentTable tr td:nth-last-child(2)").editable("loss_reason.php",
	{ 
		indicator : "<img src='img/ajax.gif'>",
		event     : "click",
		style	  : "inherit",
		placeholder   : '      '
	});
	
	$("#sh__contentTable tr td:last-child").each(function()
	{
		$(this).text($(this).text());
		$(this).attr('id', 'solution_' + $(this).parent().children().filter(':first-child').text());
	});
	
	$("#sh__contentTable tr td:last-child").editable("loss_solution.php",
	{ 
		indicator : "<img src='img/ajax.gif'>",
		event     : "click",
		style	  : "inherit",
		placeholder   : '      '
	});
});
</script>
</body>
</html>