<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$user = Login('sales');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db('integra_prod');

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=w4.csv");
header("Pragma: no-cache");
header("Expires: 0");

echo "SKU,MPN,ItemID,Quantity,Timestamp\r\n";

$q = <<<EOQ
SELECT CONCAT('EW', id) AS sku, item_id, mpn, available, timestamp
FROM ebay_scraped_listings
ORDER BY id
EOQ;

$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
{
	$sku = $row[0];
	$itemId = $row[1];
    $mpn = $row[2];
	$quantity = $row[3];
    $timestamp = $row[4];

	echo "${sku},${mpn},${itemId},${quantity},${timestamp}\r\n";
}

mysql_close();

?>
