<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/e_utils.php');
require_once(__DIR__ . '/../system/counter_utils.php');

const NUMBER_ITEM_PER_REQUEST = 20;

function getCompetitorItems($ids) {
	$q = "
		SELECT our_item_id, variance, can_increase_price, competitor_item_id
		FROM integra_prod.ebay_monitor_matrix
		WHERE competitor_item_id IN (". implode(',', $ids) .")
	";

	$rows = mysql_query($q);
	$items  = [];
	while($row = mysql_fetch_row($rows)) {
		$our_item_id = $row[0];
		if (empty($our_item_id)) continue;
		array_push($items, array('our_item_id' => $our_item_id, 'variance' => $row[1], 'can_increase_price' => $row[2],
								'competitor_item_id' => $row[3]));

	}

	LogForJob("Number competitor items in database: ".count($items));

	return $items;
}

function LogForJob($message) {
	file_put_contents(LOGS_DIR . "ebay_monitor_job.log", $message."\r\n", FILE_APPEND);
}

function getArrValuesFromItemsByKey($items, $key) {
	return array_column($items, $key);
}

function callEbayAPIToGetItems($vsOurs, $dbFieldName) {

	LogForJob("============== GET COMPETITORS ============");

	$items = [];
	#$itemIDs = getArrValuesFromItemsByKey($vsOurs, $dbFieldName);
	$invalidIds = [];

	$sumXml = [];

	try {

		$dbItems = array_chunk($vsOurs, 20);

		foreach($dbItems as $item) {
			$ids = array_column($item, $dbFieldName);
			$idsStr = implode(',', $ids);
			$res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetMultipleItems&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${idsStr}&IncludeSelector=Details,ShippingCosts");
			/*  start insert counter */
			CountersUtils::insertCounter('GetMultipleItems','Ebay Inventory',APP_ID);
			/*  end insert counter */
			$xml = simplexml_load_string($res);

			if(!empty($xml->Errors)) {
				$invalidIds = explode(',', (string)$xml->Errors->ErrorParameters->Value);
			}

			array_push($sumXml, $xml);
		}

		foreach($sumXml as $xml) {
			foreach($xml->Item as $item) {
				$id = (integer)$item->ItemID;
				$ourPrice = (string)$item->ConvertedCurrentPrice;
				$ourSku = (string)$item->SKU;
				$ourQty = (string)$item->Quantity;
				$ourSold = (string)$item->QuantitySold;

				$competitorItemId = getCompetitorIDByOurId($id, $vsOurs);
				$canIncrease = getCanIncreasePrice($id, $vsOurs);

				array_push($items, array('our_item_id' => $id, 'ourPrice' => $ourPrice, 'ourSku' => $ourSku, 'ourQty' => $ourQty, 
							'ourSold' => $ourSold, 'competitor_item_id' => $competitorItemId, 
							'can_increase_price' => $canIncrease,
							'variance' => getVariance($id, $vsOurs)));	 
			}
		}


	} catch(Exception $ex) {
		LogForJob("EXCEPTION IN callEbayAPIToGetItems: ".$ex->getMessage());
	}
	
	LogForJob("NUMBER OF COMPETITORS GET FROM EBAY: ".count($items));

	return $items;
}

function getCanIncreasePrice($id, $competitors) {
	foreach($competitors as $comp) {
		if($id == $competitors['our_item_id']) {
			return $comp['can_increase_price'];
		}
	}
	return 1;
}

function getVariance($id, $competitors) {
	foreach($competitors as $comp) {
		if($id == $competitors['our_item_id']) {
			return $comp['variance'];
		}
	}
	return 0;
}

function getCompetitorIDByOurId($ourID, $vsOurs) {
	LogForJob("========== GET competitor_item_id for ".$ourID);
	foreach($vsOurs as $our) {
		
		if($ourID == $our['our_item_id']) {
			return $our['competitor_item_id'];
		}
	}
	return 0;
}

