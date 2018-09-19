<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/ssf_utils.php');

set_time_limit(0);
header("Content-Type: text/plain");

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$extraIds = array();
$salesIds = array();
$components = array();
$extraComponents = array();
$neededSkus = array();
$outOfStock = array();
$totals = array();

define('SSF_MINORDER', 200);

$q = <<<EOQ
SELECT id
FROM sales
WHERE fulfilment = 3
AND status = 1
EOQ;

$res = mysql_query($q);
		
while ($row = mysql_fetch_row($res))
	$salesIds[] = $row[0];

$q = <<<EOQ
SELECT id, mpn, quantity
FROM extra_orders
WHERE supplier = 2
AND (order_id IS NULL OR order_id = '')
EOQ;

$res = mysql_query($q);

while ($row = mysql_fetch_row($res))
    $extraIds[$row[0]] = $row[1] . '~' . $row[2];

foreach ($salesIds as $index => $salesId)
{
	$supplierId = CheckOrderSuppliers($salesId);
	
	// pure SSF
	if ($supplierId == 2)
	{
		$parts = GetOrderComponents($salesId);
		
		foreach ($parts as $sku => $qty)
		{
			$mpn =  trim('' . (startsWith($sku, 'EOCS') ? substr($sku, 4) : $sku));
            if (stripos($mpn, '.') === false) continue;
            if (stripos($mpn, 'EOC') === 0) continue;
            if (stripos($mpn, 'EW') === 0) continue;
			if (stripos($mpn, 'EK') === 0) continue;
			if (stripos($mpn, 'PU') === 0) continue;
			if (stripos($mpn, 'WP') === 0) continue;
			if (stripos($mpn, 'TR') === 0) continue;
            if (!$qty) continue;
			$components[$salesId][$mpn] = $qty;
			$neededSkus[$mpn] += $qty;
		}
	}
	else
	{
		unset($salesIds[$index]);
	}
}

foreach ($extraIds as $extraId => $parts)
{
    $tmp = explode('~', $parts);
    $mpn = trim('' . (startsWith($tmp[0], 'EOCS') ? substr($tmp[0], 4) : $tmp[0]));
    if (stripos($mpn, '.') === false) continue;
    if (stripos($mpn, 'EOC') === 0) continue;
    if (stripos($mpn, 'EW') === 0) continue;
	if (stripos($mpn, 'EK') === 0) continue;
	if (stripos($mpn, 'PU') === 0) continue;
	if (stripos($mpn, 'WP') === 0) continue;
	if (stripos($mpn, 'TR') === 0) continue;
    $qty = intval($tmp[1]);
    if (!$qty) continue;
    $extraComponents[$extraId][$mpn] = $qty;
    $neededSkus[$mpn] += $qty;
}

file_put_contents('ssf_order.txt', print_r($neededSkus, true));

$forQuery = array_unique(array_keys($neededSkus));
file_put_contents('ssf_query.txt', print_r($forQuery, true));

echo "Querying " . count($forQuery) . " items...\n";
while (@ob_end_flush());

$chunks = array_chunk($forQuery, 1);
$queryItems = [];

foreach ($chunks as $fq)
{
	for ($tries = 0; $tries < 3; $tries++)
	{
		$qis = SsfUtils::QueryItems($fq);
		if (!empty($qis))
		{
			foreach ($qis as $qi)
			{
				$queryItems[] = $qi;
			}
			echo "OK. Items so far: " . count($queryItems) ."\n";
			break;
		}
		echo "Error querying item: " . print_r($fq, true) . "\n";
		while (@ob_end_flush());
		sleep(5);
	}
}

if (empty($queryItems))
{
    SendAdminEmail("Error fulfilling SSF bulk order / Check MPNs", print_r($neededSkus, true), false);
    exit;
}

$availScore = array();

foreach ($queryItems as $item)
{
	if (strpos($item['sku'], '.') === false)
	{
		foreach (array_keys($neededSkus) as $sku)
		{
			$dotIdx = strpos($sku, '.');
			if ($dotIdx)
			{
				$mpn = substr($sku, 0, $dotIdx);
				if ($mpn == $item['sku'])
				{
					$item['sku'] = $sku;
					break;
				}
			}
		}
	}

	$availScore[$item['sku']] = 0;
	
	foreach (SsfUtils::$siteIDs as $siteID => $siteName)
	{
		if (empty($item["site_${siteID}"]))
			continue;

		if (in_array($siteID, SsfUtils::$noBulk))
			continue;

		if ($item["site_${siteID}"] >= $neededSkus[$item['sku']])
			$availScore[$item['sku']]++;
	}
	
	if ($availScore[$item['sku']] == 0)
		$outOfStock[] = $item['sku'];
		
	$inventory[$item['sku']] = $item;
}

asort($availScore);

foreach (SsfUtils::$siteIDs as $siteID => $siteName)
{
	if (in_array($siteID, SsfUtils::$noBulk))
		continue;

	$orders[$siteID] = array();
}

