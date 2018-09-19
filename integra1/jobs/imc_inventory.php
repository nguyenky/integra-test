<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/imc_utils.php');

set_time_limit(0);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
SELECT iq.mpn
FROM imc_qty iq LEFT JOIN imc_items ii ON iq.mpn = ii.mpn
WHERE ii.timestamp IS NULL
OR ii.timestamp < DATE_SUB(NOW(), INTERVAL 3 DAY)
ORDER BY ii.timestamp ASC, iq.qty DESC LIMIT 7
EOD;
$res = mysql_query($q);

while ($row = mysql_fetch_row($res))
	$skus[] = $row[0];
	
if (count($skus) == 0)
	return;

ImcUtils::QueryItems($skus);
