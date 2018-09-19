<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/e_utils.php');
require_once(__DIR__ . '/../system/ebay/BaseEbayMonitor.php');

// const NUMBER_ITEM_PER_REQUEST = 20;

// set_time_limit(0);
// ini_set('memory_limit', '768M');

// date_default_timezone_set('America/New_York');

// mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
// mysql_select_db('integra_prod');



// function LogForJob($message) {
// 	// file_put_contents(LOGS_DIR . "ebay_monitor1_job.log", $message."\r\n", FILE_APPEND);
// }

// function getArrValuesFromItemsByKey($items, $key) {
// 	return array_column($items, $key);
// }

// function callEbayAPIToGetItems($competitorItems, $dbField) {

// 	#LogForJob("============== GET COMPETITORS FOR ". count($competitorItems) ." items ============");

// 	$items = [];
// 	$invalidIds = [];

// 	$itemIds = array_column($competitorItems, $dbField);

// 	#LogForJob("NUMBER OF ITEM IDS COMPETITORS: ".count($itemIds));

// 	$sumXml = [];

// 	try {

// 		$itemIDs = array_chunk($itemIds, 20);

// 		foreach($itemIDs as $ids) {
// 			$idsStr = implode(',', $ids);
// 			#LogForJob("CALL EBAY API TO GET RESULTS FOR: ".$idsStr);
// 			$res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetMultipleItems&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${idsStr}&IncludeSelector=Details,ShippingCosts");

// 			$xml = simplexml_load_string($res);

// 			if(!empty($xml->Errors)) {
// 				$invalidIds = explode(',', (string)$xml->Errors->ErrorParameters->Value);
// 			}

// 			array_push($sumXml, $xml);
// 		}

// 		foreach($sumXml as $xml) {
// 			foreach($xml->Item as $item) {
// 				$id = (integer)$item->ItemID;
// 				$ourPrice = (string)$item->ConvertedCurrentPrice;
// 				$ourSku = (string)$item->SKU;
// 				$ourQty = (string)$item->Quantity;
// 				$ourSold = (string)$item->QuantitySold;

// 				$competitorItemId = getCompetitorIDByOurId($id, $competitorItems);

// 				array_push($items, array('our_item_id' => $id, 'ourPrice' => $ourPrice, 'ourSku' => $ourSku, 'ourQty' => $ourQty, 'ourSold' => $ourSold, 'competitor_item_id' => $competitorItemId));	 
// 			}
// 		}


// 	} catch(Exception $ex) {
// 		LogForJob("EXCEPTION IN callEbayAPIToGetItems: ".$ex->getMessage());
// 	}
	
// 	#LogForJob("NUMBER OF COMPETITORS GET FROM EBAY: ".count($items));

// 	return $items;
// }

// function getCompetitorIDByOurId($ourID, $items) {
// 	#LogForJob("========== GET competitor_item_id for ".$ourID);
// 	foreach($items as $item) {
		
// 		if($ourID == $item['vs_ours']) {
// 			#LogForJob("FOUND COMPETITOR ITEM: ".$item['item_id']);
// 			return $item['item_id'];
// 		}
// 	}
// 	return 0;
// }

// function updateItemAndCompetitors($ebayItems, $ebayCompetitorItems, $strategies) {
// 	try {
// 		LogForJob("================ START UPDATING DATABASE IN updateItemAndCompetitors ===============");
// 		#LogForJob("NUMBER OF EBAY ITEMS TO UPDATE: ".count($ebayItems));
// 		foreach($ebayItems as $ebayItem) {
			
// 			$result = mysql_query(query("UPDATE eoc.ebay_monitor SET deleted = 0, cur_title = '%s', cur_price = %f, cur_sold = %d, last_scraped = NOW() WHERE item_id = %d", $ebayItem['title'], $ebayItem['price'], $ebayItem['sold'], $ebayItem['item_id']));

// 			$competitorOfItems = getCompetitorItemsByItemID($ebayItem['item_id'], $ebayCompetitorItems);

// 			#LogForJob(" NUMBER OF COMPETITORS IN updateItemAndCompetitors: ".count($competitorOfItems));

// 			updateCompetitorByItemID($ebayItem['item_id'], $ebayItem['price'], $competitorOfItems, $strategies,$ebayItem['status']);
// 		}

