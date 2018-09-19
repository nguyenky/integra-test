<?php

require_once('config.php');
require_once('utils.php');

class StampsUtils
{
	public static $serviceTypes = array(
		'US-FC' => 'First-Class Mail',
		'US-MM' => 'Media Mail',
		'US-PP' => 'Parcel Post ',
		'US-PM' => 'Priority Mail',
		'US-XM' => 'Priority Mail Express',
		'US-CM' => 'Critical Mail',
		'US-PS' => 'Parcel Select',
		'US-LM' => 'Library Mail');
		
	public static function CreateStamp($salesId, $recordNum, $from, $to, $rateKey, $pounds, $ounces, $length, $width, $height, $material, $user, $validateOnly)
	{
		$id = STAMPS_ID;
		$url = STAMPS_URL;
		$username = STAMPS_USERNAME;
		$password = STAMPS_PASSWORD;
		
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

        $row = mysql_fetch_row(mysql_query(query("SELECT status FROM sales WHERE id = %d", $salesId)));
        $prevStatus = $row[0];

		$now = date_create("now", new DateTimeZone('America/New_York'));
		$date = date_format($now, 'Y-m-d');
		
		if (strpos($from['zip'], '-') !== FALSE)
		{
			$z = explode('-', $from['zip']);
			$from['zip'] = trim($z[0]);
			$from['zip_ext'] = trim($z[1]);
		}
		else $from['zip_ext'] = '';
		
		if (strpos($to['zip'], '-') !== FALSE)
		{
			$z = explode('-', $to['zip']);
			$to['zip'] = trim($z[0]);
			$to['zip_ext'] = trim($z[1]);
		}
		else $to['zip_ext'] = '';

		$r = explode('|', $rateKey);
		$service = $r[0];
		$package = $r[1];
		
		$from['state'] = convert_state($from['state'], 'abbrev');
		$to['state'] = convert_state($to['state'], 'abbrev');

        $txId = $salesId . rand(0, 999);

		$data = <<<EOD
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sws="http://stamps.com/xml/namespace/2013/10/swsim/swsimv33">
	<soapenv:Header/>
	<soapenv:Body>
		<sws:CreateIndicium>
			<sws:Credentials>
				<sws:IntegrationID><![CDATA[${id}]]></sws:IntegrationID>
				<sws:Username><![CDATA[${username}]]></sws:Username>
				<sws:Password><![CDATA[${password}]]></sws:Password>
			</sws:Credentials>
			<sws:IntegratorTxID><![CDATA[${txId}]]></sws:IntegratorTxID>
			<sws:Rate>
				<sws:FromZIPCode><![CDATA[${from['zip']}]]></sws:FromZIPCode>
				<sws:ToZIPCode><![CDATA[${to['zip']}]]></sws:ToZIPCode>
				<sws:ServiceType><![CDATA[${service}]]></sws:ServiceType>
				<sws:WeightLb><![CDATA[${pounds}]]></sws:WeightLb>
				<sws:WeightOz><![CDATA[${ounces}]]></sws:WeightOz>
				<sws:PackageType><![CDATA[${package}]]></sws:PackageType>
				<sws:Length><![CDATA[${length}]]></sws:Length>
				<sws:Width><![CDATA[${width}]]></sws:Width>
				<sws:Height><![CDATA[${height}]]></sws:Height>
				<sws:ShipDate><![CDATA[${date}]]></sws:ShipDate>
				<sws:AddOns>
					<sws:AddOnV4>
						<sws:AddOnType>SC-A-HP</sws:AddOnType>
					</sws:AddOnV4>
				</sws:AddOns>
			</sws:Rate>
			<sws:From>
				<sws:FullName><![CDATA[${from['name']}]]></sws:FullName>
				<sws:Address1><![CDATA[${from['address1']}]]></sws:Address1>
				<sws:Address2><![CDATA[${from['address2']}]]></sws:Address2>
				<sws:Address3><![CDATA[${from['address3']}]]></sws:Address3>
				<sws:City><![CDATA[${from['city']}]]></sws:City>
				<sws:State><![CDATA[${from['state']}]]></sws:State>
				<sws:ZIPCode><![CDATA[${from['zip']}]]></sws:ZIPCode>
				<sws:ZIPCodeAddOn><![CDATA[${from['zip_ext']}]]></sws:ZIPCodeAddOn>
			</sws:From>
			<sws:To>
				<sws:FullName><![CDATA[${to['name']}]]></sws:FullName>
				<sws:Address1><![CDATA[${to['address1']}]]></sws:Address1>
				<sws:Address2><![CDATA[${to['address2']}]]></sws:Address2>
				<sws:Address3><![CDATA[${to['address3']}]]></sws:Address3>
				<sws:City><![CDATA[${to['city']}]]></sws:City>
				<sws:State><![CDATA[${to['state']}]]></sws:State>
				<sws:ZIPCode><![CDATA[${to['zip']}]]></sws:ZIPCode>
				<sws:ZIPCodeAddOn><![CDATA[${to['zip_ext']}]]></sws:ZIPCodeAddOn>
			</sws:To>
			<sws:ImageType>Auto</sws:ImageType>
			<sws:memo><![CDATA[${recordNum}]]></sws:memo>
			<sws:printMemo>true</sws:printMemo>
			<sws:printInstructions>true</sws:printInstructions>
		</sws:CreateIndicium>
	</soapenv:Body>
</soapenv:Envelope>
EOD;

		$headers = array
		(
			'Content-Type: text/xml',
			'SOAPAction: "http://stamps.com/xml/namespace/2013/10/swsim/swsimv33/CreateIndicium"'
		);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);
		
