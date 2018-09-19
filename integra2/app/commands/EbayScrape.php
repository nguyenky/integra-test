<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class EbayScrape extends Command
{
	protected $name = 'ebay:scrape';
    protected $description = 'Scrapes the listings of the specified seller.';

	public function __construct()
	{
		parent::__construct();
	}

	public function fire()
	{
        DB::disableQueryLog();

		$keywords = Compatibility::select('make')->distinct()->get()->toArray();

        foreach ($keywords as $keyword)
        {
            EbayUtils::GetSellerListings($this->argument('sellerId'), $keyword['make']);
        }
	}

	protected function getArguments()
	{
		return array
        (
			array('sellerId', InputArgument::REQUIRED, 'eBay seller ID'),
		);
	}
}
