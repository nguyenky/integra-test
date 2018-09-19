<?php

require_once(__DIR__ . '/../system/config.php');

set_time_limit(0);
ini_set('memory_limit', '256M');

$date = date_create("now", new DateTimeZone('America/New_York'));
$fn = date_format($date, 'Y-m-d_H-i') . '_google';
$csv = "/tmp/mysql/${fn}.txt";
$zip = "/var/shared/google/feed.zip";

if (is_file($csv)) unlink($csv);
if (is_file($zip)) unlink($zip);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db('integra_prod');

mysql_query(<<<EOQ
SELECT 'id','availability','mpn','title','description','link','price','image link','brand','condition','shipping','google product category','custom label 0','custom label 1','custom label 2','custom label 3','custom label 4','shipping weight'
UNION ALL
SELECT id, availability, custom_mpn, title, description, link, CONCAT(price, ' USD') AS price, image_link, brand, item_condition, CONCAT(shipping, ' USD') AS shipping, category, custom_label0, custom_label1, custom_label2, custom_label3, custom_label4,
CONCAT(IFNULL((SELECT cped.value FROM magento.catalog_product_entity cpe, magento.catalog_product_entity_decimal cped WHERE cpe.entity_id = cped.entity_id AND cpe.sku = gf.mpn AND cped.store_id = 0 AND cped.attribute_id = 80 LIMIT 1), 0), ' lb') AS weight
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