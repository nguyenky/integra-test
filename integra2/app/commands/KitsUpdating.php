<?php

use Illuminate\Console\Command;


class KitsUpdating extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'kit:updating';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Read csv file to update kits Components';

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
		Log::info("========= Start kit updating Job ========");
		$directory = public_path() . "/uploads/kits";
		$files = scandir($directory);
		$matches = array();
		if($files && count($files) > 0) {
			foreach($files as $file) {
				if(!in_array($file, array(".",".."))) {
					Log::info("Processing file: ".$file);

					$lines = file("{$directory}/$file", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
					Log::info("Num lines: ".count($lines));
					for($i = 1; $i < count($lines); $i++) {
						Log::info($lines[$i]);

						$cols = str_getcsv(trim($lines[$i]), ",");
						if (count($cols) < 4) {
							$cols = str_getcsv(trim($lines[$i]), ";");
							if (count($cols) < 4) {
								continue;
							} 
						}
						$newMatch = $this->processData($cols);
						$matches = array_merge($matches, $newMatch);
					}
					
					unlink("{$directory}/$file");
				}
				
			}
		}

		if(!empty($matches)) {
			Log::info("Have new matches");
			Product::updateKitsComponents($matches);
		}
		Log::info("============= DONE ==============");
	}

	protected function processData($data) {
		$currentSkuEDPTrans = $data[1];
		$currentEkTransQtyArr = explode('/', $data[2]);
		$currentMatchSkus = $this->getMatchSkus($currentEkTransQtyArr);
		

		$expectedSkuTransArr = explode('/', $data[3]);
		$expectedMatchSkus = $this->getMatchSkus($expectedSkuTransArr);

		return $this->createNewMatching($currentSkuEDPTrans, $currentMatchSkus, $expectedMatchSkus);
	}

	protected function getMatchSkus($currentEkTransQtyArr) {
		$skuTransArr = array();

		foreach($currentEkTransQtyArr as $skuQty) {
			$skus = explode('-', $skuQty);
			array_push($skuTransArr, $skus[0]);
		}
		return $skuTransArr;
	}


	private function getProductIdBySku($sku) {
		$product = Product::where('sku', trim($sku))->first();
		if($product) {
			return $product->id;
		}
		return null;
	}



	private function createNewMatching($sku, $currentMatchSkus, $expectedMatchSkus) {
		$productID = $this->getProductIdBySku($sku);

		$matches = array();
		for($i = 0; $i < count($currentMatchSkus); $i++) {
			Log::info("Sku: ".$currentMatchSkus[$i]);

			$oldMatchProductId = $this->getProductIdBySku($currentMatchSkus[$i]);
			Log::info("Old product id: ".$oldMatchProductId);

			Log::info("new sku: ".$expectedMatchSkus[$i]);
			$newMatchProductId = $this->getProductIdBySku($expectedMatchSkus[$i]);
			Log::info("New Match Product ID: ".$newMatchProductId);

			if($oldMatchProductId != null && $newMatchProductId != null) {
				$match = array('productId' => $productID, 'oldMatchProductId' => $oldMatchProductId, 
								'newMatchProductId' => $newMatchProductId);
				array_push($matches, $match);
			}
			
		}

		return $matches;
	}
}
