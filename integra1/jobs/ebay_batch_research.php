<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/e_utils.php');

set_time_limit(0);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

while (true)
{
    $q = <<<EOD
    SELECT id, mpn, brand
    FROM ebay_batch_research
    WHERE done = 0
    ORDER BY RAND()
    LIMIT 1
EOD;
    $res = mysql_query($q);
    $row = mysql_fetch_row($res);
    if (empty($row) || empty($row[0]))
        break;
    //EbayUtils::ResearchKeyword($row[1] . ' ' . $row[2], 100000 + $row[0]);
    EbayUtils::ResearchKeyword($row[1], 1000000 + $row[0]);
    $res = mysql_query("UPDATE ebay_batch_research SET done = 1 WHERE id = " . $row[0]);
}
