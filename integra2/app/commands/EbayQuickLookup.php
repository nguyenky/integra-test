<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class EbayQuickLookup extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'quick-lookup';

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

	protected $objProduct;

	public function __construct()
	{
		parent::__construct();

		$this->objProduct = new ProductController();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		set_time_limit(0);

		while (true) 
		{
			try 
			{

				$lookup_csv_file = EbayQuickLookupCsvfile::where('status',0)->with('EbayQuickLookupPendings')->get();

				if (empty($lookup_csv_file)) 
				{
					sleep(30);
					continue;
				}

				if ($lookup_csv_file)
					$this->handleLookupCSVFile($lookup_csv_file);

			} 
			catch (Exception $ex) {
				Log::error("======== ERROR: ".$ex->getMessage());
			}

		}
	}

	public function handleLookupCSVFile($attributes){

		if(!$attributes)
			return false;

		foreach ($attributes as $key => $value) {

			if($value->ebay_quick_lookup_pendings)
				$this->handleLookupPending($value->ebay_quick_lookup_pendings,$value->links,$value->id);
			EbayQuickLookupCsvfile::find($value->id)->update(['status'=>1]);
		}

	
			
	}
	public function handleLookupPending($attributes,$links,$id){
		if(!$attributes)
			return false;

		foreach ($attributes as $key => $value) {

			if($value->status == 0){

				$lookups = $this->objProduct->lookup($value->mpn);
				if($lookups)
					$this->insertCSVFile($lookups,$links);

				EbayQuickLookupPending::find($value->id)->update(['status'=>1]);
			}
			
			
			//update status in ebay quick lookup pendings = > 1
		}
		// 
	}

	public function insertCSVFile($attributes,$links){
		
		if(!$attributes)
			return false;
		$links = public_path().'/'.$links;

		$file = fopen($links,"a+");
		try{
			foreach ($attributes as $key => $value) {
			
				$sum = $this->sum($value);
				$data = [];
				$data[] = 'W'.$value['supplier'];
				$data[] = $value['mpn'];
				$data[] = !empty($value['alt']) ? $value['alt'][0] : '';
				$data[] = $value['desc'];
				$data[] = $value['brand'];
				$data[] = $value['weight'];
				$data[] = $sum;
				$data[] = $value['qtys'];
				$data[] = $value['positions'];
				fputcsv($file, $data);
			}

			fclose($file);
		}catch (Exception $e)
        {
            \Log::info(' Errors to insert csv file - '.$e->getMessage());
        }
		

	}

	public function sum($array){
		$sum = 0;

		foreach ($array as $key => $value) {
			$first_char = explode('_', $key);

			$first_char =$first_char[0];

			if($first_char === 'site'){
				$sum += $value;
			}
		}

		return $sum;

	}


}