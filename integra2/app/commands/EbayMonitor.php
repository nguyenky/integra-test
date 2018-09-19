<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
class EbayMonitor extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'ebay:monitor';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command description.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{

		Log::info("================ START EBAY MONITOR JOB AT ". date('Y-m-d H:i:s') ." ==============");


		try {

			$ebay_monitor = EbayMonitorNew::select(DB::raw('count(*)/1440 as rate'))->where('disable',0)->first();
			$rate = $ebay_monitor;

			if($rate){
				$rate = ceil((int)$rate['rate']);
			}

			$rows = EbayMonitorNew::select('item_id','vs_ours','strategy')->where('last_scraped','<',\Carbon\Carbon::now()->subDays(1))->where('disable',0)->where('strategy','<>',99)->limit(150)->get();
			$ids=[];
			$dbItems = [];
			$strategies = [];

			foreach ($rows as $k => $row) {
				$ids[] = $row->item_id;
				if($row->vs_ours != null && $row->vs_ours != '')
					array_push($dbItems, array('item_id' => $row->item_id, 'vs_ours' => $row->vs_ours));

				$strategies[$row->item_id] = $row->strategy;

			}

			$subIds = array_chunk($ids, 20);
			$sumXml = [];
			$invalidIds = [];
			$itemIdsNeedCalculateShpCost = [];
			foreach($subIds as $id) {
				$idsStr = implode(',', $id);

				// LogForJob("CALL EBAY API FOR: ".$idsStr);

				$res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetMultipleItems&responseencoding=XML&appid=" . Config::get('integra.ebay.APP_ID') . "&siteid=0&version=847&ItemID=${idsStr}&IncludeSelector=Details,ShippingCosts");
				EbayApiCallCounter::create([
							'ebay_service_name'	=> 'GetMultipleItems',
							'feature_name'		=> 'Ebay Monitor',
							'token'				=> Config::get('integra.ebay.APP_ID')
						]); 

				$xml = simplexml_load_string($res);

				array_push($sumXml, $xml);
			}
			
			$ebayCompetitorItems = $this->callEbayAPIToGetItems($dbItems, 'vs_ours');
			$ebayItems = [];
			if (!empty($sumXml)) {

				#LogForJob("============== Xml is not empty ==============");

				foreach($sumXml as $xml) {

					if(!empty($xml->Errors)) {
						#LogForJob("======================== ERRORS ======================");
						
						$invalidIds = explode(",", $xml->Errors->ErrorParameters->Value);
					}

					foreach($xml->Item as $item) {
						$itemID = (integer)$item->ItemID;

						#LogForJob("GET ITEM ID: ".$itemID);

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

						$status=0;
						if ($shippingType == 'Calculated') {

							array_push($itemIdsNeedCalculateShpCost, $id);
						    $shipping = 0;
						    $this->updateStatusEbayListing($itemID,1);   
							$status=1;
						}
						$shipping = floatval($shipping);
						$price += $shipping;

						array_push($ebayItems, array('item_id' => $itemID, 'title' => $title, 'price' => $price, 'sold' => $sold,'status'=> $status));

					}

				}

				$this->addIdsNeedCalculateShpCostToTempDatabase($itemIdsNeedCalculateShpCost);

				#LogForJob("NUMBER OF EBAY ITEMS: ".count($ebayItems));

				$this->updateItemAndCompetitors($ebayItems, $ebayCompetitorItems, $strategies);

				$this->updateDeletedItems($invalidIds);

			}

		} catch (Exception $ex) {
			Log::error("ERROR: ".$ex->getMessage());
			Log::error("FULL TRACE ERROR: ".$ex->getTraceAsString());
		}
		
