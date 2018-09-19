<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/imc_utils.php');

date_default_timezone_set("America/New_York");

set_time_limit(0);
header("Content-Type: text/plain");

$dates = " AND 1 = 0 ";

if (!empty($_REQUEST['from']) && !empty($_REQUEST['to']))
{
	$from = date('Y-m-d', strtotime($_REQUEST['from']));
	$to = strtotime($_REQUEST['to']);
}
else
{
	echo "No dates were provided\n";
	while (@ob_end_flush());
	return;
}

if ($from != $_REQUEST['from'] || date('Y-m-d', $to) != $_REQUEST['to'])
{
	echo "Invalid dates were provided\n";
	while (@ob_end_flush());
	return;
}

$to = date('Y-m-d', strtotime("+1 day", $to));
$dates = " AND order_date >= '{$from}' AND order_date < '{$to}' ";

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$orders = array();

$res = mysql_query("SELECT record_num, buyer_name, street, city, state, zip, speed, email, phone, store, id, supplier_cost FROM sales WHERE fulfilment = 1 AND status = 1 {$dates}");
		
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
	// SSF
	else if ($supplierId == 2) continue;
	// IMC
	else if ($supplierId == 1)
	{
        if ($order['supplier_cost'] >= IMC_AUTOEOC && $order['supplier_cost'] <= IMC_AUTODIRECT) // add filler to reach free shipping
		{
			$res = mysql_query(sprintf(<<<EOQ
SELECT ii.mpn, ii.name, ii.unit_price, iq.qty, LEAST(ii.timestamp, iq.timestamp) AS timestamp, CEIL((%1\$s - %2\$s) / ii.unit_price) AS qty_needed, %2\$s + CEIL((%1\$s - %2\$s) / ii.unit_price) * ii.unit_price AS total
FROM imc_items ii, imc_qty iq
WHERE ii.mpn = iq.mpn
AND iq.qty > 50
AND ii.name > ''
AND ii.unit_price < 10
AND ii.unit_price > 0.1
AND ii.inactive = 0
AND ii.core_price = 0
AND CEIL((%1\$s - %2\$s) / ii.unit_price) < 10
ORDER BY 7, ii.timestamp DESC
LIMIT 1
EOQ
			, IMC_AUTODIRECT, $order['supplier_cost']));
			$row = mysql_fetch_row($res);
			$parts[$row[0]] += $row[5];

			$msg = "Adding " . $row[5] . "x " . $row[0] . " to current subtotal of " . $order['supplier_cost'] . ". Total: " . $row[6] . "\n";
			echo $msg;
		}

		$imcData = ImcUtils::PreSelectItems($parts, $order['state'], $order['zip'], true);
		
		$sites = array();
		
		$checkParts = $imcData['parts'];
		
		foreach ($imcData['parts'] as $sku => $qty)
		{
			foreach (ImcUtils::$siteIDs as $siteID => $siteName)
			{
				if ($imcData['cart'][$siteID][$sku] == $qty)
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
			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, 'W1: Out of stock'));
			continue;
		}
		
		$siteCount = count($sites);
		
		if ($siteCount == 0)
		{
			mysql_query(sprintf("UPDATE sales SET status = 99 WHERE id = '%s'", $salesId));
			mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, 'W1: Out of stock'));
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
			reset($sites);
			$siteID = key($sites);
		
			$shipping = ImcUtils::ConvertShipping($order['speed']);
			$results = ImcUtils::OrderItems($siteID, $parts, $order['name'], $order['address'], $order['city'], $order['state'], $order['zip'], $order['phone'], $order['recordNum'], $shipping);

			if ($results['success'] == 1)
			{
				mysql_query("UPDATE sales SET status = 2 WHERE id = ${salesId}");
				SaveDirectShipment($salesId, 1, $results['message'], null, $results['subtotal'], $results['core'], $results['shipping'], $results['total']);
				mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, 'Direct W1 order #' . $results['message']));

				if ($results['shipping'] > 1 && $results['subtotal'] >= 40)
				{
					file_put_contents(LOGS_DIR . "imc_ipo/${recordNum2}_ts_${guid}.txt", $msg
							. "subtotal in orig order: " . $order['supplier_cost']
							. ". in final order: " . $results['subtotal']
							. ". shipping: " . $results['shipping']
							. "\r\n" . print_r($parts, true));
				}

				$s++;
                echo "W1: " . $results['message'] . "\n";
                while (@ob_end_flush());
			}
			else
			{
				mysql_query(sprintf("UPDATE sales SET status = 99 WHERE id = '%s'", $salesId));
				mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 1)", $salesId, 'W1: ' . $results['message']));
			}
		}
	}
}

echo "Done! $s were successfully placed without any errors.\n";
while (@ob_end_flush());