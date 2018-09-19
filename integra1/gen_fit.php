<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$user = Login();

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=imcfit.csv");
header("Pragma: no-cache");
header("Expires: 0");

echo "MPN,Make,Model,Year,Position,Fitment Notes,Misc Notes\r\n";

$q = "SELECT mpn, make, model, year, position, fit_notes, misc_notes FROM imc_fitment";
$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
{
	$mpn = $row[0];
	$make = $row[1];
	$model = $row[2];
	$year = $row[3];
	$position = $row[4];
	$fitNotes = $row[5];
	$miscNotes = $row[6];

	echo "${mpn},${make},${model},${year},${position},${fitNotes},${miscNotes}\r\n";
}

mysql_close();

?>