		file_put_contents(LOGS_DIR . "stamps/" . date_format($now, 'YmdHis') . "_req.txt", $data);
		file_put_contents(LOGS_DIR . "stamps/" . date_format($now, 'YmdHis') . "_res.txt", $res);

		$res = XMLtoArray($res);
		$tracking = asearch($res, 'TRACKINGNUMBER');
		$stampsTxId = asearch($res, 'STAMPSTXID');
		$url = asearch($res, 'URL');
		$shippingCost = asearch($res, 'AMOUNT');

        $origRes = $res;
		
		$res = array();
		$res['success'] = false;
		$res['tracking'] = '';
		$res['txid'] = '';
		$res['error'] = '';

		if (empty($tracking))
		{
			$error = asearch($origRes, 'FAULTSTRING');

			if (empty($error))
				$res['error'] = 'Stamp creation failed. Please check your data or if you have sufficient postage balance.';
            else
                $res['error'] = $error;

			return $res;
		}
		
		$res['tracking'] = $tracking;
		$res['txid'] = $stampsTxId;
		$res['success'] = true;

		$q=<<<EOQ
			INSERT INTO stamps (id, sales_id, service, pounds, ounces, length, width, height, material, tracking_num, shipping_cost, email, create_date)
			VALUES ('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
EOQ;
		mysql_query(query($q, $stampsTxId, $salesId, $rateKey, $pounds, $ounces, $length, $width, $height, $material, $tracking, $shippingCost, $user, date_format($now, 'Y-m-d H:i:s')));
		
		$q=<<<EOQ
			UPDATE sales
			SET status = 4, fulfilment = 3, shipping_cost = '%s'
			WHERE id = '%d'
EOQ;
		mysql_query(query($q, $shippingCost, $salesId));

		$rows = mysql_query(query("SELECT tracking_num FROM sales WHERE id = '%d'", $salesId));
		$row = mysql_fetch_row($rows);
		
		$q=<<<EOQ
				UPDATE sales
				SET carrier = 'USPS', tracking_num = '%s', fake_tracking = 0
				WHERE id = '%d'
EOQ;
		mysql_query(query($q, $tracking, $salesId));

		// do not overwrite existing tracking number
		if (empty($row[0]))
		{
			
			
			$s = file_get_contents("http://integra.eocenterprise.com/tracking.php?sales_id=${salesId}");
			$s = file_get_contents("http://integra.eocenterprise.com/tracking_email.php?sales_id=${salesId}");
		}
        else    // notify customer about change in tracking number without changing tracking number in ebay
            $s = file_get_contents("http://integra.eocenterprise.com/emsg/eoc_api/public/ebay_auto_response/${salesId}/edit");
		
		file_put_contents(STAMPS_DIR . $stampsTxId . ".jpg", file_get_contents($url));
		
		try
		{
			$row = mysql_fetch_row(mysql_query(query("SELECT remarks FROM sales WHERE id = '%d'", $salesId)));
			if (preg_match('/^Waiting for W1 truck order \d+ from [^\.]+\.$/i', $row[0]))
				mysql_query(query("UPDATE sales SET remarks = '' WHERE id = '%d'", $salesId));
			else if (preg_match('/^Waiting for W1 truck order \d+ from [^\.]+\. Waiting for W1 truck order \d+ from [^\.]+\.$/i', $row[0]))
				mysql_query(query("UPDATE sales SET remarks = '' WHERE id = '%d'", $salesId));
		}
		catch (Exception $e)
		{
		}
		
		try
		{
			$row = mysql_fetch_row(mysql_query(query("SELECT remarks FROM sales WHERE id = '%d'", $salesId)));
			if (preg_match('/^Waiting for W2 bulk order \d+ from [^\.]+\.$/i', $row[0]))
				mysql_query(query("UPDATE sales SET remarks = '' WHERE id = '%d'", $salesId));
		}
		catch (Exception $e)
		{
		}

        if ($validateOnly)
        {
            $q=<<<EOQ
			UPDATE sales
			SET status = %d
			WHERE id = '%d'
EOQ;
            mysql_query(query($q, $prevStatus, $salesId));
        }
        else
        {
            mysql_query(query("UPDATE stamps SET print_date = NOW() WHERE sales_id = '%s'", $salesId));
        }
		
		return $res;
	}
	
