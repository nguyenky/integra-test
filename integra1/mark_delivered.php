<?php

require_once('system/config.php');

$orderId = $_REQUEST['order_id'];
$delivered = $_REQUEST['delivered'];

if (empty($orderId) || !ctype_digit($orderId) || !ctype_digit($delivered))
	return;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_query("UPDATE direct_shipments SET is_delivered = ${delivered} WHERE order_id = '${orderId}'");
mysql_close();