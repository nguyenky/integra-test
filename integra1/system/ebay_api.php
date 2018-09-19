<?php

require_once('config.php');
require_once('utils.php');
require_once('counter_utils.php');
require_once('mage_utils.php');

class EbayAPI {

	private $ebay_app_id;
	private $items;
	private $itemIds;
	private $sumXmlsResponse;
	private $invalidItemIDs;


	public function __construct($ebay_app_id, $itemIds) {
		$this->ebay_app_id = $ebay_app_id;
		$this->itemIds = $itemIds;
		$this->items = [];
		$this->sumXmlsResponse = [];
		$this->invalidItemIDs = [];
	}

	public function searchMulitpleItems($items) {
		$results = [];
		if(!empty($items)) {
			$this->itemIds = [];
			$sellerItems = [];
			$competitorItems = [];
			$activeItems = [];
			foreach($items as $item) {
				$rawkeywords = str_replace(';', '', $item['comp_mpn']); 
				$keyword = urlencode($rawkeywords);
				$sellerItemIds = $this->searchSellerItemsByKeywords($keyword);

				$sellerItems = $this->getItems($sellerItemIds);
				$results['seller_items'][] = $sellerItems;

				$activeItems[] = $this->getActiveItems($sellerItems, $item['id']);

				$competitorItemIds = $this->searchCompetitorItemsByKeyword($keyword);
				$competitorItems = $this->getItems($competitorItems);
				$results['competitor_items'][] = $competitorItems;

				$activeItems[] = $this->getActiveItems($competitorItems, $item['id'], true);
			}

			$results['active_items'] = $activeItems;

		}

		return $results;
	}

	

	private function getActiveItems($items, $itemId, $isCompetitor = false) {
		$activeItems = [];
		$activeItems[$itemId]['item_id'] = $itemId;
		$activeItems[$itemId] = $this->getSummaryInformation($itemId, $itemIds, $activeItems, $isCompetitor);

		$activeItems[$itemId]['active_ids'] = array_column($items, 'id');

		return $activeItems;
	}

	private function getSummaryInformation($itemId, $items, $activeItems, $isCompetitor = false) {
		$info = [];
		foreach($items as $item) {
			if (($info['id'] == $itemId) && !$isCompetitor) {

				$ourTotal = $item['price'] + $item['shipping_cost'];

			    $activeItems[$itemId]['ourTotal'] = $ourTotal;
			    $activeItems[$itemId]['ourSold'] = $item['num_sold'];
			    $activeItems[$itemId]['lowTotal'] = $ourTotal;
			    $activeItems[$itemId]['lowTotalSeller'] = EBAY_SELLER;
			    $activeItems[$itemId]['topSold'] = $activeItems[$itemId]['ourSold'];
			    $activeItems[$itemId]['topSoldSeller'] = EBAY_SELLER;

			} else if($isCompetitor) {
				$total = $item['price'] + $item['shipping_cost'];
				if ($total < $activeItems[$itemId]['lowTotal'])
				{
				    $activeItems[$itemId]['lowTotal'] = $total;
				    $activeItems[$itemId]['lowTotalSeller'] = $item['seller_id'];
				}
				if ($activeItems[$itemId]['topSold'] < $item['num_sold'])
				{
				    $activeItems[$itemId]['topSold'] = $item['num_sold'];
				    $activeItems[$itemId]['topSoldSeller'] = $item['seller_id'];
				}
			}
		}
	}

	public function searchCompetitorItemsByKeyword($keyword) {
		$seller = EBAY_SELLER;
		$res = file_get_contents("http://svcs.ebay.com/services/search/FindingService/v1?OPERATION-NAME=findItemsByKeywords&SERVICE-VERSION=1.0.0&SECURITY-APPNAME=" . APP_ID . "&RESPONSE-DATA-FORMAT=XML&REST-PAYLOAD&outputSelector(0)=SellerInfo&itemFilter(0).name=ExcludeSeller&itemFilter(0).value(0)=${seller}&itemFilter(1).name=Condition&itemFilter(1).value=New&keywords=${keywords}");
		/*  start insert counter */
		CountersUtils::insertCounter('findItemsByKeywords','Ebay Sales',APP_ID);
		/*  end insert counter */
		$xml = simplexml_load_string($res);
		$ids = array();
		if (!empty($xml)) {
			foreach ($xml->searchResult->item as $i) {
			    $ids[] = (string)$i->itemId;
			}
			#array_push($this->itemIds, $ids);
		}
	}

