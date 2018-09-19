<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/acl.php');

$user = Login('agrid');

set_time_limit(0);

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

$sql = <<<EOD
SELECT
	ag.asin,
	ag.sku,
	ag.mpn,
	ag.brand,
	ag.num_merchants,
	ag.our_price,
	ag.our_sh,
	ag.our_total,
	ag.lowest_price,
	ag.lowest_sh,
	ag.lowest_total,
	ag.front_price,
	ag.front_sh,
	ag.front_total,
	ag.unit_price,
	ag.profit,
	ag.profit_pct,
	ag.price_diff,
	ag.price_diff_pct,
	ag.min_price,
	(SELECT IFNULL(SUM(si.quantity), 0) FROM sales_items si WHERE si.amazon_asin = ag.asin) AS num_sold,
	(SELECT DATE_FORMAT(MAX(order_date), '%m-%d') FROM sales s INNER JOIN sales_items si ON s.id = si.sales_id WHERE si.amazon_asin = ag.asin) AS last_sold,
	date_format(ag.timestamp, '%m-%d %H:%i') AS timestamp,
	ag.remarks
FROM amazon_grid ag INNER JOIN amazon_listings al ON (ag.asin = al.asin AND al.active = 1 AND ag.our_total > 0)
EOD;

$columns = array(
	"asin" => array(
		"header" => "ASIN",
		"type" => "link",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "asin",
		"visible" => "true",
		"field_key" => "asin", 
		"field_data" => "asin", 
		"target" => "_blank",
		"href" => "http://www.amazon.com/gp/offer-listing/{0}",
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
		"sort_by" => "sku",
		"visible" => "true",
		"on_js_event" => ""),
	"mpn" => array(
		"header" => "MPN",
		"type" => "label",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "mpn",
		"visible" => "true",
		"on_js_event" => ""),
	"brand" => array(
		"header" => "Brand",
		"type" => "label",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "brand",
		"visible" => "true",
		"on_js_event" => ""),
	"num_merchants" => array(
		"header" => "Merchants",
		"type" => "label",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "num_merchants",
		"sort_type" => "numeric",
		"visible" => "true",
		"on_js_event" => ""),
	"our_price" => array(
		"header" => "Our Price",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "our_price",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"our_sh" => array(
		"header" => "Our S&H",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "our_sh",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"our_total" => array(
		"header" => "Our Total",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "our_total",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"lowest_price" => array(
		"header" => "Low Price",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "lowest_price",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"lowest_sh" => array(
		"header" => "Low S&H",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "lowest_sh",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"lowest_total" => array(
		"header" => "Low Total",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "lowest_total",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"front_price" => array(
		"header" => "Feat. Price",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "front_price",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"front_sh" => array(
		"header" => "Feat. S&H",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "front_sh",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"front_total" => array(
		"header" => "Feat. Total",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "front_total",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"unit_price" => array(
		"header" => "Cost",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "unit_price",
		"visible" => "true",
		"sort_type" => "numeric",
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
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "profit",
		"visible" => "true",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"profit_pct" => array(
		"header" => "Profit %",
		"type" => "percent",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "profit_pct",
		"visible" => "true",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"price_diff" => array(
		"header" => "Price Diff",
		"type" => "label",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"sort_by" => "price_diff",
		"visible" => "true",
		"sort_type" => "string",
		"on_js_event" => ""),
	"price_diff_pct" => array(
		"header" => "Diff %",
		"type" => "percent",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "price_diff_pct",
		"visible" => "true",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "0",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"min_price" => array(
		"header" => "Bottom Price",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "min_price",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"num_sold" => array(
		"header" => "Qty Sold",
		"type" => "label",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "num_sold",
		"sort_type" => "numeric",
		"visible" => "true",
		"on_js_event" => ""),
	"last_sold" => array(
		"header" => "Last Sold",
		"type" => "label",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "last_sold",
		"visible" => "true",
		"on_js_event" => ""),
	"timestamp" => array(
		"header" => "Last Update",
		"type" => "label",
		"align" => "left",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "timestamp",
		"visible" => "true",
		"on_js_event" => ""),
	"remarks" => array(
		"header" => "Remarks",
		"type" => "label",
		"align" => "left",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "remarks",
		"visible" => "true",
		"on_js_event" => ""),
	);

$dgSales = new DataGrid(false, true, 'sh_');
$dgSales->SetColumnsInViewMode($columns);
$dgSales->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array('price_diff_pct' => 'DESC'));

$layouts = array(
	"view" => "0",
	"edit" => "0", 
	"details" => "1", 
	"filter" => "1"
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
	"ASIN" => array(
		"type" => "textbox",
		"table" => "ag",
		"field" => "asin",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"SKU" => array(
		"type" => "textbox",
		"table" => "ag",
		"field" => "sku",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"MPN" => array(
		"type" => "textbox",
		"table" => "ag",
		"field" => "mpn",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"# of Merchants" => array(
		"type" => "textbox",
		"table" => "ag",
		"field" => "num_merchants",
		"default_operator" => ">", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "30px", 
		"on_js_event" => ""),
	"Profit" => array(
		"type" => "textbox",
		"table" => "ag",
		"field" => "profit",
		"default_operator" => "<=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	"Profit %" => array(
		"type" => "textbox",
		"table" => "ag",
		"field" => "profit_pct",
		"default_operator" => "<=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "30px", 
		"on_js_event" => ""),
	"Price Diff" => array(
		"type" => "textbox",
		"table" => "ag",
		"field" => "price_diff",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	"Price Diff %" => array(
		"type" => "textbox",
		"table" => "ag",
		"field" => "price_diff_pct",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "30px", 
		"on_js_event" => ""),
	);

$dgSales->SetFieldsFiltering($filtering_fields);
$dgSales->Bind(false);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Amazon Price Grid</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		.default_dg_label, .default_dg_error_message
		{
			font-family: tahoma, verdana;
			font-size: 12px;
		}
		h2, h4, .dg_loading_image, p
		{
			font-family: tahoma, verdana;
		}
		.instructions
		{
			font-size: 10px;
		}
		.not-lowest
		{
			background-color:salmon !important;
		}
		.lowest-inc
		{
			background-color:cyan !important;
		}
		.lowest
		{
			background-color:lightgreen !important;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>Amazon Price Grid</h2>
<p>Note: This is the old Amazon Grid. If you are looking for the new simplified Amazon Grid, <a href="agrid.php">click here</a>.</p>
<p class="instructions">
If "Price Diff" is a positive value, it means that the competitor is selling at a lower price; try to lower the price to as low as the "Bottom Price"<br/>
If it is negative, then we are already the lowest price merchant; the value indicated is our difference to the second best merchant in terms of total price.</p>
<?php
	$dgSales->Show();
    ob_end_flush();
?>
<br/>
</center>
<br/>
<script src="js/jquery.min.js"></script>
<script src="js/jquery.jeditable.js" type="text/javascript"></script>
<script>
$(document).ready(function()
{
	$('#sh__contentTable tr td:nth-child(17)').each(function()
	{
		content = $(this).text();
		if (content != '')
		{
			contentVal = parseFloat(content);
			if (contentVal > 0)
			{
				$(this).addClass('not-lowest');
			}
			else if (contentVal < 0)
			{
				$(this).addClass('lowest-inc');
			}
			else if (contentVal == 0)
			{
				$(this).addClass('lowest');
			}
		}
	});
	
	$('#sh__contentTable tr td:nth-child(15)').each(function()
	{
		content = $(this).text();
		if (content != '')
		{
			contentVal = parseFloat(content);
			if (contentVal <= 0)
			{
				$(this).addClass('not-lowest');
			}
		}
	});
	
	$("#sh__contentTable tr td:last-child").each(function()
	{
		$(this).text($(this).text());
		$(this).attr('id', 'remarks_' + $(this).parent().children().filter(':first-child').text());
	});

	$("#sh__contentTable tr td:last-child").editable("agrid_remarks.php",
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