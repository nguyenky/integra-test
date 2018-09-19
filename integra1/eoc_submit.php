<?php

require_once('system/utils.php');

$warehouse = $_REQUEST['w'];

foreach ($_POST as $key => $val)
{
	if (empty($val))
		continue;

	if (!startsWith($key, 'w' . $warehouse. 'order_'))
		continue;

	$fields = explode('_', $key);
	
	if (empty($fields) || count($fields) != 3)
		continue;
		
	$sku = $fields[2];
	$skus[$sku] = $val;
	$descs[$sku] = $_POST["w${warehouse}desc_${sku}"];
	$prices[$sku] = $_POST["w${warehouse}price_${sku}"];
}

if (empty($skus))
	return;

$recordNum = $_POST['recordNum'];
$email = $_POST['email'];
$name = $_POST['name'];
$address = $_POST['address'];
$city = $_POST['city'];
$state = $_POST['state'];
$zip = $_POST['zip'];
$phone = $_POST['phone'];
$speed = $_POST['speed'];
$agent = $_POST['agent'];
$relatedRecordNum = $_POST['relatedRecordNum'];
$soldPrice = $_POST['soldPrice'];

if ($speed == 'GROUND')
	$speed = 'Ground';
else if ($speed == '2ND DAYAIR')
	$speed = '2nd Day Air';
else if ($speed == 'NXTDAYSAVR')
	$speed = 'Next Day Air';

$state = convert_state($state, 'abbrev');

$salesId = CreateManualOrder($skus, $prices, $descs, $recordNum, $recordNum, $email, $name, $address, $city, $state, $zip, $phone, $speed, $agent, 3, 1, $relatedRecordNum, $soldPrice, $warehouse);

header("Location: http://integra2.eocenterprise.com/#/orders/view/${salesId}");