	public function searchSellerItemsByKeywords($keyword) {
		$seller = EBAY_SELLER;
		$res = file_get_contents("http://svcs.ebay.com/services/search/FindingService/v1?OPERATION-NAME=findItemsByKeywords&SERVICE-VERSION=1.0.0&SECURITY-APPNAME=" . APP_ID . "&RESPONSE-DATA-FORMAT=XML&REST-PAYLOAD&outputSelector(0)=SellerInfo&itemFilter(0).name=Seller&itemFilter(0).value(0)=${seller}&keywords=${keywords}");
		$xml = simplexml_load_string($res);
		/*  start insert counter */
		CountersUtils::insertCounter('findItemsByKeywords','Ebay Sales',APP_ID);
		/*  end insert counter */
		$ids = array();
		if (!empty($xml)) {
			foreach ($xml->searchResult->item as $i) {
			    $ids[] = (string)$i->itemId;
			}
			#array_push($this->itemIds, $ids);
		}

	}


	public function getItems($itemIds = null) {
		if($itemIds != null) {
			$this->itemIds = $itemIds;
		}
		if(!empty($this->itemIds)) {
			$this->callShoppingAPIToGetXML();
			$this->getItemsDataFromXML();	
		}
	}

	public function getResponseItems() {
		return $this->items;
	}

	private function getItemsDataFromXML() {
		if(!empty($this->sumXmlsResponse)) {
			foreach($this->sumXmlsResponse as $xml) {
				$this->getItemsInSubXML($xml);
			}
		}
	}

	private function getItemsInSubXML($xml) {
		if(!empty($xml->Errors)) {
			
			$invalidIds = explode(",", $xml->Errors->ErrorParameters->Value);
			array_push($this->invalidItemIDs, $invalidIds);
		}

		foreach($xml->Item as $itemXML) {
			$this->items[] = $this->getDetailInformationOfItem($itemXML);
		}
	}

	private function getFitments($item) {
		if (!empty($item['compatibility']))
		{
		    $xml2 = simplexml_load_string($item['compatibility']);
		    if (!empty($xml2->Compatibility))
		    {
		        foreach ($xml2->Compatibility as $c)
		        {
		            $fit['make'] = '';
		            $fit['model'] = '';
		            $fit['year'] = '';
		            $fit['trim'] = '';
		            $fit['engine'] = '';
		            $fit['notes'] = '';
		            foreach ($c->NameValueList as $n)
		            {
		                if ($n->Name == 'Year') $fit['year'] = trim($n->Value);
		                else if ($n->Name == 'Make') $fit['make'] = trim($n->Value);
		                else if ($n->Name == 'Model') $fit['model'] = trim($n->Value);
		                else if ($n->Name == 'Trim') $fit['trim'] = trim($n->Value);
		                else if ($n->Name == 'Engine') $fit['engine'] = trim($n->Value);
		            }
		            $fit['notes'] = trim($c->CompatibilityNotes);
		            $item['fitment'][] = $fit;
		        }
		        #TODO: NEED TO RE IMPLEMENT
		        self::SaveFitment($item['id'], $item['fitment']);
		    }
		}
	}

