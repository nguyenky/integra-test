<?php

require_once('system/config.php');
require_once('system/utils.php');

$warehouseId = $_REQUEST['warehouse_id'];
settype($warehouseId, 'integer');

if (empty($warehouseId))
	return;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

// TODO: use warehouse_id to filter further.

$q = <<<EOQ
SELECT id
FROM sales s
WHERE supplier = 5
AND status = 1
AND fulfilment = 3
ORDER BY order_date
LIMIT 20
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
FROM extra_orders e
WHERE e.supplier = 5
AND (e.order_id IS NULL OR e.order_id = '')
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
    $date = date_create("now", new DateTimeZone('America/New_York'));
    $invoiceNum = substr(time(), -8);
    SaveDirectShipment(0, 5, $invoiceNum, null, 0, 0, 0, 0);

    foreach ($salesIncluded as $salesId)
    {
        mysql_query(sprintf("INSERT IGNORE INTO direct_shipments_sales (order_id, sales_id) VALUES ('%s', '%s')",
            cleanup($invoiceNum), $salesId));
        mysql_query("UPDATE sales SET status = 2 WHERE id = {$salesId}");
    }

    foreach ($extrasIncluded as $extraId)
    {
        mysql_query(sprintf("UPDATE extra_orders SET order_id = '%s' WHERE id = {$extraId}", cleanup($invoiceNum)));
    }

    $ret = ['number' => $invoiceNum, 'items' => $items];
}

mysql_close();

echo json_encode($ret);