	public static function CreateScan($txids, $from, $pounds, $xm, $pm, $om)
	{
		$id = STAMPS_ID;
		$url = STAMPS_URL;
		$username = STAMPS_USERNAME;
		$password = STAMPS_PASSWORD;
		
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$data = <<<EOD
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sws="http://stamps.com/xml/namespace/2013/10/swsim/swsimv33">
	<soapenv:Header/>
	<soapenv:Body>
		<sws:CreateScanForm>
			<sws:Credentials>
				<sws:IntegrationID><![CDATA[${id}]]></sws:IntegrationID>
				<sws:Username><![CDATA[${username}]]></sws:Username>
				<sws:Password><![CDATA[${password}]]></sws:Password>
			</sws:Credentials>
			<sws:StampsTxIDs>
EOD;

		foreach ($txids as $t)
			$data .= "<sws:guid><![CDATA[${t}]]></sws:guid>\n";

		$data .= <<<EOD
			</sws:StampsTxIDs>
			<sws:FromAddress>
				<sws:FullName><![CDATA[${from['name']}]]></sws:FullName>
				<sws:Address1><![CDATA[${from['address1']}]]></sws:Address1>
				<sws:Address2><![CDATA[${from['address2']}]]></sws:Address2>
				<sws:Address3><![CDATA[${from['address3']}]]></sws:Address3>
				<sws:City><![CDATA[${from['city']}]]></sws:City>
				<sws:State><![CDATA[${from['state']}]]></sws:State>
				<sws:ZIPCode><![CDATA[${from['zip']}]]></sws:ZIPCode>
				<sws:ZIPCodeAddOn><![CDATA[${from['zip_ext']}]]></sws:ZIPCodeAddOn>
			</sws:FromAddress>
			<sws:ImageType>Pdf</sws:ImageType>
			<sws:PrintInstructions>true</sws:PrintInstructions>
		</sws:CreateScanForm>
	</soapenv:Body>
</soapenv:Envelope>
EOD;

		$headers = array
		(
			'Content-Type: text/xml',
			'SOAPAction: "http://stamps.com/xml/namespace/2013/10/swsim/swsimv33/CreateScanForm"'
		);

		$ch = curl_init($url);
		//curl_setopt($ch, CURLOPT_PROXY, "64.64.28.183:8111");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);
		
		$now = date_create("now", new DateTimeZone('America/New_York'));
		
		file_put_contents(LOGS_DIR . "scans/" . date_format($now, 'YmdHis') . "_req.txt", $data);
		file_put_contents(LOGS_DIR . "scans/" . date_format($now, 'YmdHis') . "_res.txt", $res);
		
		if (preg_match_all('/<sdcerror code="00580102" context="([^"]+)"/', $res, $matches))
		{
			foreach ($matches[1] as $txId)
				mysql_query(query("UPDATE stamps SET scan_id = 'OTHER BATCH' WHERE id = '%s' AND scan_id = ''", $txId));

			sleep(1);
			return self::CreateScan(array_diff($txids, $matches[1]), $from, $pounds, $xm, $pm, $om);
		}
		else
		{
			$res = XMLtoArray($res);
			$url = asearch($res, 'URL');
			$scanId = asearch($res, 'SCANFORMID');
			
			if (empty($url))
				return null;

			file_put_contents(SCANS_DIR . $scanId . ".pdf", file_get_contents($url));
			
			foreach ($txids as $txId)
				mysql_query(query("UPDATE stamps SET scan_id = '%s' WHERE id = '%s'", $scanId, $txId));
			
			try
			{
				self::RequestPickup($txids, $from, $pounds, $xm, $pm, $om);
			}
			catch (Exception $e)
			{
			}

			return $scanId;
		}
	}
	
