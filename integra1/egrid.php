<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/acl.php');

$user = Login();

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
SELECT eg.item_id, el.sku, eg.mpn, eg.brand, our_total, low_total, low_total_seller, price_diff, price_diff_pct, our_sold, top_sold, top_sold_seller, sold_diff, sold_diff_pct, cost, profit, profit_pct, min_price,  DATE_FORMAT(eg.timestamp, '%m-%d') AS timestamp,
(SELECT DATE_FORMAT(MAX(order_date), '%m-%d') FROM sales s INNER JOIN sales_items si ON s.id = si.sales_id WHERE si.ebay_item_id = eg.item_id) AS last_sold,
keywords, remarks
FROM ebay_grid_summary eg LEFT JOIN ebay_listings el ON el.item_id = eg.item_id
WHERE eg.active = 1
EOD;

$columns = array(
	"item_id" => array(
		"header" => "Item ID",
		"type" => "link",
		"align" => "left",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "item_id",
		"visible" => "true",
		"field_key" => "item_id", 
		"field_data" => "item_id", 
		"target" => "egrid_comp",
		"href" => "egrid_comp.php?item_id={0}",
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
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "brand",
		"visible" => "true",
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
	"low_total" => array(
		"header" => "Lowest Total",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "low_total",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"low_total_seller" => array(
		"header" => "Lowest Seller",
		"type" => "link",
		"align" => "left",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "low_total_seller",
		"field_key" => "item_id", 
		"field_data" => "low_total_seller", 
		"target" => "egrid_comp",
		"href" => "egrid_comp.php?item_id={0}",
		"visible" => "true",
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
	"our_sold" => array(
		"header" => "Qty We Sold",
		"type" => "label",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "our_sold",
		"sort_type" => "numeric",
		"visible" => "true",
		"on_js_event" => ""),
	"top_sold" => array(
		"header" => "Best Qty Sold",
		"type" => "label",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "top_sold",
		"sort_type" => "numeric",
		"visible" => "true",
		"on_js_event" => ""),
	"top_sold_seller" => array(
		"header" => "Best Qty Seller",
		"type" => "label",
		"align" => "left",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "top_sold_seller",
		"field_key" => "item_id", 
		"field_data" => "top_sold_seller", 
		"target" => "egrid_comp",
		"href" => "egrid_comp.php?item_id={0}",
		"visible" => "true",
		"on_js_event" => ""),
	"sold_diff" => array(
		"header" => "Sold Diff",
		"type" => "label",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"sort_by" => "sold_diff",
		"visible" => "true",
		"sort_type" => "string",
		"on_js_event" => ""),
	"sold_diff_pct" => array(
		"header" => "Sold Diff %",
		"type" => "percent",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "sold_diff_pct",
		"visible" => "true",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "0",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"last_sold" => array(
		"header" => "Last Sold",
		"type" => "label",
		"align" => "right",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"visible" => "true",
		"on_js_event" => ""),
	"cost" => array(
		"header" => "Cost",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "cost",
		"visible" => "false",
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
		"visible" => "false",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"profit_pct" => array(
		"header" => "Margin",
		"type" => "percent",
		"align" => "right",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "profit_pct",
		"visible" => "false",
		"sort_type" => "string",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
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
		"visible" => "false",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	"timestamp" => array(
		"header" => "Last Update",
		"type" => "label",
		"align" => "right",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "timestamp",
		"visible" => "true",
		"on_js_event" => ""),
	"keywords" => array(
		"header" => "Keywords",
		"type" => "label",
		"align" => "left",
		"width" => "",
		"wrap" => "wrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "keywords",
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
$dgSales->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array('sold_diff_pct' => 'DESC'));

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
	"Item ID" => array(
		"type" => "textbox",
		"table" => "eg",
		"field" => "item_id",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
    "SKU" => array(
        "type" => "textbox",
        "table" => "el",
        "field" => "sku",
        "default_operator" => "%like%",
        "show_operator" => "true",
        "case_sensitive" => "false",
        "comparison_type" => "string",
        "width" => "100px",
        "on_js_event" => ""),
	"MPN" => array(
		"type" => "textbox",
		"table" => "eg",
		"field" => "mpn",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Brand" => array(
		"type" => "textbox",
		"table" => "eg",
		"field" => "brand",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Price Diff" => array(
		"type" => "textbox",
		"table" => "eg",
		"field" => "price_diff",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	"Price Diff %" => array(
		"type" => "textbox",
		"table" => "eg",
		"field" => "price_diff_pct",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "30px", 
		"on_js_event" => ""),
	"Lowest Seller" => array(
		"type" => "textbox",
		"table" => "eg",
		"field" => "low_total_seller",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Sold Diff" => array(
		"type" => "textbox",
		"table" => "eg",
		"field" => "sold_diff",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	"Sold Diff %" => array(
		"type" => "textbox",
		"table" => "eg",
		"field" => "sold_diff_pct",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "30px", 
		"on_js_event" => ""),
	"Best Qty Seller" => array(
		"type" => "textbox",
		"table" => "eg",
		"field" => "top_sold_seller",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	);

$dgSales->SetFieldsFiltering($filtering_fields);
$dgSales->Bind(false);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>eBay Market Competition Grid</title>
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
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>eBay Market Competition Grid</h2>
<?php
	$dgSales->Show();
    ob_end_flush();
?>
</center>
<br/>
<script src="js/jquery-ui.min.js" type="text/javascript"></script>
<script src="js/jquery.jeditable.js" type="text/javascript"></script>
<script type="text/javascript">

$(document).ready(init);

function init()
{
	$("#sh__contentTable tr td:last-child").each(function()
	{
		$(this).text($(this).text());
		$(this).attr('id', 'remarks_' + $(this).parent().children().filter(':first-child').text());
	});

	$("#sh__contentTable tr td:last-child").editable("egrid_remarks.php",
	{ 
		indicator : "<img src='img/ajax.gif'>",
		event     : "click",
		style	  : "inherit",
		placeholder   : '      '
	});
	
	$("#sh__contentTable tr td:nth-last-child(2)").each(function()
	{
		$(this).text($(this).text());
		$(this).attr('id', 'keywords_' + $(this).parent().children().filter(':first-child').text());
	});

	$("#sh__contentTable tr td:nth-last-child(2)").editable("egrid_keywords.php",
	{ 
		indicator : "<img src='img/ajax.gif'>",
		event     : "click",
		style	  : "inherit",
		placeholder   : '      '
	});
}

</script>
</body>
</html>