// 	} catch(Exception $ex) {
// 		LogForJob("EXCEPTION IN updateItemAndCompetitors: ".$ex->getMessage());
// 	}
	
// }

// function updateCompetitorByItemID($itemId, $price, $competitors, $strategies,$status) {
// 	try {
// 		#LogForJob("================ UPDATE COMPETITORS ===============");
// 		#LogForJob("NUMBER COMPETITORS: ".count($competitors));
// 		foreach($competitors as $comp) {
// 			#LogForJob("=== Start Updating For ". $comp['our_item_id'] ." ======");
// 			// get our current price from ebay, 
// 			$vsOurs = $comp['our_item_id'];
// 			$ourPrice = $comp['ourPrice'];
// 			mysql_query(query("INSERT IGNORE eoc.ebay_listings (item_id, sku, price, active, quantity) VALUES ('%s', '%s', '%s', 1, '%s')", $comp['our_item_id'], $comp['ourSku'], $comp['ourPrice'], $comp['ourQty']));
// 			mysql_query(query("UPDATE eoc.ebay_listings SET sold = %d WHERE item_id = %d", $comp['ourSold'], $comp['our_item_id']));

// 			// min price from database

// 			$row2 = mysql_fetch_row(mysql_query(query("SELECT min_price FROM eoc.ebay_listings WHERE item_id = %d LIMIT 1", $comp['our_item_id'])));
// 			$minPrice = $row2[0];
// 			if (empty($minPrice)) {
// 				// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " {$itemId} vs. {$vsOurs} - Invalid min price\r\n", FILE_APPEND);
// 				continue;
// 			}

// 			$strategy = $strategies[$itemId];

// 			if($status != 1) {
// 				if ($strategy == 1 )
// 				{
// 					LogForJob("strategy is 1");
// 					// our price is already below or equal to their price, do nothing
// 					if ($ourPrice <= $price)
// 					{
// 						// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Theirs: $price. Ours: $ourPrice. Doing nothing\r\n", FILE_APPEND);
// 						continue;
// 					}

// 					// target price will be their price or our price floor, whichever is HIGHER
// 					$targetPrice = max($minPrice, $price);

// 					$ret = EbayUtils::ReviseNode($vsOurs, '<StartPrice><![CDATA[' . $targetPrice . ']]></StartPrice>');
// 					// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $id - $strategy - Theirs: $price. Ours: $ourPrice. Repricing to $targetPrice. $ret\r\n", FILE_APPEND);

// 					if ($price < $targetPrice)
// 					{
// 						// if their price is still lower than our target price, raise alert
// 						mysql_query(query("UPDATE eoc.ebay_monitor SET below_min = 1 WHERE item_id = '%s'", $itemId));
// 						// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Below minimum alert!\r\n", FILE_APPEND);
// 					}
// 				}

// 				// Go Under
// 				else if ($strategy == 2)
// 				{
// 					#LogForJob("strategy is 2");
// 					// our price is already below their price, do nothing
// 					if ($ourPrice < $price)
// 					{
// 						// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Theirs: $price. Ours: $ourPrice. Doing nothing\r\n", FILE_APPEND);
// 						continue;
// 					}

// 					// target price will be their price -0.01 or our price floor, whichever is HIGHER
// 					$targetPrice = max($minPrice, $price - 0.01);

// 					$ret = EbayUtils::ReviseNode($vsOurs, '<StartPrice><![CDATA[' . $targetPrice . ']]></StartPrice>');
// 					// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Theirs: $price. Ours: $ourPrice. Repricing to $targetPrice. $ret\r\n", FILE_APPEND);

// 					if ($price <= $targetPrice)
// 					{
// 						// if their price is lower or equal to our target price, raise alert
// 						mysql_query(query("UPDATE eoc.ebay_monitor SET below_min = 1 WHERE item_id = '%s'", $itemId));
// 						// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Below minimum alert!\r\n", FILE_APPEND);
// 					}
// 				}

// 				else
// 				{
// 					// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Invalid strategy\r\n", FILE_APPEND);
// 				}
// 			}

// 		}

// 	} catch(Exception $ex) {
// 		LogForJob("EXCEPTION IN updateCompetitorByItemID: ".$ex->getMessage());
// 	}
	
