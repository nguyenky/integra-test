<?php

require_once('config.php');
require_once('utils.php');

class EndiciaUtils
{
    public static $serviceTypes = array('IPA' => 'IPA', 'CommercialePacket' => 'ePacket');

	public static function CreateStamp($salesId, $recordNum, $from, $to, $rateKey, $pounds, $ounces, $length, $width, $height, $material, $user, $validateOnly)
	{
        $url = ENDICIA_URL;
        $requesterId = ENDICIA_REQUESTERID;
        $accountId = ENDICIA_ACCOUNTID;
        $password = ENDICIA_PASSWORD;

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

        $row = mysql_fetch_row(mysql_query(query("SELECT status FROM sales WHERE id = %d", $salesId)));
        $prevStatus = $row[0];

        $row = mysql_fetch_row(mysql_query(query("SELECT first_name FROM integra_users WHERE email = '%s'", $user)));
        $signer = $row[0];

		$now = date_create("now", new DateTimeZone('America/New_York'));

		$from['state'] = convert_state($from['state'], 'abbrev');
		$to['state'] = convert_state($to['state'], 'abbrev');

        $totalWeight = ceil($ounces + ($pounds * 16));

        $parts = GetOrderComponents($salesId);

		$data = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:lab="www.envmgr.com/LabelService">
   <soap:Header/>
   <soap:Body>
      <lab:GetPostageLabel>
         <lab:LabelRequest LabelType="International" LabelSubtype="Integrated" ImageFormat="PNG" ImageRotation="Rotate90">
            <lab:MailClass><![CDATA[${rateKey}]]></lab:MailClass>
            <lab:WeightOz><![CDATA[${totalWeight}]]></lab:WeightOz>

            <lab:RequesterID><![CDATA[${requesterId}]]></lab:RequesterID>
            <lab:AccountID><![CDATA[${accountId}]]></lab:AccountID>
            <lab:PassPhrase><![CDATA[${password}]]></lab:PassPhrase>

            <lab:ReferenceID><![CDATA[${recordNum}]]></lab:ReferenceID>
            <lab:PartnerTransactionID><![CDATA[${salesId}]]></lab:PartnerTransactionID>
            <lab:IncludePostage>FALSE</lab:IncludePostage>
            <lab:PrintConsolidatorLabel>TRUE</lab:PrintConsolidatorLabel>

            <lab:FromCompany><![CDATA[${from['name']}]]></lab:FromCompany>
            <lab:ReturnAddress1><![CDATA[${from['address1']}]]></lab:ReturnAddress1>
            <lab:ReturnAddress2><![CDATA[${from['address2']}]]></lab:ReturnAddress2>
            <lab:FromCity><![CDATA[${from['city']}]]></lab:FromCity>
            <lab:FromState><![CDATA[${from['state']}]]></lab:FromState>
            <lab:FromPostalCode><![CDATA[${from['zip']}]]></lab:FromPostalCode>
            <lab:FromPhone><![CDATA[${from['phone']}]]></lab:FromPhone>

            <lab:ToName><![CDATA[${to['name']}]]></lab:ToName>
            <lab:ToAddress1><![CDATA[${to['address1']}]]></lab:ToAddress1>
            <lab:ToAddress2><![CDATA[${to['address2']}]]></lab:ToAddress2>
            <lab:ToAddress3><![CDATA[${to['address3']}]]></lab:ToAddress3>
            <lab:ToCity><![CDATA[${to['city']}]]></lab:ToCity>
            <lab:ToPostalCode><![CDATA[${to['zip']}]]></lab:ToPostalCode>
            <lab:ToCountryCode><![CDATA[${to['country']}]]></lab:ToCountryCode>

            <lab:IntegratedFormType>Form2976</lab:IntegratedFormType>
            <lab:CustomsCertify>TRUE</lab:CustomsCertify>
            <lab:CustomsSigner><![CDATA[${signer}]]></lab:CustomsSigner>
            <lab:CustomsInfo>
            	<lab:ContentsType>Merchandise</lab:ContentsType>
            	<lab:CustomsItems>
EOD;

foreach ($parts as $sku => $qty)
{
    if (startsWith($sku, "EOCE"))
        $item = ItemUtils::GetESIItem($sku);
    else if (startsWith($sku, "EOCS") || strpos($sku, '.') > 0)
        $item = ItemUtils::GetSSFItem($sku);
    else
        $item = ItemUtils::GetIMCItem($sku);

    $data .= '<lab:CustomsItem>';
    $data .= '<lab:Description><![CDATA[';

    if (!empty($item['desc']))
        $data .= $item['desc'];
    else $data .= 'Part #' . $item['mpn'];

    $data .= ']]></lab:Description>';
    $data .= '<lab:Quantity><![CDATA[' . $qty . ']]></lab:Quantity>';

    $itemWeight = $item['weight'] / 16;
    if ($itemWeight <= 0) $itemWeight = 1;
    $itemWeight = ceil($itemWeight);

    $data .= '<lab:Weight><![CDATA[' . $itemWeight . ']]></lab:Weight>';

    $data .= '<lab:Value>10</lab:Value></lab:CustomsItem>';
}

$data .= <<<EOD
			    </lab:CustomsItems>
            </lab:CustomsInfo>
         </lab:LabelRequest>
      </lab:GetPostageLabel>
   </soap:Body>
</soap:Envelope>
EOD;

		$headers = array
		(
			'Content-Type: text/xml',
			'SOAPAction: "www.envmgr.com/LabelService/GetPostageLabel"'
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
        $stampsTxId = asearch($res, 'TRANSACTIONID');
        if (empty($stampsTxId)) $stampsTxId = substr(str_pad(time() . rand(0, 999), 10, STR_PAD_LEFT), -10);
        $stampsTxId = 'E' . $stampsTxId;
		$shippingCost = asearch($res, 'AMOUNT');
        $image = asearch($res, 'IMAGE');

        if (!empty($image))
            file_put_contents(STAMPS_DIR . $stampsTxId . ".jpg", base64_decode($image));

        $origRes = $res;
		
		$res = array();
		$res['success'] = false;
		$res['tracking'] = '';
		$res['txid'] = '';
		$res['error'] = '';

		if (empty($tracking))
		{
			$error = asearch($origRes, 'ERRORMESSAGE');

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

	public static function GetBalance()
	{
        $url = ENDICIA_URL;
		$requesterId = ENDICIA_REQUESTERID;
		$accountId = ENDICIA_ACCOUNTID;
		$password = ENDICIA_PASSWORD;
		
		$data = <<<EOD
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:lab="www.envmgr.com/LabelService">
   <soap:Header/>
   <soap:Body>
      <lab:GetAccountStatus>
         <lab:AccountStatusRequest ResponseVersion="0">
            <lab:RequesterID><![CDATA[${requesterId}]]></lab:RequesterID>
            <lab:RequestID>0</lab:RequestID>
            <lab:CertifiedIntermediary>
               <lab:AccountID><![CDATA[${accountId}]]></lab:AccountID>
               <lab:PassPhrase><![CDATA[${password}]]></lab:PassPhrase>
            </lab:CertifiedIntermediary>
         </lab:AccountStatusRequest>
      </lab:GetAccountStatus>
   </soap:Body>
</soap:Envelope>
EOD;

		$headers = array
		(
			'Content-Type: text/xml',
            'SOAPAction: "www.envmgr.com/LabelService/GetAccountStatus"'
		);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);
		
		$res = XMLtoArray($res);
		$balance = asearch($res, 'POSTAGEBALANCE');

		return $balance;
	}

    public static function GetRates($fromZip, $toZip, $toCountry, $pounds, $ounces, $length, $width, $height)
    {
        foreach (self::$serviceTypes as $key => $value)
        {
            $result[$key]['desc'] = $value;
            $result[$key]['measure'] = 'N';
        }

        return $result;
    }

    public static function CleanseAddress($name, $address1, $address2, $address3, $city, $state, $zip, $country)
    {
        $result['name'] = trim($name);
        $result['address1'] = trim($address1);
        $result['address2'] = trim($address2);
        $result['address3'] = trim($address3);
        $result['city'] = trim($city);
        $result['state'] = convert_state($state, 'abbrev');
        $result['zip'] = trim($zip);
        $result['zip_ext'] = '';
        $result['country'] = trim($country);

        $result['address_match'] = true;
        $result['city_state_zip_ok'] = true;
        $result['cleanse_hash'] = 'x';
        $result['override_hash'] = 'x';

        return $result;
    }

	public static function SaveStampsPreset($salesId, $service, $pounds, $ounces, $length, $width, $height, $email)
	{
        return;
	}
	
	public static function LoadStampsPreset($salesId)
	{
        return null;
	}
}

?>
