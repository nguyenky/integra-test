<?php

require_once('system/stamps_utils.php');
require_once('system/acl.php');

$user = Login('sales');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

// temporary
define('SHIPFROM_ZIP', '33166');

// future: support multiple shipfrom sites. now loading only the first one.
$shipFrom = mysql_fetch_row(mysql_query("SELECT recipient_name, street, city, state, zip FROM pickup_sites WHERE shipping_only = 1"));
	
$from['name'] = $shipFrom[0];
$aLines = explode(';', $shipFrom[1]);
$from['address1'] = (count($aLines) >= 1) ? $aLines[0] : '';
$from['address2'] = (count($aLines) >= 2) ? $aLines[1] : '';
$from['address3'] = (count($aLines) >= 3) ? $aLines[2] : '';
$from['city'] = $shipFrom[2];
$from['state'] = $shipFrom[3];
$from['zip'] = $shipFrom[4];

$now = date_create("now", new DateTimeZone('America/New_York'));
$date = date_format($now, 'Y-m-d');

$scan = StampsUtils::CreateScan($from, $date);

if (!empty($scan))
{
	header('Content-type: application/pdf');
	header('Content-Disposition: inline; filename="scan_' . $date . '.pdf"');
	header('Content-Length: ' . strlen($scan));
	echo $scan;
	return;
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<title>Integra :: Order Shipment Tool</title>
	</head>
<body>
	<input type="button" onclick="window.location='barcode.php';" value="Back to Barcode Input" />
	<script>alert('The scan form was not properly generated.\n\nWere there any stamps created today?');</script>
</body>
</html>