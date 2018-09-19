<?php

require_once('system/ssf_utils.php');

foreach ($_POST as $key => $val)
{
	if (empty($val))
		continue;

	if (!startsWith($key, 'w2order_'))
		continue;

	$fields = explode('_', $key);
	
	if (empty($fields) || count($fields) != 3)
		continue;
		
	$sku = $fields[2];
	
	unset($order);
	$order['site'] = $fields[1];
	$order['sku'] = $sku;
	$order['qty'] = $val;
	$orders[] = $order;
	
	$skus[$sku] = $val;
	$descs[$sku] = $_POST["w2desc_${sku}"];
	$prices[$sku] = $_POST["w2price_${sku}"];
}

if (empty($orders))
	return;

$name = $_POST['name'];
$address = $_POST['address'];
$city = $_POST['city'];
$state = $_POST['state'];
$zip = $_POST['zip'];
$phone = $_POST['phone'];
$recordNum = $_POST['recordNum'];
$state = convert_state($state, 'abbrev');

$speed = $_POST['speed'];
$ssfSpeed = SsfUtils::ConvertShipping($speed);

$salesId = $_POST['salesId'];
$email = $_POST['email'];
$agent = $_POST['agent'];
$manual = empty($salesId);

$results = SsfUtils::OrderItems($orders, $skus, $name, $address, $city, $state, $zip, $phone, $recordNum, $ssfSpeed, '1');

if ($results['success'] == 1)
{
	$internalId = $results['message'];

	if ($manual)
		$salesId = SaveManualOrder($skus, $prices, $descs, $internalId, $recordNum, $email, $name, $address, $city, $state, $zip, $phone, $speed, $agent);

	SaveDirectShipment($salesId, 2, $internalId, $results['refId'], $results['subtotal'], $results['core'], $results['shipping'], $results['total']);
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Direct Shipment</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" type="text/css" href="datagrid/styles/x-blue/style.css">
	<style>
		h1
		{
			text-align: left !important;
		}
		body
		{
			padding: 20px;
			font-family: tahoma, verdana;
			font-size: 12px;
		}
	</style>
  </head>
<body>
	<?php include_once("analytics.php") ?>
	<h1 class="x-blue_dg_caption">Direct Shipment Submission Results</h1>

<?php if ($results['success'] == 1): ?>
	Order accepted for warehouse 2 under order ID <?=$internalId?>. Included items:
	<ul>
	<?php foreach ($skus as $sku => $qty) echo "<li>${qty}x ${sku}</li>\r\n"; ?>
	</ul>
<?php else: ?>
	Order for warehouse 2 was rejected. Reason: <?=$results['message']?>. Affected items:
	<ul>
	<?php foreach ($results['items'] as $sku) echo "<li>${sku}</li>\r\n"; ?>
	</ul>
<?php endif; ?>

	<br/>
	<a href="javascript:closeWindow();">Close this window</a>
	<br/>
	<a href='http://integra2.eocenterprise.com/#/orders/view/<?=$salesId?>' class='x-blue_dg_label'>Back to Order Details page</a>
	<script>
	function closeWindow()
	{
		window.open('','_self','');
		window.close();
	}
	</script> 
</body>
</html>