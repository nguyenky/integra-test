<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/ssf_utils.php');

set_time_limit(0);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
SELECT CONCAT(mpn, '.', brand_id)
FROM ssf_items
WHERE inactive = 0
ORDER BY RAND()
LIMIT 20
EOD;
$res = mysql_query($q);

while ($row = mysql_fetch_row($res))
	$skus[] = $row[0];
	
if (count($skus) == 0)
	return;

SsfUtils::QueryItems($skus);
