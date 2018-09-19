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
        mysql_query("UPDATE sales SET remarks = TRIM(CONCAT('Some of the items are no longer available from the warehouse.', TRIM(REPLACE(remarks, 'On stock in EOC WH.', '')))), status = 99 WHERE id = ${salesId}");
    }
    else
    {
        $salesIncluded[] = $salesId;
    }
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
        mysql_query("UPDATE sales SET status = 3 WHERE id = {$salesId}");
    }

    $ret = ['number' => $invoiceNum, 'items' => $items];
}

mysql_close();

echo json_encode($ret);