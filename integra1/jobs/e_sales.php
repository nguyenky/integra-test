<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/imc_utils.php');
require_once(__DIR__ . '/../system/ssf_utils.php');
require_once(__DIR__ . '/../system/counter_utils.php');

set_time_limit(0);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$callName = 'GetOrders';
$version = '867';

$url = EBAY_HOST . "wsapi?callname=${callName}&siteid=" . SITE_ID . "&appid=" . APP_ID . "&version=${version}&routing=default";

$fromHours = 5;
$toHours = 0;
$startDate = gmdate("Y-m-d\TH:i:s\Z", time() - (3600 * $fromHours));
$endDate = gmdate("Y-m-d\TH:i:s\Z", time() - (3600 * $toHours));

echo $startDate . "\n" . $endDate . "\n";

$page = 1;

nextPage:

echo "Page $page\n";

$data = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Header><h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>' . EBAY_TOKEN . '</eBayAuthToken></h:RequesterCredentials></s:Header><s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><' .$callName . 'Request xmlns="urn:ebay:apis:eBLBaseComponents"><Version>' . $version . '</Version><Pagination><EntriesPerPage>100</EntriesPerPage><PageNumber>' . $page . '</PageNumber></Pagination><OrderStatus>Completed</OrderStatus><ModTimeFrom>' . $startDate . '</ModTimeFrom><ModTimeTo>' . $endDate . '</ModTimeTo></' . $callName . 'Request></s:Body></s:Envelope>';

//test specific order id
//$ot = '<OrderIDArray><OrderID>111784792009-1518137298001</OrderID></OrderIDArray>';
//$data = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Header><h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>' . EBAY_TOKEN . '</eBayAuthToken></h:RequesterCredentials></s:Header><s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><' .$callName . 'Request xmlns="urn:ebay:apis:eBLBaseComponents"><Version>' . $version . '</Version><Pagination><EntriesPerPage>100</EntriesPerPage><PageNumber>' . $page . '</PageNumber></Pagination>' . $ot . '</' . $callName . 'Request></s:Body></s:Envelope>';

$headers = array
(
	'Content-Type: text/xml',
	'SOAPAction: ""'
);
/*  start insert counter */
CountersUtils::insertCounterProd('RequesterCredentials','Ebay Sales',APP_ID);
/*  end insert counter */
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);

//echo $res;

$res2 = XMLtoArray($res);
if (asearch($res2, 'HASMOREORDERS') == 'true')
	$moreOrders = true;
else
	$moreOrders = false;

$orderArray = search_nested_arrays($res2, 'ORDER');

if (empty($orderArray))
{
	if (!$moreOrders) SendAdminEmail('eBay sales data problem', $res, false);
	return;
}

if (array_key_exists('ORDERID', $orderArray))
	$orderArray = array(0 => $orderArray);
	
unset($res);
unset($res2);

