<?php

require_once(__DIR__ . '/../system/config.php');

set_time_limit(0);
ini_set('memory_limit', '1024M');

if ($argc != 2)
{
    echo "Usage: php google_feed.php <store_code>\n";
    return;
}

$storeCode = $argv[1];

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db('magento');

$q = <<<EOQ
SELECT cw.website_id, ccd.value
FROM core_config_data ccd, core_website cw
WHERE ccd.scope = 'websites'
AND ccd.scope_id = cw.website_id
AND ccd.path = 'web/secure/base_url'
AND cw.code = '%s'
EOQ;
$row = mysql_fetch_row(mysql_query(sprintf($q, $storeCode)));

if (empty($row))
{
    echo "Invalid store code!\n";
    return;
}

$storeId = $row[0];
$baseUrl = $row[1];

$fn = "google_${storeCode}";
$tmp = "/tmp/${fn}.txt";
$zip = realpath(dirname(__FILE__) . '/../../../magento/feeds/') . "/${fn}.zip";

$headers = array
(
    "id",
    "title",
    "description",
    "google product category",
    "link",
    "image link",
    "condition",
    "availability",
    "price",
    "brand",
    "mpn"
);

file_put_contents($tmp, implode("\t", $headers) . "\r\n");

$row = mysql_fetch_row(mysql_query("SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_product'"));
$entityTypeId = $row[0];

$row = mysql_fetch_row(mysql_query("SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'mpn' and entity_type_id = ${entityTypeId}"));
$attMpn = $row[0];

$row = mysql_fetch_row(mysql_query("SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'opn' and entity_type_id = ${entityTypeId}"));
$attOpn = $row[0];

$row = mysql_fetch_row(mysql_query("SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'opn_unspaced' and entity_type_id = ${entityTypeId}"));
$attOpnUnspaced = $row[0];

$q = <<<EOQ
SELECT
cpf.sku,
em.id,
cpf.name,
cpf.url_path,
cpf.price,
cpf.brand,
css.qty,
em.make,
em.model,
em.year,
(
    SELECT c1.value FROM catalog_product_entity_varchar c1
    WHERE c1.attribute_id = ${attMpn}
    AND c1.entity_type_id = ${entityTypeId}
    AND c1.entity_id = cpf.entity_id
    AND c1.store_id = 0
    LIMIT 1
) AS mpn,
(
    SELECT c2.value FROM catalog_product_entity_varchar c2
    WHERE c2.attribute_id = ${attOpn}
    AND c2.entity_type_id = ${entityTypeId}
    AND c2.entity_id = cpf.entity_id
    AND c2.store_id = 0
    LIMIT 1
) AS opn,
(
    SELECT c3.value FROM catalog_product_entity_varchar c3
    WHERE c3.attribute_id = ${attOpnUnspaced}
    AND c3.entity_type_id = ${entityTypeId}
    AND c3.entity_id = cpf.entity_id
    AND c3.store_id = 0
    LIMIT 1
) AS opn_unspaced
FROM catalog_product_flat_${storeId} cpf LEFT JOIN elite_1_mapping em ON cpf.entity_id = em.entity_id, cataloginventory_stock_status css
WHERE cpf.price > 0
AND css.product_id = cpf.entity_id
AND css.website_id = 1
AND css.stock_id = 1
EOQ;
$res = mysql_query($q);

while ($row = mysql_fetch_row($res))
{
    $sku = $row[0];
    $mappingId = $row[1];
    $name = $row[2];
    $urlPath = $row[3];
    $price = $row[4];
    $brand = $row[5];
    $qty = $row[6];
    $make = $row[7];
    $model = $row[8];
    $year = $row[9];
    $mpn = $row[10];
    $opn = $row[11];
    $opnUnspaced = $row[12];

    $s = explode('.', $sku);
    $skuNoBrand = $s[0];

    $nums = array($skuNoBrand, $mpn, $opn, $opnUnspaced);
    $skuMpn = implode(" / ", array_unique($nums));

    $cond = 'new';

    if ((stripos($brand, 'remanu') !== false)
        || (stripos($name, 'remanu') !== false)
        || (stripos($name, 'rebuilt') !== false))
        $cond = 'refurbished';

    $avail = ($qty > 0) ? 'in stock' : 'out of stock';

    $compatId = $sku;
    $compatTitle = $name;
    $compatUrl = "${baseUrl}${urlPath}";

    if (!empty($make) && !empty($model) && !empty($year) && !empty($mappingId))
    {
        $compatId = "${skuNoBrand}-${mappingId}";
        $compatTitle = "${name} for ${make} ${model} ${year}";
        $compatUrl = "${baseUrl}fit/${urlPath}/" . urlencode($make) . "/" . urlencode($model) . "/" . urlencode($year);
    }

    $data = array
    (
        $compatId,   // id
        $compatTitle, // title
        "{$compatTitle} - ${skuMpn} - ${brand}",  // description
        'Vehicles & Parts > Vehicle Parts & Accessories',   // google product category
        $compatUrl,  // link
        "${baseUrl}img/${sku}/cl1", // image link
        $cond,  // condition
        $avail, // availability
        number_format($price, 2, '.', '') . ' USD', // price
        $brand, // brand
        $mpn    // mpn
    );

    file_put_contents($tmp, implode("\t", $data) . "\r\n", FILE_APPEND);
}

mysql_close();

exec("zip -9j ${zip} ${tmp}");
unlink($tmp);

?>