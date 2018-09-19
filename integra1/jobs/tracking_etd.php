<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/tracking_utils.php');
require_once(__DIR__ . '/../system/utils.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = "SELECT id, tracking_num FROM direct_shipments WHERE order_date > DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND no_tracking = 0 AND tracking_num > '' AND etd IS NULL";
$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
	$dsList[$row[0]] = $row[1];

foreach ($dsList as $id => $tracking)
{
	$etd = TrackingUtils::GetETD($tracking);
	
	if (!empty($etd))
	{
		$q=<<<EOQ
		UPDATE direct_shipments SET etd = '%s'
		WHERE id = %d
EOQ;
		mysql_query(query($q, $etd, $id));
        echo "ETD for {$id} ({$tracking}) is {$etd}.\n";
	}
    else
    {
        echo "No ETD yet for {$id} ({$tracking}).\n";
    }
}

mysql_close();

?>