	public static function RequestPickup($txIds, $from, $pounds, $xm, $pm, $om)
	{
		$id = STAMPS_ID;
		$url = STAMPS_URL;
		$username = STAMPS_USERNAME;
		$password = STAMPS_PASSWORD;
		
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
		
		$from['phone'] = preg_replace("/[^0-9]/", "", $from['phone']);

		$data = <<<EOD
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sws="http://stamps.com/xml/namespace/2013/10/swsim/swsimv33">
	<soapenv:Header/>
	<soapenv:Body>
		<sws:CarrierPickup>
			<sws:Credentials>
				<sws:IntegrationID><![CDATA[${id}]]></sws:IntegrationID>
				<sws:Username><![CDATA[${username}]]></sws:Username>
				<sws:Password><![CDATA[${password}]]></sws:Password>
			</sws:Credentials>
			<sws:FirstName><![CDATA[${from['firstname']}]]></sws:FirstName>
			<sws:LastName><![CDATA[${from['lastname']}]]></sws:LastName>
			<sws:Address><![CDATA[${from['address']}]]></sws:Address>
			<sws:City><![CDATA[${from['city']}]]></sws:City>
			<sws:State><![CDATA[${from['state']}]]></sws:State>
			<sws:ZIP><![CDATA[${from['zip']}]]></sws:ZIP>
			<sws:ZIP4><![CDATA[${from['zip_ext']}]]></sws:ZIP4>
			<sws:PhoneNumber><![CDATA[${from['phone']}]]></sws:PhoneNumber>
			<sws:NumberOfExpressMailPieces>${xm}</sws:NumberOfExpressMailPieces>
			<sws:NumberOfPriorityMailPieces>${pm}</sws:NumberOfPriorityMailPieces>
			<sws:NumberOfOtherPieces>${om}</sws:NumberOfOtherPieces>
			<sws:TotalWeightOfPackagesLbs>${pounds}</sws:TotalWeightOfPackagesLbs>
			<sws:PackageLocation>KnockOnDoorOrRingBell</sws:PackageLocation>
		</sws:CarrierPickup>
	</soapenv:Body>
</soapenv:Envelope>
EOD;

		$headers = array
		(
			'Content-Type: text/xml',
			'SOAPAction: "http://stamps.com/xml/namespace/2013/10/swsim/swsimv33/CarrierPickup"'
		);

		$ch = curl_init($url);
		//curl_setopt($ch, CURLOPT_PROXY, "64.64.28.183:8111");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);
		
		$now = date_create("now", new DateTimeZone('America/New_York'));
		
		file_put_contents(LOGS_DIR . "stamps_pickups/" . date_format($now, 'YmdHis') . "_req.txt", $data);
		file_put_contents(LOGS_DIR . "stamps_pickups/" . date_format($now, 'YmdHis') . "_res.txt", $res);
		
		$res = XMLtoArray($res);
		$pickupRef = asearch($res, 'CONFIRMATIONNUMBER');
			
		if (empty($pickupRef))
			return null;

		foreach ($txIds as $txId)
			mysql_query(query("UPDATE stamps SET pickup_ref = '%s' WHERE id = '%s'", $pickupRef, $txId));

		return $pickupRef;
	}
	
