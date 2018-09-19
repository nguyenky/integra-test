<?php

require_once('system/imc_utils.php');

$sku = $_GET['sku'];

if (empty($sku))
	return;

$skus[] = $sku;
$results = ImcUtils::QueryItems($skus);

$resp = $results[0];

if ($resp['price'] != '?')
	$resp['sites'] = ImcUtils::$siteIDs;

if (!empty($results))
	echo json_encode($resp);

?>
