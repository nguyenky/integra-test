<?php

require_once('config.php');
require_once('utils.php');

class ImcUtils
{
	public static $siteIDs = array(
		101 => 'Canoga Park, CA',
		102 => 'Orange, CA',
		103 => 'Union City, CA',
		105 => 'Kirkland, WA',
		106 => 'Portland, OR',
		107 => 'Baltimore, MD',
		108 => 'Pompano Beach, FL',
		109 => 'Houston, TX',
		110 => 'Torrance, CA',
		111 => 'Dallas, TX',
		112 => 'Long Island, NY',
		115 => 'Miami, FL',
		123 => 'Norcross, GA',
		125 => 'Kearny, NJ'
		);
		
	public static function GetWarehouseRank(array &$sourceRank, array &$transit, $state, $zip)
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$q=<<<EOQ
			SELECT	shipfrom, rank, transitdays
			FROM	imc_warehouses
			WHERE	statecode = '%s'
			AND		zipstart <= LEFT('%s', 3)
			AND		zipend >= LEFT('%s', 3)
			AND		backup = 0
			AND		active = 1
			ORDER BY rank
EOQ;
		$res = mysql_query(sprintf($q, $state, $zip, $zip));
		
		while ($row = mysql_fetch_row($res))
		{
			$shipFrom = $row[0];
			
			if (array_key_exists($shipFrom, self::$siteIDs))
			{
				$sourceRank[$shipFrom] = $row[1];
				$transit[$shipFrom] = $row[2];
			}
		}
		
		if (empty($sourceRank))
		{
			$q=<<<EOQ
			SELECT	shipfrom, rank, transitdays
			FROM	imc_warehouses
			WHERE	statecode = '%s'
			AND		zipstart <= LEFT('%s', 3)
			AND		zipend >= LEFT('%s', 3)
			AND		backup = 1
			AND		active = 1
			ORDER BY rank
EOQ;
			$res = mysql_query(sprintf($q, $state, $zip, $zip));
		
			while ($row = mysql_fetch_row($res))
			{
				$shipFrom = $row[0];
				
				if (array_key_exists($shipFrom, self::$siteIDs))
				{
					$sourceRank[$shipFrom] = $row[1];
					$transit[$shipFrom] = $row[2];
				}
			}
		}
		
		foreach (self::$siteIDs as $siteID => $siteName)
		{
			if (!array_key_exists($siteID, $sourceRank))
				$sourceRank[$siteID] = 99;
		}

		asort($sourceRank);
	}

	public static function QueryItems($skus)
	{
		if (empty($skus))
			return;
			
		$results = array();
		$url = IMC_HOST;
		$username = IMC_USERNAME;
		$password = IMC_PASSWORD;
		
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$guid = uniqid();
		$date = gmdate("c");
		
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

		foreach (self::$siteIDs as $siteID => $siteName)
		{
			$data .= "\t\t\t\t\t\t\t\t\t<oa:ShipFromParty><oa:PartyId><oa:Id>${siteID}</oa:Id></oa:PartyId></oa:ShipFromParty>\r\n";
		}

		$data .= "								</oa:Parties></aaia:Header>\r\n";

		$lineNum = 1;

		foreach ($skus as $sku)
		{
			if (empty($sku))
				continue;
				
			$sku = trim(str_replace(' ', '', strtoupper($sku)));
			
			if (startsWith($sku, 'EOC'))
				$sku = substr($sku, 3);
				
			if (empty($sku))
				continue;

			$data .= <<<EOD
							<aaia:Line>
								<oa:LineNumber>${lineNum}</oa:LineNumber>
								<aaia:OrderItem>
									<aaia:ItemIds>
										<oa:CustomerItemId>
											<oa:Id>${sku}</oa:Id>
										</oa:CustomerItemId>
									</aaia:ItemIds>
								</aaia:OrderItem>
								<oa:OrderQuantity uom="EA">1</oa:OrderQuantity>
							</aaia:Line>

EOD;

			$lineNum++;
			$validSkus[] = $sku;
		}

		$data .= <<<EOD
						</oa:RequestForQuote>
					</oa:DataArea>
				</aaia:AddRequestForQuote>
			]]>
			</int:AddRequestforQuoteBOD>
		</int:Quote>
	</soapenv:Body>
</soapenv:Envelope>

