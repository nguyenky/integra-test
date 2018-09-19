<?php

use Illuminate\Console\Command;

class EbayScrapeInventory extends Command
{
	protected $name = 'ebay:scrape_inventory';
    protected $description = 'Updates the inventories of the scraped listings.';

	public function __construct()
	{
		parent::__construct();
	}

	public function fire()
	{
        DB::disableQueryLog();

        EbayScrapedListing::chunk(50, function($listings)
        {
            foreach($listings as &$listing)
            {
                $listing->available = EbayUtils::GetAvailable($listing->item_id);
                $this->info($listing->item_id . ': ' . $listing->available);
                $listing->save();
            }
        });
	}
}
