<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/acl.php');

$user = Login('egrid');

set_time_limit(0);

$itemId = $_REQUEST['item_id'];
$seller = EBAY_SELLER;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://integra.eocenterprise.com/e_update.php?item_id=${itemId}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
$ures = trim(curl_exec($ch));

if (stripos($ures, 'WARNING') !== FALSE)
	$pclass = 'warnmsg';
else if (stripos($ures, 'ERROR') !== FALSE)
	$pclass = 'errormsg';
else
{
	if (!empty($ures))
		$pclass = 'okmsg';
	else
	{
		$pclass = 'errormsg';
		$ures = 'ERROR: Data scraping failed (unknown error). Please try to refresh the page.';
	}
}

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
	"image" => array(
		"header" => "Image",
		"type" => "data",
		"align" => "center"),
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
		"align" => "left",
		"wrap" => "nowrap",
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
SELECT item_id, this_item, title, 
CONCAT('<a class="preview" href="', image_url, '"><img class="preview" src="', image_url, '"/></a>') AS image,
price, shipping, seller, CONCAT(score, ' / ', rating, '%', IF(top = 1, ' - Top', '')) AS rating, top, pos, num_hit, num_sold, num_compat, num_avail, timestamp,
CONCAT('<a target="ematch" href="ematch.php?src=', this_item, '&dest=', item_id, '"><img src="img/copy.png" title="Copy or revise listing"/></a>') AS copy
FROM ebay_grid
WHERE item_id = '${itemId}'
AND active = 1
EOD;

$dg = new DataGrid(false, false, 'sh_');
$dg->SetColumnsInViewMode($columns);
$dg->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array('num_sold' => 'DESC'));

$layouts = array(
	"view" => "0",
	"edit" => "0", 
	"details" => "1", 
	"filter" => "0"
	);

$dg->SetLayouts($layouts);
$dg->SetPostBackMethod('AJAX');
$dg->SetHttpGetVars(array("item_id"));
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
    <title>eBay Price Grid</title>
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
		.okmsg
		{
			background-color: lightgreen !important;
			font-family: tahoma, verdana;
			font-size: 14px;
			width: 800px;
			font-weight: bold;
		}
		.errormsg
		{
			background-color: salmon !important;
			font-family: tahoma, verdana;
			font-size: 14px;
			width: 800px;
			font-weight: bold;
		}
		.warnmsg
		{
			background-color: yellow !important;
			font-family: tahoma, verdana;
			font-size: 14px;
			width: 800px;
			font-weight: bold;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<center>
<br/>
<p class="<?= $pclass ?>"><?= $ures ?></p>
<br/>
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
	$('td:contains("<?=$seller?>")').closest('tr').addClass('ourListing');
});
</script>
</body>
</html>