<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/imc_utils.php');

set_time_limit(0);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$salesIds = array();
$components = array();
$querySkus = array();
$miamiAvail = array();
$miamiOrders = array();
$miamiSales = array();
$miamiExtras = array();
$pompanoAvail = array();
$pompanoOrders = array();
$pompanoSales = array();
$pompanoExtras = array();
$baltiAvail = array();
$baltiOrders = array();
$baltiSales = array();
$baltiExtras = array();
$prices = array();

$useIpo = array();
$componentsIpo = array();
$salesIdsIpo = array();
$querySkusIpo = array();
$extraIpo = array();

$res = mysql_query("SELECT mpn FROM eoc.imc_use_ipo");
echo mysql_error();
while ($row = mysql_fetch_row($res))
    $useIpo[] = $row[0];

$q = <<<EOQ
SELECT s.id, s.total, SUM(si.quantity)
FROM eoc.sales s, eoc.sales_items si
WHERE s.id = si.sales_id
AND s.fulfilment = 3
AND s.status = 1
GROUP BY s.id, s.total
ORDER BY 3 DESC, 2 DESC
EOQ;

$res = mysql_query($q);
echo mysql_error();
while ($row = mysql_fetch_row($res))
	$salesIds[] = $row[0];

foreach ($salesIds as $index => $salesId)
{
	$supplierId = CheckOrderSuppliers($salesId);
	
	// pure IMC
	if ($supplierId == 1)
	{
		$parts = GetOrderComponents($salesId);
		
		foreach ($parts as $sku => $qty)
		{
			if ($sku == IMC_FILLERITEM)
				continue;

			$components[$salesId][startsWith($sku, 'EOC') ? substr($sku, 3) : $sku] = $qty;
			$querySkus[$sku] = 1;
		}
	}
	else
	{
		//SetRemarks($salesId, 99, 'For now, only IMC orders are supported for automatic processing for EOC fulfilment.');
		unset($salesIds[$index]);
	}
}

foreach ($components as $salesId => $items)
{
    foreach ($items as $mpn => $qty)
    {
        // always order these via IPO
        if (in_array($mpn, $useIpo))
        {
            if (!in_array($salesId, $salesIdsIpo))
                $salesIdsIpo[] = $salesId;

            if (!isset($componentsIpo[$salesId]))
                $componentsIpo[$salesId] = [];

            if (!isset($componentsIpo[$salesId][$mpn]))
                $componentsIpo[$salesId][$mpn] = 0;

            $componentsIpo[$salesId][$mpn] += $qty;
            $querySkusIpo[$mpn] = 1;
        }
        else {
            mysql_query(query("INSERT INTO eoc.imc_order_queue (sales_id, mpn, qty) VALUES ('%s', '%s', '%s')", $salesId, $mpn, $qty));
            echo mysql_error();
        }
    }
}

$components = $componentsIpo;
$salesIds = $salesIdsIpo;
$querySkus = $querySkusIpo;

$extraOrders = [];

/*

// extra orders
$q = <<<EOQ
SELECT e.id, e.mpn, e.quantity, (SELECT s.id FROM eoc.sales s WHERE s.record_num = e.remarks ORDER BY id DESC LIMIT 1) AS sales_id
FROM eoc.extra_orders e
WHERE e.supplier = 1
AND (e.order_id IS NULL OR e.order_id = '')
EOQ;

$res = mysql_query($q);
echo mysql_error();

while ($row = mysql_fetch_row($res))
{
    $sku = (startsWith($row[1], 'EOC') ? substr($row[1], 3) : $row[1]);

    $extraOrders[] = ['extra_id' => $row[0],
        'sku' => $sku,
        'qty' => $row[2],
        'sales_id' => $row[3]];

    $querySkus[$sku] = 1;
}

*/

foreach ($extraOrders as $extra)
{
    // always order these via IPO
    if (in_array($extra['sku'], $useIpo))
    {
        $extraIpo[] = $extra;
    }
    else {
        mysql_query(query("INSERT INTO eoc.imc_order_queue (extra_id, sales_id, mpn, qty) VALUES ('%s', '%s', '%s', '%s')", $extra['extra_id'], $extra['sales_id'], $extra['sku'], $extra['qty']));
        echo mysql_error();
    }
}

