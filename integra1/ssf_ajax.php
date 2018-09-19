<?php

require_once('system/ssf_utils.php');

$sku = $_GET['sku'];

if (empty($sku))
	return;

$skus[] = $sku;
$results = SsfUtils::QueryItems($skus);

if (!empty($results))
{
	$output = $results[0];
	
	$resultDotIdx = strpos($output['sku'], '.');
	$requestDotIdx = strpos($sku, '.');

	if ($resultDotIdx && $requestDotIdx === false)
		$output['sku'] = $sku;
		
	if ($resultDotIdx === false && $requestDotIdx)
		$output['sku'] = $sku;
	
	if ($output['price'] != '?')
		$output['sites'] = SsfUtils::$siteIDs;
	
	echo json_encode($output);
}

?>
