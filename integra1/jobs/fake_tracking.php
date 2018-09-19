<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');

exit; // disable this

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOQ
SELECT id
FROM sales
WHERE order_date < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 12 HOUR)
AND order_date > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 WEEK)
AND (tracking_num IS NULL OR LENGTH(tracking_num) = 0)
AND status != 90
AND speed != 'Pickup'
AND pickup_id IS NULL
EOQ;

$rows = mysql_query($q);
$salesIds = array();

while ($row = mysql_fetch_row($rows))
	$salesIds[] = $row[0];

foreach ($salesIds as $salesId)
{
	$track = '1Z9XY187039' . rand(1010203, 9989798);

	mysql_query(sprintf("UPDATE sales SET tracking_num = '%s', carrier = 'UPS', fake_tracking = 1 WHERE id = %d",
		$track, $salesId));
			
	$s = file_get_contents("http://integra.eocenterprise.com/tracking.php?sales_id=${salesId}");
	
	file_put_contents("../logs/fake_track.txt", date('Y-m-d H:i:s') . "] ${track} generated for sales ID ${salesId}\r\n", FILE_APPEND);
}

mysql_close();

?>
