<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/ssf_utils.php');

set_time_limit(0);
header("Content-Type: text/plain");

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$orders = array();

$res = mysql_query("SELECT record_num, buyer_name, street, city, state, zip, speed, email, phone, store, id, supplier_cost FROM sales WHERE fulfilment = 1 AND status = 1");
		
while ($row = mysql_fetch_row($res))
{
	$order['recordNum'] = $row[0];
	$order['name'] = $row[1];
	$order['address'] = $row[2];
	$order['city'] = $row[3];
	$order['state'] = convert_state(preg_replace('/[^a-zA-Z0-9 ]/s', '', $row[4]), 'abbrev');
	$order['zip'] = $row[5];
	$order['speed'] = $row[6];
	$order['email'] = $row[7];
	$order['phone'] = $row[8];
	$order['store'] = $row[9];
    $order['supplier_cost'] = $row[11];
	
	$orders[$row[10]] = $order;
}

$s = 0;

unset($order);

foreach ($orders as $salesId => $order)
{
	$supplierId = CheckOrderSuppliers($salesId, true);
	
	if ($supplierId < 1)
		continue;
		
	unset($items);
	$items = [];

	$res = mysql_query("SELECT sku, quantity FROM sales_items WHERE sales_id = ${salesId}");
	while ($row = mysql_fetch_row($res))
		$items[$row[0]] = $row[1];
		
	$parts = GetSKUParts($items);
	
	// ESI
	if ($supplierId == 3) continue;
	// IMC
	else if ($supplierId == 1) continue;
	// SSF
	else if ($supplierId == 2)
	{
		/*
		if ($order['supplier_cost'] >= SSF_AUTOEOC && $order['supplier_cost'] <= SSF_AUTODIRECT) // add filler to reach free shipping
		{
			$res = mysql_query(sprintf(<<<EOQ
SELECT CONCAT(mpn, '.', brand_id) AS mpn, name, unit_price, qty_avail, timestamp, CEIL((%1\$s - %2\$s) / unit_price) AS qty_needed, %2\$s + CEIL((%1\$s - %2\$s) / unit_price) * unit_price AS total
FROM ssf_items
WHERE qty_avail > 50
AND name > ''
AND unit_price < 10
AND unit_price > 0.1
AND inactive = 0
AND core_unit_price = 0
AND CEIL((%1\$s - %2\$s) / unit_price) < 10
ORDER BY 7, timestamp DESC
LIMIT 1
EOQ
					, SSF_AUTODIRECT, $order['supplier_cost']));
			$row = mysql_fetch_row($res);
			$parts[$row[0]] += $row[5];

			echo "Adding " . $row[5] . "x " . $row[0] . " to current subtotal of " . $order['supplier_cost'] . ". Total: " . $row[6] . "\n";
		}
		*/

		$ssfData = SsfUtils::PreSelectItems($parts);
		
		$sites = array();
		$siteOrders = array();
		
		$checkParts = $ssfData['parts'];
		
		foreach ($ssfData['parts'] as $sku => $qty)
		{
			foreach (SsfUtils::$siteIDs as $siteID => $siteName)
			{
				if (!empty($ssfData['cart'][$siteID][$sku]))
				{
					unset($o);
					$o['site'] = $siteID;
					$o['sku'] = $sku;
					$o['qty'] = $ssfData['cart'][$siteID][$sku];
					$siteOrders[] = $o;
				}

				if ($ssfData['cart'][$siteID][$sku] == $qty)
				{
					$sites[$siteID] = 1;
					unset($checkParts[$sku]);
					break;
				}
			}
		}
		
		if (count($checkParts) > 0)
		{
			mysql_query(sprintf("UPDATE sales SET status = 99 WHERE id = '%s'", $salesId));
			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, 'W2: Out of stock'));
			continue;
		}
		
		$siteCount = count($sites);
		
		if ($siteCount == 0)
		{
			mysql_query(sprintf("UPDATE sales SET status = 99 WHERE id = '%s'", $salesId));
			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, 'W2: Out of stock'));
			continue;
		}
		else if ($siteCount > 1)
		{
			mysql_query(sprintf("UPDATE sales SET status = 99 WHERE id = '%s'", $salesId));
			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, 'Multiple warehouse order'));
			continue;
		}
		else if ($siteCount == 1)
		{
			$shipping = SsfUtils::ConvertShipping($order['speed']);
			$results = SsfUtils::OrderItems($siteOrders, $parts, $order['name'], $order['address'], $order['city'], $order['state'], $order['zip'], $order['phone'], $order['recordNum'], $shipping, '1');

			if ($results['success'] == 1)
			{
				mysql_query("UPDATE sales SET status = 2 WHERE id = ${salesId}");
				SaveDirectShipment($salesId, 2, $results['message'], $results['refId'], $results['subtotal'], $results['core'], $results['shipping'], $results['total']);
				mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, 'Direct W2 order #' . $results['message']));
				$s++;
                echo "W2: " . $results['message'] . "\n";
                while (@ob_end_flush());
			}
			else
			{
				mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, 'W2: ' . $results['message']));
			}
		}
	}
}

echo "Done! $s were successfully placed without any errors.\n";
while (@ob_end_flush());