<?php

require_once('system/config.php');
require_once('system/utils.php');

$salesId = $_REQUEST['sales_id'];
settype($salesId, 'integer');

if (empty($salesId))
	return;

$email = $_REQUEST['email'];

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$row = mysql_fetch_row(mysql_query("SELECT country, tracking_num FROM sales WHERE id = $salesId"));
if (empty($row))
    return;
$country = $row[0];
$oldTracking = $row[1];

$newTracking = $_REQUEST['tracking'];

mysql_query(sprintf("UPDATE eoc.sales SET tracking_num = '%s' WHERE id = ${salesId}",
    mysql_real_escape_string($newTracking)));

mysql_query(query("INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES ('%s', '%s', '%s', 0, 1, 1, 1)", $salesId, $email, "Tracking set to: " . $newTracking . " - USPS"));

if ($country != 'US' && !empty($newTracking))
{
    mysql_query("UPDATE eoc.sales SET status = 4, carrier='USPS' WHERE id = ${salesId}");

    if (empty($oldTracking) && !empty($newTracking)) // only post tracking number if this is the first time
        $s = file_get_contents("http://integra.eocenterprise.com/tracking.php?sales_id=${salesId}");
}

mysql_close();