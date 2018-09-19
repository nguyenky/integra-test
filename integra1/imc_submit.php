<?php

require_once('system/imc_utils.php');

foreach ($_POST as $key => $val)
{
	if (empty($val))
		continue;

	if (!startsWith($key, 'w1order_'))
		continue;

	$fields = explode('_', $key);
	
	if (empty($fields) || count($fields) != 3)
		continue;
	
	$siteId = $fields[1];
	$sku = $fields[2];
	$sites[$siteId] = 1;

	$toOrder[$siteId][$sku] = $val;
	$descs[$sku] = $_POST["w1desc_${sku}"];
	$prices[$sku] = $_POST["w1price_${sku}"];
}

if (empty($toOrder))
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

$salesId = $_POST['salesId'];
$email = $_POST['email'];
$agent = $_POST['agent'];
$manual = empty($salesId);

$recordCtr = 1;

$siteResults = array();

foreach ($toOrder as $siteID => $skus)
{
	if (count($sites) > 1)
	{
		$rNum = $recordNum . "-${recordCtr}";
		$recordCtr++;
	}
	else
		$rNum = $recordNum;

	$results = ImcUtils::OrderItems($siteID, $skus, $name, $address, $city, $state, $zip, $phone, $rNum, $speed);
	
	if ($results['success'] == 1)
	{
		$internalId = $results['message'];

		if ($manual)
			$salesId = SaveManualOrder($skus, $prices, $descs, $internalId, $recordNum, $email, $name, $address, $city, $state, $zip, $phone, $speed, $agent);

		SaveDirectShipment($salesId, 1, $internalId, null, $results['subtotal'], $results['core'], $results['shipping'], $results['total']);
	}
	
	$siteResults[$siteID] = $results;
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
	<ul>
<?php
	foreach ($toOrder as $siteID => $skus)
	{
		if ($siteResults[$siteID]['success'] == 1)
			echo "<li>Order accepted for warehouse 1 (" . ImcUtils::$siteIDs[$siteID] . ") under order ID " . $siteResults[$siteID]['message'] . ". Included items:</li>\r\n";
		else
			echo "<li>Order for warehouse 1 (" . ImcUtils::$siteIDs[$siteID] . ") was rejected. Reason: " . $siteResults[$siteID]['message'] . ". Affected items:</li>\r\n";
		
		echo "<ul>\r\n";
		foreach ($skus as $sku => $qty)
			echo "<li>${qty}x ${sku}</li>\r\n";
		echo "</ul>\r\n";
	}
?>
	</ul>
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