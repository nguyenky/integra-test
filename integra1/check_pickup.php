<?php

require_once('system/config.php');
require_once('system/utils.php');

$sku = $_GET['sku'];

if (empty($sku))
	return;

$sku = strtoupper($sku);

if (startsWith($sku, 'EOC'))
	$sku = substr($sku, 3);

if (empty($sku))
	return;

$url = IMC_HOST;
$username = IMC_USERNAME;
$password = IMC_PASSWORD;
$guid = uniqid();
$date = gmdate("c");

$siteIDs = array(
	7 => 'Baltimore, MD',
	8 => 'Pompano Beach, FL');
	
$site1day = 8;
$site2days = 7;

$data = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:int="http://www.aftermarket.org/InternetPartsOrder">
	<soapenv:Header>
		<wsse:Security soapenv:mustUnderstand="0" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
			<wsse:UsernameToken>
				<wsse:Username>${username}</wsse:Username>
				<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">${password}</wsse:Password>
			</wsse:UsernameToken>
		</wsse:Security>
	</soapenv:Header>
	<soapenv:Body>
		<int:Quote>
			<int:AddRequestforQuoteBOD><![CDATA[<?xml version="1.0" encoding="UTF-8"?>
				<aaia:AddRequestForQuote
					xsi:schemaLocation = "http://www.aftermarket.org/oagis AddRequestForQuote.xsd"
					revision = "1.2.1"
					environment = "Production"
					lang = "en"
					xmlns:oa = "http://www.openapplications.org/oagis"
					xmlns:xsi = "http://www.w3.org/2001/XMLSchema-instance"
					xmlns:aaia = "http://www.aftermarket.org/oagis">
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
						<oa:Add confirm="Always"/>
						<oa:RequestForQuote>
							<aaia:Header>
								<oa:Parties>
									<oa:BillToParty>
										<oa:PartyId>
											<oa:Id>${username}</oa:Id>
										</oa:PartyId>
									</oa:BillToParty>

EOD;

foreach ($siteIDs as $siteID => $siteName)
{
	$data .= "\t\t\t\t\t\t\t\t\t<oa:ShipFromParty><oa:PartyId><oa:Id>${siteID}</oa:Id></oa:PartyId></oa:ShipFromParty>\r\n";
}
$data .= <<<EOD
								</oa:Parties>
							</aaia:Header>
							<aaia:Line>
								<oa:LineNumber>1</oa:LineNumber>
								<aaia:OrderItem>
									<aaia:ItemIds>
										<oa:CustomerItemId>
											<oa:Id>${sku}</oa:Id>
										</oa:CustomerItemId>
									</aaia:ItemIds>
								</aaia:OrderItem>
								<oa:OrderQuantity uom="EA">1</oa:OrderQuantity>
							</aaia:Line>
						</oa:RequestForQuote>
					</oa:DataArea>
				</aaia:AddRequestForQuote>
			]]>
			</int:AddRequestforQuoteBOD>
		</int:Quote>
	</soapenv:Body>
</soapenv:Envelope>

EOD;

//echo $data;

$headers = array
(
	'Content-Type: text/xml',
	'SOAPAction: ""'
);

$ch = curl_init($url);
//curl_setopt($ch, CURLOPT_PROXY, "64.64.28.183:8111");
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);

$res = XMLtoArray($res);
$res = asearch($res, 'P23:ADDQUOTEBOD', '', false);
$res = XMLtoArray($res);
$lines = search_nested_arrays($res, 'AAIA:LINE');
unset($res);

if (array_key_exists('OA:LINENUMBER', $lines))
	$lines = array(0 => $lines);

$site2daysOk = false;
	
foreach ($lines as $line)
{
	$status = asearch($line, 'OA:TO', 'AAIA:SUBLINE');
		
	if ($status != "Item – Not Found")
	{
		$sites = search_nested_arrays($line, 'OA:INVENTORYBALANCE', 'AAIA:SUBLINE');
		
		if (empty($sites))
			continue;
		
		if (array_key_exists('OA:SITE', $sites))
			$sites = array(0 => $sites);

		if (empty($sites))
			continue;
		
		foreach ($sites as $site)
		{
			$siteId = asearch($site, 'OA:ID');
			$qty = intval($site['OA:AVAILABLEQUANTITY']['content']);
			
			if ($siteId == $site1day && $qty > 0)
			{
				echo '<font size="4" face="Arial" color="green"><b>Same day pickup is available for this item.</b></font>';
				return;
			}
			else if ($siteId == $site2days && $qty > 0)
				$site2daysOk = true;
		}
	}
}

if ($site2daysOk)
{
	echo '<font size="4" face="Arial" color="mediumseagreen"><b>2nd day pickup is available for this item.</b></font>';
	return;
}

echo '<font size="4" face="Arial" color="red"><b>This item is currently not available for pickup.</b></font>';
return;

?>