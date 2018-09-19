<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class KitHunter extends Command
{
	protected $name = 'kit:hunt';
    protected $description = 'Finds kit combinations.';

	public function __construct()
	{
		parent::__construct();
	}

    protected function getArguments()
    {
        return array
        (
            array('max_vehicles', InputArgument::REQUIRED, 'Maximum vehicles to process per run'),
        );
    }

	public function fire()
    {
        DB::disableQueryLog();

        $q = <<<EOQ
SELECT khq.elements, khp.vehicle_id, khp.id AS proc_id, khq.id AS kit_hunter_id
FROM integra_prod.kit_hunter_queue khq, integra_prod.kit_hunter_proc khp
WHERE khq.id = khp.queue_id
AND khp.status = 0
AND khq.job_type = 1
AND khq.stop = 0
ORDER BY khp.id ASC LIMIT ?
EOQ;
        $procs = DB::select($q, [$this->argument('max_vehicles')]);
        // empty queue
        if (empty($procs)) {
            $this->info("Queue is now empty.");
            return;
        }

        foreach ($procs as $proc) {
            try {
                $procId = $proc['proc_id'];
                $elements = explode('|', $proc['elements']);
                $vehicleId = $proc['vehicle_id'];
                $kitHunterId = $proc['kit_hunter_id'];

                DB::update('UPDATE integra_prod.kit_hunter_proc SET status = 1 WHERE id = ?', [$procId]);
                $this->info("=========================================================================");

                $noMatch = false;
                $groups = [];

                foreach ($elements as $element) {
                    $fields = explode('~', $element);
                    $name = $fields[0];
                    if (count($fields) > 1) $position = $fields[1];
                    else $position = '';

                    $this->info("Finding part: {$name} for vehicle {$vehicleId}");

                    // match description
                    $q = <<<EOQ
SELECT em.entity_id, cpev.value, cpe.sku, em.position, em.qty_required, em.engine_size
FROM magento.catalog_product_entity_varchar cpev, magento.catalog_product_entity cpe, magento.elite_1_definition ed, magento.elite_1_mapping em
WHERE cpe.entity_id = em.entity_id
AND em.make_id =  ed.make_id
AND em.model_id = ed.model_id
AND em.year_id = ed.year_id
AND cpev.entity_id = em.entity_id
AND cpev.store_id = 0
AND cpev.attribute_id = 71
AND ed.id = ?
AND cpev.value = ?
AND cpe.inactive = 0
ORDER BY em.position DESC
EOQ;
                    $matches = DB::select($q, [$vehicleId, $name]);
                    if (empty($matches)) {
                        $this->error("No match for element {$element}");
                        $noMatch = true; // no immediate match
                        break;
                    }

                    $groups[$element] = [];

                    foreach ($matches as $match) {
                        $curSku = $match['sku'];

                        if (array_key_exists($curSku, $groups[$element])) continue; // no duplicate SKUs

                        $curEntity = $match['entity_id'];
                        $curName = $match['value'];
                        $curPosition = $match['position'];
                        $curQty = $match['qty_required'];
                        if (empty($curQty)) $curQty = 1;

                        if (!empty($position) && !empty($curPosition) && $position != $curPosition) // check position if provided
                        {
                            $this->error("Position of {$curSku} ({$curPosition}) does not match filter ({$position}). Skipping...");
                            continue; // skip this part if position does not match (allow if no position filter is provided or no position data
                        }

                        $skuData = IntegraUtils::getPrice($curSku);
                        if (empty($skuData['cost']) || $skuData['inactive']) {
                            $this->error("Unable to retrieve item cost of {$curSku} (possibly discontinued). Skipping...");
                            continue;
                        }

                        $this->info("Found matching part: {$curSku}");

                        $groups[$element][$curSku] = [
                            'sku' => $curSku,
                            'entity_id' => $curEntity,
                            'name' => $curName,
                            'position' => $curPosition,
                            'qty' => $curQty,
                            'brand' => $skuData['brand'],
                            'cost' => $skuData['cost'],
                            'core' => $skuData['core'],
                            'supplier' => $skuData['supplier'],
                            'engine' => $match['engine_size']
                        ];
                    }

                    if (empty($groups[$element])) {
                        $this->error("No match for element {$element}");
                        $noMatch = true; // matches were filtered out by position
                        break;
                    }
                }

                // at least one element did not match, abort search for this vehicle
                if ($noMatch) {
                    $this->error("At least one element did not match. Search completed for vehicle {$vehicleId}");
                    DB::update('UPDATE integra_prod.kit_hunter_proc SET status = 2 WHERE id = ?', [$procId]);
                    continue;
                }

                // all elements matched
                $this->info("All elements matched for vehicle {$vehicleId}. Processing combinatorials and applying same-supplier and same-engine filter...");

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
                    DB::update('UPDATE integra_prod.kit_hunter_proc SET status = 2 WHERE id = ?', [$procId]);
                    continue;
                }

                // remove kits that have different engine sizes. ignore parts with no engine size
                $toDelete = [];
                foreach ($combos as $idx => $combo) {
                    $engines = [];

                    foreach ($combo as $desc => $data) {
                        if (!empty($data['engine']))
                            $engines[$data['engine']] = 1;
                    }

                    if (count($engines) > 1)
                        $toDelete[] = $idx;
                }

                foreach ($toDelete as $td)
                    unset($combos[$td]);

                if (count($combos) == 0) {
                    $this->error('No combos left due to different engine sizes.');
                    DB::update('UPDATE integra_prod.kit_hunter_proc SET status = 2 WHERE id = ?', [$procId]);
                    continue;
                }

                $this->info(count($combos) . ' combos found. Creating kits...');

                foreach ($combos as $combo) {
                    $vehicleIds = IntegraUtils::getCommonCompatibility($combo);
                    IntegraUtils::saveKit($combo, $kitHunterId, $vehicleIds);
                }

                DB::update('UPDATE integra_prod.kit_hunter_proc SET status = 2 WHERE id = ?', [$procId]);
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }
    }
}
