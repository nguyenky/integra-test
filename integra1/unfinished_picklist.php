<?php

require_once('system/config.php');
require_once('system/utils.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOQ
SELECT ds.order_date, ds.order_id
FROM direct_shipments ds
WHERE ds.is_delivered = 0
AND ds.supplier = 5
ORDER BY ds.order_date DESC
EOQ;

$ids = [];

$rows = mysql_query($q);
while ($row = mysql_fetch_row($rows))
    $ids[] = ['id' => $row[1], 'date' => $row[0]];

mysql_close();
echo json_encode($ids);