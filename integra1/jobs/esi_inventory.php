<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/esi_utils.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
SELECT mpn
FROM esi_items
WHERE `timestamp` < DATE_SUB(NOW(), INTERVAL 3 DAY)
AND obsolete = 0
ORDER BY `timestamp` ASC
LIMIT 20
EOD;
$res = mysql_query($q);

while ($row = mysql_fetch_row($res))
	$skus[] = $row[0];

if (count($skus) == 0)
	return;

EsiUtils::QueryItems($skus, true);
