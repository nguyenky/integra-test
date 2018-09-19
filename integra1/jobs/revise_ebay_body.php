<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/e_utils.php');

set_time_limit(0);
ini_set('memory_limit', '512M');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
	SELECT item_id
	FROM ebay_listings
	WHERE active = 1
EOD;
$rows = mysql_query($q);

while ($row = mysql_fetch_row($rows))
{
	$itemId = $row[0];
    $response = EbayUtils::ReviseBody($itemId);
    echo "{$itemId} - {$response}\n";
}

mysql_close();

?>
