<?php

error_reporting(8183);

require_once('system/config.php');
require_once('system/utils.php');

$orderId = $_POST['sales_id'];
$email = $_POST['email'];
$remarks = $_POST['remarks'];
$markError = $_POST['mark_error'];
$showSales = $_POST['show_sales'];
$showData = $_POST['show_data'];
$showPricing = $_POST['show_pricing'];
$showShipping = $_POST['show_shipping'];

settype($orderId, 'integer');
if (empty($orderId))
	return;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

if ($markError)
{
    mysql_query("UPDATE eoc.sales SET status = 99 WHERE id = {$orderId}");
    mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '%s', '%s', 0, 1, 1, 1)", $orderId, $email, "Status set to: Error"));
}

$date = date_create("now", new DateTimeZone('America/New_York'));
$ts = date_format($date, 'Y-m-d H:i:s');

mysql_query(query("INSERT INTO integra_prod.order_history (ts, order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '%s', '%s', '%s', %d, %d, %d, %d)",
    $ts, $orderId, $email, $remarks,
    $showSales ? 0 : 1,
    $showData ? 0 : 1,
    $showPricing ? 0 : 1,
    $showShipping ? 0 : 1));

mysql_close();

echo '<tr><td>' . $ts . '</td><td>' . str_replace('@eocenterprise.com', '', $email) . '</td><td class="wrap">' . nl2br($remarks) . '</td></tr>';