<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$user = Login();

$orderId = $_REQUEST['order_id'];

if (empty($orderId))
	exit;

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

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Integra :: Bulk Orders</title>
	<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" /> 
	<style>
		#orders_list
		{
			margin-top: 30px;
			margin-left: 30px;
			margin-right: 30px;
			width: 80% !important;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>

<div id="orders_list">
<h2>Bulk Order Details - <?=$orderId?></h2>
<table class="table table-bordered table-condensed">
	<thead>
		<tr>
			<th>Record #</th>
			<th>Status</th>
			<th>Tracking</th>
			<th>Remarks</th>
		</tr>
	</thead>
	<tbody>
<?
$q = <<<EOQ
SELECT dss.sales_id, o.record_num, o.status, IFNULL(s.tracking_num, o.tracking_num),
	(SELECT oh.remarks
    FROM integra_prod.order_history oh, integra_prod.users u
    WHERE oh.order_id = o.id
    AND u.email = '%s'
    AND NOT (u.group_name = 'Sales' AND oh.hide_sales = 1)
    AND NOT (u.group_name = 'Data' AND oh.hide_data = 1)
    AND NOT (u.group_name = 'Pricing' AND oh.hide_pricing = 1)
    AND NOT (u.group_name = 'Shipping' AND oh.hide_shipping = 1)
    AND oh.remarks > ''
    ORDER BY oh.ts DESC
    LIMIT 1) AS remarks
FROM eoc.direct_shipments ds, eoc.sales o, eoc.direct_shipments_sales dss LEFT JOIN eoc.stamps s ON dss.sales_id = s.sales_id
WHERE ds.order_id = dss.order_id
AND o.fulfilment = 3
AND o.id = dss.sales_id
AND ds.order_id = '%s'
ORDER BY o.record_num ASC
EOQ;

$res = mysql_query(query($q, $user, $orderId));

while ($row = mysql_fetch_row($res))
{
	echo '<tr class="';
	echo (($row[2] != 4 && $row[2] != 90) ? 'danger' : 'success');
	echo '">';

	echo '<td><a target="_blank" href="http://integra2.eocenterprise.com/#/orders/view/' . $row[0] . '">' . htmlentities($row[1]) . '</a></td>';
	echo '<td>' . htmlentities($statusCodes[$row[2]]) . '</td>';
	echo '<td>' . htmlentities($row[3]) . '</td>';
	echo '<td>' . htmlentities($row[4]) . '</td>';

	echo "</tr>\n";
}
?>
	</tbody>
</table>
</div>
</body>
</html>