function updateItemAndCompetitors($ebayItems, $ebayCompetitorItems) {
	try {
		LogForJob("================ START UPDATING DATABASE IN updateItemAndCompetitors ===============");
		LogForJob("NUMBER OF EBAY ITEMS TO UPDATE: ".count($ebayItems));
		foreach($ebayItems as $ebayItem) {
			
			$result = mysql_query(query("UPDATE eoc.ebay_monitor SET deleted = 0, cur_title = '%s', cur_price = %d, cur_sold = %d, last_scraped = NOW() WHERE item_id = %d", $ebayItem['title'], $ebayItem['price'], $ebayItem['sold'], $ebayItem['item_id']));

			$competitorOfItems = getCompetitorItemsByItemID($ebayItem['item_id'], $ebayCompetitorItems);

			LogForJob(" NUMBER OF COMPETITORS IN updateItemAndCompetitors: ".count($competitorOfItems));

			updateCompetitorByItemID($ebayItem['item_id'], $ebayItem['price'], $competitorOfItems);
		}

	} catch(Exception $ex) {
		LogForJob("EXCEPTION IN updateItemAndCompetitors: ".$ex->getMessage());
	}
	
}

function updateCompetitorByItemID($itemId, $price, $competitors) {
	try {
		LogForJob("================ UPDATE COMPETITORS ===============");
		LogForJob("NUMBER COMPETITORS: ".count($competitors));
		foreach($competitors as $comp) {
			$vsOurs = $comp['our_item_id'];
			$ourPrice = $comp['ourPrice'];
			$canIncrease = $comp['can_increase_price'];
			mysql_query(query("INSERT IGNORE eoc.ebay_listings (item_id, sku, price, active, quantity) VALUES ('%s', '%s', '%s', 1, '%s')", $comp['our_item_id'], $comp['ourSku'], $comp['ourPrice'], $comp['ourQty']));
			mysql_query(query("UPDATE eoc.ebay_listings SET sold = %d WHERE item_id = %d", $comp['ourSold'], $comp['our_item_id']));

			// get our current price from ebay, min price from database

			$row2 = mysql_fetch_row(mysql_query(query("SELECT min_price FROM eoc.ebay_listings WHERE item_id = %d LIMIT 1", $comp['our_item_id'])));
			$minPrice = $row2[0];
			if (empty($minPrice)) {
				file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$itemId} vs. {$vsOurs} - Invalid min price\r\n", FILE_APPEND);
				continue;
			}

			$targetPrice = $price + $comp['variance'];

			if ($targetPrice == $ourPrice) {
				file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$itemId} ($ourPrice) vs. {$vsOurs} ($price) - Already at target price\r\n", FILE_APPEND);
				continue;
			}

			if ($targetPrice > $ourPrice && !$canIncrease) {
				file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$itemId} ($ourPrice) vs. {$vsOurs} ($price) - Not going up to target price of {$targetPrice}\r\n", FILE_APPEND);
				continue;
			}

			if ($targetPrice < $minPrice) {
				// if target price is lower than our min price, raise alert
				mysql_query(query("UPDATE eoc.ebay_monitor SET below_min = 1 WHERE item_id = %d", $itemId));
				file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$itemId} ($ourPrice) vs. {$vsOurs} ($price) - Target price of {$targetPrice} is below our minimum!\r\n", FILE_APPEND);

				// cap min price
				$targetPrice = $minPrice;
			}

			if ($targetPrice == $ourPrice) {
				file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$itemId} ($ourPrice) vs. {$vsOurs} ($price) - Can't reprice, we are already at minimum!\r\n", FILE_APPEND);
				continue;
			}

			LogForJob("================ REVISE NODE " . date('Y-m-d H:i:s') . " ===============");

			file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s'). " Start ReviseNode \n", FILE_APPEND);

			$ret = EbayUtils::ReviseNode($vsOurs, '<StartPrice><![CDATA[' . $targetPrice . ']]></StartPrice>');
			file_put_contents(LOGS_DIR . "monitor.txt", date('Y-m-d H:i:s') . " {$itemId} ($ourPrice) vs. {$vsOurs} ($price) - Repricing to $targetPrice. $ret\r\n", FILE_APPEND);
		}

	} catch(Exception $ex) {
		LogForJob("EXCEPTION IN updateCompetitorByItemID: ".$ex->getMessage());
	}
	
}

function getCompetitorItemsByItemID($itemID, $ebayCompetitorItems) {
	$competitorItems = array();
	LogForJob("Look for competitors items for ".$itemID);
	foreach($ebayCompetitorItems as $item) {

		if($itemID == $item['competitor_item_id']) {
			array_push($competitorItems, $item);
		}
	}
	return $competitorItems;
}

