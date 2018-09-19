<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/counter_utils.php');

$url = IMC_HOST;
$username = IMC_USERNAME;
$password = IMC_PASSWORD;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

file_put_contents(LOGS_DIR . "imc__check_response.log", "=============== START LOG IMC TRACKING AT ". date('Y-m-d H:i:s') ." ===============\r\n", FILE_APPEND);

try {

	$checkingItems = ['3000116340', '3000116344', '3000113906', '3000113905', '3000113826', '3000112540', '3000112440', '3000112223', '3000112424'];

	$checkingItemStr = implode("','", $checkingItems);


	$q = "SELECT id, order_id FROM direct_shipments WHERE supplier = 1 AND order_id IN ('%s') ORDER BY id DESC";
	echo sprintf($q, $checkingItemStr);
$rows = mysql_query(sprintf($q, $checkingItemStr));

while ($row = mysql_fetch_row($rows))
	$dsList[$row[0]] = $row[1];

file_put_contents(LOGS_DIR . "imc__check_response.log", "Total dsList: ". count($dsList) ."  \r\n", FILE_APPEND);

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
	CountersUtils::insertCounterProd('CheckTracking','Ebay IMC Check Tracking',APP_ID);
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

	file_put_contents(LOGS_DIR . "imc__check_response.log", "Response from IMC: ". serialize($res) ."  \r\n", FILE_APPEND);

	$res = XMLtoArray($res);
	$res = asearch($res, 'ORDER:SHOWSHIPMENTBOD', '', false);
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

	file_put_contents(LOGS_DIR . "imc__check_response.log", "Tracking number: ". $tracking ."  \r\n", FILE_APPEND);
	
}



} catch(Exception $ex) {
	file_put_contents(LOGS_DIR . "imc__check_response.log", "ERROR: ". $ex->getMessage() ."  \r\n", FILE_APPEND);
}



?>
