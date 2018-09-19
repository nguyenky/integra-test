<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/e_utils.php');

set_time_limit(0);
ini_set('memory_limit', '768M');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

while (true) {

	$q = <<<EOD
SELECT this_item
FROM eoc.ebay_grid
WHERE img_done = 0
AND seller = 'partscontainer'
ORDER BY RAND()
LIMIT 10
EOD;

	$rows = mysql_query($q);
	if (!empty($rows)) {
		while ($row = mysql_fetch_row($rows)) {
			try {
				$id = $row[0];
				$res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${id}&IncludeSelector=Details");
				$xml = simplexml_load_string($res);
				if ($xml->Ack != 'Success' && $xml->Ack != 'Warning') {
					mysql_query(query("UPDATE eoc.ebay_grid SET img_done = 1, `timestamp` = NOW(), active = 0 WHERE this_item = '%s'", $id));
					continue;
				}

				$title = (string)$xml->Item->Title;
				$price = (string)$xml->Item->ConvertedCurrentPrice;
				$sold = intval(trim(str_replace(',', '', (string)$xml->Item->QuantitySold)));

				mysql_query(query("UPDATE eoc.ebay_grid SET img_done = 1, `timestamp` = NOW(), active = 1, title = '%s', price = '%s', shipping = 0, num_sold = %d WHERE this_item = '%s'", $title, $price, $sold, $id));
			} catch (Exception $e) {
				error_log($e->getMessage());
			}
		}
	}
	else return;
}

mysql_close();
