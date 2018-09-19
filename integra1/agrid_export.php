<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$user = Login('agrid');

set_time_limit(0);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$now = time();
$csv = "/tmp/mysql/agrid_{$now}.csv";
$zip = "/tmp/mysql/agrid_{$now}.zip";

$q = <<<EOD
SELECT
al.asin,
al.sku,
sc.price,
sc.shipping,
sc.cond,
sc.seller_code,
sc.seller,
sc.rating,
(SELECT bb.price + bb.shipping AS bb_price FROM amazon_scraper bb WHERE bb.buybox = 1 AND bb.asin = al.asin ORDER BY 1 ASC LIMIT 1) AS bb_price,
al.last_scraped
INTO OUTFILE '${csv}'
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
FROM amazon_listings al LEFT JOIN amazon_scraper sc ON al.asin = sc.asin
WHERE al.active = 1
EOD;

mysql_query($q);

for ($i = 0; $i < 60; $i++) {
    sleep(1);
    if (is_file($csv)) break;
}

if (is_file($csv))
{
    exec("zip -9j ${zip} ${csv}");

    header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-Disposition: attachment; filename=agrid.zip");
    header("Content-Type: application/zip");
    header("Content-Transfer-Encoding: binary");

    readfile($zip);
}
else
{
    echo "Unknown error while exporting file.";
}