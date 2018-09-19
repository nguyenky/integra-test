<?php

require_once(__DIR__ . '/../system/config.php');

set_time_limit(0);
ini_set('memory_limit', '256M');

$date = date_create("now", new DateTimeZone('America/New_York'));
$fn = date_format($date, 'Y-m-d_H-i') . '_google_daily';
$csv = "/tmp/mysql/${fn}.txt";
$zip = "/var/shared/google/daily.zip";

if (is_file($csv)) unlink($csv);
if (is_file($zip)) unlink($zip);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db('integra_prod');

mysql_query(<<<EOQ
SELECT 'id','availability','price'
UNION ALL
SELECT id, availability, CONCAT(price, ' USD') AS price
FROM integra_prod.google_feed gf
INTO OUTFILE '{$csv}' FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\n'
EOQ
);

mysql_close();

for ($i = 0; $i < 60; $i++) {
    sleep(1);
    if (is_file($csv)) break;
}

exec("zip -9j {$zip} {$csv}");
unlink($csv);

?>