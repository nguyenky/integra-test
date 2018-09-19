<?php

require_once('system/esi_utils.php');

$sku = $_GET['sku'];

if (empty($sku))
	return;

$skus[] = $sku;
$results = EsiUtils::QueryItems($skus);

if (!empty($results))
	echo json_encode($results[0]);

?>