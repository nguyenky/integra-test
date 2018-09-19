<?php

require_once(__DIR__ . '/../system/e_utils.php');
require_once(__DIR__ . '/../system/counter_utils.php');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

while (true)
{
    $q = <<<EOQ
SELECT item_id
FROM eoc.ebay_listings
WHERE active = 1
AND (title IS NULL OR timestamp < DATE_SUB(NOW(), INTERVAL 1 HOUR))
ORDER BY RAND()
LIMIT 20
EOQ;
    $rows = mysql_query($q);

    $itemIds = [];
    while ($row = mysql_fetch_row($rows))
    {
        $itemIds[] = $row[0];
    }

    if (empty($itemIds)) break;

    foreach ($itemIds as $itemId)
    {
        try
        {
            echo "$itemId\n";
            ScrapeItemId($itemId);
        }
        catch (Exception $e)
        {
            echo $e->getMessage() . "\n";
            mysql_query(query("UPDATE eoc.ebay_listings SET timestamp = NOW() WHERE item_id = '%s'", $itemId));
        }
    }
}

echo "DONE!";

function ScrapeItemId($itemId)
{
    $res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${itemId}&IncludeSelector=ItemSpecifics");
    /*  start insert counter */
    CountersUtils::insertCounterProd('GetSingleItem','Ebay Scrape Listing',APP_ID);
    /*  end insert counter */
    $xml = simplexml_load_string($res);
    if ($xml->Ack != 'Success' && $xml->Ack != 'Warning')
    {
        mysql_query(query("UPDATE eoc.ebay_listings SET timestamp = NOW() WHERE item_id = '%s'", $itemId));
        return;
    }

    $title = (string)$xml->Item->Title;
    $brand = '';
    $mpn = '';
    $ipn = '';
    $opn = '';

    if (!empty($xml->Item->ItemSpecifics) && !empty($xml->Item->ItemSpecifics->NameValueList))
    {
        foreach ($xml->Item->ItemSpecifics->NameValueList as $pair)
        {
            if ($pair->Name == 'Manufacturer Part Number')
                $mpn = (string)$pair->Value;
            else if ($pair->Name == 'Interchange Part Number')
                $ipn = (string)$pair->Value;
            else if ($pair->Name == 'Other Part Number')
                $opn = (string)$pair->Value;
            else if ($pair->Name == 'Part Brand')
                $brand = (string)$pair->Value;
            else if ($pair->Name == 'Brand')
                $brand = (string)$pair->Value;
        }
    }

    mysql_query(query("UPDATE eoc.ebay_listings SET title = '%s', brand = '%s', mpn = '%s', inter = '%s', opn = '%s', timestamp = NOW() WHERE item_id = '%s'",
        $title, $brand, $mpn, $ipn, $opn, $itemId));
}