EOD;

		$headers = array
		(
			'Content-Type: text/xml',
			'SOAPAction: ""'
		);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);
		//curl_setopt($ch, CURLOPT_PROXY, "64.64.28.183:8111");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);
		$origRes = $res;
		
		if (!empty($_REQUEST['debug']))
		{
			print_r($res);
		}
		
		$origRes = $res;
		//error_log($res);

		$res = XMLtoArray($res);
		$res = asearch($res, 'P23:ADDQUOTEBOD', '', false);
		$res = XMLtoArray($res);
		$lines = search_nested_arrays($res, 'AAIA:LINE');
		unset($res);
		
		if (empty($lines))
			error_log("NULL Lines for QueryItems: ${origRes} " . print_r($skus, true) . "\r\n{$origRes}\r\n");

		unset($origRes);

		if (array_key_exists('OA:LINENUMBER', $lines))
			$lines = array(0 => $lines);
			
		foreach ($lines as $line)
		{
			$status = asearch($line, 'OA:TO', 'AAIA:SUBLINE');
            $mpn = '';

			if ($status != "Item – Not Found")
			{
				$eocSKU = asearch($line, 'OA:CUSTOMERITEMID', 'AAIA:SUBLINE');
                $mpn = asearch($line, 'AAIA:SUPPLIERITEMID', 'AAIA:SUBLINE');
				$description = asearch($line, 'OA:DESCRIPTION', array('OA:ITEMSTATUS', 'OA:CHARGES'));
				$unitPrice = asearch($line, 'OA:UNITPRICE', 'AAIA:SUBLINE');
				$brand = asearch($line, 'OA:MANUFACTURERNAME', 'AAIA:SUBLINE');
				$orderItem = search_nested_arrays($line, 'AAIA:ORDERITEM');
				$weight = search_name_value($orderItem, 'Weight');
                $ac = search_nested_arrays($line, 'OA:ADDITIONALCHARGE', 'AAIA:SUBLINE');
                $core = trim(asearch($ac, 'OA:TOTAL'));
			}
			
			unset($item);
			$item['sku'] = $eocSKU;
            $item['mpn'] = $mpn;
			$item['desc'] = $description;
			$item['price'] = $unitPrice;
			$item['weight'] = $weight;
			$item['brand'] = $brand;
            $item['core'] = $core;
            $item['alt'] = [];
			
			foreach (self::$siteIDs as $siteID => $siteName)
				$item["site_${siteID}"] = 0;
				
			if ($status != "Item – Not Found")
			{
                $alternates = search_nested_arrays($line, 'AAIA:SUPPLIERITEMID');
                if (!empty($alternates))
                {
                    foreach ($alternates as $alt)
                    {
                        if (is_array($alt)) $altMpn = $alt['OA:ID'];
                        else $altMpn = $alt;
                        if (empty($altMpn)) continue;
						$altMpn = str_replace(' ', '', $altMpn);
                        $item['alt'][] = $altMpn;

						$q = <<<EOQ
INSERT IGNORE INTO magento.catalog_product_link (product_id, linked_product_id, link_type_id)
(SELECT cpe1.entity_id, cpe2.entity_id, 4
FROM magento.part_numbers pn, magento.catalog_product_entity cpe1, magento.catalog_product_entity cpe2
WHERE pn.code = '%s'
AND cpe1.sku = '%s'
AND cpe1.sku != cpe2.sku
AND pn.sku = cpe2.sku)
EOQ;

						mysql_query(sprintf($q, $altMpn, $mpn));
						$linkId = mysql_insert_id();
						if ($linkId)
							mysql_query("INSERT IGNORE INTO magento.catalog_product_link_attribute_int (product_link_attribute_id, link_id, value) VALUES (4, {$linkId}, 0)");
                    }
                }

				$sites = search_nested_arrays($line, 'OA:INVENTORYBALANCE', 'AAIA:SUBLINE');
				if (!empty($sites))
                {
                    if (array_key_exists('OA:SITE', $sites))
                        $sites = array(0 => $sites);

                    if (!empty($sites))
                    {
                        foreach ($sites as $site)
                        {
                            $siteID = asearch($site, 'OA:ID');
                            $item['site_' . $siteID] = intval($site['OA:AVAILABLEQUANTITY']['content']);
                        }
                    }
                }

				mysql_query(sprintf("INSERT IGNORE INTO integra_prod.products (sku, brand, name, supplier_id) VALUES ('%s', '%s', '%s', 1)",
						cleanup(trim(str_replace(' ', '', $eocSKU))),
						cleanup($brand),
						cleanup($description)));

				mysql_query(sprintf("INSERT INTO imc_items (mpn, brand, name, unit_price, weight, timestamp, core_price) VALUES('%s', '%s', '%s', '%s', '%s', NOW(), '%s') ON DUPLICATE KEY UPDATE brand = VALUES(brand), name = VALUES(name), unit_price = VALUES(unit_price), weight = VALUES(weight), timestamp=NOW(), core_price = VALUES(core_price)",
					cleanup(trim(str_replace(' ', '', $eocSKU))),
					cleanup($brand),
					cleanup($description),
					cleanup($unitPrice),
					cleanup($weight),
                    cleanup($core)));
			}
			
			$results[] = $item;
		}
		
		foreach ($validSkus as $vsku)
		{
			$found = false;
			
			foreach ($results as $result)
			{
				if ($result['sku'] == $vsku)
				{
					$found = true;
					break;
				}
			}
			
			if (!$found)
			{
				unset($item);
				$item['sku'] = $vsku;
				$item['desc'] = 'Not found';
				$item['price'] = '?';
				$item['weight'] = 0;
				$item['brand'] = '';
                $item['core'] = '?';
							
				foreach (self::$siteIDs as $siteID => $siteName)
					$item["site_${siteID}"] = 0;

				$results[] = $item;
				
				mysql_query(sprintf("UPDATE imc_items SET timestamp = NOW() WHERE mpn = '%s'",
					cleanup($vsku)));
			}
		}
		
		return $results;
	}
	
	public static function ScrapeSiteItems($mpns)
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$url = IMC_WEB_HOST;
		$username = IMC_WEB_USERNAME;
		$password = IMC_WEB_PASSWORD;
		$store = IMC_WEB_STORE;

		$cookie = tempnam("/tmp", "imcweb");

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_POST, true);

		curl_setopt($ch, CURLOPT_URL, "${url}Logon");
		curl_setopt($ch, CURLOPT_POSTFIELDS, "storeId=${store}&catalogId=${store}&langId=-1&reLogonURL=LogonForm&URL=HomePageView%3Flogon*%3D&postLogonPage=HomePageView&logonId=${username}&logonPassword=${password}&logonImg.x=32&logonImg.y=15");
		$output = curl_exec($ch);
		
		curl_setopt($ch, CURLOPT_POSTFIELDS, '');
		curl_setopt($ch, CURLOPT_POST, false);
		
		if (count($mpns) < 30)
		{
			try
			{
				ImcUtils::QueryItems($mpns);
			}
			catch (Exception $e)
			{
			}
		}
		
		foreach ($mpns as $mpn)
		{
			if (count($mpns) >= 30)
			{
				try
				{
					ImcUtils::QueryItems(array($mpn));
				}
				catch (Exception $e)
				{
				}
			}
			
			$mpn = trim(str_replace(' ', '', $mpn));

			curl_setopt($ch, CURLOPT_URL, "${url}PartNumSearchResultView?storeId=${store}&catalogId=${store}&partNumber=${mpn}");
			$output = curl_exec($ch);
			
			preg_match('/ApplicationInfoView.+?productId=(?<id>\d+)&.+?partNumber=' . $mpn . '/is', $output, $result);
			$imcProductId = trim($result['id']);
			
			if (empty($imcProductId))
			{
				error_log('IMC Product ID not found for: ' . $mpn);
				continue;
			}
			
			curl_setopt($ch, CURLOPT_URL, "${url}ProductDisplay?storeId=${store}&catalogId=${store}&productId=${imcProductId}");
			$output = curl_exec($ch);
			
			preg_match('/Part Notes(?<part_notes>.+?)<\/p/is', $output, $result);
			if (array_key_exists('part_notes', $result))
				$partNotes = trim(strip_tags($result['part_notes']));

			preg_match('/Pack Qty:.+?dd>(?<pack_qty>[^<]+)</is', $output, $result);
			$packQty = trim($result['pack_qty']);
			
			preg_match('/Application #.+?dd>(?<application_id>[^<]+)</is', $output, $result);
			$applicationId = trim($result['application_id']);
			
			if (empty($imcProductId))
			{
				error_log('IMC Application ID not found for: ' . $mpn);
				continue;
			}
			
			$qr = sprintf("UPDATE imc_items SET imc_product_id = '%s', application_id = '%s', pack_qty = '%s', part_notes = '%s', timestamp = NOW() WHERE mpn = '%s'",
					cleanup($imcProductId),
					cleanup($applicationId),
					cleanup($packQty),
					cleanup($partNotes),
					cleanup($mpn));
			
			mysql_query($qr);
			
			mysql_query(sprintf("DELETE FROM imc_fitment WHERE mpn = '%s'", cleanup($mpn)));
			
			preg_match_all("/AppInfoHeader[^']*?'\\), '[^']*', '(?<make>[^']+)', '(?<model>[^']+)'\\);/is", $output, $results, PREG_SET_ORDER);
		
			foreach ($results as $result)
			{
				$make = trim($result['make']);
				$model = ($result['model']);
			
				curl_setopt($ch, CURLOPT_URL, "${url}AjaxApplicationInformationURL?applicationNumber=" . urlencode($applicationId) . "&makeName=" . urlencode($make) . "&modelName=" . urlencode($model));
				$output = curl_exec($ch);
			
				$fits = json_decode($output, true);
			
				foreach ($fits['vehicles'] as $fit)
				{
					$miscNotes = $fit['nonFitmentNotes'];
					$year = $fit['year'];
					$position = $fit['position'];
					$fitNotes = $fit['fitmentNotes'];
					
					$qr = sprintf("INSERT INTO imc_fitment (mpn, make, model, year, position, fit_notes, misc_notes) VALUES('%s', '%s', '%s', '%s', '%s', '%s', '%s')",
						cleanup($mpn),
						cleanup($make),
						cleanup($model),
						cleanup($year),
						cleanup($position),
						cleanup($fitNotes),
						cleanup($miscNotes));
					
					mysql_query($qr);
				}
			}
		}

		curl_setopt($ch, CURLOPT_URL, "${url}Logoff?langId=-1&storeId=${store}&catalogId=${store}&URL=LogonForm&isLogOff=0");
		curl_exec($ch);
		curl_close($ch);
		
		unlink($cookie);
	}
	
	public static function PreSelectItems($parts, $state, $zip, $dropship = false)
	{
		$results = array();
		$sourceRank = array();
		$transit = array();
		
		$imcParts = TrimSKUPrefix($parts, 'EOC');

		if (empty($imcParts))
			return $results;

		self::GetWarehouseRank($sourceRank, $transit, $state, $zip);
		$imcAvails = self::QueryItems(array_keys($imcParts));

		foreach (self::$siteIDs as $siteID => $siteName)
			$fill[$siteID] = 0;
			
		foreach ($imcAvails as $imcAvail)
		{
			$sku = $imcAvail['sku'];
			$results['desc'][$sku] = $imcAvail['desc'];
			$results['price'][$sku] = $imcAvail['price'];
			
			foreach (self::$siteIDs as $siteID => $siteName)
			{				
				$qtyAvail = $imcAvail["site_${siteID}"];
                if ($dropship && $siteID == 15) $qtyAvail = 0; // disable miami since it does not support dropshipping
				$avail[$siteID][$sku] = $qtyAvail;
				$fill[$siteID] += min($qtyAvail, $imcParts[$sku]);
			}
		}

		arsort($fill);
		$maxFill = reset($fill);
		
		$sources = array();
		
		for ($i = $maxFill; $i >= 0; $i--)
		{
			unset($batch);
			
			foreach ($fill as $siteID => $total)
			{
				if ($total == $i)
					$batch[] = $siteID;
			}
			
			if (empty($batch))
				continue;
			
			unset($prio);
			
			foreach ($batch as $b)
				$prio[$b] = $sourceRank[$b];
				
			asort($prio);
			
			$sources = array_merge($sources, array_keys($prio));
		}
		
		$neededFill = $imcParts;
		
		foreach ($sources as $siteID)
		{
			foreach ($neededFill as $sku => $qty)
			{
				$take = min($qty, $avail[strval($siteID)][$sku]);
				$cart[$siteID][$sku] = $take;
				$neededFill[$sku] -= $take;
			}
		}
		
		$results['parts'] = $imcParts;
		$results['avail'] = $avail;
		$results['cart'] = $cart;
		$results['transit'] = $transit;
		
		return $results;
	}
	
	public static function OrderItems($siteID, $skus, $name, $address, $city, $state, $zip, $phone, $recordNum, $shipping, $ipoUsername = null, $ipoPassword = null)
	{
		//print_r(func_get_args());

		$results['success'] = false;
		$results['message'] = '';
		$results['items'] = array_keys($skus);
		
		//return $results;
		
		$url = IMC_HOST;
		$username = IMC_USERNAME;
		$password = IMC_PASSWORD;
		$dropShip = "1";
		
		if (!empty($ipoUsername))
		{
			$username = $ipoUsername;
			$password = $ipoPassword;
			$dropShip = "0";
		}

		$guid = uniqid();
		$date = gmdate("c");
		
		try
		{
			$recordNum2 = preg_replace("/[^a-zA-Z0-9]+/", '', $recordNum);
			
			$name = htmlentities($name);
			$city = htmlentities($city);
			$phone = htmlentities($phone);

			$data = <<<EOD
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
		<int:CreatePurchaseOrder>
			<int:ProcessPurchaseOrderBOD><![CDATA[<?xml version="1.0"?>
					<aaia:ProcessPurchaseOrder xmlns:oa="http://www.openapplications.org/oagis" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:aaia="http://www.aftermarket.org/oagis" xsi:schemaLocation="http://www.aftermarket.org/oagis ../Runtime/ProcessPurchaseOrder.xsd " revision="1.2.1" environment="Production" lang="en">
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
							<oa:Process confirm="Always" acknowledge="Always"/>
							<oa:PurchaseOrder>
								<aaia:Header>
									<ShipNote lang="en">G</ShipNote>
									<oa:DocumentIds>
										<oa:CustomerDocumentId>
											<oa:Id>${recordNum2}</oa:Id>
										</oa:CustomerDocumentId>
									</oa:DocumentIds>
									<oa:DocumentDateTime>${date}</oa:DocumentDateTime>
									<oa:DropShipInd>${dropShip}</oa:DropShipInd>
									<oa:Parties>
										<oa:BillToParty>
											<oa:PartyId>
												<oa:Id>${username}</oa:Id>
											</oa:PartyId>
										</oa:BillToParty>
										<oa:ShipToParty>
											<oa:Name>${name}</oa:Name>
											<oa:Addresses>
												<oa:Address>

EOD;

			$aLines = explode(';', $address);
			
			foreach ($aLines as $aLine)
			{
				$addLine = htmlentities(trim($aLine));
				$data .= <<<EOD
													<oa:AddressLine>${addLine}</oa:AddressLine>

EOD;
			}

			$data .= <<<EOD
													<oa:City>${city}</oa:City>
													<oa:StateOrProvince>${state}</oa:StateOrProvince>
													<oa:Country>US</oa:Country>
													<oa:PostalCode>${zip}</oa:PostalCode>
												</oa:Address>
											</oa:Addresses>
										</oa:ShipToParty>
										<oa:ShipFromParty>
											<oa:PartyId>
												<oa:Id>${siteID}</oa:Id>
											</oa:PartyId>
										</oa:ShipFromParty>
										<oa:CarrierParty>
											<oa:PartyId>
												<oa:Id>${shipping}</oa:Id>
											</oa:PartyId>
										</oa:CarrierParty>
									</oa:Parties>
								</aaia:Header>
EOD;

			$lineNum = 1;

			foreach ($skus as $sku => $qty)
			{
				$sku = strtoupper($sku);
							
				if (startsWith($sku, 'EOC'))
					$sku = substr($sku, 3);
								
				if (empty($sku))
					continue;

				$data .= <<<EOD
								<aaia:Line>
									<oa:LineNumber>${lineNum}</oa:LineNumber>
									<aaia:OrderItem>
										<aaia:ItemIds>
											<oa:CustomerItemId>
												<oa:Id>${sku}</oa:Id>
											</oa:CustomerItemId>
										</aaia:ItemIds>
									</aaia:OrderItem>
									<oa:OrderQuantity uom="EA">${qty}</oa:OrderQuantity>
								</aaia:Line>
EOD;
				$lineNum++;
			}

			$data .= <<<EOD
							</oa:PurchaseOrder>
						</oa:DataArea>
					</aaia:ProcessPurchaseOrder>
				]]>
			</int:ProcessPurchaseOrderBOD>
		</int:CreatePurchaseOrder>
	</soapenv:Body>
</soapenv:Envelope>

EOD;
			file_put_contents(LOGS_DIR . "imc_ipo/${recordNum2}_req_${guid}.txt", $data);

			$headers = array
			(
				'Content-Type: text/xml',
				'SOAPAction: ""'
			);

			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); 
			curl_setopt($ch, CURLOPT_TIMEOUT, 600);
			//curl_setopt($ch, CURLOPT_PROXY, "64.64.28.183:8111");
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$res = curl_exec($ch);
			if (empty($res))
				SendAdminEmail('W1 IPO Order Failed', $data);
		
			curl_close($ch);
			
			file_put_contents(LOGS_DIR . "imc_ipo/${recordNum2}_res_${guid}.txt", $res);
			
			if (stristr($res, 'faultcode') === FALSE)
			{
				$res = XMLtoArray($res);
				$res = asearch($res, 'P23:ACKNOWLEDGEPURCHASEORDERBOD', '', false);
				$res = XMLtoArray($res);
				$ac = search_nested_arrays($res, 'OA:ACKNOWLEDGE');
				$ack = asearch($ac, 'OA:CODE');
			}
			else
				$ack = 'Fault';
			
			if ($ack == 'Accepted' || $ack == 'Modified')
			{
				$results['success'] = true;
				$results['message'] = trim(asearch($res, 'OA:SUPPLIERDOCUMENTID'));
				
				$head = search_nested_arrays($res, 'AAIA:HEADER');
				$total = trim(asearch($head, 'OA:TOTALAMOUNT')) + 0;
				$bfc = search_nested_arrays($res, 'OA:BASICFREIGHTCHARGE');
				$shipping = trim(asearch($bfc, 'OA:TOTAL')) + 0;
				$ac = search_nested_arrays($res, 'OA:ADDITIONALCHARGE');
				$core = trim(asearch($ac, 'OA:TOTAL')) + 0;
				$subtotal = $total - $shipping - $core;

				$results['subtotal'] = $subtotal;
				$results['core'] = $core;
				$results['shipping'] = $shipping;
				$results['total'] = $total;
			}
			else
			{
				$results['message'] = 'W1 server error. Check with W1';

				if ($ack != 'Fault')
				{
					$os = search_nested_arrays($res, 'OA:ORDERSTATUS');
					
					if (!empty($os))
					{
						$error = asearch($os, 'OA:DESCRIPTION');
						if (!empty($error))
							$results['message'] = $error . ' - Check with W1';
					}
				}
			}
		}
		catch (Exception $e)
		{
			$results['message'] = $e->getMessage();
		}

		return $results;
	}
	
	public static function ConvertShipping($speed)
	{
		if ($speed == 'GROUND')
			return IMC_SHIPPING_GROUND;
		else if ($speed == '2ND DAYAIR')
			return IMC_SHIPPING_2DAY;
		else if ($speed == 'NXTDAYSAVR')
			return IMC_SHIPPING_1DAY;
		else if (stristr('ground', $speed) !== false)
			return IMC_SHIPPING_GROUND;
		else if (stristr($speed, 'standard') !== false)
			return IMC_SHIPPING_GROUND;
		else if (stristr($speed, 'expedited') !== false)
			return IMC_SHIPPING_GROUND;
		else if (stristr($speed, 'second') !== false)
			return IMC_SHIPPING_2DAY;
		else if (stristr($speed, '2nd') !== false)
			return IMC_SHIPPING_2DAY;
		else if (stristr($speed, 'next') !== false)
			return IMC_SHIPPING_1DAY;
        else if (stristr($speed, 'overnight') !== false)
            return IMC_SHIPPING_1DAY;
		else
			return IMC_SHIPPING_GROUND;
	}
	
	public static function LocalOrder($salesId)
	{
		try
		{
			$q=<<<EOQ
			SELECT p.id, s.record_num, s.buyer_name, ps.supplier_site, ps.recipient_name, ps.street, ps.city, ps.state, ps.zip, ps.phone, p.sku, ps.ipo_username, ps.ipo_password
			FROM sales s, pickups p, pickup_sites ps
			WHERE s.id = '%s'
			AND p.id = s.pickup_id
			AND ps.id = p.site_id
			AND p.status IN (1, 99)
EOQ;
			$row = mysql_fetch_row(mysql_query(sprintf($q,
				cleanup($salesId))));

			if (empty($row) || empty($row[0]))
			{
				SendAdminEmail('Error fulfilling local pickup order', "Unable to find sales ID: ${salesId}");
				return;
			}

			$pickupId = $row[0];
			$recordNum = $row[1];
			$name = $row[2];
			$siteId = $row[3];
			$shipName = $row[4];
			$shipStreet = $row[5];
			$shipCity = $row[6];
			$shipState = $row[7];
			$shipZip = $row[8];
			$shipPhone = $row[9];
			$origSku = $row[10];
			$ipoUsername = $row[11];
			$ipoPassword = $row[12];

			unset($items);
			$res = mysql_query("SELECT sku, quantity FROM sales_items WHERE sales_id = ${salesId}");
			while ($row = mysql_fetch_row($res))
				$items[$row[0]] = $row[1];

			foreach (GetSKUParts($items) as $sku => $qty)
			{
				if (empty($sku))
					continue;
						
				$sku = strtoupper($sku);
					
				if (startsWith($sku, 'EOC'))
					$sku = substr($sku, 3);
						
				if (empty($sku))
					continue;
				
				$parts[$sku] = $qty;
			}

			$results = ImcUtils::QueryItems(array_keys($parts));
			if (empty($results))
			{
				SendAdminEmail('Error fulfilling local pickup order', "SKU/MPN not found. sales ID: ${salesId}, sku: ${origSku}", false);
				mysql_query("UPDATE pickups SET status = 99 WHERE id = ${pickupId}");
				mysql_query("UPDATE sales SET fulfilment = 2, status = 99 WHERE pickup_id = ${pickupId}");
				mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 0, 0, 1)", $salesId,
						'W1: SKU/MPN not found'));
				return;
			}
			else
			{
				foreach ($results as $result)
				{
					$sku = $result['sku'];
					$needed = $parts[$sku];
					
					if ($result["site_${siteId}"] < $needed)
					{
						SendAdminEmail('Error fulfilling local pickup order', "Insufficient stock. sales ID: ${salesId}, record number: ${recordNum}, sku: ${origSku}", false);
						mysql_query("UPDATE pickups SET status = 99 WHERE id = ${pickupId}");
						mysql_query("UPDATE sales SET fulfilment = 2, status = 99 WHERE pickup_id = ${pickupId}");
						mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId,
								'W1: Out of stock'));

						return;
					}
				}
				
				$results = ImcUtils::OrderItems($siteId, $parts, "${name} c/o ${shipName}", $shipStreet, $shipCity, $shipState, $shipZip, $shipPhone, $recordNum, "OUR TRUCK", $ipoUsername, $ipoPassword);

				if ($results['success'] == 1)
				{
					mysql_query("UPDATE pickups SET status = 2 WHERE id = ${pickupId}");
					mysql_query("UPDATE sales SET fulfilment = 2, status = 2 WHERE id = ${salesId}");
					SaveDirectShipment($salesId, 1, $results['message'], null, $results['subtotal'], $results['core'], $results['shipping'], $results['total'], null, false, false);
					return;
				}
				else
				{
					SendAdminEmail('Error fulfilling local pickup order', $results['message'] . ". sales ID: ${salesId}, record number: ${recordNum}, sku: ${origSku}", false);
					mysql_query("UPDATE pickups SET status = 99 WHERE id = ${pickupId}");
					mysql_query(sprintf("UPDATE sales SET fulfilment = 2, status = 99 WHERE id = '%s'", $salesId));
					mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId,
							'W1: ' . $results['message']));
					return;
				}
			}
		}
		catch (Exception $e)
		{
			SendAdminEmail('Error fulfilling local pickup order', $e->getMessage() . ". sales ID: ${salesId}", false);
			
			if (!empty($pickupId))
				mysql_query("UPDATE pickups SET status = 99 WHERE id = ${pickupId}");

			mysql_query(sprintf("UPDATE sales SET fulfilment = 2, status = 99 WHERE id = '%s'", $salesId));
			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId,
					'W1: ' . $e->getMessage()));
		}
	}

    public static function ScrapeFreight($items, $address, $city, $state, $zip)
    {
        $ret['weight'] = null;
        $ret['options'] = [];
        $ret['subtotal'] = null;
        $ret['error'] = '';

        mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
        mysql_select_db(DB_SCHEMA);

        $url = IMC_WEB_DROPSHIP_HOST;
        $username = IMC_WEB_DROPSHIP_USERNAME;
        $password = IMC_WEB_DROPSHIP_PASSWORD;
        $store = IMC_WEB_DROPSHIP_STORE;

        $cookie = tempnam("/tmp", "imcwebds");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);

        // login
        curl_setopt($ch, CURLOPT_URL, "${url}Logon");
        curl_setopt($ch, CURLOPT_POSTFIELDS, "storeId=${store}&catalogId=${store}&langId=-1&reLogonURL=LogonForm&URL=HomePageView%3Flogon*%3D&postLogonPage=HomePageView&logonId=${username}&logonPassword=${password}&logonImg.x=32&logonImg.y=15");
        $output = curl_exec($ch);

        if (stripos($output, 'Quick Cart') && stripos($output, '(0 item)') === false)
        {
            // existing cart not empty, load and clear existing cart
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_URL, "${url}RequisitionListDisplay?langId=-1&storeId=${store}&catalogId=${store}&orderId=.");
            $output = curl_exec($ch);
            $start = stripos($output, 'id="ShopCartForm"');
            $end = stripos($output, '</form', $start);
            preg_match_all("/type=\"hidden\" name=\"(?<key>[^\"]+)\" value=\"(?<val>[^\"]*)\"/i", substr($output, $start, $end - $start), $matches, PREG_SET_ORDER);
            $args = [];
            foreach ($matches as $match)
            {
                $key = $match['key'];

                if (stripos($key, 'drpShip') === 0) continue;

                if (!array_key_exists($key, $args))
                    $args[$key] = $match['val'];

                if (stripos($key, 'branchName_') === 0)
                {
                    $idx = explode('_', $key)[1];
                    $args["quantity_{$idx}"] = '0';
                    $args["comment_{$idx}"] = '';
                }
            }
            $args['removeAllFlag'] = '1';
            $args['salesRepRelease'] = '';
            $args['orderComment'] = '';
            $args['orderTotalWeight'] = '0';
            $args['orderPrice'] = '0';

            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_URL, "${url}RequisitionListItemUpdate");
            $output = curl_exec($ch);
        }

        // validate address
        $paramsAddr = [];
        $paramsAddr['address1'] = $address;
        $paramsAddr['address2'] = '';
        $paramsAddr['city'] = $city;
        $paramsAddr['dsstate'] = $state;
        $paramsAddr['zip'] = explode('-', $zip)[0];

        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "${url}AjaxAddressValidationJSON?" . http_build_query($paramsAddr));
        $outputAddr = json_decode(curl_exec($ch), true);
        if ($outputAddr['status'] != 'true')
        {
            $ret['error'] = $outputAddr['message'];
            return $ret;
        }

        $cartItems = [];

        // check each item against IMC catalog
        foreach ($items as $mpn => $qty)
        {
            if (empty($mpn)) continue;

            $mpn = trim(str_replace(' ', '', strtoupper($mpn)));
            if (startsWith($mpn, 'EOC'))
                $mpn = substr($mpn, 3);
            curl_setopt($ch, CURLOPT_URL, "${url}AjaxFastOrderPartSearch?partNumber={$mpn}");
            $output = json_decode(curl_exec($ch), true);
            if (count($output['parts']) == 0)
            {
                $ret['error'] = "Invalid MPN: {$mpn}";
                return $ret;
            }
            $cartItem = [];
            $cartItem['orderedAsPartNumber'] = $mpn;
            $cartItem['quantity'] = $qty;
            $cartItem['catentryId'] = $output['parts'][0]['catentryId'];
            $cartItem['partNumber'] = $output['parts'][0]['partNumber'];
            curl_setopt($ch, CURLOPT_URL, "${url}AjaxFastOrderPartSearch?partNumber={$mpn}&catentryId=" . $cartItem['catentryId']);
            $output = curl_exec($ch);
            file_put_contents(LOGS_DIR . 'imc_parts' . DIRECTORY_SEPARATOR . $cartItem['partNumber'] . '_' . $cartItem['catentryId'] . '.json', $output);
            $output = json_decode($output, true);
            $cartItem['batteryFlag'] = $output['partInfoBean']['batteryFlag'];
            $cartItems[] = $cartItem;
        }

        // add items to cart
        $params = [];
        $params['catalogId'] = '';
        $params['storeId'] = IMC_WEB_DROPSHIP_STORE;
        $params['langId'] = '-1';
        $params['redirecturl'] = '';
        $params['URL'] = '';
        $params['viewTaskName'] = 'FastOrderView';
        $params['fromPage'] = 'FastOrder';

        for ($ctr = 1; $ctr <= count($cartItems); $ctr++)
        {
            $params["quantity_{$ctr}"] = $cartItems[$ctr-1]['quantity'];
            $params["orderedAsPartNumber_{$ctr}"] = $cartItems[$ctr-1]['orderedAsPartNumber'];
            $params["partNumber_{$ctr}"] = $cartItems[$ctr-1]['partNumber'];
            $params["catEntryId_{$ctr}"] = $cartItems[$ctr-1]['catentryId'];
            $params["batteryFlag_{$ctr}"] = $cartItems[$ctr-1]['batteryFlag'];
        }

        $params["quantity_{$ctr}"] = '';

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_URL, "${url}FastOrderEntries");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $output = curl_exec($ch);

        // submit address
        $paramsAddr['contact'] = 'imc';
        $paramsAddr['company'] = 'x x';
        $paramsAddr['address1'] = $outputAddr['address1'][0];
        $paramsAddr['address2'] = '';
        $paramsAddr['address3'] = '';
        $paramsAddr['city'] = $outputAddr['city'][0];
        $paramsAddr['dsstate'] = $outputAddr['dsstate'][0];
        $paramsAddr['zip'] = $outputAddr['zip'][0];

        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "${url}IMCDropShipperInfoCmd?" . http_build_query($paramsAddr));
        $output = curl_exec($ch);

        // go to shipping page
        $start = stripos($output, 'id="ShopCartForm"');
        $end = stripos($output, '</form', $start);
        preg_match_all("/type=\"hidden\" name=\"(?<key>[^\"]+)\" value=\"(?<val>[^\"]*)\"/i", substr($output, $start, $end - $start), $matches, PREG_SET_ORDER);
        $args = [];
        foreach ($matches as $match)
            $args[$match['key']] = $match['val'];

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_URL, "${url}ShippingOptionsView?showFreight=true");
        $output = curl_exec($ch);

        if (stripos($output, 'branchArray[1]'))
        {
            $ret['error'] = "Order will need to come from multiple warehouses";
            return $ret;
        }

        $orderId = $args['orderId'];
        $total = $args['totalPrice'];
        $branch = $args['branch'];

        preg_match("/branchWeight_{$branch}\"\\s+value=\"(?<n>[^\"]+)/i", $output, $matches);
        $branchWeight = $matches['n'];

        $args = [];
        $args['branches'] = $branch;
        $args['orderTotal'] = $total;
        $args['orderId'] = $orderId;
        $args['branchWeight'] = $branchWeight;

        preg_match_all("/freightOptionSelected\\([^']+'(?<name>[^']+)',(?<price>[^,]+)/i", $output, $matches, PREG_SET_ORDER);

        // get freight costs
        foreach ($matches as $match)
        {
            $args['selectedServiceLevels'] = $match['name'];
            $args['totalFreight'] = $match['price'];
            $args['freightCost'] = $match['price'];

            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_URL, "${url}AjaxCalculateFreight?" . http_build_query($args));
            $output = json_decode(curl_exec($ch), true);

            $option['service'] = $match['name'];
            $option['cost'] = $output['freightCost'];
            $option['cost_nodisc'] = $output['freightCostWithoutDiscount'];
            $ret['options'][] = $option;
        }

        $ret['weight'] = $branchWeight;
        $ret['subtotal'] = $total;

        return $ret;

        // store results
