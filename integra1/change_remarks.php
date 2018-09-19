<?php

require_once('system/config.php');
require_once('system/utils.php');

$salesId = $_REQUEST['sales_id'];
settype($salesId, 'integer');

if (empty($salesId))
	return;

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$row = mysql_fetch_row(mysql_query("SELECT country, tracking_num FROM sales WHERE id = $salesId"));
if (empty($row))
    return;
$country = $row[0];
$oldTracking = $row[1];

$newTracking = $_REQUEST['tracking'];

mysql_query(sprintf("UPDATE sales SET remarks = '%s', tracking_num = '%s' WHERE id = ${salesId}",
    mysql_real_escape_string($_REQUEST['remarks']),
    mysql_real_escape_string($newTracking)));

if (!empty($_REQUEST['error']))
    mysql_query("UPDATE sales SET status = 99 WHERE id = ${salesId}");
else
{
    if ($country != 'US' && !empty($_REQUEST['tracking']))
    {
        mysql_query("UPDATE sales SET status = 4, carrier='USPS' WHERE id = ${salesId}");

        if (empty($oldTracking) && !empty($newTracking)) // only post tracking number if this is the first time
            $s = file_get_contents("http://integra.eocenterprise.com/tracking.php?sales_id=${salesId}");
    }
}

mysql_close();