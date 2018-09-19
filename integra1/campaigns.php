<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');
require_once('system/acl.php');

$user = Login();

header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');    
session_start();    
ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'cam_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = <<<EOD
SELECT id, name, subject, body, send_date, created_date, creator,
 (SELECT COUNT(*) FROM recipients r WHERE r.campaign_id = c.id AND sent_date IS NOT NULL) AS sent_count,
 (SELECT COUNT(*) FROM recipients r WHERE r.campaign_id = c.id) AS recipient_count
FROM campaigns c
EOD;
$default_order = array('id' => 'DESC');
$dgrid->DataSource('PDO', 'mysql', DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, $default_order);
$layouts = array('view'=>'0', 'edit'=>'1', 'details'=>'0', 'filter'=>'2');
$dgrid->SetLayouts($layouts);
$modes = array
(
	'add'	  =>array('view'=>true, 'edit'=>false, 'type'=>'link',  'show_button'=>true, 'show_add_button'=>'inside'),
	'edit'	  =>array('view'=>true, 'edit'=>true,  'type'=>'link',  'show_button'=>true, 'byFieldValue'=>''),
	'details' =>array('view'=>false, 'edit'=>false, 'type'=>'link',  'show_button'=>true),
	'delete'  =>array('view'=>true, 'edit'=>true,  'type'=>'image', 'show_button'=>true)
);
$dgrid->SetModes($modes);
$css_class = 'x-blue';
$dgrid->SetCssClass($css_class);
$dgrid->AllowPrinting(false);
$paging_option = false;
$rows_numeration = false;
$numeration_sign = '';
$dropdown_paging = false;
$dgrid->AllowPaging($paging_option, $rows_numeration, $numeration_sign, $dropdown_paging);
$dgrid->SetPagingSettings(null, null, null, 10000);
$vm_table_properties = array('width'=>'70%');
$dgrid->SetViewModeTableProperties($vm_table_properties);  
$vm_columns = array
(
	'name'=>array('header'=>'Name', 'type'=>'link', 'align'=>'left', 'wrap'=>'wrap', "href" => "http://integra.eocenterprise.com/recipients.php?campaign_id={0}", "field_key" => "id", "field_data" => "name"),
	'subject'=>array('header'=>'Subject', 'type'=>'link', 'align'=>'left', 'wrap'=>'wrap', "href" => "http://integra.eocenterprise.com/recipients.php?campaign_id={0}", "field_key" => "id", "field_data" => "subject"),
	'body'=>array('header'=>'Body', 'type'=>'label', 'align'=>'left', 'wrap'=>'wrap'),
	'send_date'=>array('header'=>'Send Date', 'type'=>'label', 'align'=>'left', 'wrap'=>'wrap'),
	'created_date'=>array('header'=>'Date Created', 'type'=>'label', 'align'=>'left', 'wrap'=>'wrap'),
	'creator'=>array('header'=>'Creator', 'type'=>'label', 'align'=>'left', 'wrap'=>'wrap'),
	'sent_count'=>array('header'=>'Sent', 'type'=>'link', 'align'=>'right', 'wrap'=>'nowrap', "href" => "http://integra.eocenterprise.com/recipients.php?campaign_id={0}", "field_key" => "id", "field_data" => "sent_count"),
	'recipient_count'=>array('header'=>'Recipients', 'type'=>'link', 'align'=>'right', 'wrap'=>'nowrap', "href" => "http://integra.eocenterprise.com/recipients.php?campaign_id={0}", "field_key" => "id", "field_data" => "recipient_count"),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$em_table_properties = array('width'=>'50%');
$dgrid->SetEditModeTableProperties($em_table_properties);
$table_name  = 'campaigns';
$primary_key = 'id';
$condition   = 'campaigns.id = ' . $_REQUEST['cam_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);
$em_columns = array
(
	'name'  =>array('header'=>'Name', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'210px', 'maxlength'=>'200'),
	'subject'  =>array('header'=>'Subject', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'210px', 'maxlength'=>'500'),
	'body'  =>array('header'=>'Body', 'type'=>'textarea', 'req_type'=>'ry', 'width'=>'210px', 'edit_type'=>'wysiwyg', 'resizable'=>'both', 'upload_images'=>'true', 'rows'=>'7', 'cols'=>'50'),
	'send_date'  =>array('header'=>'Send Date', 'type'=>'datetime', 'default_null'=>'true', 'show_seconds'=>'false', 'calendar_type'=> 'floating', 'req_type'=>'st', 'width'=>'210px', 'maxlength'=>'20'),
	'creator'  =>array('header'=>'Creator', 'type'=>'hidden', 'default'=>$user, 'value'=>$user),
);
$dgrid->SetColumnsInEditMode($em_columns);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: Email Campaign Admin</title>
<?php $dgrid->WriteCssClass(); ?>
<style>
	h2, p, li { font-family: tahoma, verdana; }
	#instructions
	{
		width: 700px;
		display: block;
		margin-left: auto;
		margin-right: auto;
	}
</style>
</head>
<body>
<?php include_once("analytics.php") ?>
<center>
<h2>Email Campaign Admin</h2>
<?php
$dgrid->Bind();
ob_end_flush();
?>
</center>
<?php if ($_REQUEST['cam_mode'] == 'add' || $_REQUEST['cam_mode'] == 'edit'): ?>
<div id="instructions">
	<p>You can use the following variables in the subject and body of the email:</p>
	<ul>
		<li><b>[EMAIL]</b> - Email address</li>
		<li><b>[BUYERID]</b> - eBay ID or Amazon account name</li>
		<li><b>[NAME]</b> - Ship to name</li>
		<li><b>[CITY]</b> - Ship to city</li>
		<li><b>[STATECODE]</b> - Ship to state code (i.e. FL, NV, etc.)</li>
		<li><b>[STATENAME]</b> - Ship to state name (i.e. Florida, Nevada, etc.)</li>
		<li><b>[PHONE]</b> - Phone number</li>
		<li><b>[LASTAGENT]</b> - Last agent or store name</li>
		<li><b>[LASTORDERDATE]</b> - Order date of most recent order</li>
		<li><b>[LASTORDERRECORDNUM]</b> - Record number of most recent order</li>
		<li><b>[LASTORDERTOTAL]</b> - Total amount of most recent order</li>
		<li><b>[LASTORDERTRACKING]</b> - Tracking number of most recent order</li>
		<li><b>[LASTORDERCARRIER]</b> - Carrier of most recent order (i.e. UPS, Fedex, etc.)</li>
		<li><b>[LASTORDERITEMNAME]</b> - Name of the most recent ordered item (i.e. Xenon Headlight Control Module)</li>
		<li><b>[LASTORDERITEMSKU]</b> - SKU of the most recent ordered item (i.e. EOC008855017)</li>
		<li><b>[LASTORDERITEMPRICE]</b> - Unit price of the most recent ordered item</li>
		<li><b>[TOTALORDERCOUNT]</b> - Total number of times the customer has ordered with the same email address</li>
		<li><b>[TOTALORDERAMOUNT]</b> - Total amount in dollars that the customer has ordered with the same email address</li>
	</ul>
</div>
<?php else: ?>
<center><p><i>Click on the campaign name to view the recipients list.</i></p></center>
<?php endif; ?>
<center><p><i>Note: Scheduled emails are sent roughly every 7AM Eastern time. If you add a recipient to a campaign that has a past send date, the email will be sent on the next 7AM schedule.</i></p></center>
</body>
</html>