<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class KitHybrid extends Command
{
	protected $name = 'kit:hybrid';
    protected $description = 'Finds kit combinations given a set of MPNs and generates several versions.';

	public function __construct()
	{
		parent::__construct();
	}

    protected function getArguments()
    {
        return array
        (
            array('max_jobs', InputArgument::REQUIRED, 'Maximum jobs to process per run'),
        );
    }

	public function fire()
    {
        DB::disableQueryLog();

        $q = <<<EOQ
SELECT id, elements
FROM kit_hunter_queue
WHERE job_type = 2
AND stop = 0
ORDER BY id ASC LIMIT ?
EOQ;
        $jobs = DB::select($q, [$this->argument('max_jobs')]);
        // empty queue
        if (empty($jobs)) {
            $this->info("Queue is now empty.");
            return;
        }

        foreach ($jobs as $job) {
            try {
                $queueId = $job['id'];
                $elements = explode('|', $job['elements']);

                // mark as in progress to avoid conflict with other parallel threads
                DB::update('UPDATE integra_prod.kit_hunter_queue SET job_type = 3 WHERE id = ?', [$queueId]);
                $this->info("=========================================================================");

                $groups = [];

                foreach ($elements as $element) {
                    $fields = explode('~', $element);
                    $curQty = $fields[0];
                    $matches = explode(',', $fields[1]);

                    $groups[$element] = [];

                    foreach ($matches as $match) {
                        $curSku = trim($match);

                        if (array_key_exists($curSku, $groups[$element])) continue; // no duplicate SKUs

                        $rows = DB::select("SELECT entity_id FROM magento.catalog_product_entity WHERE sku = ?", [$curSku]);
                        if (empty($rows))
                        {
                            $this->error("MPN is not in Magento: " + $curSku);
                            continue;
                        }

                        $curEntity = $rows[0]['entity_id'];
                        if (empty($curQty)) $curQty = 1;

                        $skuData = IntegraUtils::getPrice($curSku);
                        if (empty($skuData['cost']) || $skuData['inactive']) {
                            $this->error("Unable to retrieve item cost of {$curSku} (possibly discontinued). Skipping...");
                            continue;
                        }

                        $this->info("Using part: {$curSku}");

                        $groups[$element][$curSku] = [
                            'sku' => $curSku,
                            'entity_id' => $curEntity,
                            'qty' => $curQty,
                            'name' => $skuData['name'],
                            'brand' => $skuData['brand'],
                            'cost' => $skuData['cost'],
                            'core' => $skuData['core'],
                            'supplier' => $skuData['supplier']
                        ];
                    }
                }

                // all elements matched
                $this->info("Processing combinatorials and applying same-supplier filter...");

                // process combinatorials
                $combos = IntegraUtils::getCombos($groups);

                // remove kits that have elements coming from different suppliers
                $toDelete = [];
                foreach ($combos as $idx => $combo) {
                    $suppliers = [];
                    foreach ($combo as $desc => $data) {
                        $suppliers[$data['supplier']] = 1;
                    }

                    if (count($suppliers) > 1)
                        $toDelete[] = $idx;
                }

                foreach ($toDelete as $td)
                    unset($combos[$td]);

                if (count($combos) == 0) {
                    $this->error('No combos left due to different supplier sources.');
                    DB::update('UPDATE integra_prod.kit_hunter_queue SET job_type = 4 WHERE id = ?', [$queueId]);
                    continue;
                }

                $this->info(count($combos) . ' combos found. Creating kits...');

                foreach ($combos as $combo) {
                    $vehicleIds = IntegraUtils::getCommonCompatibility($combo);
                    if (empty($vehicleIds))
                    {
                        $this->error('No common compatibility for the following combo:');
                        print_r($combo);
                        continue;
                    }

                    IntegraUtils::saveKit($combo, $queueId, $vehicleIds);
                }

                DB::update('UPDATE integra_prod.kit_hunter_queue SET job_type = 4 WHERE id = ?', [$queueId]);

            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }
    }
}
