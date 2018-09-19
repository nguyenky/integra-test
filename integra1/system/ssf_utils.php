<?php

require_once('config.php');
require_once('utils.php');

class SsfUtils
{
	public static $siteIDs = array(
		'SF' => 'San Francisco, CA',
		'LB' => 'Carson, CA',
		'SD' => 'San Diego, CA',
		'PH' => 'Phoenix, AZ',
		'OC' => 'Orange County, CA',
		'AT' => 'Atlanta, GA',
	);

	public static $noBulk = array(
		'AT'
	);
		
	public static $prefSiteID = 'LB';

	public static function DecodeLines($lines)
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$items = array();

		if (!empty($lines))
		{
			if (array_key_exists('LINENUMBER', $lines))
				$lines = array(0 => $lines);
				
			foreach ($lines as $line)
			{
				unset($item);
				unset($options);
				$options = array();

				if (array_key_exists('AAIA:SUBLINE', $line))
				{
					$sublines = $line['AAIA:SUBLINE'];
					if (array_key_exists('LINENUMBER', $sublines))
						$sublines = array(0 => $sublines);
					
					foreach ($sublines as $subline)
					{
						$option = self::DecodeLine($subline);
						if (!empty($option) && !empty($option['brand_id']))
						{
							$options[] = $option;
						}
					}
					
					unset($line['AAIA:SUBLINE']);
				}

				$item = self::DecodeLine($line);
				$item['options'] = $options;
				$items[] = $item;

				foreach ($options as $opt)
				{
					if (empty($opt['sku'])) continue;

					$q = <<<EOQ
INSERT IGNORE INTO magento.catalog_product_link (product_id, linked_product_id, link_type_id)
(SELECT cpe1.entity_id, cpe2.entity_id, 4
FROM magento.catalog_product_entity cpe1, magento.catalog_product_entity cpe2
WHERE cpe1.sku != cpe2.sku
AND cpe1.sku = '%s'
AND cpe2.sku = '%s');
EOQ;

					mysql_query(sprintf($q, $item['sku'], $opt['sku']));
					$linkId = mysql_insert_id();
					if ($linkId)
						mysql_query("INSERT IGNORE INTO magento.catalog_product_link_attribute_int (product_link_attribute_id, link_id, value) VALUES (4, {$linkId}, 0)");
				}
			}
		}
		
