<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$user = Login();

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=direct_shipments.csv");
header("Pragma: no-cache");
header("Expires: 0");

echo "Record #,Agent,Warehouse #,Order #,Date Ordered,Subtotal,Core,Shipping,Total\r\n";

$q = <<<EOQ
SELECT s.record_num, s.agent, d.supplier, d.order_id, d.order_date, d.subtotal, d.core, d.shipping, d.total, d.tracking_num
FROM direct_shipments d, direct_shipments_sales dss, sales s
WHERE d.order_date IS NOT NULL
AND d.order_id = dss.order_id
AND dss.sales_id = s.id
ORDER BY d.order_date DESC
EOQ;

$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
{
	$recordNum = $row[0];
	$agent = $row[1];
	$supplier = $row[2];
	$orderId = $row[3];
	$orderDate = $row[4];
	$subtotal = $row[5];
	$core = $row[6];
	$shipping = $row[7];
	$total = $row[8];
	$tracking = $row[9];

	echo "${recordNum},${agent},${supplier},${orderId},${orderDate},${subtotal},${core},${shipping},${total},${tracking}\r\n";
}

mysql_close();

?>
