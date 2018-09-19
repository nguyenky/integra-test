<?php

require_once('system/config.php');
require_once('system/utils.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=ipo_mpn.txt");
header("Pragma: no-cache");
header("Expires: 0");

$q = "SELECT mpn FROM imc_use_ipo";
$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
{
	$mpn = $row[0];

	echo "${mpn}\r\n";
}

mysql_close();

?>
