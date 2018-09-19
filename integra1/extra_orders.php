<?php

require_once('system/config.php');
require_once(DATAGRID_DIR . 'datagrid.class.php');
require_once('system/acl.php');

$user = Login('sales');

$suppliers = array(
	1 => 'W1',
	2 => 'W2',
	5 => 'W5'
);

$now = date_create("now", new DateTimeZone('America/New_York'));
$date = date_format($now, 'Y-m-d');

header('content-type: text/html; charset=utf-8');
session_cache_limiter ('private, must-revalidate');    
session_start();    
ob_start();
$debug_mode = false;
$messaging = true;
$unique_prefix = 'ex_';
$dgrid = new DataGrid($debug_mode, $messaging, $unique_prefix);
$sql = <<<EOD
SELECT id, mpn, quantity, supplier, date_added, added_by, remarks, order_id
FROM extra_orders WHERE date_added >= '2018-01-01'
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
	'supplier'=>array('header'=>'Supplier', 'type'=>'enum', 'align'=>'center', 'wrap'=>'nowrap', 'source' => $suppliers),
	'mpn'=>array('header'=>'MPN', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
	'quantity'=>array('header'=>'Quantity', 'type'=>'label', 'align'=>'right', 'wrap'=>'nowrap'),
	'date_added'=>array('header'=>'Date Added', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
	'added_by'=>array('header'=>'Added By', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
	'remarks'=>array('header'=>'Order Record #', 'type'=>'label', 'align'=>'left', 'wrap'=>'wrap'),
	'order_id'=>array('header'=>'Supplier Order ID', 'type'=>'label', 'align'=>'left', 'wrap'=>'nowrap'),
);
$dgrid->SetColumnsInViewMode($vm_columns);
$em_table_properties = array('width'=>'50%');
$dgrid->SetEditModeTableProperties($em_table_properties);
$table_name  = 'extra_orders';
$primary_key = 'id';
$condition   = 'extra_orders.id = ' . $_REQUEST['ex_rid'];
$dgrid->SetTableEdit($table_name, $primary_key, $condition);
$em_columns = array
(
	'supplier'  =>array('header'=>'Supplier', 'type'=>'enum', 'req_type'=>'rt', 'source' => $suppliers),
	'mpn'  =>array('header'=>'MPN', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'210px', 'maxlength'=>'50'),
	'quantity'  =>array('header'=>'Quantity', 'type'=>'textbox', 'req_type'=>'rn', 'width'=>'210px', 'maxlength'=>'3'),
	'remarks'  =>array('header'=>'Order Record #', 'type'=>'textbox', 'req_type'=>'rt', 'width'=>'210px', 'maxlength' =>'50'),
	'date_added'  =>array('header'=>'Date Added', 'type'=>'hidden', 'default'=>$date, 'value'=>$date),
	'added_by'  =>array('header'=>'Added By', 'type'=>'hidden', 'default'=>$user, 'value'=>$user),
);
$dgrid->SetColumnsInEditMode($em_columns);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Integra :: Extra Items for Bulk Orders</title>
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
<h2>Extra Items for Bulk Orders</h2>
</center>
<div id="instructions">
	<ul>
		<li>The items below will be added to the bulk order for EOC fulfilment.</li>
		<li>W1 bulk orders (delivered by truck) are placed at 5:30 AM EST from Monday to Saturday.</li>
		<li>W2 bulk orders (delivered by Next Day Air) are placed at 6:30 PM from Monday to Friday.</li>
		<li>Items that have been ordered from the supplier will have an Order ID in the grid below.</li>
		<li>Items that don't have an Order ID will be included in the next supplier order.</li>
		<li>Do not include the EOC/EOCS prefix for the MPNs.</li>
		<li>For W2 items, always include the brand code after the MPN and a dot (1234567.89)</li>
	</ul>
</div>
<br/>
<center>
<?php
$dgrid->Bind();
ob_end_flush();
?>
</center>
<script src="js/jquery.min.js" type="text/javascript"></script>
<script type="text/javascript">
$(document).ready(function()
{
	$('a:contains("Create")').attr('href', 'javascript:alert("Invalid MPN.");');
	$('#rtympn').after('<br/><span id="desc"></span>');
	$('#rtympn').change(querympn);
	$('#rtysupplier').change(querympn);
});

function querympn()
{
	if ($('#rtympn').val() == '')
	{
		$('#desc').text('');
		$('a:contains("Create")').attr('href', 'javascript:alert("Invalid MPN.");');
		$('a:contains("Update")').attr('href', 'javascript:alert("Invalid MPN.");');
	}

	if ($('#rtympn').val().indexOf('.') == -1)
	{
		$.getJSON('imc_ajax.php?sku=' + $('#rtympn').val(), function(data)
		{
			$('#desc').text(data.desc);
			if (data.desc == 'Not found')
			{
				$('a:contains("Create")').attr('href', 'javascript:alert("Invalid MPN.");');
				$('a:contains("Update")').attr('href', 'javascript:alert("Invalid MPN.");');
			}
			else
			{
				$('a:contains("Create")').attr('href', 'javascript:ex_sendEditFields();');
				$('a:contains("Update")').attr('href', 'javascript:ex_sendEditFields();');
			}
		});
	}
	else
	{
		$.getJSON('ssf_ajax.php?sku=' + $('#rtympn').val(), function(data)
		{
			if (data.desc == 'Not found')
			{
				$('#desc').text(data.desc);
				$('a:contains("Create")').attr('href', 'javascript:alert("Invalid MPN.");');
				$('a:contains("Update")').attr('href', 'javascript:alert("Invalid MPN.");');
			}
			else
			{
				$('#desc').text(data.desc + ' (' + data.brand + ')');
				$('a:contains("Create")').attr('href', 'javascript:ex_sendEditFields();');
				$('a:contains("Update")').attr('href', 'javascript:ex_sendEditFields();');
			}
		});
	}
}
</script>
</body>
</html>