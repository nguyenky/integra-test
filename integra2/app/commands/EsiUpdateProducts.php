<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class EsiUpdateProducts extends Command
{
    protected $name = 'esi:update_products';
    protected $description = 'Updates product attributes.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        for ($i = 0; $i < 5; $i++)
        {
            $sku = EsiProduct::orderBy('last_scraped')->pluck('sku');

            if (empty($sku))
            {
                $this->error("No products to update! Run esi:scrape first.");
                return;
            }

            try
            {
                $this->info("Updating {$sku}...");
                EsiUtils::UpdateSKU($sku);
            }
            catch (Exception $e)
            {
                $this->error("Scraping error: " . $e->getMessage());
            }
        }
    }
}