foreach ($orderArray as $order)
{
    $o['FORCEDROPSHIP'] = false;
    $o['FORCEWAREHOUSE'] = false;

	$o['ORDERID'] = $order['ORDERID'];
	$o['TOTAL'] = $order['TOTAL']['content'];
	
	if ($o['TOTAL'] <= 50)
		$o['LISTINGFEE'] = $o['TOTAL'] * 0.1;
	else if ($o['TOTAL'] <= 1000)
		$o['LISTINGFEE'] = (($o['TOTAL']-50) * 0.08) + 5;
	else if ($o['TOTAL'] > 1000)
		$o['LISTINGFEE'] = (($o['TOTAL']-1000) * 0.02) + 81;	
	
	$orderDate = date_create($order['CREATEDTIME']);
	$orderDate->setTimezone(new DateTimeZone('America/Los_Angeles'));
	$o['ORDERDATE'] = date_format($orderDate, 'Y-m-d H:i:s');
	$o['BUYERID'] = $order['BUYERUSERID'];
	$o['RECORDNUMBER'] = asearch($order, 'SELLINGMANAGERSALESRECORDNUMBER');
	$o['BUYERNAME'] = asearch($order, 'NAME', 'SELLERSHIPMENTTOLOGISTICSPROVIDER');

	$street1 = asearch($order, 'STREET1', 'SELLERSHIPMENTTOLOGISTICSPROVIDER');
	$street2 = asearch($order, 'STREET2', 'SELLERSHIPMENTTOLOGISTICSPROVIDER');
	$city = asearch($order, 'CITYNAME', 'SELLERSHIPMENTTOLOGISTICSPROVIDER');
	$state = asearch($order, 'STATEORPROVINCE', 'SELLERSHIPMENTTOLOGISTICSPROVIDER');
	$country = asearch($order, 'COUNTRY', 'SELLERSHIPMENTTOLOGISTICSPROVIDER');
	$zip = asearch($order, 'POSTALCODE', 'SELLERSHIPMENTTOLOGISTICSPROVIDER');
	
	$street = trim($street1);
	
	if (!empty($street2) && stristr($street2, 'null') === FALSE)
		$street .= "; ${street2}";
	
	$forward = search_nested_arrays($order, 'SELLERSHIPMENTTOLOGISTICSPROVIDER');
	
	if (!empty($forward))
	{
		$fstreet1 = asearch($forward, 'STREET1');
		$fstreet2 = asearch($forward, 'STREET2');
		$fcity = asearch($forward, 'CITYNAME');
		$fstate = asearch($forward, 'STATEORPROVINCE');
		$fcountry = asearch($forward, 'COUNTRY');
		$fzip = asearch($forward, 'POSTALCODE');
		$fref = asearch($forward, 'REFERENCEID');
		
		$fstreet = trim($fstreet1);
	
		if (!empty($fstreet2) && stristr($fstreet2, 'null') === FALSE)
			$fstreet .= "; ${fstreet2}; ${fref}";
		else
			$fstreet .= "; ${fref}";
		
		$o['STREET'] = $fstreet;
		$o['CITY'] = $fcity;
		$o['STATE'] = $fstate;
		$o['COUNTRY'] = $fcountry;
		$o['ZIP'] = $fzip;
		
		$o['INTL_STREET'] = $street;
		$o['INTL_CITY'] = $city;
		$o['INTL_STATE'] = $state;
		$o['INTL_COUNTRY'] = $country;
		$o['INTL_ZIP'] = $zip;
	}
	else
	{
		$o['STREET'] = $street;
		$o['CITY'] = $city;
		$o['STATE'] = $state;
		$o['COUNTRY'] = $country;
		$o['ZIP'] = $zip;
		
		$o['INTL_STREET'] = '';
		$o['INTL_CITY'] = '';
		$o['INTL_STATE'] = '';
		$o['INTL_COUNTRY'] = '';
		$o['INTL_ZIP'] = '';
	}
	
	$o['PHONE'] = asearch($order, 'PHONE');
	$o['SPEED'] = standardize_shipping(asearch($order, 'SHIPPINGSERVICE', 'SHIPPINGDETAILS'));

	$items = search_nested_arrays($order, 'TRANSACTION');
	
	if (array_key_exists('BUYER', $items))
		$items = array(0 => $items);
		
	unset($tracking);
	$tracking = array();

	foreach ($items as $item)
	{
		$o['EMAIL'] = asearch($item, 'EMAIL');
		if ($o['EMAIL'] == 'Invalid Request')
			$o['EMAIL'] = '';
			
		$tracking[] = asearch($item, 'SHIPMENTTRACKINGNUMBER');
		$carrier = asearch($item, 'SHIPPINGCARRIERUSED');
		if (!empty($carrier))
			$o['CARRIER'] = $carrier;

		$i['ITEMID'] = asearch($item, 'ITEMID');
		$i['ITEMNAME'] = asearch($item, 'TITLE');

        // translate dupes then kit overrides
		$i['SKU'] = trim(TranslateSKU(TranslateSKU(asearch($item, 'SKU'))));

        if (endsWith($i['SKU'], '$D'))
            $o['FORCEDROPSHIP'] = true;
        else if (endsWith($i['SKU'], '$W'))
            $o['FORCEWAREHOUSE'] = true;

		$i['QUANTITY'] = asearch($item, 'QUANTITYPURCHASED');
		$i['ITEMPRICE'] = asearch($item, 'TRANSACTIONPRICE');
		$i['TOTALPRICE'] = $i['QUANTITY'] * $i['ITEMPRICE'];

		$orderItems[$o['ORDERID']][] = $i;
		unset($i);
	}
	
	$o['TRACKING'] = implode(',', array_unique($tracking));

    if ($o['SPEED'] == 'Local Pick Up')
    {
        $o['FORCEWAREHOUSE'] = true;
        $o['FORCEDROPSHIP'] = false;
    }

	$orders[] = $o;
	unset($o);
}

