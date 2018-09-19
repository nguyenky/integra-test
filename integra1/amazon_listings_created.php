<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/acl.php');

$user = Login('agrid');

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

$sql = "SELECT id, asin, created_by, created_on, price, sku
FROM v_amazon_new_listings ";

$columns = array(
	"id" => array(
		"type" => "label",
		"visible" => "false"),
	"created_on" => array(
		"header" => "Created On",
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
	"created_by" => array(
		"header" => "Created By",
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
	"asin" => array(
		"header" => "ASIN",
		"type" => "link",
		"align" => "center",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"field_key" => "asin",
		"field_data" => "asin",
		"target" => "asinDetails",
		"href" => "https://www.amazon.com/gp/offer-listing/{0}",
		"on_js_event" => ""),
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
	"price" => array(
		"header" => "Price",
		"type" => "money",
		"align" => "right",
		"width" => "",
		"wrap" => "nowrap",
		"text_length" => "-1",
		"case" => "normal",
		"summarize" => "false",
		"sort_by" => "",
		"visible" => "true",
		"sort_type" => "numeric",
		"sign" => "$",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ",",
		"on_js_event" => ""),
	);

$dgSales = new DataGrid(false, true, 'sh_');
$dgSales->SetColumnsInViewMode($columns);
$dgSales->DataSource("PEAR", "mysql", DB_HOST, 'integra_prod', DB_USERNAME, DB_PASSWORD, $sql, array('created_on' => 'DESC'));

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
		"table" => "v_amazon_new_listings ",
		"field" => "created_on",
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
		"table" => "v_amazon_new_listings ",
		"field" => "created_on",
		"field_type" => "to",
		"filter_condition" => "", 
		"show_operator" => "false", 
		"default_operator" => "<=", 
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "", 
		"on_js_event" => "", 
		"calendar_type" => "floating"),
	"Created By" => array(
		"type" => "dropdownlist",
		"table" => "v_amazon_new_listings ",
		"field" => "created_by",
		"filter_condition" => "",
		"show_operator" => "false",
		"default_operator" => "=",
		"case_sensitive" => "false",
		"comparison_type" => "string",
		"width" => "",
		"on_js_event" => ""),
	"ASIN" => array(
		"type" => "textbox",
		"table" => "v_amazon_new_listings ",
		"field" => "asin",
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
    <title>Amazon Listings Created</title>
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
        h2
        {
            font-family: tahoma, verdana;
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
<h2>Amazon Listings Created</h2>
<br/>

<?php
	$dgSales->Show();
    ob_end_flush();
?>
</center>
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

