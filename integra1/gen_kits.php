<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$user = Login('translate_sku');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=kits.csv");
header("Pragma: no-cache");
header("Expires: 0");

echo "Kit SKU,Component MPN,Component Name,Quantity,Direct Unit Price,Warehouse Unit Price\r\n";

$q = <<<EOQ
SELECT kit_sku, component_mpn, REPLACE(component_name, ',', ' ') AS component_name, quantity, ds_unit_price, export_unit_price
FROM eoc.v_kit_cost
EOQ;

$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
{
	echo implode(',', $row) . "\r\n";
}

mysql_close();

?>
