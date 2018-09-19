<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/imc_utils.php');

set_time_limit(0);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOQ
SELECT e.id, e.mpn, e.quantity, (SELECT s.id FROM eoc.sales s WHERE s.record_num = e.remarks ORDER BY id DESC LIMIT 1) AS sales_id
FROM eoc.extra_orders e
WHERE e.supplier = 1
AND (e.order_id IS NULL OR e.order_id = '')
EOQ;

$res = mysql_query($q);
echo "---c---\n";

echo mysql_error();
$extraOrders = [];

while ($row = mysql_fetch_row($res))
{

}
