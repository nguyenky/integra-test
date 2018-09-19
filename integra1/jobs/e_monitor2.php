<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/e_utils.php');

set_time_limit(0);
ini_set('memory_limit', '768M');

date_default_timezone_set('America/New_York');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db('integra_prod');

$q = <<<EOD
SELECT COUNT(*) / 1440 AS rate
FROM eoc.ebay_monitor
WHERE disable = 0
EOD;

$rate = ceil(mysql_fetch_row(mysql_query($q))[0]);
echo "Scraping rate: {$rate}\r\n";

$q = <<<EOD
SELECT item_id
FROM eoc.ebay_monitor
WHERE last_scraped < DATE_SUB(NOW(), INTERVAL 1 DAY)
AND disable = 0
AND strategy = 99
ORDER BY last_scraped
LIMIT {$rate}
EOD;

$ids = [];
$rows = mysql_query($q);
while ($row = mysql_fetch_row($rows)) {
	$ids[] = $row[0];
}

foreach ($ids as $id) {
	echo $id . "\r\n";

	$res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${id}&IncludeSelector=Details,ShippingCosts");

	if (stripos($res, 'Invalid item ID') !== false)
	{
		// this item no longer exists
		mysql_query(query("UPDATE eoc.ebay_monitor SET deleted = 1, last_scraped = NOW() WHERE item_id = '%s'", $id));
		echo "item no longer exists\r\n";
		continue;
	}

	$xml = simplexml_load_string($res);

	if ($xml->Ack != 'Success' && $xml->Ack != 'Warning')
		continue;

	$status = (string)$xml->Item->ListingStatus;

	if ($status !== 'Active')
	{
		// this item is no longer available
		mysql_query(query("UPDATE eoc.ebay_monitor SET deleted = 1, last_scraped = NOW() WHERE item_id = '%s'", $id));
		echo "item is no longer available\r\n";
		continue;
	}

	$title = (string)$xml->Item->Title;
	$price = (string)$xml->Item->ConvertedCurrentPrice;
	$price = floatval($price);
	$shipping = (string)$xml->Item->ShippingCostSummary->ShippingServiceCost;
	$shippingType = (string)$xml->Item->ShippingCostSummary->ShippingType;
	if ($shippingType == 'Calculated') {
		$res = file_get_contents("http://www.ebay.com/itm/getrates?item=${id}&quantity=1&country=1&zipCode=77057&co=0&cb=j");
		unset($match);
		preg_match('/US \$(?P<shipping>[^<]+)/i', $res, $match);
		if (isset($match) && array_key_exists('shipping', $match))
			$shipping = $match['shipping'];
	}
	$shipping = floatval($shipping);
	$price += $shipping;

	$sold = intval(trim(str_replace(',', '', (string)$xml->Item->QuantitySold)));

	mysql_query(query("UPDATE eoc.ebay_monitor SET deleted = 0, cur_title = '%s', cur_price = '%s', cur_sold = %d, last_scraped = NOW() WHERE item_id = '%s'", $title, $price, $sold, $id));

	$q = <<<EOD
SELECT our_item_id, variance, can_increase_price
FROM integra_prod.ebay_monitor_matrix
WHERE competitor_item_id = '{$id}'
EOD;

	$rows = mysql_query($q);

	while ($row = mysql_fetch_row($rows)) {
		$vsOurs = $row[0];
		if (empty($vsOurs)) continue;

		$variance = $row[1];
		$canIncrease = $row[2];
		echo "comparing vs. $vsOurs (variance: $variance)\r\n";

		$res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${vsOurs}&IncludeSelector=Details");
		$xml = simplexml_load_string($res);
		if ($xml->Ack != 'Success' && $xml->Ack != 'Warning') {
			file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$id} vs. {$vsOurs} - Cannot find our item\r\n", FILE_APPEND);
			continue;
		}

		$ourPrice = (string)$xml->Item->ConvertedCurrentPrice;
		$ourSku = (string)$xml->Item->SKU;
		$ourQty = (string)$xml->Item->Quantity;
		$ourSold = (string)$xml->Item->QuantitySold;

		mysql_query(query("INSERT IGNORE eoc.ebay_listings (item_id, sku, price, active, quantity) VALUES ('%s', '%s', '%s', 1, '%s')", $vsOurs, $ourSku, $ourPrice, $ourQty));
		mysql_query(query("UPDATE eoc.ebay_listings SET sold = '%s' WHERE item_id = '%s'", $ourSold, $vsOurs));

		// get our current price from ebay, min price from database

		$row2 = mysql_fetch_row(mysql_query(query("SELECT min_price FROM eoc.ebay_listings WHERE item_id = '%s' LIMIT 1", $vsOurs)));
		$minPrice = $row2[0];
		if (empty($minPrice)) {
			file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$id} vs. {$vsOurs} - Invalid min price\r\n", FILE_APPEND);
			continue;
		}

		$targetPrice = $price + $variance;

		if ($targetPrice == $ourPrice) {
			file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$id} ($ourPrice) vs. {$vsOurs} ($price) - Already at target price\r\n", FILE_APPEND);
			continue;
		}

		if ($targetPrice > $ourPrice && !$canIncrease) {
			file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$id} ($ourPrice) vs. {$vsOurs} ($price) - Not going up to target price of {$targetPrice}\r\n", FILE_APPEND);
			continue;
		}

		if ($targetPrice < $minPrice) {
			// if target price is lower than our min price, raise alert
			mysql_query(query("UPDATE eoc.ebay_monitor SET below_min = 1 WHERE item_id = '%s'", $id));
			file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$id} ($ourPrice) vs. {$vsOurs} ($price) - Target price of {$targetPrice} is below our minimum!\r\n", FILE_APPEND);

			// cap min price
			$targetPrice = $minPrice;
		}

		if ($targetPrice == $ourPrice) {
			file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$id} ($ourPrice) vs. {$vsOurs} ($price) - Can't reprice, we are already at minimum!\r\n", FILE_APPEND);
			continue;
		}

		$ret = EbayUtils::ReviseNode($vsOurs, '<StartPrice><![CDATA[' . $targetPrice . ']]></StartPrice>');
		file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$id} ($ourPrice) vs. {$vsOurs} ($price) - Repricing to $targetPrice. $ret\r\n", FILE_APPEND);
	}
}

mysql_close();