	public static function GetBalance()
	{
		$id = STAMPS_ID;
		$url = STAMPS_URL;
		$username = STAMPS_USERNAME;
		$password = STAMPS_PASSWORD;
		
		$data = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
				xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
				xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
				xmlns:sws="http://stamps.com/xml/namespace/2013/10/swsim/swsimv33">
	<soap:Body>
		<sws:GetAccountInfo>
			<sws:Credentials>
				<sws:IntegrationID><![CDATA[${id}]]></sws:IntegrationID>
				<sws:Username><![CDATA[${username}]]></sws:Username>
				<sws:Password><![CDATA[${password}]]></sws:Password>
			</sws:Credentials>
		</sws:GetAccountInfo>
	</soap:Body>
</soap:Envelope>
EOD;

		$headers = array
		(
			'Content-Type: text/xml',
			'SOAPAction: "http://stamps.com/xml/namespace/2013/10/swsim/swsimv33/GetAccountInfo"'
		);

		$ch = curl_init($url);
		//curl_setopt($ch, CURLOPT_PROXY, "64.64.28.183:8111");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);
		
		$res = XMLtoArray($res);
		$balance = asearch($res, 'AVAILABLEPOSTAGE');

		return $balance;
	}
	
	public static function GetRates($fromZip, $toZip, $toCountry, $pounds = 0, $ounces = 0, $length = 0, $width = 0, $height = 0)
	{
		$id = STAMPS_ID;
		$url = STAMPS_URL;
		$username = STAMPS_USERNAME;
		$password = STAMPS_PASSWORD;

        $toCountry = 'US'; // override country. only use stamps for US

		$date = date_create("now", new DateTimeZone('America/New_York'));
		$date = date_format($date, 'Y-m-d');
		
		if (strpos($fromZip, '-') !== FALSE)
		{
			$z = explode('-', $fromZip);
			$fromZip = trim($z[0]);
		}
		
		if (strpos($toZip, '-') !== FALSE)
		{
			$z = explode('-', $toZip);
			$toZip = trim($z[0]);
		}
		
		$data = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
				xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
				xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
				xmlns:sws="http://stamps.com/xml/namespace/2013/10/swsim/swsimv33">
	<soap:Body>
		<sws:GetRates>
			<sws:Credentials>
				<sws:IntegrationID><![CDATA[${id}]]></sws:IntegrationID>
				<sws:Username><![CDATA[${username}]]></sws:Username>
				<sws:Password><![CDATA[${password}]]></sws:Password>
			</sws:Credentials>
			<sws:Rate>
				<sws:FromZIPCode><![CDATA[${fromZip}]]></sws:FromZIPCode>
				<sws:ToZIPCode><![CDATA[${toZip}]]></sws:ToZIPCode>
				<sws:ToCountry><![CDATA[${toCountry}]]></sws:ToCountry>
EOD;
		if (!empty($pounds))
			$data .= "<sws:WeightLb><![CDATA[${pounds}]]></sws:WeightLb>";
		if (!empty($ounces))
			$data .= "<sws:WeightOz><![CDATA[${ounces}]]></sws:WeightOz>";
		if (!empty($length))
			$data .= "<sws:Length><![CDATA[${length}]]></sws:Length>";
		if (!empty($width))
			$data .= "<sws:Width><![CDATA[${width}]]></sws:Width>";
		if (!empty($pounds))
			$data .= "<sws:Height><![CDATA[${height}]]></sws:Height>";

		$data .= <<<EOD
				<sws:ShipDate><![CDATA[${date}]]></sws:ShipDate>
				<sws:AddOns>
					<sws:AddOnV4>
						<sws:AddOnType>SC-A-HP</sws:AddOnType>
					</sws:AddOnV4>
				</sws:AddOns>
			</sws:Rate>
		</sws:GetRates>
	</soap:Body>
</soap:Envelope>
EOD;

		$headers = array
		(
			'Content-Type: text/xml',
			'SOAPAction: "http://stamps.com/xml/namespace/2013/10/swsim/swsimv33/GetRates"'
		);

		$ch = curl_init($url);
		//curl_setopt($ch, CURLOPT_PROXY, "64.64.28.183:8111");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);

        file_put_contents('/tmp/sz1', $data);
        file_put_contents('/tmp/sz', $res);

		$res = XMLtoArray($res);
		$rates = search_nested_arrays($res, 'RATE');
		
		foreach ($rates as $rate)
		{
			$package = asearch($rate, 'PACKAGETYPE');
			if ($package == 'Letter' || $package == 'Postcard')
				continue;
			
			$serviceCode = asearch($rate, 'SERVICETYPE');
			
			if ($package == 'Large Envelope or Flat' && $serviceCode == 'US-FC')
				continue;
			
			$serviceType = self::$serviceTypes[$serviceCode];
			$days = asearch($rate, 'DELIVERDAYS');
			$amount = number_format(asearch($rate, 'AMOUNT', 'ADDONS'), 2);
			$suffix = (intval(substr($days, -1)) > 1) ? 's' : '';
			
			$key = "${serviceCode}|${package}";
			$result[$key]['desc'] = "${serviceType} ${package} (${days} day${suffix}) - $${amount}";
			$result[$key]['measure'] = asearch($rate, 'DIMWEIGHTING');
		}

		return $result;
	}