	private function getItemItemSpecifics($item, $itemXML) {
		$item['mpn'] = '';
		$item['ipn'] = '';
		$item['opn'] = '';
		$item['placement'] = '';
		$item['brand'] = '';
		$item['comp_mpn'] = '';
		$item['comp_brand'] = '';
		$item['comp_name'] = '';
		$item['part_notes'] = '';
		$item['comp_weight'] = '';
		if (!empty($itemXML->ItemSpecifics) && !empty($itemXML->ItemSpecifics->NameValueList))
		{
		    $placements = array();
		    $others = array();
		    foreach ($itemXML->ItemSpecifics->NameValueList as $pair)
		    {
		        if ($pair->Name == 'Manufacturer Part Number')
		            $item['mpn'] = (string)$pair->Value;
		        else if ($pair->Name == 'Interchange Part Number')
		            $item['ipn'] = (string)$pair->Value;
		        else if ($pair->Name == 'Other Part Number')
		            $item['opn'] = (string)$pair->Value;
		        else if ($pair->Name == 'Placement on Vehicle')
		        {
		            foreach ($pair->Value as $v)
		                $placements[] = $v;
		        }
		        else if ($pair->Name == 'Part Brand')
		            $item['brand'] = (string)$pair->Value;
		        else if ($pair->Name == 'Brand')
		            $item['brand'] = (string)$pair->Value;
		        else if ($pair->Name == 'Surface Finish')
		            $item['surface_finish'] = (string)$pair->Value;
		        else if ($pair->Name == 'Warranty')
		            $item['warranty'] = (string)$pair->Value;
		        else

		            $others[] = array((string)$pair->Name, (string)$pair->Value);
		    }
		    $item['placement'] = implode(', ', $placements);
		    $item['other_attribs'] = $others;
		}
	}

	private function getSkuInformation($item) {
		if (!empty($item['sku']))
		{
		    $parts = GetSKUParts(array($item['sku'] => 1));
		    foreach ($parts as $sku => $qty)
		    {
		        if (stripos($sku, '/'))
		        {
		            $sku = str_replace('/', '.', strtoupper($sku));
		            $sku = str_replace('EOCF', 'EOCS', strtoupper($sku));
		        }
		        if (stripos($sku, '.') !== false)
		        {
		            //$mpn = substr($sku, 4);
		            $mpn = $sku;
		            $brandId = '';
		            $dotIdx = strpos($sku, '.');
		            if ($dotIdx)
		            {
		                $mpn = substr($sku, 0, $dotIdx);
		                $brandId = substr($sku, $dotIdx + 1);
		            }
		            if (!empty($brandId))
		                $res = mysql_query(sprintf("SELECT name, brand, weight, part_notes FROM ssf_items WHERE mpn = '%s' AND brand_id = '%s'", mysql_real_escape_string($mpn), mysql_real_escape_string($brandId)));
		            else

		                $res = mysql_query(sprintf("SELECT name, brand, weight, part_notes FROM ssf_items WHERE mpn = '%s' LIMIT 1", mysql_real_escape_string($mpn)));
		            $row = mysql_fetch_row($res);
		            $item['mpns'][] = $mpn;
		            $item['names'][] = $row[0];
		            $item['brands'][] = $row[1];
		            $item['weights'][] = $row[2] * $qty;
		            $item['notes'][] = $row[3];
		        }
		        else if (startsWith($sku, "EOCE"))
		        {
		            continue;
		        }
		        else
		        {
		            // ignore filler item for free shipping
		            if ($sku == IMC_FILLERITEM)
		                continue;
		            //$mpn = substr($sku, 3);
		            $mpn = $sku;
		            $res = mysql_query(sprintf("SELECT name, brand, weight, part_notes FROM imc_items WHERE mpn = '%s'", mysql_real_escape_string($mpn)));
		            $row = mysql_fetch_row($res);
		            $item['mpns'][] = $mpn;
		            $item['names'][] = $row[0];
		            $item['brands'][] = $row[1];
		            $item['weights'][] = $row[2] * $qty;
		            $item['notes'][] = $row[3];
		        }
		    }
		    if (!empty($item['mpns']))
		        $item['comp_mpn'] = trim(implode('; ', array_unique($item['mpns'])), '; ');
		    if (!empty($item['brands']))
		        $item['comp_brand'] = trim(implode('; ', array_unique($item['brands'])), '; ');
		    if (!empty($item['names']))
		        $item['comp_name'] = trim(implode('; ', array_unique($item['names'])), '; ');
		    if (!empty($item['notes']))
		        $item['part_notes'] = trim(implode('; ', array_unique($item['notes'])), '; ');
		    if (!empty($item['weights']))
		        $item['comp_weight'] = array_sum($item['weights']);
		    //unset($item['mpns']);
		    unset($item['brands']);
		    unset($item['names']);
		    unset($item['notes']);
		    unset($item['weights']);
		}
	}