		Log::info("================ END EBAY MONITOR JOB AT ". date('Y-m-d H:i:s') ." ==============");

	}

	function addIdsNeedCalculateShpCostToTempDatabase($itemIdsNeedCalculateShpCost) {
	    if(!empty($itemIdsNeedCalculateShpCost)) {
	        foreach($itemIdsNeedCalculateShpCost as $itemId) {
	         //    $item = EbayEtemNeedCalculateShipping::create([
	         //    	'item_id'=>$itemId,
	         //    	'user_id'=>1,
	         //    	'status'=>0,
	        	// ]);

	        }
	    }
	}

	function updateStatusEbayListing($itemID,$status){

			$ebay_monitor = EbayMonitorNew::where('item_id',$itemID)->first();
			$ebay_monitor->status = $status;
			$ebay_monitor->save();
	}

	function updateItemAndCompetitors($ebayItems, $ebayCompetitorItems, $strategies) {
		try {
			// LogForJob("================ START UPDATING DATABASE IN updateItemAndCompetitors ===============");
			#LogForJob("NUMBER OF EBAY ITEMS TO UPDATE: ".count($ebayItems));
			foreach($ebayItems as $ebayItem) {
				
				$result = EbayMonitorNew::where('item_id',$ebayItem['item_id'])->first();
				$result->deleted =0;
				$result->cur_title = $ebayItem['title'];
				$result->cur_price = $ebayItem['price'];
				$result->cur_sold  = $ebayItem['sold'];
				$result->last_scraped  = \Carbon\Carbon::now();
				$result->save();

				$competitorOfItems = $this->getCompetitorItemsByItemID($ebayItem['item_id'], $ebayCompetitorItems);

				#LogForJob(" NUMBER OF COMPETITORS IN updateItemAndCompetitors: ".count($competitorOfItems));

				$this->updateCompetitorByItemID($ebayItem['item_id'], $ebayItem['price'], $competitorOfItems, $strategies,$ebayItem['status']);
			}

		} catch(Exception $ex) {
			// LogForJob("EXCEPTION IN updateItemAndCompetitors: ".$ex->getMessage());
		}
		
	}

	function updateDeletedItems($ids) {

		$deleted = EbayMonitorNew::whereIn('item_id',$ids)->delete();
	}

	function callEbayAPIToGetItems($competitorItems, $dbField) {
		#LogForJob("============== GET COMPETITORS FOR ". count($competitorItems) ." items ============");

		$items = [];
		$invalidIds = [];

		$itemIds = array_column($competitorItems, $dbField);

		#LogForJob("NUMBER OF ITEM IDS COMPETITORS: ".count($itemIds));

		$sumXml = [];

		try {

			$itemIDs = array_chunk($itemIds, 20);

			foreach($itemIDs as $ids) {
				$idsStr = implode(',', $ids);
				#LogForJob("CALL EBAY API TO GET RESULTS FOR: ".$idsStr);
				$res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetMultipleItems&responseencoding=XML&appid=" . Config::get('integra.ebay.APP_ID') . "&siteid=0&version=847&ItemID=${idsStr}&IncludeSelector=Details,ShippingCosts");
				EbayApiCallCounter::create([
							'ebay_service_name'	=> 'GetMultipleItems',
							'feature_name'		=> 'Ebay Monitor',
							'token'				=> Config::get('integra.ebay.APP_ID')
						]);

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

					$competitorItemId = $this->getCompetitorIDByOurId($id, $competitorItems);

					array_push($items, array('our_item_id' => $id, 'ourPrice' => $ourPrice, 'ourSku' => $ourSku, 'ourQty' => $ourQty, 'ourSold' => $ourSold, 'competitor_item_id' => $competitorItemId));	 
				}
			}


		} catch(Exception $ex) {
			// LogForJob("EXCEPTION IN callEbayAPIToGetItems: ".$ex->getMessage());
		}
		
		#LogForJob("NUMBER OF COMPETITORS GET FROM EBAY: ".count($items));

		return $items;
	}
	function getCompetitorIDByOurId($ourID, $items) {
		#LogForJob("========== GET competitor_item_id for ".$ourID);
		foreach($items as $item) {
			
			if($ourID == $item['vs_ours']) {
				#LogForJob("FOUND COMPETITOR ITEM: ".$item['item_id']);
				return $item['item_id'];
			}
		}
		return 0;
	}

	function getCompetitorItemsByItemID($itemID, $ebayCompetitorItems) {
		$competitorItems = array();
		#LogForJob("Look for competitors items for ".$itemID);
		foreach($ebayCompetitorItems as $item) {

			if($itemID == $item['competitor_item_id']) {
				array_push($competitorItems, $item);
			}
		}
		return $competitorItems;
	}

	function updateCompetitorByItemID($itemId, $price, $competitors, $strategies,$status) {
		try {
			#LogForJob("================ UPDATE COMPETITORS ===============");
			#LogForJob("NUMBER COMPETITORS: ".count($competitors));
			foreach($competitors as $comp) {
				#LogForJob("=== Start Updating For ". $comp['our_item_id'] ." ======");
				// get our current price from ebay, 
				$vsOurs = $comp['our_item_id'];
				$ourPrice = $comp['ourPrice'];

				$ebay_listing = EbayListingEOC::where('item_id',$comp['our_item_id'])->first();

				if($ebay_listing){
					$ebay_listing->sku = $comp['ourSku'];
					$ebay_listing->price = $comp['ourPrice'];
					$ebay_listing->quantity = $comp['ourQty'];
					$ebay_listing->active = 1;
					$ebay_listing->save();
				}else{
					$ebay_listing = EbayListingEOC::create([
						'item_id'=>$comp['our_item_id'],
						'sku'=>$comp['ourSku'],
						'price'=>$comp['ourPrice'],
						'quantity'=>$comp['ourQty'],
						'active'=>1
					]);
				}


				// min price from database

				$row = EbayListingEOC::select('min_price')->where('item_id',$comp['our_item_id'])->first();

				$minPrice = $row->min_price;
				if (empty($minPrice)) {
					// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " {$itemId} vs. {$vsOurs} - Invalid min price\r\n", FILE_APPEND);
					// continue;
					return false;
				}

				$strategy = $strategies[$itemId];

				if($status != 1) {
					if ($strategy == 1 )
					{
						// LogForJob("strategy is 1");
						// our price is already below or equal to their price, do nothing
						if ($ourPrice <= $price)
						{
							// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Theirs: $price. Ours: $ourPrice. Doing nothing\r\n", FILE_APPEND);
							return false;
						}

						// target price will be their price or our price floor, whichever is HIGHER
						$targetPrice = max($minPrice, $price);
						$ret = $this->reviseNode($vsOurs, '<StartPrice><![CDATA[' . $targetPrice . ']]></StartPrice>');



						// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $id - $strategy - Theirs: $price. Ours: $ourPrice. Repricing to $targetPrice. $ret\r\n", FILE_APPEND);

						if ($price < $targetPrice)
						{
							// if their price is still lower than our target price, raise alert

							$ebay_monitor = EbayMonitorNew::where('item_id',$itemId)->first();
							$ebay_monitor->below_min =1;
							$ebay_monitor->save();


							// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Below minimum alert!\r\n", FILE_APPEND);
						}
					}

					// Go Under
					else if ($strategy == 2)
					{
						#LogForJob("strategy is 2");
						// our price is already below their price, do nothing
						if ($ourPrice < $price)
						{
							// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Theirs: $price. Ours: $ourPrice. Doing nothing\r\n", FILE_APPEND);
							return false;
						}

						// target price will be their price -0.01 or our price floor, whichever is HIGHER
						$targetPrice = max($minPrice, $price - 0.01);


						$ret = $this->reviseNode($vsOurs, '<StartPrice><![CDATA[' . $targetPrice . ']]></StartPrice>');
						// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Theirs: $price. Ours: $ourPrice. Repricing to $targetPrice. $ret\r\n", FILE_APPEND);

						if ($price <= $targetPrice)
						{
							// if their price is lower or equal to our target price, raise alert


							$ebay_monitor = EbayMonitorNew::where('item_id',$itemId)->first();
							$ebay_monitor->below_min =1;
							$ebay_monitor->save();
							// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Below minimum alert!\r\n", FILE_APPEND);
						}
					}

					else
					{
						// file_put_contents(LOGS_DIR . "reprice.txt", date('Y-m-d H:i:s') . " vs. {$vsOurs} - $itemId - $strategy - Invalid strategy\r\n", FILE_APPEND);
					}
				}

			}

		} catch(Exception $ex) {
			// LogForJob("EXCEPTION IN updateCompetitorByItemID: ".$ex->getMessage());
		}
		
	}
	public function reviseNode($itemId, $node){
		// file_put_contents(LOGS_DIR . "revise_node.txt", "====== START REVISE NODE ". $node ." ======== \n", FILE_APPEND);
        if (empty($node)) 
        	return false;

        $callName = 'ReviseFixedPriceItem';
        $version = '845';
        $url = Config::get('integra.ebay.EBAY_HOST') . "wsapi?callname=${callName}&siteid=" . Config::get('integra.ebay.SITE_ID') . "&appid=" . Config::get('integra.ebay.APP_ID') . "&version=${version}&routing=default";
        $ebayToken = Config::get('integra.ebay.EBAY_TOKEN');

        $data = <<< EOD
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Header>
        <h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>${ebayToken}</eBayAuthToken></h:RequesterCredentials>
    </s:Header>
    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <${callName}Request xmlns="urn:ebay:apis:eBLBaseComponents">
        <Version>${version}</Version>
        <Item>
            <ItemID>${itemId}</ItemID>
            ${node}
        </Item>
    </${callName}Request>
    </s:Body>
</s:Envelope>
EOD;
        $headers = array
        (
            'Content-Type: text/xml',
            'SOAPAction: ""'
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        $date = date_create("now", new DateTimeZone('America/New_York'));
        // file_put_contents(LOGS_DIR . "ebay_match/" . date_format($date, 'Y-m-d_H-i-s') . "_req.txt", $data);
        // file_put_contents(LOGS_DIR . "ebay_match/" . date_format($date, 'Y-m-d_H-i-s') . "_res.txt", $res);

        if (stripos($res, "success") !== false) return 'OK';
        $res = XMLtoArray($res);
        $error='error';
        // $error = asearch($res, 'LONGMESSAGE');
        // if (empty($error))
        //     $error = asearch($res, 'DETAILEDMESSAGE');
        // if (empty($response['error']))
        //     $error = asearch($res, 'FAULTSTRING');
        return $error;
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	// protected function getArguments()
	// {
	// 	// dd(array(
	// 	// 	array('example', InputArgument::REQUIRED, 'An example argument.'),
	// 	// ));
	// 	return array(
	// 		array('example', InputArgument::REQUIRED, 'An example argument.'),
	// 	);
	// }

	// /**
	//  * Get the console command options.
	//  *
	//  * @return array
	//  */
	// protected function getOptions()
	// {
	// 	return array(
	// 		array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
	// 	);
	// }

}