$extraOrders = $extraIpo;

//SendAdminEmail('W1 Orders', print_r($components, true), false, 'kbcware@yahoo.com,eduardo@eocenterprise.com,karla@eocenterprise.com');
//exit; //temp


/*
$salesIds[] = 0;

$components[0]['12631279720'] += 1;
$querySkus['12631279720'] += 1;

//*/

//print_r($components);

$queryItems = ImcUtils::QueryItems(array_keys($querySkus));

foreach ($queryItems as $item)
{
	$miamiAvail[$item['sku']] = $item['site_15'];
	$pompanoAvail[$item['sku']] = $item['site_8'];
    $baltiAvail[$item['sku']] = $item['site_7'];
    $prices[$item['sku']] = $item['price'];
}

$baltiTotal = 0;

foreach ($salesIds as $index => $salesId)
{
	$allAvail = true;
	
	foreach ($components[$salesId] as $sku => $qty)
	{
		$totalAvail = $miamiAvail[$sku] + $pompanoAvail[$sku] + $baltiAvail[$sku];
		if ($totalAvail < $qty)
		{
			$allAvail = false;
			break;
		}
	}
	
	if (!$allAvail)
	{
		SetRemarks($salesId, 99, "Insufficient stock for $sku in Miami/Pompano/Baltimore.");
		continue;
	}
	
	foreach ($components[$salesId] as $sku => $qty)
	{
		$needed = $qty;

		if ($miamiAvail[$sku] != 0)
		{
			$take = min($needed, $miamiAvail[$sku]);
			$needed -= $take;
			$miamiAvail[$sku] -= $take;
            if (!isset($miamiOrders[$sku])) $miamiOrders[$sku] = 0;
			$miamiOrders[$sku] += $take;
			$miamiSales[$salesId] = 1;
		}
		
		if ($needed > 0 && $pompanoAvail[$sku] != 0)
		{
			$take = min($needed, $pompanoAvail[$sku]);
			$needed -= $take;
			$pompanoAvail[$sku] -= $take;
            if (!isset($pompanoOrders[$sku])) $pompanoOrders[$sku] = 0;
			$pompanoOrders[$sku] += $take;
			$pompanoSales[$salesId] = 1;
		}

        if ($needed > 0 && $baltiAvail[$sku] != 0)
        {
            $take = min($needed, $baltiAvail[$sku]);
            $needed -= $take;
            $baltiAvail[$sku] -= $take;
            if (!isset($baltiOrders[$sku])) $baltiOrders[$sku] = 0;
            $baltiOrders[$sku] += $take;
            $baltiSales[$salesId] = 1;
            $baltiTotal += ($prices[$sku] * $take);
        }
	}
}


foreach ($extraOrders as $eo)
{
    $sku = $eo['sku'];
    $qty = $eo['qty'];
    $salesId = $eo['sales_id'];
    $extraId = $eo['extra_id'];

    $totalAvail = $miamiAvail[$sku] + $pompanoAvail[$sku] + $baltiAvail[$sku];
    if ($totalAvail < $qty)
    {
        SetRemarks($salesId, 99, "Insufficient stock for $sku in Miami/Pompano/Baltimore.");
        SetExtraRemarks($eo['extra_id'], 'OUT OF STOCK');
        continue;
    }

    $needed = $qty;

    if ($miamiAvail[$sku] != 0)
    {
        $take = min($needed, $miamiAvail[$sku]);
        $needed -= $take;
        $miamiAvail[$sku] -= $take;
        $miamiOrders[$sku] += $take;
        $miamiSales[$salesId] = 1;
        $miamiExtras[$extraId] = 1;
    }

    if ($needed > 0 && $pompanoAvail[$sku] != 0)
    {
        $take = min($needed, $pompanoAvail[$sku]);
        $needed -= $take;
        $pompanoAvail[$sku] -= $take;
        $pompanoOrders[$sku] += $take;
        $pompanoSales[$salesId] = 1;
        $pompanoExtras[$extraId] = 1;
    }

    if ($needed > 0 && $baltiAvail[$sku] != 0)
    {
        $take = min($needed, $baltiAvail[$sku]);
        $needed -= $take;
        $baltiAvail[$sku] -= $take;
        $baltiOrders[$sku] += $take;
        $baltiSales[$salesId] = 1;
        $baltiExtras[$extraId] = 1;
        $baltiTotal += ($prices[$sku] * $take);
    }
}

