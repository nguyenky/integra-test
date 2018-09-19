<?php

	Class IntegraIMCUtils
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
		protected $utils;
		public function __construct()
		{
			$this->utils = new Utils;
		}
		public function QueryItems($skus){
			if (empty($skus))
			return;

			$results = array();
			$url = Config::get('integra.imc.IMC_HOST'); 
			$username = Config::get('integra.imc.IMC_USERNAME');
			$password = Config::get('integra.imc.IMC_PASSWORD');

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
						
						if ($this->startsWith($sku, 'EOC'))
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
					// if (!empty($_REQUEST['debug']))
					// {
					// 	print_r($res);
					// }
					
					$origRes = $res;
					//-----
					$res = $this->utils->XMLtoArray($res);
					$res = $this->utils->asearch($res, 'P23:ADDQUOTEBOD', '', false);
					$res = $this->utils->XMLtoArray($res);
					$lines = $this->utils->search_nested_arrays($res, 'AAIA:LINE');
					unset($res);
		
					if (empty($lines))
						error_log("NULL Lines for QueryItems: ${origRes} " . print_r($skus, true) . "\r\n{$origRes}\r\n");

					unset($origRes);

					if (array_key_exists('OA:LINENUMBER', $lines))
						$lines = array(0 => $lines);
			
					foreach ($lines as $line)
					{
						$status = $this->utils->asearch($line, 'OA:TO', 'AAIA:SUBLINE');
			            $mpn = '';

						if ($status != "Item – Not Found")
						{
							$eocSKU = $this->utils->asearch($line, 'OA:CUSTOMERITEMID', 'AAIA:SUBLINE');
			                $mpn = $this->utils->asearch($line, 'AAIA:SUPPLIERITEMID', 'AAIA:SUBLINE');
							$description = $this->utils->asearch($line, 'OA:DESCRIPTION', array('OA:ITEMSTATUS', 'OA:CHARGES'));
							$unitPrice = $this->utils->asearch($line, 'OA:UNITPRICE', 'AAIA:SUBLINE');
							$brand = $this->utils->asearch($line, 'OA:MANUFACTURERNAME', 'AAIA:SUBLINE');
							$orderItem = $this->utils->search_nested_arrays($line, 'AAIA:ORDERITEM');
							$weight = $this->utils->search_name_value($orderItem, 'Weight');
			                $ac = $this->utils->search_nested_arrays($line, 'OA:ADDITIONALCHARGE', 'AAIA:SUBLINE');
			                $core = trim($this->utils->asearch($ac, 'OA:TOTAL'));
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
			                $alternates = $this->utils->search_nested_arrays($line, 'AAIA:SUPPLIERITEMID');
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

				$sites = $this->utils->search_nested_arrays($line, 'OA:INVENTORYBALANCE', 'AAIA:SUBLINE');
				if (!empty($sites))
                {
                    if (array_key_exists('OA:SITE', $sites))
                        $sites = array(0 => $sites);

                    if (!empty($sites))
                    {
                        foreach ($sites as $site)
                        {
                            $siteID = $this->utils->asearch($site, 'OA:ID');
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
					//-----
		}
		//end query items
		public function startsWith($haystack, $needle)
		{
		    $length = strlen($needle);
		    return (substr($haystack, 0, $length) === $needle);
		}
	}
?>