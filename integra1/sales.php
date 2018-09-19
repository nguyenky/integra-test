<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/sphinxapi.php');
require_once('system/acl.php');

$user = Login('sales');

set_time_limit(120);

session_start();
ob_start();

$fulfilmentCodes = array(
	0 => 'Unspecified',
	1 => 'Direct',
	2 => 'Pickup',
	3 => 'EOC',
);

$classCodes = array(
    'Standard / Ground' => 'Standard / Ground',
    'Expedited / Express' => 'Expedited / Express',
    'Second Day' => 'Second Day',
    'Next Day / Overnight' => 'Next Day / Overnight',
    'International' => 'International',
    'Local Pick Up' => 'Local Pick Up'
);

$fulfilmentTips = array(
	0 => 'The system will NOT automatically process this order.',
	1 => 'The supplier will ship the items directly to the customer.',
	2 => 'The customer will get the items from a local pickup site.',
	3 => 'EOC will pack and ship the items coming from W1 truck.',
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

$statusTips = array(
	0 => 'The system will NOT automatically process this order.',
	1 => 'The system will automatically process this order on schedule.',
	2 => 'The item has been ordered from the supplier, but has not yet arrived.',
	3 => 'The item has arrived from the supplier, and ready for pickup or shipping.',
	4 => 'The item is either on the way or has been received by the customer.',
	90 => 'The customer cancelled the order.',
	99 => 'Due to some errors, the order must be processed manually. Change the status afterwards.',
);

$query = $_GET[q];
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
		internal_id,
		record_num,
		(IF (buyer_id > '', CONCAT(buyer_id, ' - ', buyer_name), buyer_name)) as buyer,
		total,
		fulfilment,
		status,
		speed,
		TRIM(BOTH ' / ' FROM CONCAT(tracking_num, ' / ', remarks)) as tracking_num
	FROM sales" . $inFilter;

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
	"buyer" => array(
		"header" => "Name",
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
		"field_data" => "buyer", 
		"target" => "orderDetails",
		"href" => "http://integra2.eocenterprise.com/#/orders/view/{0}",
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
    "speed" => array(
        "header" => "Class",
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
	"tracking_num" => array(
		"header" => "Tracking #",
		"type" => "label",
		"readonly" => true,
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "60",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
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
	"internal_id" => array(
		"header" => "Internal ID",
		"type" => "label",
		"visible" => "false"),
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
		"field" => "country",
		"filter_condition" => "",
		"show_operator" => "true",
		"default_operator" => "!=",
		"case_sensitive" => "false",
		"comparison_type" => "string",
		"width" => "",
		"on_js_event" => ""),
    "Class" => array(
        "type" => "dropdownlist",
        "table" => "sales",
        "field" => "speed",
        "source" => $classCodes,
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
    <title>Sales</title>
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
        #sh__ff_sales_order_date_fo_from.x-blue_dg_textbox, #sh__ff_sales_order_date_fo_to.x-blue_dg_textbox
        {
            width: 80px !important;
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
	&nbsp;
	<input class="x-blue_dg_button" type="button" onclick="javascript:window.open('http://integra2.eocenterprise.com/#/orders/create','_blank');" value="Create Order" />
	&nbsp;
	<input class="x-blue_dg_button" type="button" onclick="javascript:window.open('index.php','_blank');" value="Dashboard" />
</form>

	

<?php
		if (!empty($query))
		{
			if ($result[total] > 500)
				echo '<span class="x-blue_dg_warning_message">' . $result['total'] . ' matching orders found. Only first 500 are displayed. Please refine your search.</span>&nbsp;&nbsp;';

			echo "<a href='sales.php' class='x-blue_dg_label'>Show all orders</a><br/><br/>";
		}
	}

	$dgSales->Show();
    ob_end_flush();
?>
</center>
<br/>
<br/>
<script src="js/jquery.min.js"></script>
<script>
$(document).ready(function()
{
	$('table.x-blue_dg_filter_table tr').append($('table.tblToolBar').contents().contents().contents());
	$('table.tblToolBar').remove();
	$('table.x-blue_dg_filter_table td[align=right]').attr('align','left');
	$('tr.dg_tr').each(function(i, v)
	{
		temp = $(this).find('td').eq(3).find('a').attr('href');
		if (temp === undefined)
			return true;
		temp = temp.split('/');
		sales_id = temp[temp.length-1];

		fulfilment = $(this).find('td').eq(4).text();

		$(this).find('td').eq(4).html('<select onchange="changeFulfilment(' + sales_id + ')" id="fulfilment_' + sales_id + '">'
<?
	foreach ($fulfilmentCodes as $code => $text)
		echo "+ '<option value=" . '"' . $code . '" title="' . $fulfilmentTips[$code] . '">' . $text . "</option>'";
?>
		+ '</select>');
			
		$('#fulfilment_' + sales_id).find('option:contains("' + fulfilment + '")').attr('selected', true);
		
		status = $(this).find('td').eq(5).text();

		$(this).find('td').eq(5).html('<select onchange="changeStatus(' + sales_id + ')" id="status_' + sales_id + '">'
<?
	foreach ($statusCodes as $code => $text)
				echo "+ '<option value=" . '"' . $code . '" title="' . $statusTips[$code] . '">' . $text . "</option>'";
?>
		+ '</select>');
			
		$('#status_' + sales_id).find('option:contains("' + status + '")').attr('selected', true);
	});
});

function changeFulfilment(sales_id)
{
	$.ajax('change_fulfilment.php?code=' + $('#fulfilment_' + sales_id).val() + '&sales_id=' + sales_id).fail(function()
	{
		alert('There was an error while changing the order fulfilment method.\nPlease check your internet connection.');
	});
}

function changeStatus(sales_id)
{
	$.ajax('change_status.php?code=' + $('#status_' + sales_id).val() + '&sales_id=' + sales_id).fail(function()
	{
		alert('There was an error while changing the order status.\nPlease check your internet connection.');
	});
}
</script>
</body>
</html>