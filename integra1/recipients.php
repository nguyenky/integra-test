<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');
require_once('system/acl.php');

$user = Login('campaigns');

$campaignId = $_REQUEST['campaign_id'];
settype($salesId, 'integer');

if (empty($campaignId))
{
	header('Location: campaigns.php');
	return;
}

header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');    
session_start();    
ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'rec_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = "SELECT id, email, sent_date FROM recipients WHERE campaign_id = ${campaignId}";
$default_order = array('id' => 'ASC');
$dgrid->DataSource('PDO', 'mysql', DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, $default_order);
$layouts = array('view'=>'0', 'edit'=>'0', 'details'=>'0', 'filter'=>'2');
$dgrid->SetLayouts($layouts);
$modes = array
(
	'add'	  =>array('view'=>false, 'edit'=>false, 'type'=>'link',  'show_button'=>true, 'show_add_button'=>'inside'),
	'edit'	  =>array('view'=>false, 'edit'=>false,  'type'=>'link',  'show_button'=>true, 'byFieldValue'=>''),
	'details' =>array('view'=>false, 'edit'=>false, 'type'=>'link',  'show_button'=>true),
	'delete'  =>array('view'=>true, 'edit'=>true,  'type'=>'image', 'show_button'=>true)
);
$dgrid->SetModes($modes);
$css_class = 'x-blue';
$dgrid->SetCssClass($css_class);
$dgrid->AllowPrinting(false);
$http_get_vars = array('campaign_id');
$dgrid->SetHttpGetVars($http_get_vars);

$paging = array(
	"results" => true,
	"results_align" => "left",
	"pages" => true,
	"pages_align" => "center",
	"page_size" => true,
	"page_size_align" => "right"
	);

$pages_array = array(
	"100" => "100",
	"200" => "200",
	"500" => "500"
	);

$paging_arrows = array(
	"first" => "|&lt;&lt;",
	"previous" => "&lt;&lt;",
	"next" => "&gt;&gt;",
	"last" => "&gt;&gt;|"
	);

$dgrid->SetPagingSettings($paging, array(), $pages_array, 100, $paging_arrows);
$vm_table_properties = array('width'=>'50%');
$dgrid->SetViewModeTableProperties($vm_table_properties);

$vm_columns = array
(
	'email'=>array('header'=>'Email', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
	'sent_date'=>array('header'=>'Sent Date', 'type'=>'label', 'align'=>'right', 'wrap'=>'nowrap'),
	
);
$dgrid->SetColumnsInViewMode($vm_columns);
$table_name  = 'recipients';
$primary_key = 'id';
$condition   = 'recipients.id = ' . $_REQUEST['rec_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);

$dSet = $dgrid->ExecuteSQL("SELECT name FROM campaigns WHERE id=${campaignId}");
if ($row = $dSet->fetch())
	$name = $row[0];

if ($_REQUEST['add'] == '1')
{
	$emails = $_REQUEST['emails'];

	if (!empty($emails))
	{
		$email_list = explode("\n", $emails);
		foreach ($email_list as $email)
		{
			$q = "INSERT IGNORE INTO recipients (campaign_id, email) VALUES (${campaignId}, '%s')";
			$dgrid->ExecuteSQL(sprintf($q, str_replace("'", '', trim($email))));
		}
	}
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: Email Campaign Admin</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2, h4, p { font-family: tahoma, verdana; }
</style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2><a href="campaigns.php">Email Campaign</a> Recipients</h2>
<h4><?=htmlentities($name)?></h4>
<?php
$dgrid->Bind();
ob_end_flush();
?>
<br/><hr/>
<h4>Add Recipients</h4>
<form action="recipients.php?add=1&campaign_id=<?=$campaignId?>" method="POST">
	<p>Add emails to the recipient list, one email per line.</p>
	<p>WARNING: If you add an email whose order is not in Integra, variable substitutions will NOT work and will appear as blank.</p>
	<p>The recommended approach is to open <a href="customers.php">Customers Grid</a>, select customers, and click 'Add to email campaign' at the bottom of the page.</p>
	<textarea name="emails" rows="15" cols="30"></textarea>
	<br/>
	<input type="submit" value="Add Recipients"/>
</form>
</center>
</body>
</html>