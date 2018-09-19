<?php

require_once('system/config.php');
require_once('system/utils.php');

$orderId = $_REQUEST['id'];

if (empty($orderId) || !ctype_digit($orderId))
{
    echo '0';
    return;
}

// add 0 prefix for IMC
if ((stripos($query, '2') === 0) && (strlen($query) == 9))
    $query = '0' . $query;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

mysql_query("UPDATE sales s, direct_shipments_sales dss SET s.status = 3 WHERE s.id = dss.sales_id AND s.status < 4 AND fulfilment = 3 AND dss.order_id = ${orderId}");
$rows = mysql_affected_rows();

echo $rows;

mysql_close();
