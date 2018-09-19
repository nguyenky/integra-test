<?php

require_once('system/stamps_utils.php');

define('SHIPFROM_ZIP', '33166');

$rates = StampsUtils::GetRates(SHIPFROM_ZIP,
	$_REQUEST['zip'],
    $_REQUEST['country'],
	$_REQUEST['pounds'],
	$_REQUEST['ounces'],
	$_REQUEST['length'],
	$_REQUEST['width'],
	$_REQUEST['height']);

foreach ($rates as $key => $rate)
{
	echo "<option value='${key}' measure='" . $rate['measure'] . "' ";
	
	if (!empty($preset) && $key == $preset['service'])
		echo "selected";

	echo ">"  . $rate['desc'] . "</option>\n";
}