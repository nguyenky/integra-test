<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/imc_utils.php');
require_once(__DIR__ . '/../system/ssf_utils.php');

set_time_limit(0);

set_include_path(get_include_path() . PATH_SEPARATOR . realpath(__DIR__ . '/../system/amazon'));

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$toAck = [];

$serviceUrl = "https://mws.amazonservices.com/Orders/2013-09-01"; 

$config = array
(
	'ServiceURL' => $serviceUrl,
	'ProxyHost' => null,
	'ProxyPort' => -1,
	'MaxErrorRetry' => 3,
);

$service = new MarketplaceWebServiceOrders_Client(
	AWS_ACCESS_KEY_ID,
	AWS_SECRET_ACCESS_KEY,
	APPLICATION_NAME,
	APPLICATION_VERSION,
	$config);

echo "Retrieving first page\n";

file_put_contents(LOGS_DIR . "a_sales.log", "=============== START AMAZON SALES JOB AT ". date('Y-m-d H:i:s') ." ===============\r\n", FILE_APPEND);
file_put_contents(LOGS_DIR . "a_sales.log", "=============== CALLING LIST ORDERS API ==============\r\n", FILE_APPEND);
try
{
	$request = new MarketplaceWebServiceOrders_Model_ListOrdersRequest();

	$request->setSellerId(MERCHANT_ID);
	#$request->setCreatedAfter(new DateTime('-17 hour', new DateTimeZone('America/New_York')));
	$d = new DateTime('-4 hour', new DateTimeZone('America/New_York'));
	#$d1 = new DateTime('-1 day', new DateTimeZone('America/New_York'));
	$d = $d->format('Y-m-d\TH:i:s.u\Z');
	#$d1 = $d1->format('Y-m-d\TH:i:s.u\Z');
	file_put_contents(LOGS_DIR . "a_sales.log", "Date Time: ". $d ."\r\n", FILE_APPEND);
	$request->setCreatedAfter($d);
	#$request->setCreatedBefore($d1);

}
catch(Exception $e)
{
	file_put_contents(LOGS_DIR . "a_sales.log", "ListOrdersRequest Errors: ".$e->getMessage()."\r\n", FILE_APPEND);
}


#$request->setLastUpdatedBefore(new DateTime('-1 day', new DateTimeZone('America/New_York')));
#$request->setCreatedBefore(new DateTime('-2 minute', new DateTimeZone('America/New_York')));

$orderList = [];
$nextToken = null;

try
{
	#$marketplaceIdList = new MarketplaceWebServiceOrders_Model_MarketplaceIdList();

	$totalOrderGot = 0;


	#$marketplaceIdList->setId(array(MARKETPLACE_ID));
	

	$request->setMarketplaceId([MARKETPLACE_ID]);

	$response = $service->listOrders($request);


	$listOrdersResult = $response->getListOrdersResult();

	#var_dump($listOrdersResult);

	$nextToken = $listOrdersResult->getNextToken();
	$orderList = $listOrdersResult->getOrders();

	#var_dump($orderArray);	

	#$orderList = $orderArray->getOrder();
	#var_dump($orderList);
}
catch(Exception $e)
{
	file_put_contents(LOGS_DIR . "a_sales.log", "MarketplaceIdList Errors: ".$e->getMessage()."\r\n", FILE_APPEND);
}



file_put_contents(LOGS_DIR . "a_sales.log", "Next token: ".$nextToken." \r\n", FILE_APPEND);



foreach ($orderList as $order)
{
    $o = getOrder($order);
    if (empty($o))
        continue;
    $totalOrderGot++;
	$orders[] = $o;
	
	unset($o);
}

