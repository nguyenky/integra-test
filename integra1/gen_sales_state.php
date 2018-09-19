<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$user = Login('sales');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=sales_state.csv");
header("Pragma: no-cache");
header("Expires: 0");

echo "PO,State,Country\r\n";

$q = <<<EOQ
SELECT record_num, state, country
FROM sales
WHERE order_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01 00:00:00')
AND order_date < DATE_FORMAT(CURDATE(), '%Y-%m-01 00:00:00')
EOQ;

$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
{
	$recordNum = $row[0];
	$state = '"' . $row[1] . '"';
	$country = $row[2];

	echo "${recordNum},${state},${country}\r\n";
}

mysql_close();

?>
