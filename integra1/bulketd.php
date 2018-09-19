<?php

require_once('system/config.php');
require_once('system/utils.php');

$orderId = $_REQUEST['id'];

if (empty($orderId) || !ctype_digit($orderId))
{
    echo '0';
    return;
}

$etd = strtotime($_REQUEST['etd']);

if ($etd < strtotime("-1 month"))
{
    echo '0';
    return;
}

// add 0 prefix for IMC
if ((stripos($query, '2') === 0) && (strlen($query) == 9))
    $query = '0' . $query;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

mysql_query(query("UPDATE sales s, direct_shipments_sales dss SET s.etd = '%s' WHERE s.id = dss.sales_id AND dss.order_id = '%s'", date('Y-m-d', $etd), $orderId));
$rows = mysql_affected_rows();

echo $rows;

mysql_close();