while (!empty($nextToken))
{
	file_put_contents(LOGS_DIR . "a_sales.log", "List orders by Next Token: ".$nextToken." \r\n", FILE_APPEND);
	try {
		file_put_contents(LOGS_DIR . "a_sales.log", "======= CALLING ListOrdersByNextToken API ========\r\n", FILE_APPEND);
        echo "Retrieving next page\n";
        $request = new MarketplaceWebServiceOrders_Model_ListOrdersByNextTokenRequest();
        $request->setSellerId(MERCHANT_ID);
        $request->setNextToken($nextToken);
        $response = $service->listOrdersByNextToken($request);

        #file_put_contents(LOGS_DIR . "a_sales.log", "Response from amazon: ".serialize($response)." \r\n", FILE_APPEND);

        $listOrdersResult = $response->getListOrdersByNextTokenResult();
        $nextToken = $listOrdersResult->getNextToken();
        $orderList = $listOrdersResult->getOrders();
        #$orderList = $orderArray->getOrder();

        #$ordersStr = implode(',', $listOrdersResult);

        
        foreach ($orderList as $order) {
            $o = getOrder($order);
            if (empty($o))
                continue;

            $orders[] = $o;
            $totalOrderGot++;
            unset($o);
        }
    }
    catch (Exception $e)
	{
		file_put_contents(LOGS_DIR . "a_sales.log", "ListOrdersByNextToken Errors: ".$e->getTraceAsString()." \r\n", FILE_APPEND);
	}
}