foreach ($availScore as $mpn => $score)
{
	$ordered = false;
	
	// pick site that has less than minimum order but already has an order, from highest total to lowest
	
	UpdateTotals();
	
	if (!empty($totals))
	{
		foreach ($totals as $siteID => $total)
		{
			if ($total >= SSF_MINORDER)
				continue;
				
			if (empty($inventory[$mpn]["site_${siteID}"]))
				continue;

			if (in_array($siteID, SsfUtils::$noBulk))
				continue;
				
			if ($inventory[$mpn]["site_${siteID}"] >= $neededSkus[$mpn])
			{
				$orders[$siteID][$mpn] = $neededSkus[$mpn];
                $inventory[$mpn]["site_${siteID}"] -= $neededSkus[$mpn];
				$ordered = true;
				break;
			}
		}
	}
	
	// or order from a warehouse with an existing order, from lowest total to highest
	
	if (!$ordered)
	{
		asort($totals);

		foreach ($totals as $siteID => $total)
		{
			if ($total == 0)
				continue;

			if (empty($inventory[$mpn]["site_${siteID}"]))
				continue;

			if (in_array($siteID, SsfUtils::$noBulk))
				continue;
				
			if ($inventory[$mpn]["site_${siteID}"] >= $neededSkus[$mpn])
			{
				$orders[$siteID][$mpn] = $neededSkus[$mpn];
                $inventory[$mpn]["site_${siteID}"] -= $neededSkus[$mpn];
				$ordered = true;
				break;
			}
		}
	}
	
	// or just pick the first one that has this item available
	
	if (!$ordered)
	{
		foreach (SsfUtils::$siteIDs as $siteID => $siteName)
		{
			if (empty($inventory[$mpn]["site_${siteID}"]))
				continue;

			if (in_array($siteID, SsfUtils::$noBulk))
				continue;

			if ($inventory[$mpn]["site_${siteID}"] >= $neededSkus[$mpn])
			{
				$orders[$siteID][$mpn] = $neededSkus[$mpn];
                $inventory[$mpn]["site_${siteID}"] -= $neededSkus[$mpn];
				$ordered = true;
				break;
			}
		}
	}
}

$oosExtras = array();
$oosSales = array();

// try to save some out of stock orders, at least partially order them

foreach ($extraIds as $extraId => $parts)
{
    foreach ($extraComponents[$extraId] as $mpn => $qty)
    {
        // not included in out of stock condition
        if (!in_array($mpn, $outOfStock))
            continue;

        UpdateTotals();
        asort($totals);

        $ordered = false;

        foreach ($totals as $siteID => $total)
        {
            if ($total == 0)
                continue;

            if (empty($inventory[$mpn]["site_${siteID}"]))
                continue;

			if (in_array($siteID, SsfUtils::$noBulk))
				continue;

            if ($inventory[$mpn]["site_${siteID}"] >= $qty)
            {
                //echo "saved: $mpn, extraId: $extraId \n";
                $orders[$siteID][$mpn] += $qty;
                $inventory[$mpn]["site_${siteID}"] -= $qty;
                $ordered = true;
                break;
            }
        }

        if (!$ordered)
        {
            //echo "cant save: $mpn, extraId: $extraId \n";
            $oosExtras[] = $extraId;
            SetExtraRemarks($extraId, "OUT OF STOCK");
            unset($extraIds[$extraId]);
            unset($extraComponents[$extraId]);
            break;
        }
    }
}

foreach ($salesIds as $index => $salesId)
{
    foreach ($components[$salesId] as $mpn => $qty)
    {
        // not included in out of stock condition
        if (!in_array($mpn, $outOfStock))
            continue;

        UpdateTotals();
        asort($totals);

        $ordered = false;

        foreach ($totals as $siteID => $total)
        {
            if ($total == 0)
                continue;

            if (empty($inventory[$mpn]["site_${siteID}"]))
                continue;

			if (in_array($siteID, SsfUtils::$noBulk))
				continue;

            if ($inventory[$mpn]["site_${siteID}"] >= $qty)
            {
                //echo "saved: $mpn, salesId: $salesId \n";
                $orders[$siteID][$mpn] += $qty;
                $inventory[$mpn]["site_${siteID}"] -= $qty;
                $ordered = true;
                break;
            }
        }

        if (!$ordered)
        {
            //echo "cant save: $mpn, salesId: $salesId \n";
            $oosSales[] = $salesId;
			mysql_query(query("UPDATE sales SET fulfilment = 3, status = 99 WHERE id = %d", $salesId));
			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, "W2: Out of stock for $mpn"));
            unset($salesIds[$index]);
            unset($components[$salesId]);
            break;
        }
    }
}

UpdateTotals();

echo "Processing " . count($orders) . " orders for bulk ordering from W2...\n";
while (@ob_end_flush());

echo "\n---------------\nTOTALS\n---------------\n";
print_r($totals);

echo "\n---------------\nORDERS\n---------------\n";
print_r($orders);

echo "\n---------------\nOUT OF STOCK\n---------------\n";
print_r($outOfStock);

//echo "\n---------------\nCOMPONENTS\n---------------\n";
//print_r($components);

