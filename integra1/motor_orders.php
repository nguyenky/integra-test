<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$user = Login('sales');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=motor_orders.csv");
header("Pragma: no-cache");
header("Expires: 0");

echo "Quantity,SKU,Description,Unit Price,Record #,Date,Status,Weight\r\n";

$statusCodes = array(
		0 => 'Unspecified',
		1 => 'Scheduled',
		2 => 'Item Ordered / Waiting',
		3 => 'Ready for Dispatch',
		4 => 'Order Complete',
		90 => 'Cancelled',
		91 => 'Payment Pending',
		92 => 'Return Pending',
		93 => 'Return Complete',
		94 => 'Refund Pending',
		99 => 'Error',
);

$q = <<<EOQ
select quantity, sku, CONCAT('"', REPLACE(si.description, '"', '""'), '"'), si.unit_price, record_num, order_date, status, (select pounds + (ounces / 16) from stamps_preset sp where sp.sku = si.sku limit 1) as weight
from eoc.sales_items si, eoc.sales s
where s.id = si.sales_id
and (sku like 'WP%' or sku like 'PU%' or sku like 'TR%' or sku like 'PSK%' or sku like 'PSD%' or sku like 'PST%')
and order_date >= date_sub(curdate(), interval 7 day)
order by order_date
EOQ;

$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
{
	$row[6] = $statusCodes[$row[6]];
	echo implode(',', $row) . "\r\n";
}

mysql_close();

?>
