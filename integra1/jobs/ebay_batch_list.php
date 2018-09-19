<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/e_utils.php');

set_time_limit(0);

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

while(true)
{
    $q = <<<EOD
    SELECT id, name, quantity, item_id, sku
    FROM ebay_batch_list2
    WHERE selected = 1
    AND new_item_id IS NULL
    AND item_cost < price + shipping
    AND quantity > 0
    ORDER BY RAND()
    LIMIT 1
EOD;
    $res = mysql_query($q);
    $row = mysql_fetch_row($res);
    if (empty($row) || empty($row[0]))
        break;

    $id = $row[0];
    $name = $row[1];
    $quantity = $row[2];
    $srcItemId = $row[3];
    $sku = $row[4];

    echo "Source: $srcItemId\n";

    // processing
    mysql_query("UPDATE ebay_batch_list2 SET selected = 2 WHERE id = " . $id);

    $item = EbayUtils::GetItem($srcItemId);
    if (empty($item))
    {
        // listing error - src not found
        echo "Source not found\n";
        mysql_query("UPDATE ebay_batch_list2 SET selected = 98 WHERE id = " . $id);
        continue;
    }

    $response = EbayUtils::ListItem(
        $name,
        $sku,
        $quantity,
        $item['price'] + $item['shipping'],
        null,
        null,
        $item['mpn'],
        $item['ipn'],
        $item['opn'],
        $item['placement'],
        $item['brand'],
        $item['surface_finish'],
        $item['warranty'],
        'http://catalog.eocenterprise.com/img/' . str_replace('-', '', str_replace('EOC', '', str_replace('EOCS', '', $sku))) . '/cl1,loqe,boqe',
        1,
        $item['mpn'] . "\n" . $item['opn'],
        $item['category'],
        $item['compatibility'],
        1000);

    if ($response['success'] == 1)
    {
        echo "Successful: " . $response['id'] . "\n";
        mysql_query(query("UPDATE ebay_batch_list2 SET selected = 3, new_item_id = '%s' WHERE id = " . $id, $response['id']));
    }
    else
    {
        echo "Listing failed: " . $response['error'] . "\n";
        mysql_query("UPDATE ebay_batch_list2 SET selected = 97 WHERE id = " . $id);
    }
}