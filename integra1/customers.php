<?php

require_once('system/config.php');
require_once('datagrid/datagrid.class.php');
require_once('system/acl.php');

$user = Login();

$campaignId = $_GET['campaign_id'];
settype($campaignId, 'integer');
$rids = $_GET['sh_rid'];

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
	"email" => array(
		"header" => "Email",
		"type" => "label",
		"align" => "left"),
	"buyer_id" => array(
		"header" => "Buyer ID",
		"type" => "label",
		"align" => "left"),
	"name" => array(
		"header" => "Name",
		"type" => "label",
		"align" => "left"),
	"city" => array(
		"header" => "City",
		"type" => "label",
		"align" => "left"),
	"state" => array(
		"header" => "State",
		"type" => "label",
		"align" => "left"),
	"phone" => array(
		"header" => "Phone",
		"type" => "label",
		"align" => "left"),
	"last_agent" => array(
		"header" => "Last Agent",
		"type" => "label",
		"align" => "left"),
	"last_order" => array(
		"header" => "Last Order Date",
		"type" => "label",
		"align" => "right"),
	"order_count" => array(
		"header" => "Order Count",
		"type" => "label",
		"align" => "right"),
	"order_total" => array(
		"header" => "Order Total",
		"type" => "label",
		"align" => "right"),
	);

$sql = 'SELECT id, email, buyer_id, name, city, state, phone, last_agent, last_order, order_count, order_total FROM customers';
$dg = new DataGrid(false, false, 'sh_');
$dg->SetColumnsInViewMode($columns);
$dg->DataSource("PDO", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql);

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
	"Email" => array(
		"type" => "textbox",
		"table" => "customers",
		"field" => "email",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Buyer ID" => array(
		"type" => "textbox",
		"table" => "customers",
		"field" => "buyer_id",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Name" => array(
		"type" => "textbox",
		"table" => "customers",
		"field" => "name",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"City" => array(
		"type" => "textbox",
		"table" => "customers",
		"field" => "city",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"State" => array(
		"type" => "textbox",
		"table" => "customers",
		"field" => "state",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Phone" => array(
		"type" => "textbox",
		"table" => "customers",
		"field" => "phone",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Last Agent" => array(
		"type" => "textbox",
		"table" => "customers",
		"field" => "last_agent",
		"default_operator" => "%like%", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => ""),
	"Last Order From" => array(
		"type" => "calendar",
		"table" => "customers",
		"field" => "last_order",
		"field_type" => "from",
		"filter_condition" => "", 
		"show_operator" => "false", 
		"default_operator" => ">=", 
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => "", 
		"calendar_type" => "floating"),
	"Last Order To" => array(
		"type" => "calendar",
		"table" => "customers",
		"field" => "last_order",
		"field_type" => "to",
		"filter_condition" => "", 
		"show_operator" => "false", 
		"default_operator" => "<=", 
		"case_sensitive" => "false", 
		"comparison_type" => "string", 
		"width" => "100px", 
		"on_js_event" => "", 
		"calendar_type" => "floating"),
	"Order Count" => array(
		"type" => "textbox",
		"table" => "customers",
		"field" => "order_count",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	"Order Total" => array(
		"type" => "textbox",
		"table" => "customers",
		"field" => "order_total",
		"default_operator" => ">=", 
		"show_operator" => "true",
		"case_sensitive" => "false", 
		"comparison_type" => "numeric", 
		"width" => "40px", 
		"on_js_event" => ""),
	);

$dSet = $dg->ExecuteSQL('SELECT id, name FROM campaigns ORDER BY name');
while ($row = $dSet->fetch())
	$campaigns[$row[0]] = $row[1];

if (!empty($campaignId))
{
	$rids_parts = explode("-", $rids);

	foreach ($rids_parts as $rid)
	{
		settype($rid, 'integer');
		
		if (!empty($rid))
			$dg->ExecuteSQL('INSERT IGNORE INTO recipients (campaign_id, email) (SELECT ' . $campaignId . ', email FROM customers WHERE id = ' . $rid . ')');
	}
	
	$message = 'The selected customers have been added to the email campaign recipients list.';
}

$dg->SetFieldsFiltering($filtering_fields);

$dg->AllowMultirowOperations(true);
$multirow_operations = array(
   "campaign" => array
   (
		"view" => true,
		"flag_name" => "campaign_id",
		"flag_value" => '\' + getCampaignId() + \'',
		"tooltip" => "Add to email campaign"
	),
	"edit"		=> array("view"=>false),
	"details"	=> array("view"=>false),
	"clone"		=> array("view"=>false),
	"delete"	=> array("view"=>false)
);
$dg->SetMultirowOperations($multirow_operations);

$dg->Bind(false);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Integra :: Customers Grid</title>
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
<h2>Customers Grid</h2>
<?php
	$dg->Show();
    ob_end_flush();
?>
</center>
<br/><br/><br/>
<select id="campaigns">
<?php
foreach ($campaigns as $id => $name)
	echo '<option value="' . $id . '">' . htmlentities($name) . "</option>\n";
?>
</select>
<script src="js/jquery.min.js" type="text/javascript"></script>
<script type="text/javascript">
function getCampaignId()
{
	return $('#campaigns').val();
}
$( document ).ready(function()
{
	$('img[title="Add to email campaign"]').before($('#campaigns'));
	$('img[title="Add to email campaign"]').css('vertical-align', 'middle');

	<?php if (!empty($message)): ?>
		alert('<?= $message ?>');
	<?php endif; ?>
});
</script>
</body>
</html>