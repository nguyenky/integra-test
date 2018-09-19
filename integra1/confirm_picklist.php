<?php

require_once('system/config.php');
require_once('system/utils.php');

$json = file_get_contents('php://input');
if (empty($json))
	return;

$list = json_decode($json, true);
if (empty($list['number']) || empty($list['items']))
    return;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$errorSales = [];

foreach ($list['items'] as $item)
{
    // skipped
    if ($item['status'] == -1)
    {
        // go thru linked sales
        foreach ($item['sales'] as $salesId)
        {
            // copy reason
            $errorSales[$salesId][] = $item['reason'];
        }
    }
}

foreach ($errorSales as $salesId => $errors)
{
    $combinedReason = implode('; ', $errors);

    // set to error
    mysql_query(sprintf("UPDATE sales SET status = 99 WHERE id = %d", $salesId));
    mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '', '%s', 1, 1, 1, 0)", $salesId, $combinedReason));

    // remove link
    mysql_query(sprintf("DELETE FROM direct_shipments_sales WHERE order_id = '%s' AND sales_id = %d",
        cleanup($list['number'], true), $salesId));
}

// set non-local pickups to ready for dispatch
mysql_query(sprintf("
UPDATE sales s, direct_shipments ds, direct_shipments_sales dss
SET s.status = 3
WHERE s.id = dss.sales_id
AND dss.order_id = ds.order_id
AND s.pickup_id IS NULL
AND ds.order_id = '%s'", cleanup($list['number'], true)));


// set local pickups to complete
mysql_query(sprintf("
UPDATE sales s, direct_shipments ds, direct_shipments_sales dss
SET s.status = 4
WHERE s.id = dss.sales_id
AND dss.order_id = ds.order_id
AND s.pickup_id IS NOT NULL
AND ds.order_id = '%s'", cleanup($list['number'], true)));

// mark picklist as delivered
mysql_query(sprintf("
UPDATE direct_shipments ds
SET ds.is_delivered = 1
WHERE ds.order_id = '%s'", cleanup($list['number'], true)));

mysql_close();

echo '1';