/*


        preg_match('/ApplicationInfoView.+?productId=(?<id>\d+)&.+?partNumber=' . $mpn . '/is', $output, $result);
            $imcProductId = trim($result['id']);

            if (empty($imcProductId))
            {
                error_log('IMC Product ID not found for: ' . $mpn);
                continue;
            }

            curl_setopt($ch, CURLOPT_URL, "${url}ProductDisplay?storeId=${store}&catalogId=${store}&productId=${imcProductId}");
            $output = curl_exec($ch);

            preg_match('/Part Notes(?<part_notes>.+?)<\/p/is', $output, $result);
            if (array_key_exists('part_notes', $result))
                $partNotes = trim(strip_tags($result['part_notes']));

            preg_match('/Pack Qty:.+?dd>(?<pack_qty>[^<]+)</is', $output, $result);
            $packQty = trim($result['pack_qty']);

            preg_match('/Application #.+?dd>(?<application_id>[^<]+)</is', $output, $result);
            $applicationId = trim($result['application_id']);

            if (empty($imcProductId))
            {
                error_log('IMC Application ID not found for: ' . $mpn);
                continue;
            }

            $qr = sprintf("UPDATE imc_items SET imc_product_id = '%s', application_id = '%s', pack_qty = '%s', part_notes = '%s', timestamp = NOW() WHERE mpn = '%s'",
                cleanup($imcProductId),
                cleanup($applicationId),
                cleanup($packQty),
                cleanup($partNotes),
                cleanup($mpn));

            mysql_query($qr);

            mysql_query(sprintf("DELETE FROM imc_fitment WHERE mpn = '%s'", cleanup($mpn)));

            preg_match_all("/AppInfoHeader[^']*?'\\), '[^']*', '(?<make>[^']+)', '(?<model>[^']+)'\\);/is", $output, $results, PREG_SET_ORDER);

            foreach ($results as $result)
            {
                $make = trim($result['make']);
                $model = ($result['model']);

                curl_setopt($ch, CURLOPT_URL, "${url}AjaxApplicationInformationURL?applicationNumber=" . urlencode($applicationId) . "&makeName=" . urlencode($make) . "&modelName=" . urlencode($model));
                $output = curl_exec($ch);

                $fits = json_decode($output, true);

                foreach ($fits['vehicles'] as $fit)
                {
                    $miscNotes = $fit['nonFitmentNotes'];
                    $year = $fit['year'];
                    $position = $fit['position'];
                    $fitNotes = $fit['fitmentNotes'];

                    $qr = sprintf("INSERT INTO imc_fitment (mpn, make, model, year, position, fit_notes, misc_notes) VALUES('%s', '%s', '%s', '%s', '%s', '%s', '%s')",
                        cleanup($mpn),
                        cleanup($make),
                        cleanup($model),
                        cleanup($year),
                        cleanup($position),
                        cleanup($fitNotes),
                        cleanup($miscNotes));

                    mysql_query($qr);
                }
            }
        }

        curl_setopt($ch, CURLOPT_URL, "${url}Logoff?langId=-1&storeId=${store}&catalogId=${store}&URL=LogonForm&isLogOff=0");
        curl_exec($ch);
        curl_close($ch);

        unlink($cookie);*/
    }
}

?>