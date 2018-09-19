<?php
require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/counter_utils.php');

class EbayItemShippingCostModel {
	public function __construct() {
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
	}

	public function getItemIds() {
		$sql = "
			SELECT item_id FROM ebay_item_need_calculate_shipping 
			WHERE status = 0 
		";

		$rows = mysql_query($sql);
		$itemIds = [];
		foreach($rows as $row) {
			array_push($itemIds, $row[0]);
		}

		return $itemIds;
	}

	public function removeItemsInIDs($itemIds) {

		if(!empty($itemIds)) {
			$itemIdsStr = implode(', ', $itemIds);

			$sql = "
				DELETE FROM ebay_item_need_calculate_shipping 
				WHERE item_id IN (%s)
			";

			mysql_query(sprintf($sql, $itemIdsStr));
		}

	}

	public function updateItemsStatus($itemIds) {

	}
}

class EbayResearchModel {

	public function __construct() {
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
	}

	public function updateShippingCost($shippingCost, $itemId) {
		$sql = "
			UPDATE eoc.ebay_research SET shipping_cost = %d 
			WHERE item_id = %d AND user_id = %d 
		";

		return mysql_query(sprintf($sql, $shippingCost));
	}
}


class EbayAPIProcessor {

	public function callAPIToGetShippingCost($itemId) {
		$match = [];
		$shippingCost = 0;
		$res = file_get_contents("http://www.ebay.com/itm/getrates?item=${itemId}&quantity=1&country=1&zipCode=77057&co=0&cb=j");
		/*  start insert counter */
		CountersUtils::insertCounterProd('getrates','Ebay Egrid Shipping',APP_ID);
		/*  end insert counter */
		
		preg_match('/US \$(?P<shipping>[^<]+)/i', $res, $match);
		
		if (isset($match) && array_key_exists('shipping', $match))
		    $shippingCost = $match['shipping'];

		return $shippingCost;
	}
}

class UpdateShippingCostJob {

	private $tempItemModel;
	private $ebayResearchModel;
	private $ebayApiProcessor;

	public function __construct(EbayItemShippingCostModel $tempItemModel, EbayResearchModel $ebayResearchModel, EbayAPIProcessor $ebayApiProcessor) {
		$this->tempItemModel = $tempItemModel;
		$this->ebayResearchModel = $ebayResearchModel;
		$this->ebayApiProcessor = $ebayApiProcessor;
	}

	public function doingUpdateShippingCost() {
		$listItemIdsUpdateSuccess = [];
		$itemIds = $this->tempItemModel->getItemIds();

		foreach($itemIds as $itemId) {
			$shippingCost = $this->ebayApiProcessor->callAPIToGetShippingCost($itemId);

			if($shippingCost != 0) {
				$status = $this->ebayResearchModel->updateShippingCost($shippingCost, $itemId);

				if($status) {
					array_push($listItemIdsUpdateSuccess, $itemId);
				}
			}
		}

		return $listItemIdsUpdateSuccess;
	}

	public function removeItemUpdated($itemIds) {
		$this->tempItemModel->removeItemsInIDs($itemIds);
	}
}

function LogForJob($message) {
    $filename = "ebay_research_update_shipping_cost_job.log";
    file_put_contents(LOGS_DIR . $filename, $message."\r\n", FILE_APPEND);
}

LogForJob("========== Start Updating Shipping Cost Job ". date('Y-m-d H:i:s') ." ==========");

$tempItemModel = new EbayItemShippingCostModel();
$ebayResearchModel = new EbayResearchModel();
$ebayApiProcessor = new EbayAPIProcessor();

$job = new UpdateShippingCostJob($tempItemModel, $ebayResearchModel, $ebayApiProcessor);
$itemIdsUpdatedSuccess = $job->doingUpdateShippingCost();
$job->removeItemUpdated($itemIdsUpdatedSuccess);

LogForJob("========== End Shipping Cost Job ". date('Y-m-d H:i:s') ." ==============");

?>