// }


// function getCompetitorItemsByItemID($itemID, $ebayCompetitorItems) {
// 	$competitorItems = array();
// 	#LogForJob("Look for competitors items for ".$itemID);
// 	foreach($ebayCompetitorItems as $item) {

// 		if($itemID == $item['competitor_item_id']) {
// 			array_push($competitorItems, $item);
// 		}
// 	}
// 	return $competitorItems;
// }

// function updateDeletedItems($ids) {
// 	$idsStr = implode(',', $ids);
// 	$sql = "UPDATE eoc.ebay_monitor SET deleted = 1, last_scraped = NOW() WHERE item_id IN (%s)";

// 	mysql_query(query($sql, $idsStr));
	
// }


// function addIdsNeedCalculateShpCostToTempDatabase($itemIdsNeedCalculateShpCost) {
//     if(!empty($itemIdsNeedCalculateShpCost)) {
//         $sql = "
//             INSERT INTO ebay_item_need_calculate_shipping 
//             (item_id, user_id, status) VALUES 
//         ";

//         foreach($itemIdsNeedCalculateShpCost as $itemId) {
//             $sql .= " (".$itemId . ", 1, 0),";
//         }

//         $sql = substr($sql, 0, -1);
//         mysql_query($sql);
//     }
// }
// function updateStatusEbayListing($itemID,$status){

// 	$sql ="UPDATE eoc.ebay_monitor SET status= %s WHERE item_id = %s";

// 	mysql_query(query($sql,$status,$itemID));
// }

$jobStarted = new DateTime();

LogForJob("============== Ebay Monitor Job Started At: ".$jobStarted->format('Y-m-d H:i:s')." ================");

try {
	$baseEbayMonitor = new BaseEbayMonitor;
	$q = "
		SELECT COUNT(*) / 1440 AS rate
		FROM eoc.ebay_monitor
		WHERE disable = 0
	";

	$rate = ceil(mysql_fetch_row(mysql_query($q))[0]);
	
	#echo "Scraping rate: {$rate}\r\n";

	$q = "
		SELECT item_id, vs_ours, strategy
		FROM eoc.ebay_monitor
		WHERE last_scraped < DATE_SUB(NOW(), INTERVAL 1 DAY)
		AND disable = 0
		AND strategy != 99
		ORDER BY last_scraped 
		LIMIT 150 ";

	$rows = mysql_query($q);
	$data = $baseEbayMonitor->renderData($rows);
	$ids = $data['ids'];
	$dbItems = $data['dbItems'];
	$strategies = $data['strategies'];

	#LogForJob("NUMBER OF RECORDS: ".count($ids));
	#LogForJob("NUMBER competitorItems in DB: ".count($dbItems));


	$subIds = array_chunk($ids, 20);
	
	
	$sumXml = $baseEbayMonitor->GetMultipleItems($subIds);

	$ebayCompetitorItems = $baseEbayMonitor->callEbayAPIToGetItems($dbItems,'vs_ours');

	$ebayItems = [];
	if (!empty($sumXml)) {

		#LogForJob("============== Xml is not empty ==============");

		$renderXml = $baseEbayMonitor->renderXML($sumXml);

		$itemIdsNeedCalculateShpCost = $renderXml['itemIdsNeedCalculateShpCost'];
		$invalidIds = $renderXml['invalidIds'];
		$ebayItems = $renderXml['ebayItems'];

		$baseEbayMonitor->addIdsNeedCalculateShpCostToTempDatabase($itemIdsNeedCalculateShpCost);

		#LogForJob("NUMBER OF EBAY ITEMS: ".count($ebayItems));

		$baseEbayMonitor->updateItemAndCompetitors($ebayItems, $ebayCompetitorItems, $strategies);

		$baseEbayMonitor->updateDeletedItems($invalidIds);

	}

} catch(Exception $ex) {
	LogForJob("EXCEPTION: ".$ex->getMessage());
}



mysql_close();

$jobEnded = new DateTime();

LogForJob("============== Ebay Monitor Job Total Run Time: ".$jobStarted->diff($jobEnded)." ================");

LogForJob("============== Ebay Monitor Job Ended At: ".$jobEnded->format('Y-m-d H:i:s')." ================");

?>