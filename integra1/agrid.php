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
		al.asin,
		al.sku,
		sc.price,
		sc.shipping,
		sc.cond,
		sc.seller_code,
		sc.seller,
		sc.rating,
		(SELECT bb.price + bb.shipping AS bb_price FROM amazon_scraper bb WHERE bb.buybox = 1 AND bb.asin = al.asin ORDER BY 1 ASC LIMIT 1) AS bb_price,
		al.last_scraped
	FROM amazon_listings al LEFT JOIN amazon_scraper sc ON al.asin = sc.asin
	WHERE al.active = 1";

$columns = array(
	"asin" => array(
		"header" => "ASIN",
		"type" => "label",
		"wrap" => "nowrap"),
	"sku" => array(
		"header" => "SKU",
		"type" => "label",
		"wrap" => "nowrap"),
	"cond" => array(
		"header" => "Condition",
		"type" => "label",
		"wrap" => "nowrap"),
	"seller_code" => array(
		"header" => "Seller Code",
		"type" => "label",
		"wrap" => "wrap"),
	"seller" => array(
		"header" => "Seller Name",
		"type" => "label",
		"wrap" => "wrap"),
	"rating" => array(
		"header" => "Rating",
		"type" => "label",
		"wrap" => "wrap"),
	"price" => array(
		"header" => "Price",
		"type" => "label",
		"align" => "right",
		"wrap" => "nowrap"),
	"shipping" => array(
		"header" => "Shipping",
		"type" => "label",
		"align" => "right",
		"wrap" => "nowrap"),
	"bb_price" => array(
		"header" => "Buy Box",
		"type" => "label",
		"align" => "right",
		"wrap" => "nowrap"),
	"last_scraped" => array(
		"header" => "Last Scraped",
		"type" => "label",
		"wrap" => "wrap"),
	);

$dgSales = new DataGrid(false, true, 'sh_');
$dgSales->SetColumnsInViewMode($columns);
$dgSales->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array('last_scraped' => 'DESC'));

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
$dgSales->AllowExporting(false, false);
$dgSales->SetPagingSettings($paging, array(), $pages_array, 50, $paging_arrows);
$dgSales->AllowFiltering(true, false);

$filtering_fields = array(
	"ASIN" => array(
		"type" => "text",
		"table" => "al",
		"field" => "asin",
		"show_operator" => "false", 
		"default_operator" => "=", 
		"case_sensitive" => "false", 
		"comparison_type" => "string"),
	"SKU" => array(
		"type" => "text",
		"table" => "al",
		"field" => "sku",
		"show_operator" => "false", 
		"default_operator" => "=", 
		"case_sensitive" => "false", 
		"comparison_type" => "string"),
	"Seller Name" => array(
		"type" => "text",
		"table" => "sc",
		"field" => "seller",
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
    <title>Amazon Grid</title>
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
		h2, h4, .dg_loading_image, p
		{
			font-family: tahoma, verdana;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>Amazon Price Grid</h2>
<p>New: To export all the data into a zipped CSV file, <a href="agrid_export.php">click here</a>.</p>
<?php
$dgSales->Show();
ob_end_flush();
?>
<br/>
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