echo "Orders: " . count($orders) . "\n";

if ($moreOrders)
{
	$page++;
	goto nextPage;
}

//print_r($orders);

foreach ($orders as $order)
{
	echo "Inserting order " . $order['ORDERID'] . "\n";

	$q=<<<EOQ
INSERT IGNORE INTO sales (
store,	internal_id,	record_num,			order_date,	total,	listing_fee,	buyer_id,	email,	buyer_name,	street,	city,	state,	country,	zip,	phone,	speed,	tracking_num,	carrier,	agent,	intl_street,		intl_city,			intl_state,			intl_country,		intl_zip)
VALUES (
'eBay',	'%s',			NULLIF('%s', ''),	'%s',		'%s',	'%s',			'%s',		'%s',	'%s',		'%s',	'%s',	'%s',	'%s',		'%s',	'%s',	'%s',	'%s',			'%s',		'eBay',	NULLIF('%s', ''),	NULLIF('%s', ''),	NULLIF('%s', ''),	NULLIF('%s', ''),	NULLIF('%s', ''))
EOQ;
	
	mysql_query(query($q,
		$order['ORDERID'],
						$order['RECORDNUMBER'],
											$order['ORDERDATE'],
														$order['TOTAL'],
																$order['LISTINGFEE'],
																				$order['BUYERID'],
																							$order['EMAIL'],
																									$order['BUYERNAME'],
																												$order['STREET'],
																														$order['CITY'],
																																$order['STATE'],
																																		$order['COUNTRY'],
																																					$order['ZIP'],
																																							$order['PHONE'],
																																									$order['SPEED'],
																																											$order['TRACKING'],
																																															$order['CARRIER'],
																																																				$order['INTL_STREET'],
																																																									$order['INTL_CITY'],
																																																														$order['INTL_STATE'],
																																																																			$order['INTL_COUNTRY'],
																																																																								$order['INTL_ZIP']));
	$salesId = mysql_insert_id();
	
	if (empty($salesId))
	{
		$q=<<<EOQ
UPDATE sales SET
record_num = NULLIF('%s', ''),
	order_date = '%s',
		total = '%s',
			listing_fee = '%s',
				buyer_id = '%s',
					email = '%s',
						buyer_name = '%s',
							street = '%s',
								city = '%s',
									state = '%s',
										country = '%s',
											zip = '%s',
												phone = '%s',
													speed = '%s',
														tracking_num = '%s',
															carrier = '%s',
																intl_street = NULLIF('%s', ''),
																	intl_city = NULLIF('%s', ''),
																		intl_state = NULLIF('%s', ''),
																			intl_country = NULLIF('%s', ''),
																				intl_zip = NULLIF('%s', '')
	WHERE store = 'eBay' AND														internal_id = '%s'
EOQ;
		mysql_query(query($q,
$order['RECORDNUMBER'],
	$order['ORDERDATE'],
		$order['TOTAL'],
			$order['LISTINGFEE'],
				$order['BUYERID'],
					$order['EMAIL'],
						$order['BUYERNAME'],
							$order['STREET'],
								$order['CITY'],
									$order['STATE'],
										$order['COUNTRY'],
											$order['ZIP'],
												$order['PHONE'],
													$order['SPEED'],
														$order['TRACKING'],
															$order['CARRIER'],
																$order['INTL_STREET'],
																	$order['INTL_CITY'],
																		$order['INTL_STATE'],
																			$order['INTL_COUNTRY'],
																				$order['INTL_ZIP'],
																					$order['ORDERID']));
		$q=<<<EOQ
			SELECT id
			FROM sales
			WHERE internal_id = '%s' AND store = 'eBay'
EOQ;
		$row = mysql_fetch_row(mysql_query(query($q, $order['ORDERID'])));
		$salesId = $row[0];
	}
	
	if (empty($salesId))
		continue;
		
	$actualTotal = 0;
	
	foreach ($orderItems[$order['ORDERID']] as $item)
	{
		$actualTotal += $item['TOTALPRICE'];
	}

	$q=<<<EOQ
		SELECT COUNT(*), SUM(total)
		FROM sales_items
		WHERE sales_id = '%s'
EOQ;
	$row = mysql_fetch_row(mysql_query(sprintf($q, cleanup($salesId))));
	$itemCount = $row[0];
	$itemTotal = $row[1];
	$actualCount = count($orderItems[$order['ORDERID']]);

	if ($itemCount != $actualCount || $itemTotal != $actualTotal)
	{
		$q=<<<EOQ
			DELETE FROM sales_items
			WHERE sales_id = '%s'
EOQ;
		mysql_query(sprintf($q, cleanup($salesId)));
	
		foreach ($orderItems[$order['ORDERID']] as $item)
		{
			$q=<<<EOQ
			INSERT IGNORE INTO sales_items (sales_id, ebay_item_id, sku, description, quantity, unit_price, total)
			VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s')
EOQ;
			mysql_query(sprintf($q,
				$salesId,
				cleanup($item['ITEMID']),
				cleanup($item['SKU']),
				cleanup($item['ITEMNAME']),
				cleanup($item['QUANTITY']),
				cleanup($item['ITEMPRICE']),
				cleanup($item['TOTALPRICE'])));
		}
	}
	
	$result = GetTotalWeight($salesId);
	$weight = $result['weight'];
	if (!empty($weight))
		mysql_query("UPDATE sales SET weight = '${weight}' WHERE id = ${salesId}");
	
	$q=<<<EOQ
	SELECT status
	FROM sales
	WHERE id = '%s'
EOQ;
	$row = mysql_fetch_row(mysql_query(sprintf($q, cleanup($salesId))));
	if (!empty($row) && !empty($row[0]))
		$actualCount = 0; // disable local pickup processing if this order has been fulfilled.
		
	if ($order['COUNTRY'] != 'US')
		$actualCount = 0; // disable local pickup if international
	
	$pickupId = 0;
	
	if ($actualCount == 1)
	{
		$sku = $orderItems[$order['ORDERID']][0]['SKU'];

		$q=<<<EOQ
		SELECT id
		FROM pickups p
		WHERE buyer_id = '%s'
		AND status = 1
		AND sku = '%s'
		AND sales_id IS NULL
		AND NOT EXISTS (SELECT 1 FROM sales s WHERE s.pickup_id = p.id)
		ORDER BY id
		LIMIT 1
EOQ;
		$row = mysql_fetch_row(mysql_query(sprintf($q,
			cleanup(trim(strtolower($order['BUYERID']))),
			cleanup($sku))));
			
		if (!empty($row) && !empty($row[0]))
		{
			$pickupId = $row[0];
		
			$q=<<<EOQ
			UPDATE pickups
			SET sales_id = '%s', order_date = '%s'
			WHERE id = '%s'
EOQ;
			mysql_query(sprintf($q, cleanup($salesId), cleanup($order['ORDERDATE']), cleanup($pickupId)));
			
			$q=<<<EOQ
			UPDATE sales
			SET pickup_id = '%s', status = 1
			WHERE id = '%s'
EOQ;
			mysql_query(sprintf($q, cleanup($pickupId), cleanup($salesId)));

			$supplierId = CheckOrderSuppliers($salesId);
			if ($supplierId != 1)
			{
				SendAdminEmail('Error fulfilling local pickup order', "Supplier error for sales ID: ${salesId}, supplier ID: ${supplierId}, SKU: ${sku}", false);
				mysql_query("UPDATE pickups SET status = 99 WHERE id = ${pickupId}");

				mysql_query(sprintf("UPDATE sales SET status = 99 WHERE pickup_id = '%s' AND status = 0", cleanup($pickupId)));

				if (mysql_affected_rows() > 0)
					mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 0, 0, 1)", $salesId,
						'SKU/MPN not found'));
			}
			else
			{
				ImcUtils::LocalOrder($salesId);
			}
		}
	}

	if (empty($pickupId))
	{
		$supplierId = CheckOrderSuppliers($salesId);
		mysql_query("UPDATE sales SET supplier = '${supplierId}' WHERE id = ${salesId}");
	
		if ($supplierId == 1)
		{
			$parts = GetOrderComponents($salesId);
			$items = ImcUtils::QueryItems(array_keys($parts));
			$total = 0;
			
			foreach ($items as $item)
			{
				if (empty($item['price']))
				{
					$total = 0;
					break;
				}
				else $total += ($item['price'] * $parts[$item['sku']]);
			}

			mysql_query("UPDATE sales SET supplier_cost = '${total}' WHERE id = ${salesId}");

			if ($actualTotal < ($total + (0.8 * $order['LISTINGFEE'])) && $total > 0)
			{
				mysql_query("UPDATE sales SET status = 99 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
				if (mysql_affected_rows() > 0)
					mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 0, 1)", $salesId,
						'Selling price is below item cost + fees!'));
			}
			else if ($order['COUNTRY'] != 'US')	// EOC if international
			{
				mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
			}
			else if ($total >= IMC_AUTODIRECT)	// Dropship if product has IMC free shipping
			{
				mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
			}
            else if ($order['FORCEDROPSHIP']) // $D suffix
            {
                mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
            }
            else if ($order['FORCEWAREHOUSE']) // $W suffix
            {
                mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
            }
			else if ($total < IMC_AUTOEOC && $total > 0)	// EOC if product is cheap; no IMC free shipping
			{
				mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
			}
            else if ($total >= IMC_AUTOEOC && $total < IMC_AUTODIRECT) // gray area
            {
				$fillerCost = IMC_AUTODIRECT - $total;

				if (!empty($weight))
				{
					$res = mysql_query(sprintf(<<<EOQ
SELECT MAX(rate)
FROM e_shipping_rate
WHERE weight_from <= %1\$s
AND weight_to >= %1\$s
EOQ
							, $weight));
					$row = mysql_fetch_row($res);
					$stampsCost = $row[0];

                    if ($fillerCost > $stampsCost) // cheaper to ship via EOC
                        mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
                    else // cheaper to dropship with filler
                        mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
                }
                else // unknown weight. default to dropship.
                    mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
            }
		}
		else if ($supplierId == 2)
		{
			$parts = GetOrderComponents($salesId);
			$items = SsfUtils::QueryItems(array_keys($parts));
			$total = 0;
			
			foreach ($items as $item)
			{
				if (empty($item['price']))
				{
					$total = 0;
					break;
				}
				else $total += ($item['price'] * $parts[$item['sku']]);
			}
			
			mysql_query("UPDATE sales SET supplier_cost = '${total}' WHERE id = ${salesId}");

			if ($actualTotal < ($total + $order['LISTINGFEE']) && $total > 0)
			{
				mysql_query("UPDATE sales SET status = 99 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
				if (mysql_affected_rows() > 0)
					mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 0, 1)", $salesId,
						'Selling price is below item cost + fees!'));
			}
			else if ($order['COUNTRY'] != 'US')	// EOC if international
			{
				mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
			}
			else if ($total >= SSF_AUTODIRECT)	// Dropship if product has SSF free shipping
			{
				mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
			}
            else if ($order['FORCEDROPSHIP']) // $D suffix
            {
                mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
            }
            else if ($order['FORCEWAREHOUSE']) // $W suffix
            {
                mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
            }
			else if ($total < SSF_AUTOEOC && $total > 0)	// EOC if product is cheap; no SSF free shipping
			{
				mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
			}
			/*else if ($total >= SSF_AUTOEOC && $total < SSF_AUTODIRECT) // gray area
			{
				$fillerCost = SSF_AUTODIRECT - $total;

				if (!empty($weight))
				{
					$res = mysql_query(sprintf(<<<EOQ
SELECT MAX(rate)
FROM e_shipping_rate
WHERE weight_from <= %1\$s
AND weight_to >= %1\$s
EOQ
							, $weight));
					$row = mysql_fetch_row($res);
					$stampsCost = $row[0];

					if ($fillerCost > $stampsCost) // cheaper to ship via EOC
						mysql_query("UPDATE sales SET fulfilment = 3, status = 1, site_id = 3 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
					else // cheaper to dropship with filler
						mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
				}
				else // unknown weight. default to dropship.
					mysql_query("UPDATE sales SET fulfilment = 1, status = 1 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
			}*/
		}
        else if ($supplierId == 5)
        {
            $parts = GetOrderComponents($salesId);
            $partial = false;
            $total = 0;
            $fromImc = true;

            foreach ($parts as $sku => $qty)
            {
                if (strpos($sku, '.'))
                {
                    $i = SsfUtils::QueryItems([$sku]);
                    $fromImc = false;
                }
                else $i = ImcUtils::QueryItems([$sku]);

                if (!empty($i) && count($i) > 0 && !empty($i[0]['price']))
                    $total += ($i[0]['price'] * $qty);

                $eocStock = GetEOCStock($sku);
                if ($eocStock < $qty)
                {
                    $partial = true;
                    break;
                }
            }

            if ($partial)
            {
                mysql_query("UPDATE sales SET supplier = -2, status = 99 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
				if (mysql_affected_rows() > 0)
					mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, 'Multiple warehouse order'));
            }
            else
            {
                mysql_query("UPDATE sales SET supplier_cost = '${total}' WHERE id = ${salesId}");

                if ($actualTotal < ($total + $order['LISTINGFEE']) && $total > 0)
                {
                    mysql_query("UPDATE sales SET status = 99 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
					if (mysql_affected_rows() > 0)
						mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 0, 1)", $salesId,
							'Selling price is below item cost + fees!'));
                }
                else if ($fromImc && $total >= IMC_AUTODIRECT) // dropship from IMC
                {
                    mysql_query("UPDATE sales SET fulfilment = 1, status = 1, supplier = 1 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
                }
                else if (!$fromImc && $total >= SSF_AUTODIRECT) // dropship from SSF
                {
                    mysql_query("UPDATE sales SET fulfilment = 1, status = 1, supplier = 2 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
                }
                else
                {
                    mysql_query("UPDATE sales SET supplier = 5, status = 1, fulfilment = 3, site_id = 3 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
					if (mysql_affected_rows() > 0)
						mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 0)", $salesId,
							'On stock in EOC WH'));
                }
            }
        }
    }
}

$q=<<<EOQ
UPDATE pickups
SET status = 0
WHERE added_date <= DATE_SUB(NOW(), INTERVAL 1 DAY)
AND status = 1
EOQ;
mysql_query($q);

mysql_close();

?>