	public static function CleanseAddress($name, $address1, $address2, $address3, $city, $state, $zip, $country)
	{
		$id = STAMPS_ID;
		$url = STAMPS_URL;
		$username = STAMPS_USERNAME;
		$password = STAMPS_PASSWORD;
		
		$state = convert_state($state, 'abbrev');
        $country = 'US'; // override country. only use stamps for US
		
		if (strpos($zip, '-') !== FALSE)
		{
			$z = explode('-', $zip);
			$zip = trim($z[0]);
			$zip_ext = trim($z[1]);
		}
		else
			$zip_ext = '';
		
		$data = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
				xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
				xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
				xmlns:sws="http://stamps.com/xml/namespace/2013/10/swsim/swsimv33">
	<soap:Body>
		<sws:CleanseAddress>
			<sws:Credentials>
				<sws:IntegrationID><![CDATA[${id}]]></sws:IntegrationID>
				<sws:Username><![CDATA[${username}]]></sws:Username>
				<sws:Password><![CDATA[${password}]]></sws:Password>
			</sws:Credentials>
			<sws:Address>
				<sws:FullName><![CDATA[${name}]]></sws:FullName>
				<sws:Address1><![CDATA[${address1}]]></sws:Address1>
EOD;

if (!empty($address2))
	$data .= "<sws:Address2><![CDATA[${address2}]]></sws:Address2>";
if (!empty($address3))
	$data .= "<sws:Address3><![CDATA[${address3}]]></sws:Address3>";

$data .= <<<EOD
				<sws:City><![CDATA[${city}]]></sws:City>
				<sws:State><![CDATA[${state}]]></sws:State>
				<sws:ZIPCode><![CDATA[${zip}]]></sws:ZIPCode>
				<sws:ZIPCodeAddOn><![CDATA[${zip_ext}]]></sws:ZIPCodeAddOn>
				<sws:Country><![CDATA[${country}]]></sws:Country>
			</sws:Address>
		</sws:CleanseAddress>
	</soap:Body>
</soap:Envelope>
EOD;

		$headers = array
		(
			'Content-Type: text/xml',
			'SOAPAction: "http://stamps.com/xml/namespace/2013/10/swsim/swsimv33/CleanseAddress"'
		);

		$ch = curl_init($url);
		//curl_setopt($ch, CURLOPT_PROXY, "64.64.28.183:8111");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);

		$res = XMLtoArray($res);
		
		$address = search_nested_arrays($res, 'ADDRESS', 'CANDIDATEADDRESSES');
		$result['name'] = asearch($address, 'FULLNAME');
		$result['address1'] = asearch($address, 'ADDRESS1');
		$result['address2'] = asearch($address, 'ADDRESS2');
		$result['address3'] = asearch($address, 'ADDRESS3');
		$result['city'] = asearch($address, 'CITY');
		$result['state'] = convert_state(asearch($address, 'STATE'), 'abbrev');
		$result['zip'] = asearch($address, 'ZIPCODE');
		$result['zip_ext'] = asearch($address, 'ZIPCODEADDON');
        $result['country'] = $country; //asearch($address, 'COUNTRY');
		
		$result['address_match'] = asearch($res, 'ADDRESSMATCH');
		$result['city_state_zip_ok'] = asearch($res, 'CITYSTATEZIPOK');
		$result['cleanse_hash'] = asearch($address, 'CLEANSEHASH');
		$result['override_hash'] = asearch($address, 'OVERRIDEHASH');
		
		return $result;
	}
	