echo "Miami orders:\n";
print_r($miamiOrders);

echo "Pompano orders:\n";
print_r($pompanoOrders);

echo "Baltimore orders:\n";
print_r($baltiOrders);

echo "Miami sales:\n";
print_r($miamiSales);

echo "Pompano sales:\n";
print_r($pompanoSales);

echo "Baltimore sales:\n";
print_r($baltiSales);

//echo "Baltimore total: $baltiTotal\n";

//exit
$res = mysql_query("SELECT recipient_name, street, city, state, zip, phone, ipo_username, ipo_password FROM eoc.pickup_sites WHERE shipping_only = 1");
echo mysql_error();
$row = mysql_fetch_row($res);
$shipName = $row[0];
$shipStreet = $row[1];
$shipCity = $row[2];
$shipState = $row[3];
$shipZip = $row[4];
$shipPhone = $row[5];
$ipoUsername = $row[6];
$ipoPassword = $row[7];

$date = date_create("now", new DateTimeZone('America/New_York'));

echo "Processing " . count($miamiSales) . " orders for truck ordering from W1 Miami...\n";
while (@ob_end_flush());

// MIAMI ORDERS
if (!empty($miamiOrders))
{
	$recordNum = date_format($date, 'YmdHi') . "_miami";
	$results = ImcUtils::OrderItems(15, $miamiOrders, $shipName, $shipStreet, $shipCity, $shipState, $shipZip, $shipPhone, $recordNum, "OUR TRUCK", $ipoUsername, $ipoPassword);

	if ($results['success'] == 1)
	{
		SaveDirectShipment(0, 1, $results['message'], null, $results['subtotal'], $results['core'], $results['shipping'], $results['total'], null, true, true);
        echo "Done ordering from W1 Miami: " . $results['message'] . "\n";
        while (@ob_end_flush());

		foreach ($miamiSales as $salesId => $x)
		{
			mysql_query(sprintf("INSERT IGNORE INTO eoc.direct_shipments_sales (order_id, sales_id) VALUES ('%s', '%s')",
				cleanup($results['message']), $salesId));
            echo mysql_error();

			SetRemarks($salesId, 2, "");
		}

        foreach ($miamiExtras as $extraId => $x)
        {
            SetExtraRemarks($extraId, cleanup($results['message']));
        }
	}
	else
	{
        echo "Error while ordering from Miami -- double check with W1: " . $results['message'] . "\n";
        while (@ob_end_flush());

		SendAdminEmail('Error fulfilling Miami truck order', $results['message'], false);
		
		foreach ($miamiSales as $salesId => $x)
		{
			SetRemarks($salesId, 99, "Error while placing W1 truck order from Miami: " . $results['message'] . ".");
		}

        foreach ($miamiExtras as $extraId => $x)
        {
            SetExtraRemarks($extraId, cleanup($results['message']));
        }
	}
}

echo "Processing " . count($pompanoSales) . " orders for truck ordering from W1 Pompano...\n";
while (@ob_end_flush());