	private function getDetailInformationOfItem($itemXML) {
		$item = [];
		$itemID = (string)$itemXML->ItemID;
		$item['id'] = $itemID
		$item['title'] = (string)$itemXML->Title;
		$item['description'] = (string)$itemXML->Description;
		$item['category'] = (string)$itemXML->PrimaryCategoryID;
		$allCat = (string)$itemXML->PrimaryCategoryName;
		$cats = explode(':', $allCat);
		$catName = $allCat;
		if (count($cats) > 1)
		    $catName = $cats[count($cats) - 1];
		$item['category_name'] = $catName;
		$item['num_avail'] = (string)$itemXML->Quantity;
		$item['price'] = (string)$itemXML->CurrentPrice;
		$item['num_sold'] = (string)$itemXML->QuantitySold;
		$hits = (string)$itemXML->HitCount;
		settype($hits, 'integer');
		$item['num_hit'] = $hits;
		$item['condition'] = (string)$itemXML->ConditionDisplayName;
		$item['sku'] = (string)$itemXML->SKU;
		$item['seller_id'] = (string)$itemXML->Seller->UserID;
		$item['seller_score'] = (string)$itemXML->Seller->FeedbackScore;
		$item['seller_rating'] = (string)$itemXML->Seller->PositiveFeedbackPercent;
		$item['seller_top'] = ((string)$itemXML->Seller->TopRatedSeller == 'true' ? 1 : 0);
		$item['picture_small'] = (string)$itemXML->GalleryURL;
		$item['picture_big'] = (string)$itemXML->PictureURL;
		$item['shipping_cost'] = (string)$itemXML->ShippingCostSummary->ShippingServiceCost;
		$item['shipping_type'] = (string)$itemXML->ShippingCostSummary->ShippingType;
		if ($item['shipping_type'] == 'Calculated')
		{
		    $res = file_get_contents("http://www.ebay.com/itm/getrates?item=${itemId}&quantity=1&country=1&zipCode=77057&co=0&cb=j");
		    /*  start insert counter */
			CountersUtils::insertCounter('getrates','Ebay Sales',APP_ID);
			/*  end insert counter */
		    unset($match);
		    preg_match('/US \$(?P<shipping>[^<]+)/i', $res, $match);
		    if (isset($match) && array_key_exists('shipping', $match))
		        $item['shipping_cost'] = $match['shipping'];
		}
		$item['num_compat'] = (string)$itemXML->ItemCompatibilityCount;
		if (empty($item['num_compat']))
		    $item['num_compat'] = 0;
		$item['compatibility'] = str_replace('<NameValueList/>', '', (string)$itemXML->ItemCompatibilityList->asXML());
		
		$this->getFitments($item);

		$this->getItemItemSpecifics($item, $itemXML);
		
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$this->getSkuInformation($item);

		return $item;
	}

	private function callShoppingAPIToGetXML() {
		$subItemIds = array_chunk($this->itemIds, 20);

		foreach($subItemIds as $itemId) {
			$idsStr = implode(',', $itemId);
			$res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetMultipleItems&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${idsStr}&IncludeSelector=Details,ShippingCosts");
			/*  start insert counter */
			CountersUtils::insertCounter('GetMultipleItems','Ebay Sales',APP_ID);
			/*  end insert counter */

			$xml = simplexml_load_string($res);

			array_push($this->sumXmlsResponse, $xml);
		}

	}
}

?>