function updateDeletedItems($ids) {
	$idsStr = implode(',', $ids);
	$sql = "UPDATE eoc.ebay_monitor SET deleted = 1, last_scraped = NOW() WHERE item_id IN (%s)";

	mysql_query(query($sql, $idsStr));
	
}

LogForJob("============== Ebay Monitor Job Started At: ".date('Y-m-d H:i:s')." ================");

try {

	$q = "
		SELECT COUNT(*) / 1440 AS rate
		FROM eoc.ebay_monitor
		WHERE disable = 0
	";

	$rate = ceil(mysql_fetch_row(mysql_query($q))[0]);
	
	#echo "Scraping rate: {$rate}\r\n";

	$q = "
		SELECT item_id
		FROM eoc.ebay_monitor
		WHERE last_scraped < DATE_SUB(NOW(), INTERVAL 1 DAY)
		AND disable = 0
		AND strategy = 99
		ORDER BY last_scraped 
		LIMIT 500";
		#LIMIT {$rate} ";

	$ids = [];
	$rows = mysql_query($q);
	while ($row = mysql_fetch_row($rows)) {
		$ids[] = $row[0];
	}

	LogForJob("NUMBER OF RECORDS: ".count($ids));

	$subIds = array_chunk($ids, 20);
	$sumXml = [];
	$invalidIds = [];

	foreach($subIds as $id) {
		$idsStr = implode(',', $id);

		LogForJob("CALL EBAY API FOR: ".$idsStr);

		$res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetMultipleItems&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${idsStr}&IncludeSelector=Details,ShippingCosts");
		/*  start insert counter */
		CountersUtils::insertCounter('GetMultipleItems','Ebay Inventory',APP_ID);
		/*  end insert counter */
		$xml = simplexml_load_string($res);

		array_push($sumXml, $xml);
	}

	$competitorItems = getCompetitorItems($ids);

	$ebayCompetitorItems = callEbayAPIToGetItems($competitorItems, 'our_item_id');

	$ebayItems = [];

	if (!empty($sumXml)) {

		LogForJob("============== Xml is not empty ==============");

		foreach($sumXml as $xml) {

			if(!empty($xml->Errors)) {
				LogForJob("======================== ERRORS ======================");
				
				$invalidIds = explode(",", $xml->Errors->ErrorParameters->Value);
				LogForJob(" LIST ITEM ERRORS: ".(string)$xml->Errors->ErrorParameters->Value);
				LogForJob("ERRORS MESSAGE: ".(string)$xml->Errors->LongMessage);
			}

			foreach($xml->Item as $item) {
				$itemID = (integer)$item->ItemID;

				LogForJob("GET ITEM ID: ".$itemID);

				$status = (string)$item->ListingStatus;
				if($status != 'Active') {
					array_push($invalidIds, $itemID);
				}

				$title = (string)$item->Title;
				$price = (string)$item->ConvertedCurrentPrice;
				$price = floatval($price);
				$shipping = (string)$item->ShippingCostSummary->ShippingServiceCost;
				$shippingType = (string)$item->ShippingCostSummary->ShippingType;
				$sold = intval(trim(str_replace(',', '', (string)$xml->Item->QuantitySold)));

				if ($shippingType == 'Calculated') {
					$res = file_get_contents("http://www.ebay.com/itm/getrates?item=${id}&quantity=1&country=1&zipCode=77057&co=0&cb=j");
					/*  start insert counter */
					CountersUtils::insertCounter('getrates','Ebay Inventory',APP_ID);
					/*  end insert counter */
					unset($match);
					preg_match('/US \$(?P<shipping>[^<]+)/i', $res, $match);
					if (isset($match) && array_key_exists('shipping', $match))
						$shipping = $match['shipping'];
				}
				$shipping = floatval($shipping);
				$price += $shipping;

				array_push($ebayItems, array('item_id' => $itemID, 'title' => $title, 'price' => $price, 'sold' => $sold));

			}

		}

		

		LogForJob("NUMBER OF EBAY ITEMS: ".count($ebayItems));

		updateItemAndCompetitors($ebayItems, $ebayCompetitorItems);

		updateDeletedItems($invalidIds);

	}

} catch(Exception $ex) {
	LogForJob("EXCEPTION: ".$ex->getMessage());
}



mysql_close();

LogForJob("============== Ebay Monitor Job Ended At: ".date('Y-m-d H:i:s')." ================");

?>