<?php

require_once('system/config.php');
require_once('system/utils.php');

set_include_path(get_include_path() . PATH_SEPARATOR . realpath('system/amazon'));

$salesId = $_GET['sales_id'];
settype($salesId, 'integer');

if (empty($salesId))
	exit;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q=<<<EOQ
	SELECT store, internal_id, tracking_num, carrier
	FROM sales
	WHERE id = %d
EOQ;

$row = mysql_fetch_row(mysql_query(sprintf($q, $salesId)));
if (empty($row))
	exit;

$store = $row[0];
$orderId = $row[1];
$tracking = $row[2];
$carrier = $row[3];

if (empty($store))
	exit;

if (empty($orderId))
	exit;

$store = trim(strtoupper($store));
$orderId = trim(str_replace(' ', '', $orderId));
$carrier = trim($carrier);
$tracking = str_replace(' ', '', $tracking);
$tracking = trim(str_replace('-', '', $tracking));
$date = date_create("now", new DateTimeZone('America/New_York'));

if ($carrier == "ups")
	$carrier = "UPS";

if ($store == 'EBAY')
{
	mysql_close();

	for ($tries = 0; $tries < 5; $tries++)
	{
		$callName = 'CompleteSale';
		$version = '783';

		$url = EBAY_HOST . "wsapi?callname=${callName}&siteid=" . SITE_ID . "&appid=" . APP_ID . "&version=${version}&routing=default";

		$data = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Header><h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>' . EBAY_TOKEN . '</eBayAuthToken></h:RequesterCredentials></s:Header><s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><' . $callName . 'Request xmlns="urn:ebay:apis:eBLBaseComponents"><Version>' . $version . '</Version><Shipment><ShipmentTrackingDetails><ShippingCarrierUsed>' . $carrier . '</ShippingCarrierUsed><ShipmentTrackingNumber>' . $tracking . '</ShipmentTrackingNumber></ShipmentTrackingDetails></Shipment><OrderID>' . $orderId . '</OrderID></CompleteSaleRequest></s:Body></s:Envelope>';

		$headers = array
		(
			'Content-Type: text/xml',
			'SOAPAction: ""'
		);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);
		
		file_put_contents("logs/ebay_track/${salesId}_" . date_format($date, 'Y-m-d_H-i-s') . ".txt", $res);
		
		if (stripos($res, "success") !== false)
			break;
	}
}
else if ($store == 'AMAZON')
{
    mysql_query(query(<<<EOD
INSERT INTO eoc.amazon_tracking_queue (sales_id, internal_id, tracking_num, carrier, queue_date)
VALUES ('%s', '%s', '%s', '%s', NOW())
EOD
        , $salesId, $orderId, $tracking, $carrier));

    mysql_close();
}
else
{
	mysql_select_db(MAGENTO_SCHEMA);
	
	$orderIncrement = $orderId;

	$q=<<<EOQ
		SELECT entity_id
		FROM sales_flat_order
		WHERE increment_id = '%s'
EOQ;

	$row = mysql_fetch_row(mysql_query(sprintf($q, $orderIncrement)));
	if (empty($row))
		exit;

	$orderEntity = $row[0];
	
	if (empty($orderEntity))
		exit;
		
	$client = null;
	$session = null;
		
	$q=<<<EOQ
		SELECT increment_id
		FROM sales_flat_shipment
		WHERE order_id = '%s'
EOQ;

	$row = mysql_fetch_row(mysql_query(sprintf($q, $orderEntity)));
	if (!empty($row))
	{
		$shipmentIncrement = $row[0];
	}
	else
	{
		$client = new SoapClient(MAGENTO_API_SERVER);
		$session = $client->login(MAGENTO_API_USERNAME, MAGENTO_API_PASSWORD);
		$shipmentIncrement = $client->salesOrderShipmentCreate($session, $orderId, null, null, 0, 0);
	}
	
	$q=<<<EOQ
		SELECT track_number
		FROM sales_flat_shipment_track
		WHERE order_id = '%s'
		LIMIT 1
EOQ;

	$row = mysql_fetch_row(mysql_query(sprintf($q, $orderEntity)));
	if (empty($row))
	{
		if (empty($client))
		{
			$client = new SoapClient(MAGENTO_API_SERVER);
			$session = $client->login(MAGENTO_API_USERNAME, MAGENTO_API_PASSWORD);
		}

		$client->salesOrderShipmentAddTrack($session, $shipmentIncrement, strtolower($carrier), strtoupper($carrier), $tracking);
		sleep(3);
		$client->salesOrderShipmentSendInfo($session, $shipmentIncrement);
	}
	else
	{
		$existingTrack = $row[0];
		
		if ($existingTrack == $tracking)
			exit;
		
		$q=<<<EOQ
			UPDATE sales_flat_shipment_track
			SET track_number = '%s', carrier_code = '%s', title = '%s'
			WHERE order_id = '%s'
			LIMIT 1
EOQ;
		mysql_query(sprintf($q, $tracking, strtolower($carrier), strtoupper($carrier), $orderEntity));
	}
	
	mysql_close();
}

function __autoload($className)
{
	$filePath = 'system/amazon/' . str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
	if (file_exists($filePath))
	{
		require_once $filePath;
		return;
	}
}

?>

