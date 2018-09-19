<?php

require_once('system/config.php');
require_once('system/utils.php');

$salesId = $_REQUEST['sales_id'];
settype($salesId, 'integer');

$orderId = $_REQUEST['order_id'];

$supplier = $_REQUEST['supplier'];
settype($supplier, 'integer');

if (empty($salesId))
	return;
	
if (empty($orderId))
	return;
	
if (empty($supplier))
	return;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_query(query(
	"INSERT IGNORE INTO direct_shipments_sales (sales_id, order_id) VALUES ('%s', '%s')",
	$salesId, $orderId));
mysql_query(query(
	"INSERT IGNORE INTO direct_shipments (sales_id, order_id, supplier, is_bulk) VALUES ('%s', '%s', '%s', 1)",
	$salesId, $orderId, $supplier));
mysql_close();

echo '1';