// POMPANO ORDERS
if (!empty($pompanoOrders))
{
	$recordNum = date_format($date, 'YmdHi') . "_pompano";
	$results = ImcUtils::OrderItems(8, $pompanoOrders, $shipName, $shipStreet, $shipCity, $shipState, $shipZip, $shipPhone, $recordNum, "OUR TRUCK", $ipoUsername, $ipoPassword);

	if ($results['success'] == 1)
	{
		SaveDirectShipment(0, 1, $results['message'], null, $results['subtotal'], $results['core'], $results['shipping'], $results['total'], null, true, true);
        echo "Done ordering from W1 Pompano: " . $results['message'] . "\n";
        while (@ob_end_flush());

		foreach ($pompanoSales as $salesId => $x)
		{
			mysql_query(sprintf("INSERT IGNORE INTO eoc.direct_shipments_sales (order_id, sales_id) VALUES ('%s', '%s')",
				cleanup($results['message']), $salesId));
            echo mysql_error();

			SetRemarks($salesId, 2, "");
		}

        foreach ($pompanoExtras as $extraId => $x)
        {
            SetExtraRemarks($extraId, cleanup($results['message']));
        }
	}
	else
	{
        echo "Error while ordering from Pompano -- double check with W1: " . $results['message'] . "\n";
        while (@ob_end_flush());

		SendAdminEmail('Error fulfilling Pompano truck order', $results['message'], false);
		
		foreach ($pompanoSales as $salesId => $x)
		{
			SetRemarks($salesId, 99, "Error while placing W1 truck order from Pompano: " . $results['message'] . ".");
		}

        foreach ($pompanoExtras as $extraId => $x)
        {
            SetExtraRemarks($extraId, cleanup($results['message']));
        }
	}
}

echo "Processing " . count($baltiSales) . " orders for truck ordering from W1 Baltimore...\n";
while (@ob_end_flush());

// BALTIMORE ORDERS
//if (!empty($baltiOrders) && intval(date('H')) > 10)
if (!empty($baltiOrders))
{
    if ($baltiTotal < 50)
    {
        echo "Error while ordering from Baltimore -- order is below $50 (only $baltiTotal)\n";
        while (@ob_end_flush());

        SendAdminEmail('Error fulfilling Baltimore bulk order', 'Order is below $50', false);

        foreach ($baltiSales as $salesId => $x)
        {
            SetRemarks($salesId, 99, "Error while placing W1 bulk order from Baltimore: Order is below $50");
        }

        foreach ($baltiExtras as $extraId => $x)
        {
            SetExtraRemarks($extraId, 'Below $50 for Baltimore');
        }
    }
    else
    {
        $recordNum = date_format($date, 'YmdHi') . "_baltimore";
        $results = ImcUtils::OrderItems(7, $baltiOrders, $shipName, $shipStreet, $shipCity, $shipState, $shipZip, $shipPhone, $recordNum, IMC_SHIPPING_GROUND);

        if ($results['success'] == 1)
        {
            SaveDirectShipment(0, 1, $results['message'], null, $results['subtotal'], $results['core'], $results['shipping'], $results['total'], null, true, false);
            echo "Done ordering from W1 Baltimore: " . $results['message'] . "\n";
            while (@ob_end_flush());

            foreach ($baltiSales as $salesId => $x)
            {
                mysql_query(sprintf("INSERT IGNORE INTO eoc.direct_shipments_sales (order_id, sales_id) VALUES ('%s', '%s')",
                    cleanup($results['message']), $salesId));
                echo mysql_error();

                SetRemarks($salesId, 2, "");
            }

            foreach ($baltiExtras as $extraId => $x)
            {
                SetExtraRemarks($extraId, cleanup($results['message']));
            }
        }
        else
        {
            echo "Error while ordering from Baltimore -- double check with W1: " . $results['message'] . "\n";
            while (@ob_end_flush());

            SendAdminEmail('Error fulfilling Baltimore bulk order', $results['message'], false);

            foreach ($baltiSales as $salesId => $x)
            {
                SetRemarks($salesId, 99, "Error while placing W1 bulk order from Baltimore: " . $results['message'] . ".");
            }

            foreach ($baltiExtras as $extraId => $x)
            {
                SetExtraRemarks($extraId, cleanup($results['message']));
            }
        }
    }
}

function SetRemarks($salesId, $status, $remarks)
{
	mysql_query(sprintf("UPDATE eoc.sales SET fulfilment = 3, status = %d, remarks = TRIM(CONCAT('%s', ' ', remarks)) WHERE id = %d", $status, cleanup($remarks), $salesId));
    echo mysql_error();
}

function SetExtraRemarks($extraId, $orderId)
{
    mysql_query(sprintf("UPDATE eoc.extra_orders SET order_id = '%s' WHERE id = %d", cleanup($orderId), cleanup($extraId)));
    echo mysql_error();
}