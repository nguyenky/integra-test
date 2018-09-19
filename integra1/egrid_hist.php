<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');

$itemId = $_REQUEST['item_id'];
$thisItem = $_REQUEST['this_item'];

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

$columns = array(
	"timestamp" => array(
		"header" => "Record Date",
		"type" => "label",
		"align" => "left",
		"wrap" => "nowrap",
		"sort_by" => "timestamp"),
	"this_item" => array(
		"header" => "Item ID",
		"type" => "link",
		"align" => "left",
		"wrap" => "nowrap",
		"sort_by" => "title",
		"field_key" => "this_item", 
		"field_data" => "title", 
		"target" => "_blank",
		"href" => "http://www.ebay.com/itm/{0}"),
	"seller" => array(
		"header" => "Seller",
		"type" => "label",
		"align" => "left"),
	"rating" => array(
		"header" => "Rating",
		"type" => "label",
		"align" => "left",
		"wrap" => "nowrap"),
	"price" => array(
		"header" => "Price",
		"type" => "money",
		"align" => "right",
		"sort_by" => "price",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ","),
	"shipping" => array(
		"header" => "Shipping",
		"type" => "money",
		"align" => "right",
		"sort_by" => "shipping",
		"sort_type" => "numeric",
		"sign" => "",
		"sign_place" => "before",
		"decimal_places" => "2",
		"dec_separator" => ".",
		"thousands_separator" => ","),
	"num_sold" => array(
		"header" => "Qty Sold",
		"type" => "label",
		"align" => "right",
		"sort_by" => "num_sold",
		"sort_type" => "numeric"),
	"num_compat" => array(
		"header" => "Compatible Vehicles",
		"type" => "label",
		"align" => "right",
		"sort_by" => "num_compat",
		"sort_type" => "numeric"),
	"num_avail" => array(
		"header" => "Qty Available",
		"type" => "label",
		"align" => "right",
		"sort_by" => "num_avail",
		"sort_type" => "numeric"),
	);
	
$sql = <<<EOD
SELECT * FROM
(
	SELECT item_id, this_item, title, price, shipping, seller, CONCAT(score, ' / ', rating, '%', IF(top = 1, ' - Top', '')) AS rating, top, pos, num_sold, num_compat, num_avail, timestamp
	FROM ebay_grid_history
	WHERE item_id = '${itemId}'
	AND this_item = '${thisItem}'
	UNION
	SELECT item_id, this_item, title, price, shipping, seller, CONCAT(score, ' / ', rating, '%', IF(top = 1, ' - Top', '')) AS rating, top, pos, num_sold, num_compat, num_avail, timestamp
	FROM ebay_grid
	WHERE item_id = '${itemId}'
	AND this_item = '${thisItem}'
) x
EOD;

$dg = new DataGrid(false, false, 'sh_');
$dg->SetColumnsInViewMode($columns);
$dg->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array('timestamp' => 'DESC'));

$layouts = array(
	"view" => "0",
	"edit" => "0", 
	"details" => "1", 
	"filter" => "0"
	);

$dg->SetLayouts($layouts);
$dg->SetPostBackMethod('AJAX');
$dg->SetHttpGetVars(array("item_id", "this_item"));
$dg->SetModes(array());
$dg->SetCssClass("x-blue");
$dg->AllowSorting(true);
$dg->AllowPrinting(false);
$dg->AllowExporting(false, false);
//$dg->AllowExportingTypes(array('csv'=>'true', 'xls'=>'true', 'pdf'=>'false', 'xml'=>'false'));
$dg->SetPagingSettings($paging, array(), array(), 100, array());
$dg->AllowFiltering(false, false);
$dg->Bind(false);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>eBay Price Grid - Listing Historical Snapshots</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		h2, h4, .dg_loading_image, p
		{
			font-family: tahoma, verdana;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>Listing Historical Snapshots</h2>
<?php
	$dg->Show();
    ob_end_flush();
?>
</center>
</body>
</html>