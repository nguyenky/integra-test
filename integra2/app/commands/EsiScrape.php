<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class EsiScrape extends Command
{
    protected $name = 'esi:scrape';
    protected $description = 'Scrapes the products for the next target vehicle and category.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        DB::disableQueryLog();
        set_time_limit(0);

        $re = "/ProdMaster\\('([^']+)'/i";

        for ($i = 0; $i < 10; $i++)
        {
            $targetRow = EsiTarget::with(['vehicle', 'category'])->orderBy('last_scraped')->first();

            if (empty($targetRow))
            {
                $this->error("No targets to update! Run esi:update_targets first.");
                return;
            }

            $target = $targetRow->toArray();
            $page = 1;
            $updated = false;

            while (true)
            {
                $data = '';

                try
                {
                    $this->info("Scraping " . $target['vehicle']['year'] . ' ' . $target['vehicle']['make'] . ' ' . $target['vehicle']['model'] . ' - ' . $target['category']['title'] . " Page {$page}...");
                    $make = trim(explode(' ', trim($target['vehicle']['make']))[0]);
                    $url = "http://67.228.201.103/cgi-bin/e3catalog.exe?w3exec=ken.completec&w3serverpool=e3commerce1&E3Bridge=108.166.4.4&E3Port=1505&w3altsubmit=xrefsearch&formname=xrefsearch&submit=*" . $target['category']['id'] . "*&userid=" . EsiUtils::$userId . "&custno=" . EsiUtils::$custNo . "&Year=" . $target['vehicle']['year'] . "&Mfg={$make}&Model=" . $target['vehicle']['model_id'] . "&pageno={$page}";
                    $data = file_get_contents($url);
                }
                catch (Exception $e)
                {
                }

                $idx1 = stripos($data, 'No matches');
                $idx2 = stripos($data, 'Enter the quantity');

                if ($idx1 === false && $idx2 === false)
                {
                    $this->error("Scraping error on {$url} - {$data}");
                    break;
                }

                if (!$updated)
                {
                    $updated = true;
                    $targetRow->last_scraped = Carbon::now();
                    $targetRow->save();
                }

                if ($idx2 === false) break;
                else $page++;

                preg_match_all($re, $data, $matches, PREG_SET_ORDER);

                foreach ($matches as $match)
                {
                    $sku = trim($match[1]);

                    $productId = EsiProduct::where('sku', $sku)->pluck('id');

                    if (empty($productId))
                    {
                        $this->info("Scraping {$sku}...");
                        $productId = EsiUtils::UpdateSKU($sku);
                    }
                    else $this->info("Existing sku: {$sku}");

                    EsiCompatibility::firstOrCreate(['esi_product_id' => $productId, 'esi_target_id' => $targetRow->id]);
                }
            }
        }
    }
}