if (!empty($orders))
{
	foreach ($orders as &$order)
	{
        try
        {
        	file_put_contents(LOGS_DIR . "a_sales.log", "========== CALLING ListOrderItems API ==========\r\n", FILE_APPEND);
            echo "Retrieving order " . $order['ORDERID'] . "\n";
            $request = new MarketplaceWebServiceOrders_Model_ListOrderItemsRequest();
            $request->setSellerId(MERCHANT_ID);
            $request->setAmazonOrderId($order['ORDERID']);
            $response = $service->listOrderItems($request);
            $listOrderItemsResult = $response->getListOrderItemsResult();
            $orderItemList = $listOrderItemsResult->getOrderItems();
            #$orderItemList = $orderItemsArray->getOrderItem();

            #$itemsStr = implode(',', $listOrderItemsResult);
            #file_put_contents(LOGS_DIR . "a_sales.log", "List Items: ".$itemsStr." \r\n", FILE_APPEND);

            foreach ($orderItemList as $orderItem)
            {
                // translate dupes then kit overrides
                $i['SKU'] = trim(TranslateSKU(TranslateSKU($orderItem->getSellerSKU())));

                if (endsWith($i['SKU'], '$D'))
                    $order['FORCEDROPSHIP'] = true;
                else if (endsWith($i['SKU'], '$W'))
                    $order['FORCEWAREHOUSE'] = true;

                $i['ITEMASIN'] = $orderItem->getASIN();
                $i['ITEMNAME'] = $orderItem->getTitle();
                $i['QUANTITY'] = $orderItem->getQuantityOrdered();

                $i['TOTALPRICE'] = 0;
                $i['ITEMPRICE'] = 0;

                if ($orderItem->isSetItemPrice())
                {
                    $itemPrice = $orderItem->getItemPrice();
                    $i['TOTALPRICE'] = $itemPrice->getAmount();
                }

                if (!empty($i['QUANTITY']))
                    $i['ITEMPRICE'] = $i['TOTALPRICE'] / $i['QUANTITY'];

                $orderItems[$order['ORDERID']][] = $i;
                unset($i);
            }

            if ($order['SPEED'] == 'Local Pick Up')
            {
                $order['FORCEWAREHOUSE'] = true;
                $order['FORCEDROPSHIP'] = false;
            }
        }
        catch (Exception $e)
        {
            $order['ORDERID'] = '';
            file_put_contents(LOGS_DIR . "a_sales.log", "ListOrderItems Errors: ".$e->getTraceAsString()." \r\n", FILE_APPEND);
        }

        sleep(2);
	}

	file_put_contents(LOGS_DIR . "a_sales.log", "========== START INSERT DATA INTO INTEGRA DATABASE =========\r\n", FILE_APPEND);

try
{
	foreach ($orders as $order)
	{

        if (empty($order['ORDERID'])) continue;
        file_put_contents(LOGS_DIR . "a_sales.log", " Insert data for Order ID:". $order['ORDERID'] ." \r\n", FILE_APPEND);
		$q=<<<EOQ
		INSERT IGNORE INTO sales (store, internal_id, record_num, order_date, total, listing_fee, buyer_id, email, buyer_name, street, city, state, country, zip, phone, speed, agent)
		VALUES ('%s', '%s', NULLIF('%s', ''), '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
EOQ;

		file_put_contents(LOGS_DIR. "a_sales.log", "Sql: ".sprintf($q,
			'Amazon',
			cleanup($order['ORDERID']),
			cleanup($order['RECORDNUMBER']),
			cleanup($order['ORDERDATE']),
			cleanup($order['TOTAL']),
			cleanup($order['LISTINGFEE']),
			cleanup($order['BUYERID']),
			cleanup($order['EMAIL']),
			cleanup($order['BUYERNAME']),
			cleanup($order['STREET']),
			cleanup($order['CITY']),
			cleanup($order['STATE']),
			cleanup($order['COUNTRY']),
			cleanup($order['ZIP']),
			cleanup($order['PHONE']),
			cleanup($order['SPEED']),
			'Amazon'). " \r\n", FILE_APPEND);

		mysql_query(sprintf($q,
			'Amazon',
			cleanup($order['ORDERID']),
			cleanup($order['RECORDNUMBER']),
			cleanup($order['ORDERDATE']),
			cleanup($order['TOTAL']),
			cleanup($order['LISTINGFEE']),
			cleanup($order['BUYERID']),
			cleanup($order['EMAIL']),
			cleanup($order['BUYERNAME']),
			cleanup($order['STREET']),
			cleanup($order['CITY']),
			cleanup($order['STATE']),
			cleanup($order['COUNTRY']),
			cleanup($order['ZIP']),
			cleanup($order['PHONE']),
			cleanup($order['SPEED']),
			'Amazon'));
		$salesId = mysql_insert_id();

		file_put_contents(LOGS_DIR . "a_sales.log", " INSERT ID:". $salesId ." \r\n", FILE_APPEND);
		
		if (empty($salesId))
		{
			file_put_contents(LOGS_DIR . "a_sales.log", " Item already existed: ". $order['ORDERID'] ." \r\n", FILE_APPEND);
			$q=<<<EOQ
			UPDATE sales SET record_num = NULLIF('%s', ''), order_date = '%s', total = '%s', listing_fee = '%s', buyer_id = '%s',
			email = '%s', buyer_name = '%s', street = '%s', city = '%s', state = '%s', country = '%s', zip = '%s', phone = '%s',
			speed = '%s'
			WHERE store = 'Amazon' AND internal_id = '%s'
EOQ;
			mysql_query(sprintf($q,
				cleanup($order['RECORDNUMBER']),
				cleanup($order['ORDERDATE']),
				cleanup($order['TOTAL']),
				cleanup($order['LISTINGFEE']),
				cleanup($order['BUYERID']),
				cleanup($order['EMAIL']),
				cleanup($order['BUYERNAME']),
				cleanup($order['STREET']),
				cleanup($order['CITY']),
				cleanup($order['STATE']),
				cleanup($order['COUNTRY']),
				cleanup($order['ZIP']),
				cleanup($order['PHONE']),
				cleanup($order['SPEED']),
				cleanup($order['ORDERID'])));
				
			$q=<<<EOQ
				SELECT id
				FROM sales
				WHERE internal_id = '%s' AND store = 'Amazon'
EOQ;
			$row = mysql_fetch_row(mysql_query(sprintf($q, cleanup($order['ORDERID']))));
			$salesId = $row[0];
		}
		
		if (empty($salesId))
			continue;
		
		if (empty($order['RECORDNUMBER']))
			$toAck[$order['ORDERID']] = $salesId;
			
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
				INSERT IGNORE INTO sales_items (sales_id, amazon_asin, sku, description, quantity, unit_price, total)
				VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s')
EOQ;
				mysql_query(sprintf($q,
					$salesId,
					cleanup($item['ITEMASIN']),
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
	
		$supplierId = CheckOrderSuppliers($salesId);
		mysql_query("UPDATE sales SET supplier = '${supplierId}' WHERE id = ${salesId}");

        echo "id: " . $salesId . ", supplier: " . $supplierId . "\n";

		if ($supplierId == 1)
		{
			file_put_contents(LOGS_DIR . "a_sales.log", " Order for IMC: ". $supplierId ." \r\n", FILE_APPEND);
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
				else if (array_key_exists($item['sku'], $parts))
                    $total += ($item['price'] * $parts[$item['sku']]);
                else
                    $total += ($item['price'] * $parts[$item['sku']]);
			}

			mysql_query("UPDATE sales SET supplier_cost = '${total}' WHERE id = ${salesId}");
			
			if ($actualTotal < ($total + $order['LISTINGFEE']) && $total > 0)
			{
				mysql_query("UPDATE sales SET status = 99 WHERE id = ${salesId} AND status = 0 AND fulfilment = 0");
				if (mysql_affected_rows() > 0)
					mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 0, 1)", $salesId,
						'Selling price is below item cost + fees!'));
			}
			else if ($total >= IMC_AUTODIRECT)
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
			else if ($total < IMC_AUTOEOC && $total > 0)
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
                else // unknown weight. default tan(arg)o dropship.
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
			else if ($total >= SSF_AUTODIRECT)
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
			else if ($total < SSF_AUTOEOC && $total > 0)
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
					mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1, 0)", $salesId, 'Multiple warehouse order'));
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
catch (Exception $e)
{
	file_put_contents(LOGS_DIR . "a_sales.log", "Insert Database Errors:".$e->getTraceAsString(). " \r\n", FILE_APPEND);
}
}

setRecordNumbers($toAck);

mysql_close();

file_put_contents(LOGS_DIR . "a_sales.log", "Job Done at: ".date('Y-m-d H:i:s'). " \r\n", FILE_APPEND);

function __autoload($className)
{
	$filePath = __DIR__ . '/../system/amazon/' . str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
	if (file_exists($filePath))
	{
		require_once $filePath;
		return;
	}
}

function getOrder($order)
{
    $o['FORCEWAREHOUSE'] = true;
    $o['FORCEDROPSHIP'] = false;

    $o['BUYERID'] = $order->getBuyerName();
    if (empty($o['BUYERID']))
        return null;

    $o['ORDERID'] = $order->getAmazonOrderId();
    $o['RECORDNUMBER'] = trim($order->getSellerOrderId());

    $orderDate = date_create($order->getPurchaseDate());
    $orderDate->setTimezone(new DateTimeZone('America/Los_Angeles'));
    $o['ORDERDATE'] = date_format($orderDate, 'Y-m-d H:i:s');

    if ($order->isSetShippingAddress())
    {
        $shippingAddress = $order->getShippingAddress();
        $o['BUYERNAME'] = $shippingAddress->getName();
        $o['STREET'] = trim($shippingAddress->getAddressLine1());
        $street2 = $shippingAddress->getAddressLine2();
        $street3 = $shippingAddress->getAddressLine3();

        if (!empty($street2))
        {
            $street2 = trim($street2);
            $o['STREET'] .= "; ${street2}";
        }

        if (!empty($street3))
        {
            $street3 = trim($street3);
            $o['STREET'] .= "; ${street3}";
        }

        $o['CITY'] = $shippingAddress->getCity();
        $o['STATE'] = $shippingAddress->getStateOrRegion();
        $o['COUNTRY'] = $shippingAddress->getCountryCode();
        $o['ZIP'] = $shippingAddress->getPostalCode();
        $o['PHONE'] = $shippingAddress->getPhone();
    }

    if ($order->isSetOrderTotal())
    {
        $orderTotal = $order->getOrderTotal();
        $o['TOTAL'] = $orderTotal->getAmount();
        $o['LISTINGFEE'] = $o['TOTAL'] * 0.12;
    }

    $o['EMAIL'] = $order->getBuyerEmail();
    $o['SPEED'] = standardize_shipping($order->getShipmentServiceLevelCategory());

    return $o;
}

function setRecordNumbers($toAck)
{
	if (empty($toAck))
	{
        file_put_contents(__DIR__ . "/../logs/amazon_sales.txt", date('Y-m-d H:i:s') . "] no new record nums to set\r\n", FILE_APPEND);
        return;
	}

	try {
        $config = array
        (
            'ServiceURL' => 'https://mws.amazonservices.com/',
            'ProxyHost' => null,
            'ProxyPort' => -1,
            'MaxErrorRetry' => 3,
        );

        $service = new MarketplaceWebService_Client(
            AWS_ACCESS_KEY_ID,
            AWS_SECRET_ACCESS_KEY,
            $config,
            APPLICATION_NAME,
            APPLICATION_VERSION);

        $merchantId = MERCHANT_ID;

        $feed = <<<EOD
<?xml version="1.0"?>
<AmazonEnvelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="amzn-envelope.xsd">
	<Header>
		<DocumentVersion>1.01</DocumentVersion>
		<MerchantIdentifier>${merchantId}</MerchantIdentifier>
	</Header>
	<MessageType>OrderAcknowledgement</MessageType>
EOD;

        $ctr = 1;
        $lines = "";

        foreach ($toAck as $orderId => $salesId) {
            $q = <<<EOQ
		SELECT CONCAT('A-', IFNULL(MAX(SUBSTRING(record_num, 3) + 0), 0) + 1) AS next
		FROM sales
		WHERE store = 'Amazon'
		AND record_num LIKE 'A-%'
EOQ;

            $row = mysql_fetch_row(mysql_query($q));
            $next = $row[0];

            $q = <<<EOQ
		UPDATE sales SET record_num = '%s'
		WHERE id = '%s'
EOQ;
            mysql_query(sprintf($q, cleanup($next), cleanup($salesId)));

            $feed .= <<<EOD
	<Message>
		<MessageID>${ctr}</MessageID>
		<OrderAcknowledgement>
			<AmazonOrderID>${orderId}</AmazonOrderID>
			<MerchantOrderID>${next}</MerchantOrderID>
			<StatusCode>Success</StatusCode>
		</OrderAcknowledgement>
	</Message>
EOD;

            $lines .= " sales_id: ${salesId}, order_id: ${orderId}, assigned record_num: ${next}\r\n";
            $ctr++;
        }

        $feed .= "</AmazonEnvelope>";

        $feedHandle = @fopen('php://temp', 'rw+');
        fwrite($feedHandle, $feed);
        rewind($feedHandle);
        $request = new MarketplaceWebService_Model_SubmitFeedRequest();
        $request->setMerchant(MERCHANT_ID);
        $request->setMarketplaceIdList(array("Id" => array(MARKETPLACE_ID)));
        $request->setFeedType('_POST_ORDER_ACKNOWLEDGEMENT_DATA_');
        $request->setContentMd5(base64_encode(md5(stream_get_contents($feedHandle), true)));
        rewind($feedHandle);
        $request->setPurgeAndReplace(false);
        $request->setFeedContent($feedHandle);
        rewind($feedHandle);
        $response = $service->submitFeed($request);
        @fclose($feedHandle);
        $status = $response->getSubmitFeedResult()->getFeedSubmissionInfo()->getFeedProcessingStatus();
        $feedId = $response->getSubmitFeedResult()->getFeedSubmissionInfo()->getFeedSubmissionId();

        file_put_contents(__DIR__ . "/../logs/amazon_sales.txt", date('Y-m-d H:i:s') . "] feed_id: ${feedId}, status: ${status}\r\n" . $lines, FILE_APPEND);
    }
    catch (Exception $e)
	{
        file_put_contents(__DIR__ . "/../logs/amazon_sales.txt", date('Y-m-d H:i:s') . "] error: " . $e->getMessage() . "\r\n" . $lines, FILE_APPEND);
	}
}
?>
