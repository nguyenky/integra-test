<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/acl.php');

$user = Login();

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

$columns = array(
	"image" => array(
		"header" => "Image",
		"type" => "data",
		"align" => "center"),
	"item_id" => array(
		"header" => "Similar to ID",
		"type" => "link",
		"align" => "left",
		"wrap" => "nowrap",
		"sort_by" => "title",
		"field_key" => "item_id", 
		"field_data" => "item_id", 
		"target" => "egrid_all",
		"href" => "egrid_comp.php?item_id={0}"),
	"this_item" => array(
		"header" => "Item ID",
		"type" => "link",
		"align" => "left",
		"wrap" => "nowrap",
		"sort_by" => "title",
		"field_key" => "this_item", 
		"field_data" => "this_item", 
		"target" => "_blank",
		"href" => "http://www.ebay.com/itm/{0}"),
	"brand" => array(
		"header" => "Brand",
		"type" => "label",
		"align" => "left"),
	"title" => array(
		"header" => "Title",
		"type" => "link",
		"align" => "left",
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
	"num_hit" => array(
		"header" => "Hits",
		"type" => "label",
		"align" => "right",
		"sort_by" => "num_hit",
		"sort_type" => "numeric"),
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
	"timestamp" => array(
		"header" => "Last Update",
		"type" => "link",
		"align" => "right",
		"sort_by" => "timestamp",
		"field_key_0" => "item_id", 
		"field_key_1" => "this_item", 
		"field_data" => "timestamp", 
		"target" => "_blank",
		"href" => "egrid_hist.php?item_id={0}&this_item={1}"),
	"copy" => array(
		"header" => "Revise",
		"type" => "data",
		"align" => "center"),
	);
	
$sql = <<<EOD
SELECT item_id, this_item, brand, title, 
CONCAT('<a class="preview" href="', image_url, '"><img class="preview" src="', image_url, '"/></a>') AS image,
price, shipping, seller, CONCAT(score, ' / ', rating, '%', IF(top = 1, ' - Top', '')) AS rating, top, pos, num_hit, num_sold, num_compat, num_avail, DATE_FORMAT(timestamp, '%m-%d') AS timestamp,
CONCAT('<a target="ematch" href="ematch.php?src=', this_item, '&dest=', item_id, '"><img src="img/copy.png" title="Copy or revise listing"/></a>') AS copy
FROM ebay_grid
WHERE active = 1
EOD;

$dg = new DataGrid(false, false, 'sh_');
$dg->SetColumnsInViewMode($columns);
$dg->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql);

$layouts = array(
	"view" => "0",
	"edit" => "0", 
	"details" => "1", 
	"filter" => "1"
	); 
$dg->setLayouts($layouts);
$dg->SetPostBackMethod('GET');
$dg->SetModes(array());
$dg->SetCssClass("x-blue");
$dg->AllowSorting(true);
$dg->AllowPrinting(false);
$dg->AllowExporting(true, true);
$dg->AllowExportingTypes(array('csv'=>'true', 'xls'=>'true', 'pdf'=>'false', 'xml'=>'false'));
$dg->SetPagingSettings($paging, array(), $pages_array, 50, $paging_arrows);
$dg->AllowFiltering(true, false);

$filtering_fields = array(
	"Similar to ID" => array(
		"type" => "textbox",
		"table" => "ebay_grid",
		"field" => "item_id",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Item ID" => array(
		"type" => "textbox",
		"table" => "ebay_grid",
		"field" => "this_item",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Title" => array(
		"type" => "textbox",
		"table" => "ebay_grid",
		"field" => "title",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Seller" => array(
		"type" => "textbox",
		"table" => "ebay_grid",
		"field" => "seller",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Price" => array(
		"type" => "textbox",
		"table" => "ebay_grid",
		"field" => "price",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	"Shipping" => array(
		"type" => "textbox",
		"table" => "ebay_grid",
		"field" => "shipping",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	"Hits" => array(
		"type" => "textbox",
		"table" => "ebay_grid",
		"field" => "num_hit",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	"Qty Sold" => array(
		"type" => "textbox",
		"table" => "ebay_grid",
		"field" => "num_sold",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	"Compatible Vehicles" => array(
		"type" => "textbox",
		"table" => "ebay_grid",
		"field" => "num_compat",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	"Qty Available" => array(
		"type" => "textbox",
		"table" => "ebay_grid",
		"field" => "num_avail",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	);

$dg->SetFieldsFiltering($filtering_fields);
$dg->Bind(false);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>eBay Price Grid - All Scraped Active Listings</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		img.preview
		{
			height: 50px;
		}
		#preview
		{
			position:absolute;
			border:1px solid #ccc;
			background:#333;
			padding:5px;
			display:none;
			color:#fff;
		}
		.ourListing
		{
			background-color: lightgreen !important;
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
<h2>eBay Grid - All Scraped Active Listings</h2>
<?php
	$dg->Show();
    ob_end_flush();
?>
</center>
<br/><br/><br/><br/><br/><br/>
<script src="js/jquery.min.js"></script>
<script>
this.imagePreview = function()
{
	xOffset = 10;
	yOffset = 30;
	
	$("a.preview").hover(function(e)
	{
		this.t = this.title;
		this.title = "";	
		var c = (this.t != "") ? "<br/>" + this.t : "";
		$("body").append("<p id='preview'><img src='"+ this.href +"' alt='Image preview' />"+ c +"</p>");								 
		$("#preview")
			.css("top",(e.pageY - xOffset) + "px")
			.css("left",(e.pageX + yOffset) + "px")
			.fadeIn("fast");						
    },
	function()
	{
		this.title = this.t;	
		$("#preview").remove();
    });	
	$("a.preview").mousemove(function(e)
	{
		$("#preview")
			.css("top",(e.pageY - xOffset) + "px")
			.css("left",(e.pageX + yOffset) + "px");
	});			
};

$(document).ready(function()
{
	imagePreview();
});
</script>
</body>
</html>