	public static function Login()
	{
		$id = STAMPS_ID;
		$url = STAMPS_URL;
		$username = STAMPS_USERNAME;
		$password = STAMPS_PASSWORD;

		$data = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
				xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
				xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
				xmlns:tns="http://stamps.com/xml/namespace/2013/10/swsim/swsimv33">
	<soap:Body>
		<tns:AuthenticateUser>
			<tns:Credentials>
				<tns:IntegrationID><![CDATA[${id}]]></tns:IntegrationID>
				<tns:Username><![CDATA[${username}]]></tns:Username>
				<tns:Password><![CDATA[${password}]]></tns:Password>
			</tns:Credentials>
		</tns:AuthenticateUser>
	</soap:Body> 
</soap:Envelope>
EOD;

		$headers = array
		(
			'Content-Type: text/xml',
			'SOAPAction: "http://stamps.com/xml/namespace/2013/10/swsim/swsimv33/AuthenticateUser"'
		);

		$ch = curl_init($url);
		//curl_setopt($ch, CURLOPT_PROXY, "64.64.28.183:8111");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);

		if (stristr($res, 'Authenticator') === FALSE)
			return '';

		$res = XMLtoArray($res);
		return asearch($res, 'AUTHENTICATOR');
	}
	
	public static function SaveStampsPreset($salesId, $service, $pounds, $ounces, $length, $width, $height, $email, $speed = '')
	{
		if (empty($service) || empty($salesId) || empty($email))
			return;

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		if (empty($speed))
		{
			$res = mysql_query("SELECT speed FROM sales WHERE id = ${salesId}");
			$row = mysql_fetch_row($res);
			$speed = $row[0];
		}
		
		$res = mysql_query("SELECT sku, quantity FROM sales_items WHERE sales_id = ${salesId}");
		while ($row = mysql_fetch_row($res))
			$items[] = array($row[0], $row[1]);
		
		if (count($items) != 1)
			return;

		if ($items[0][1] != 1)
			return;
		
		$sku = $items[0][0];
		
		$q=<<<EOQ
			INSERT INTO stamps_preset (sku, service, pounds, ounces, length, width, height, email, last_sales_id, speed)
			VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
			ON DUPLICATE KEY UPDATE
				service=VALUES(service),
				pounds=VALUES(pounds),
				ounces=VALUES(ounces),
				length=VALUES(length),
				width=VALUES(width),
				height=VALUES(height),
				email=VALUES(email),
				last_sales_id=VALUES(last_sales_id),
				speed=VALUES(speed),
				timestamp=NOW()
EOQ;
		mysql_query(query($q, $sku, $service, $pounds, $ounces, $length, $width, $height, $email, $salesId, $speed));
	}
	
	public static function LoadStampsPreset($salesId)
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$res = mysql_query("SELECT speed FROM sales WHERE id = ${salesId}");
		$row = mysql_fetch_row($res);
		$speed = $row[0];
	
		$items = array();
		$res = mysql_query("SELECT sku, quantity FROM sales_items WHERE sales_id = ${salesId}");
		while ($row = mysql_fetch_row($res))
			$items[] = array($row[0], $row[1]);
		
		// no preset for orders with more than 1 item
		if (count($items) != 1)
			return null;
		
		// no preset for multiple quantity items
		if ($items[0][1] != 1)
			return null;
			
		$row = mysql_fetch_row(mysql_query(query(
			"SELECT service, pounds, ounces, length, width, height, email, last_sales_id, timestamp FROM stamps_preset WHERE sku = '%s' AND speed = '%s'", $items[0][0], $speed)));
			
		if (empty($row))
			return null;
		
		$res = array();
		$res['service'] = $row[0];
		$res['pounds'] = $row[1];
		$res['ounces'] = $row[2];
		$res['length'] = $row[3];
		$res['width'] = $row[4];
		$res['height'] = $row[5];
		$res['email'] = $row[6];
		$res['last_sales_id'] = $row[7];

		$dt = new DateTime($row[8]);
		$res['timestamp'] = $dt->format('Y-m-d H:i:s');

		return $res;
	}
}

?>