		return $items;
	}
	
	public static function DecodeLine($line)
	{
		$item['sku'] = '';
		$item['brand'] = '';
		$item['brand_id'] = '';
		$item['desc'] = 'Not found';
		$item['price'] = '?';
		$item['weight'] = 0;
        $item['core'] = '?';

		$item['sku'] = asearch($line, 'AAIA:SUPPLIERITEMID');
		$item_status = asearch($line, 'ITEMSTATUS');
		
		// multimfg
		if ($item_status == 'S02')
		{
			$item['desc'] = 'Select brand';
			return $item;
		}
		
		if (empty($item['sku']))
			return $item;
			
		$item['brand_id'] = asearch($line, 'AAIA:MANUFACTURERCODE');
		$item['sequence'] = asearch($line, 'SSF:SEQUENCE');
			
		// not found
		if ($item_status == 'I04' || $item_status == 'Q05')
		{
			if (!empty($item['brand_id']))
			{
				$q=<<<EOQ
				UPDATE ssf_items SET qty_avail = 0, `timestamp` = NOW()
				WHERE mpn = '%s' AND brand_id = '%s'
EOQ;
				mysql_query(sprintf($q,
					cleanup($item['sku']), 
					cleanup($item['brand_id'])));
					
				//echo $item['sku'] . ' - ' . mysql_affected_rows() . "<br/>\r\n";
			}

			return $item;
		}

		$item['brand'] = asearch($line, 'AAIA:MANUFACTURERNAME');
		$item['desc'] = asearch($line, 'DESCRIPTION', array('ITEMSTATUS', 'CHARGES'));
		
		setlocale(LC_MONETARY, 'en_US');
        $price = trim(asearch($line, 'UNITPRICE'));
        if (!empty($price))
		    $item['price'] = money_format('%^!i', $price);
        else $item['price'] = '0';
		
		$item['weight'] = self::searchNameValue($line, 'partWeight');
		
		$with_core_price = self::searchNameValue($line, 'YourPrice withCore');
		$core_unit_price = self::searchNameValue($line, 'coreUnit');
		$list_price = asearch($line, 'AAIA:LISTPRICE');

        $item['core'] = $core_unit_price;
		
		$maxQty = 0;
		
		foreach (self::$siteIDs as $siteID => $siteName)
		{
			$qty = intval(asearch($line, "SSF:${siteID}"));
			$item['site_' . $siteID] = $qty;
			if ($qty > $maxQty)
				$maxQty = $qty;
		}
		
		if (!empty($item['brand_id']))
		{
			$q=<<<EOQ
			UPDATE ssf_items SET brand = '%s', description = '%s', weight = '%s', with_core_price = '%s', core_unit_price = '%s', list_price = '%s', unit_price = '%s', qty_avail = '%s', `timestamp` = NOW()
			WHERE mpn = '%s' AND brand_id = '%s'
EOQ;
			mysql_query(sprintf($q,
                cleanup($item['brand']),
                cleanup($item['desc']),
				cleanup($item['weight']),
				cleanup($with_core_price),
				cleanup($core_unit_price),
				cleanup($list_price),
				cleanup($item['price']),
				cleanup($maxQty),
				cleanup($item['sku']), 
				cleanup($item['brand_id'])));
			
			$item['sku'] .= '.' . $item['brand_id'];

			mysql_query(sprintf("INSERT IGNORE INTO integra_prod.products (sku, brand, name, supplier_id) VALUES ('%s', '%s', '%s', 2)",
					cleanup(trim(str_replace(' ', '', $item['sku']))),
					cleanup($item['brand']),
					cleanup($item['desc'])));
			
			//echo $item['sku'] . ' - ' . mysql_affected_rows() . "<br/>\r\n";
		}
		
		return $item;
	}

	public static function QueryItems($skus)
	{
		if (empty($skus))
			return;
			
		$results = array();
		
		$url = SSF_HOST;
		$username = SSF_USERNAME;
		$password = SSF_PASSWORD;

		$guid = uniqid();
		$date = gmdate("c");
		
		$bod = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<aaia:AddRequestForQuote xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.openapplications.org/oagis" xmlns:aaia="http://www.aftermarket.org/oagis" xmlns:ssf="${url}" lang="en">
	<ApplicationArea>
		<Sender>
			<ReferenceId>${guid}</ReferenceId>
			<Confirmation>1</Confirmation>
		</Sender>
		<CreationDateTime>${date}</CreationDateTime>
		<BODId>${guid}</BODId>
	</ApplicationArea>
	<DataArea>
		<Add confirm="Always"/>
		<RequestForQuote>
			<aaia:Header>
				<DropShipInd>1</DropShipInd>
				<Parties>
					<ShipFromParty active="1" oneTime="0">
						<PartyId>
						</PartyId>
					</ShipFromParty>
				</Parties>
			</aaia:Header>

EOD;

		$lineNum = 1;

		foreach ($skus as $sku)
		{
			if (empty($sku))
				continue;
				
			$sku = strtoupper($sku);
			
			if (startsWith($sku, 'EOCS'))
				$sku = substr($sku, 4);
				
			if (startsWith($sku, 'EOCF'))
				$sku = substr($sku, 4);
				
			if (empty($sku))
				continue;
				
			$mpn = $sku;
			$brandId = "";
			
			$dotIdx = strpos($sku, '.');
			if ($dotIdx)
			{
				$mpn = substr($sku, 0, $dotIdx);
				$brandId = substr($sku, $dotIdx+1);
			}

			$bod .= <<<EOD
			<aaia:Line>
				<LineNumber>${lineNum}</LineNumber>
				<aaia:OrderItem>
					<aaia:ItemIds>
						<aaia:SupplierItemId>
							<Id>${mpn}</Id>
						</aaia:SupplierItemId>
						<aaia:ManufacturerCode>${brandId}</aaia:ManufacturerCode>
					</aaia:ItemIds>
				</aaia:OrderItem>
				<OrderQuantity>1</OrderQuantity>
			</aaia:Line>

EOD;

			$lineNum++;
		}
		
		$bod .= <<<EOD
		</RequestForQuote>
	</DataArea>
</aaia:AddRequestForQuote>
EOD;

		$bod = htmlentities($bod);

		$soap = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:tns="${url}">
	<soap:Header>
		<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
			<wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
				<wsse:Username>${username}</wsse:Username>
				<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">${password}</wsse:Password>
			</wsse:UsernameToken>
		</wsse:Security>
	</soap:Header>
	<soap:Body>
		<tns:Quote>
			<tns:AddRequestforQuoteBOD>${bod}</tns:AddRequestforQuoteBOD>
		</tns:Quote>
	</soap:Body>
</soap:Envelope>
EOD;

		$headers = array
		(
			"Content-Type: text/xml",
			"SOAPAction: ${url}:QuoteSoapIn"
		);

		$ch = curl_init("${url}/ipo.asmx");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $soap);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);

        //file_put_contents('/tmp/ssfreq', $soap, FILE_APPEND);
        //file_put_contents('/tmp/ssfres', $res, FILE_APPEND);
		
		$res = XMLtoArray($res);

		$res = asearch($res, 'ADDQUOTEBOD', '', false);

		if (!empty($_REQUEST['debug']))
		{
			print_r($res);
		}

		$res = XMLtoArray($res);

		$lines = search_nested_arrays($res, 'AAIA:LINE');
		unset($res);
		
		//print_r($lines);

		$res = self::DecodeLines($lines);

		return $res;
	}
	
	public static function ScrapeItems($skus)
	{
		if (empty($skus))
			return;
			
		$results = array();
		
		$url = SSF_HOST;
		$username = SSF_USERNAME;
		$password = SSF_PASSWORD;

		$guid = uniqid();
		$date = gmdate("c");
		
		$bod = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<aaia:AddRequestForQuote xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.openapplications.org/oagis" xmlns:aaia="http://www.aftermarket.org/oagis" xmlns:ssf="${url}" lang="en">
	<ApplicationArea>
		<Sender>
			<ReferenceId>${guid}</ReferenceId>
			<Confirmation>1</Confirmation>
		</Sender>
		<CreationDateTime>${date}</CreationDateTime>
		<BODId>${guid}</BODId>
	</ApplicationArea>
	<DataArea>
		<Add confirm="Always"/>
		<RequestForQuote>
			<aaia:Header>
				<DropShipInd>1</DropShipInd>
				<Parties>
					<ShipFromParty active="1" oneTime="0">
						<PartyId>
						</PartyId>
					</ShipFromParty>
				</Parties>
			</aaia:Header>

EOD;

		$lineNum = 1;

		foreach ($skus as $sku)
		{
			if (empty($sku))
				continue;
				
			$sku = strtoupper($sku);
				
			if (empty($sku))
				continue;

			$bod .= <<<EOD
			<aaia:Line>
				<LineNumber>${lineNum}</LineNumber>
				<aaia:OrderItem>
					<aaia:ItemIds>
						<aaia:SupplierItemId>
							<Id>${sku}</Id>
						</aaia:SupplierItemId>
					</aaia:ItemIds>
				</aaia:OrderItem>
				<OrderQuantity>1</OrderQuantity>
			</aaia:Line>

EOD;

			$lineNum++;
			$validSkus[] = $sku;
		}
		
		$bod .= <<<EOD
		</RequestForQuote>
	</DataArea>
</aaia:AddRequestForQuote>
EOD;

		$bod = htmlentities($bod);

		$soap = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:tns="${url}">
	<soap:Header>
		<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
			<wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
				<wsse:Username>${username}</wsse:Username>
				<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">${password}</wsse:Password>
			</wsse:UsernameToken>
		</wsse:Security>
	</soap:Header>
	<soap:Body>
		<tns:Quote>
			<tns:AddRequestforQuoteBOD>${bod}</tns:AddRequestforQuoteBOD>
		</tns:Quote>
	</soap:Body>
</soap:Envelope>
EOD;

		$headers = array
		(
			"Content-Type: text/xml",
			"SOAPAction: ${url}:QuoteSoapIn"
		);

		$ch = curl_init("${url}/ipo.asmx");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $soap);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);

		$res = XMLtoArray($res);
		
		$res = asearch($res, 'ADDQUOTEBOD', '', false);
		// TODO: Change folder. this file will be parsed by tools/ssf/ssf_parse_items.php
		file_put_contents('ssf/' . time() . '.xml', $res);
		
		$ok = array();
		
		foreach ($validSkus as $vsku)
		{
			if (stristr($res, '>' . $vsku . '<'))
			{
				$ok[] = $vsku;
			}
		}
		
		return $ok;
	}

	public static function PreSelectItems($parts)
	{
		$results = array();
		
		$ssfParts = TrimSKUPrefix($parts, 'EOCS');
		$ssfParts = TrimSKUPrefix($ssfParts, 'EOCF');

		if (empty($ssfParts))
			return $results;
			
		foreach (self::$siteIDs as $siteID => $siteName)
		{
			$fill[$siteID] = 0;
			
			foreach ($ssfParts as $sku => $qty)
				$cart[$siteID][$sku] = 0;
		}

		$ssfAvails = self::QueryItems(array_keys($ssfParts));

		foreach ($ssfAvails as $ssfAvail)
		{
			$sku = $ssfAvail['sku'];

			foreach (self::$siteIDs as $siteID => $siteName)
			{
				$qtyAvail = $ssfAvail["site_${siteID}"];
				
				if (empty($qtyAvail))
					$qtyAvail = 0;
				
				$avail[$siteID][$sku] = $qtyAvail;
				$fill[$siteID] += min($qtyAvail, $ssfParts[$sku]);
			}
			
			$results['desc'][$sku] = $ssfAvail['desc'];
			$results['brand'][$sku] = $ssfAvail['brand'];
			$results['price'][$sku] = $ssfAvail['price'];
		}
		
		arsort($fill);

		if (reset($fill) == $fill[self::$prefSiteID])
		{
			$qty = $fill[self::$prefSiteID];
			unset($fill[self::$prefSiteID]);
			$fill = array(self::$prefSiteID => $qty) + $fill; 
		}

		$neededFill = $ssfParts;

		foreach ($fill as $siteID => $total)
		{
			foreach ($neededFill as $sku => $qty)
			{
				$take = min($qty, $avail[$siteID][$sku]);
				if ($take == 0)
					continue;

				$cart[$siteID][$sku] = $take;
				$neededFill[$sku] -= $take;
			}
		}
		
		$results['cart'] = $cart;
		$results['avail'] = $avail;
		$results['parts'] = $ssfParts;
		
		return $results;
	}
	
	public static function OrderItems($orders, $skus, $name, $address, $city, $state, $zip, $phone, $recordNum, $shipping, $dropShip = '1')
	{
		$results['success'] = false;
		$results['message'] = '';
		$results['items'] = array_keys($skus);
		
		$url = SSF_HOST;
		$username = SSF_USERNAME;
		$password = SSF_PASSWORD;

		$guid = uniqid();
		$date = substr(gmdate("c"), 0, 19) . 'Z';
		
		try
		{
			$firstOrder = reset($orders);
			$firstSite = $firstOrder['site'];

			$recordNum2 = preg_replace("/[^a-zA-Z0-9]+/", '', $recordNum);
			
			$name = htmlentities($name);
			$city = htmlentities($city);
			$phone = htmlentities($phone);

			$bod = <<<EOD
<aaia:ProcessPurchaseOrder xmlns="http://www.openapplications.org/oagis" xmlns:ssf="https://www.ssfparts.com/ssfconnect" xmlns:aaia="http://www.aftermarket.org/oagis" xmlns:oa="http://www.openapplications.org/oagis" xmlns:xs="http://www.w3.org/2001/XMLSchema-instance">
  <oa:ApplicationArea>
    <oa:Sender>
      <oa:ReferenceId>${guid}</oa:ReferenceId>
      <oa:Confirmation>1</oa:Confirmation>
    </oa:Sender>
    <oa:CreationDateTime>${date}</oa:CreationDateTime>
    <oa:BODId>${guid}</oa:BODId>
  </oa:ApplicationArea>
  <oa:DataArea>
    <oa:Process acknowledge="Always" />
    <oa:PurchaseOrder>
      <aaia:Header>
        <oa:DocumentIds>
          <oa:CustomerDocumentId>
            <oa:Id>${recordNum2}</oa:Id>
          </oa:CustomerDocumentId>
        </oa:DocumentIds>
		<ShipNote author="">${shipping}</ShipNote>
        <oa:DropShipInd>${dropShip}</oa:DropShipInd>
        <Note lang="en" author="Delivery Notes"></Note>
        <oa:Parties>
          <oa:ShipFromParty active="1" oneTime="0">
            <oa:PartyId>
              <oa:Id>${firstSite}</oa:Id>
            </oa:PartyId>
          </oa:ShipFromParty>
          <oa:ShipToParty active="1" oneTime="0">
            <oa:Name>${name}</oa:Name>
            <oa:Addresses>
              <oa:Address>

EOD;
			$aLines = explode(';', $address);
			
			foreach ($aLines as $aLine)
			{
				$addLine = htmlentities(trim($aLine));
				$bod .= <<<EOD
                <oa:AddressLine>${addLine}</oa:AddressLine>

EOD;
			}

			$bod .= <<<EOD
                <oa:City>${city}</oa:City>
                <oa:StateOrProvince>${state}</oa:StateOrProvince>
                <oa:Country>US</oa:Country>
                <oa:PostalCode>${zip}</oa:PostalCode>
                <oa:Telephone>${phone}</oa:Telephone>
              </oa:Address>
            </oa:Addresses>
          </oa:ShipToParty>
        </oa:Parties>
      </aaia:Header>

EOD;
			$lineNum = 1;

			foreach ($orders as $order)
			{
				$sku = str_replace('.', '-', strtoupper($order['sku']));
				$site = $order['site'];
				$qty = $order['qty'];

                if (empty($qty))
                {
                    error_log('Blank quantity for ' . $sku);
                    continue;
                }

				$mpn = $sku;
				$brandId = "";
				
				$dotIdx = strpos($sku, '-');
				if ($dotIdx)
				{
					$mpn = substr($sku, 0, $dotIdx);
					$brandId = substr($sku, $dotIdx+1);
				}
							
				if (startsWith($mpn, 'EOCS'))
					$mpn = substr($mpn, 4);

				$bod .= <<<EOD
      <aaia:Line>
        <oa:LineNumber>${lineNum}</oa:LineNumber>
        <aaia:OrderItem>
          <aaia:ItemIds>
            <oa:CustomerItemId>
              <oa:Id />
            </oa:CustomerItemId>
            <aaia:SupplierItemId>
              <oa:Id>${mpn}</oa:Id>
            </aaia:SupplierItemId>
            <aaia:ManufacturerCode>${brandId}</aaia:ManufacturerCode>
            <ssf:SearchId>
              <ssf:Sequence>0</ssf:Sequence>
            </ssf:SearchId>
            <ssf:PrimaryShipFrom>
              <ssf:Location>${site}</ssf:Location>
            </ssf:PrimaryShipFrom>
          </aaia:ItemIds>
        </aaia:OrderItem>
        <oa:OrderQuantity uom="Each">${qty}</oa:OrderQuantity>
      </aaia:Line>

EOD;
				$lineNum++;
			}
		
			$bod .= <<<EOD
    </oa:PurchaseOrder>
  </oa:DataArea>
</aaia:ProcessPurchaseOrder>

EOD;

			$bod = htmlentities($bod);

			$soap = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:tns="${url}">
	<soap:Header>
		<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
			<wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
				<wsse:Username>${username}</wsse:Username>
				<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">${password}</wsse:Password>
			</wsse:UsernameToken>
		</wsse:Security>
	</soap:Header>
	<soap:Body>
    <CreatePurchaseOrder xmlns="${url}">
		<ProcessPurchaseOrderBOD>${bod}</ProcessPurchaseOrderBOD>
    </CreatePurchaseOrder>
	</soap:Body>
</soap:Envelope>
EOD;

			file_put_contents(LOGS_DIR . "ssf_ipo/${recordNum2}_req_${guid}.txt", $soap);

			$headers = array
			(
				"Content-Type: text/xml",
				"SOAPAction: ${url}:CreatePurchaseOrderSoapIn"
			);

			$ch = curl_init("${url}/ipo.asmx");
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $soap);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			$res = curl_exec($ch);
			if (empty($res))
				SendAdminEmail('W2 IPO Order Failed', $data);
			curl_close($ch);
			
			file_put_contents(LOGS_DIR . "ssf_ipo/${recordNum2}_res_${guid}.txt", $res);

			if (stristr($res, 'faultcode') === FALSE)
			{
				$res = XMLtoArray($res);
				$res = asearch($res, 'ACKNOWLEDGEPURCHASEORDERBOD', '', false);
				$res = XMLtoArray($res);
				$ac = search_nested_arrays($res, 'ACKNOWLEDGE');
				$ack = asearch($ac, 'CODE');
			}
			else
				$ack = 'Fault';
			
			if ($ack == 'Accepted')
			{
				$results['success'] = true;
				$rf = search_nested_arrays($res, 'RFQDOCUMENTREFERENCE');
				$results['message'] = trim(asearch($rf, 'CUSTOMERDOCUMENTID'));
				$results['refId'] = trim(asearch($res, 'REFERENCEID'));

				$core = 0;
				$shipping = 0;
				$head = search_nested_arrays($res, 'AAIA:HEADER');
				$total = trim(asearch($head, 'TOTALAMOUNT')) + 0;
				$subtotal = trim(asearch($head, 'EXTENDEDPRICE')) + 0;
				$acs = search_nested_arrays($head, 'ADDITIONALCHARGE');

				if (array_key_exists('DESCRIPTION', $acs))
					$acs = array(0 => $acs);

				foreach ($acs as $ac)
				{
					$d = search_nested_arrays($ac, 'DESCRIPTION');
					$desc = asearch($d, 'content');
					$owner = asearch($d, 'OWNER');
					$t = search_nested_arrays($ac, 'TOTAL');
					$amount = asearch($t, 'content');

					if ($desc == 'CORE')
						$core = trim($amount) + 0;
					else if ($owner == 'CN' || $owner == 'SERVICE' || $owner == 'CARRIER')
						$shipping = trim($amount) + 0;
				}

				if (empty($subtotal))
					$subtotal = $total - $shipping - $core;

				$results['subtotal'] = $subtotal;
				$results['core'] = $core;
				$results['shipping'] = $shipping;
				$results['total'] = $total;
			}
			else
			{
				$results['message'] = 'W2 server error. Check with W2';

				if ($ack != 'Fault')
					$results['message'] = 'Some of the items are not available. Check with W2';
			}
		}
		catch (Exception $e)
		{
			$results['message'] = $e->getMessage();
		}

		return $results;
	}
	
	public static function DownloadImage($sequence, $filename)
	{
		$url = SSF_HOST;
		$username = SSF_USERNAME;
		$password = SSF_PASSWORD;

		$guid = uniqid();

		$soap = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:tns="${url}">
	<soap:Header>
		<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
			<wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
				<wsse:Username>${username}</wsse:Username>
				<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">${password}</wsse:Password>
			</wsse:UsernameToken>
		</wsse:Security>
	</soap:Header>
	<soap:Body>
		<GetPartImage xmlns="${url}/valueAdded">
			<anv_reference_id>${guid}</anv_reference_id>
			<ai_searchId_sequence>${sequence}</ai_searchId_sequence>
		</GetPartImage>
	</soap:Body>
</soap:Envelope>
EOD;

		$headers = array
		(
			"Content-Type: text/xml",
			"SOAPAction: ${url}/valueAdded/GetPartImage"
		);

		$ch = curl_init("${url}/valueAdded.asmx");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $soap);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);
		
		if (empty($res))
			return;

		$res = XMLtoArray($res);
		$status = asearch($res, 'AV_ERROR_MSJ');
		
		if ($status == 'okay')
		{
			$img = asearch($res, 'PI_PART_IMAGE');
			if (!empty($img))
				file_put_contents($filename, base64_decode($img));
		}
	}
	
	public static function searchNameValue($line, $name)
	{
		$nvs = search_nested_arrays($line, 'NAMEVALUE');
		
		if (empty($nvs))
			return null;
			
		$ret = array();

		foreach ($nvs as $key => $pair)
		{
			if (!array_key_exists('NAME', $pair))
				continue;

			if ($pair['NAME'] == $name)
				if (array_key_exists('content', $pair))
					$ret[] = $pair['content'];
		}
		
		if (empty($ret))
			return null;
		else if (count($ret) == 1)
			return $ret[0];
		else
			return $ret;
	}
	
	public static function ConvertShipping($speed)
	{
		if ($speed == 'GROUND')
			return SSF_SHIPPING_GROUND;
		else if ($speed == '2ND DAYAIR')
			return SSF_SHIPPING_2DAY;
		else if ($speed == 'NXTDAYSAVR')
			return SSF_SHIPPING_1DAY;
		else if (stristr('ground', $speed) !== false)
			return SSF_SHIPPING_GROUND;
		else if (stristr($speed, 'standard') !== false)
			return SSF_SHIPPING_GROUND;
		else if (stristr($speed, 'expedited') !== false)
			return SSF_SHIPPING_GROUND;
		else if (stristr($speed, 'second') !== false)
			return SSF_SHIPPING_2DAY;
		else if (stristr($speed, '2nd') !== false)
			return SSF_SHIPPING_2DAY;
		else if (stristr($speed, 'next') !== false)
			return SSF_SHIPPING_1DAY;
        else if (stristr($speed, 'overnight') !== false)
            return SSF_SHIPPING_1DAY;
		else
			return SSF_SHIPPING_GROUND;
	}
}

?>