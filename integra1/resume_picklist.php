<?php

require_once('system/config.php');
require_once('system/utils.php');

$orderId = $_REQUEST['id'];
settype($orderId, 'integer');

if (empty($orderId))
	return;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOQ
SELECT dss.sales_id
FROM direct_shipments ds, direct_shipments_sales dss
WHERE ds.order_id = '{$orderId}'
AND dss.order_id = ds.order_id
AND ds.is_delivered = 0
AND dss.sales_id NOT IN (SELECT s.id FROM sales s, extra_orders e WHERE s.record_num = e.remarks AND e.order_id = '{$orderId}')
EOQ;

$salesIds = [];
$items = [];

$rows = mysql_query($q);
while ($row = mysql_fetch_row($rows))
    $salesIds[] = $row[0];

foreach ($salesIds as $salesId)
{
    $parts = GetOrderComponents($salesId);
    $partial = false;

    foreach ($parts as $sku => $qty)
    {
        $eocStock = GetEOCStock($sku);
        if ($eocStock < $qty)
        {
            $partial = true;
            break;
        }

        $items[$sku]['quantity'] += $qty;
        $items[$sku]['sales'][] = $salesId;
    }

    if ($partial)
    {
        mysql_query(sprintf("UPDATE sales SET status = 99 WHERE id = %d", $salesId));
        mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 0)", $salesId, 'Some of the items are no longer available from the warehouse'));
    }
    else
    {
        $salesIncluded[] = $salesId;
    }
}

// extra orders
$q = <<<EOQ
SELECT e.id, e.mpn, e.quantity, (SELECT s.id FROM sales s WHERE s.record_num = e.remarks ORDER BY id DESC LIMIT 1) AS sales_id
FROM extra_orders e, direct_shipments ds
WHERE e.supplier = 5
AND ds.is_delivered = 0
AND ds.order_id = e.order_id
AND e.order_id = '{$orderId}'
EOQ;

$extrasIncluded = [];
$res = mysql_query($q);
while ($row = mysql_fetch_row($res))
{
    $extraId = $row[0];
    $sku = trim($row[1]);
    $qty = $row[2];
    $salesId = $row[3];

    $eocStock = GetEOCStock($sku);
    if ($eocStock < $qty)
    {
        //echo "UPDATE extra_orders SET order_id = 'OUT OF STOCK' WHERE id = {$extraId}\n";
        mysql_query("UPDATE extra_orders SET order_id = 'OUT OF STOCK' WHERE id = {$extraId}");
        continue;
    }

    $items[$sku]['quantity'] += $qty;
    $items[$sku]['sales'][] = $salesId;

    $salesIncluded[] = $salesId;
    $extrasIncluded[] = $extraId;
}

if (empty($salesIncluded))
{
    $ret = ['number' => null, 'items' => []];
}
else
{
    $ret = ['number' => $orderId, 'items' => $items];
}

mysql_close();

echo json_encode($ret);