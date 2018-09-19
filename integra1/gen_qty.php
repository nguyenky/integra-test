<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$user = Login();

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=inventory.csv");
header("Pragma: no-cache");
header("Expires: 0");

echo "Supplier,MPN,SKU,Quantity\r\n";

$q = "SELECT supplier, mpn, sku, qty FROM inventory_map";
$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
{
	$supplier = $row[0];
	$mpn = $row[1];
	$sku = $row[2];
	$qty = $row[3];

	echo "${supplier},${mpn},${sku},${qty}\r\n";
}

mysql_close();

?>
