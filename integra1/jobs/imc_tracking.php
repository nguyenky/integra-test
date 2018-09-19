<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/counter_utils.php');

$url = IMC_HOST;
$username = IMC_USERNAME;
$password = IMC_PASSWORD;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

file_put_contents(LOGS_DIR . "imc_response.log", "=============== START LOG IMC TRACKING AT ". date('Y-m-d H:i:s') ." ===============\r\n", FILE_APPEND);

try {

	$q = "SELECT id, order_id FROM direct_shipments WHERE supplier = 1 AND order_date > DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND no_tracking = 0 AND (tracking_num IS NULL OR LENGTH(tracking_num) = 0) ORDER BY id DESC";
$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
	$dsList[$row[0]] = $row[1];

file_put_contents(LOGS_DIR . "imc_response.log", "Total dsList: ". count($dsList) ."  \r\n", FILE_APPEND);

foreach ($dsList as $id => $orderId)
{
	$guid = uniqid();
	$date = gmdate("c");

	$data = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv = "http://schemas.xmlsoap.org/soap/envelope/" xmlns:int = "http://www.aftermarket.org/InternetPartsOrder">
	<soapenv:Header>
		<wsse:Security soapenv:mustUnderstand = "0" xmlns:wsse = "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
			<wsse:UsernameToken>
				<wsse:Username>${username}</wsse:Username>
				<wsse:Password Type = "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">${password}</wsse:Password>
			</wsse:UsernameToken>
		</wsse:Security>
	</soapenv:Header>
	<soapenv:Body>
		<int:ShipmentStatus>
			<int:GetShipmentBOD><![CDATA[<?xml version="1.0" encoding="UTF-8"?>
				<aaia:GetShipment xmlns:oa="http://www.openapplications.org/oagis" xmlns:aaia="http://www.aftermarket.org/oagis" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.aftermarket.org/oagis ../BODs/GetShipment.xsd" revision="1.2.1" environment="Production" lang="en">
					<oa:ApplicationArea>
						<oa:Sender>
							<oa:LogicalId>IPO</oa:LogicalId>
							<oa:ReferenceId>${guid}</oa:ReferenceId>
							<oa:Confirmation>0</oa:Confirmation>
						</oa:Sender>
						<oa:CreationDateTime>${date}</oa:CreationDateTime>
						<oa:BODId>${guid}</oa:BODId>
					</oa:ApplicationArea>
					<oa:DataArea>
						<oa:Get confirm="Always"/>
						<oa:Shipment>
							<oa:Header>
								<oa:DocumentDate>${guid}</oa:DocumentDate>
								<oa:DocumentReferences>
									<oa:QuoteDocumentReference>
										<oa:DocumentIds>
											<oa:SupplierDocumentId>
												<oa:Id>${orderId}</oa:Id>
											</oa:SupplierDocumentId>
										</oa:DocumentIds>
									</oa:QuoteDocumentReference>
								</oa:DocumentReferences>
							</oa:Header>
						</oa:Shipment>
					</oa:DataArea>
				</aaia:GetShipment>
			]]>
			</int:GetShipmentBOD>
		</int:ShipmentStatus>
	</soapenv:Body>
</soapenv:Envelope>

EOD;

	$headers = array
	(
		'Content-Type: text/xml',
		'SOAPAction: ""'
	);
	/*  start insert counter */
	CountersUtils::insertCounterProd('IMC Tracking','Ebay IMC Tracking',APP_ID);
	/*  end insert counter */
	$ch = curl_init($url);
	//curl_setopt($ch, CURLOPT_PROXY, "64.64.28.183:8111");
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$res = curl_exec($ch);
	curl_close($ch);

	if (stristr($res, 'SHIPPINGTRACKINGID') === FALSE)
		continue;

	/*file_put_contents(LOGS_DIR . "imc_response.log", "========================================================  \r\n", FILE_APPEND);

	file_put_contents(LOGS_DIR . "imc_response.log", "Response from IMC: ". serialize($res) ."  \r\n", FILE_APPEND);
	file_put_contents(LOGS_DIR . "imc_response.log", "========================================================  \r\n", FILE_APPEND);*/
	$res = XMLtoArray($res);
	$res = asearch($res, 'P23:SHOWSHIPMENTBOD', '', false);
	$res = XMLtoArray($res);
	$shipments = search_nested_arrays($res, 'OA:SHIPUNIT');
	$trackingNums = [];

	if (!empty($shipments))
	{
		if (array_key_exists('OA:SHIPPINGTRACKINGID', $shipments))
			$shipments = array(0 => $shipments);

		foreach ($shipments as $shipment)
		{
			$num = asearch($shipment, 'OA:SHIPPINGTRACKINGID');
			if (!empty($num) && !in_array($num, $trackingNums))
				$trackingNums[] = $num;
		}
	}

	$tracking = implode(', ', $trackingNums);

	file_put_contents(LOGS_DIR . "imc_response.log", "Tracking number 1: ". $tracking ."  \r\n", FILE_APPEND);
	
	if (!empty($tracking) && strlen($tracking) >= 12)
	{
        $nums = 0;

        for ($i = 0; $i < strlen($tracking); $i++)
        {
            if (is_numeric($tracking[$i]))
                $nums++;
        }

        // at least 3 digits in the tracking number
        if ($nums >= 3)
        {
            $q = <<<EOQ
            UPDATE direct_shipments SET tracking_num = '%s'
            WHERE id = %d AND supplier = 1
EOQ;
            mysql_query(query($q, $tracking, $id));

            $rows = mysql_query(query("SELECT dss.sales_id FROM direct_shipments ds, direct_shipments_sales dss WHERE ds.order_id = dss.order_id AND dss.order_id = '%s' AND ds.is_bulk = 0", $orderId));

            while ($row = mysql_fetch_row($rows))
                $sync[] = $row[0];
        }
	}
}

if (!empty($sync))
{
	$sync = array_unique($sync);

	foreach ($sync as $salesId)
	{
		unset($trackList);
		$trackList = array();

		$q = "SELECT tracking_num FROM sales WHERE id = ${salesId} AND fake_tracking = 0";
		$row = mysql_fetch_row(mysql_query($q));
		if (!empty($row))
		{
			if (!empty($row[0]))
				continue; // temporarily prevent overwriting of tracking numbers
				//$trackList[] = $row[0];
		}
		
		$q = "SELECT DISTINCT ds.tracking_num FROM direct_shipments ds, direct_shipments_sales dss WHERE ds.order_id = dss.order_id AND dss.sales_id = ${salesId} AND ds.tracking_num > '' AND ds.is_bulk = 0";
		$rows = mysql_query($q);
		while ($row = mysql_fetch_row($rows))
		{
			$trackList[] = explode(', ', $row[0])[0];
		}
		
		$trackList = array_unique($trackList);
		sort($trackList);
		
		if (!empty($trackList))
		{
			$q=<<<EOQ
			UPDATE sales SET tracking_num = '%s', carrier = '%s', fulfilled = 1, fake_tracking = 0, status = 4
			WHERE id = %d
EOQ;

			if (startsWith($trackList[0], '1Z'))
				$carrier = 'UPS';
			else if (startsWith($trackList[0], '9'))
				$carrier = 'USPS';
			else if (startsWith($trackList[0], 'D'))
				$carrier = 'OnTrac';
			else $carrier = 'FedEx';

			$newTracking = implode(',', $trackList);
			mysql_query(query($q, implode(',', $trackList), $carrier, $salesId));

			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 0, 1, 1, 1)", $salesId, "Status set to: Order Complete"));
			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 0, 1, 1, 1)", $salesId, "Tracking set to: " . $newTracking . " - " . $carrier));

			$s = file_get_contents("http://integra.eocenterprise.com/tracking.php?sales_id=${salesId}");
			$s = file_get_contents("http://integra.eocenterprise.com/tracking_email.php?sales_id=${salesId}");
		}
	}
}

mysql_close();

} catch(Exception $ex) {
	file_put_contents(LOGS_DIR . "imc_response.log", "ERROR: ". $ex->getMessage() ."  \r\n", FILE_APPEND);
}



?>