$row = mysql_fetch_row(mysql_query("SELECT recipient_name, street, city, state, zip, phone, ipo_username, ipo_password FROM pickup_sites WHERE shipping_only = 1"));
$shipName = $row[0];
$shipStreet = $row[1];
$shipCity = $row[2];
$shipState = $row[3];
$shipZip = $row[4];
$shipPhone = $row[5];
$ipoUsername = $row[6];
$ipoPassword = $row[7];

$date = date_create("now", new DateTimeZone('America/New_York'));

foreach (SsfUtils::$siteIDs as $siteID => $siteName)
{
	if (!array_key_exists($siteID, $orders))
		continue;
		
	if (empty($orders[$siteID]))
		continue;

	if (in_array($siteID, SsfUtils::$noBulk))
		continue;

	$temp = explode(',', $siteName);
	$siteShortName = $temp[0];

	unset($curOrders);
	unset($includedSales);
    unset($includedExtras);
	
	foreach ($orders[$siteID] as $sku => $qty)
	{
		unset($curOrder);

		$curOrder['sku'] = $sku;
		$curOrder['site'] = $siteID;
		$curOrder['qty'] = $qty;
		
		$curOrders[] = $curOrder;
		
		foreach ($salesIds as $salesId)
		{
			if (in_array($sku, array_keys($components[$salesId])))
				$includedSales[$salesId] = 1;
		}

        foreach ($extraIds as $extraId => $parts)
        {
            if (in_array($sku, array_keys($extraComponents[$extraId])))
                $includedExtras[$extraId] = 1;
        }
	}

	$recordNum = date_format($date, 'YmdHis') . "_" . $siteID;
	$results = SsfUtils::OrderItems($curOrders, array_keys($orders[$siteID]), $shipName, $shipStreet, $shipCity, $shipState, $shipZip, $shipPhone, $recordNum, SSF_SHIPPING_1DAYAM, '0');

	//echo "\n---------------\nIPO $siteID\n---------------\n";
	//print_r($curOrders);
	
	//echo "\n---------------\nRESULTS $siteID\n---------------\n";
	//print_r($results);
	
	//echo "\n---------------\nSALES $siteID\n---------------\n";
	//print_r($includedSales);

	if ($results['success'] == 1)
	{
		$internalId = $results['message'];

		SaveDirectShipment(0, 2, $internalId, $results['refId'], $results['subtotal'], $results['core'], $results['shipping'], $results['total'], null, true, false);
        echo "Done ordering from W2: " . $results['message'] . "\n";
        while (@ob_end_flush());

		foreach ($includedSales as $salesId => $x)
		{
			mysql_query(query("INSERT IGNORE INTO direct_shipments_sales (order_id, sales_id) VALUES ('%s', '%s')", $internalId, $salesId));
            if (!array_key_exists($salesId, $oosSales)) {
				mysql_query(query("UPDATE sales SET fulfilment = 3, status = 2 WHERE id = %d", $salesId));
				mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 0)", $salesId, 'Included in W2 order #' . $internalId));
			}
		}

        if (!empty($includedExtras))
        {
            foreach ($includedExtras as $extraId => $x)
            {
                $res = mysql_query(query("SELECT s.id FROM sales s, extra_orders e WHERE e.remarks = s.record_num AND e.id = %d ORDER BY s.id DESC LIMIT 1", $extraId));
                $row = mysql_fetch_row($res);
                if (!empty($row) && !empty($row[0])) {
					mysql_query(query("INSERT IGNORE INTO direct_shipments_sales (order_id, sales_id) VALUES ('%s', '%s')", $internalId, $row[0]));
					mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 0)", $row[0], 'Included in W2 order #' . $internalId));
				}

                if (!array_key_exists($extraId, $oosExtras))
                    SetExtraRemarks($extraId, $internalId);
            }
        }
	}
	else
	{
        echo "Error while ordering from W2: " . $results['message'] . "\n";
        while (@ob_end_flush());

		SendAdminEmail("Error fulfilling ${siteShortName} bulk order", $results['message'], false);
		
		foreach ($includedSales as $salesId => $x)
		{
			mysql_query(query("UPDATE sales SET fulfilment = 3, status = 99 WHERE id = %d", $salesId));
			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, "W2: Error while placing bulk order from ${siteShortName}: " . $results['message'] . "."));
		}

        foreach ($includedExtras as $extraId => $x)
        {
            SetExtraRemarks($extraId, "ERROR");
        }
	}
}


function SetExtraRemarks($extraId, $orderId)
{
    mysql_query(sprintf("UPDATE extra_orders SET order_id = '%s' WHERE id = %d", cleanup($orderId), cleanup($extraId)));
}

function UpdateTotals()
{
	global $totals;
	global $orders;
	global $inventory;

	$totals = array();
	
	if (empty($orders))
		return;

	foreach ($orders as $siteID => $items)
	{
		$total = 0;

		foreach ($items as $mpn => $qty)
			$total += $inventory[$mpn]['price'] * $qty;

		if ($total > 0)
			$totals[$siteID] = $total;
	}
	
	arsort($totals);
}
