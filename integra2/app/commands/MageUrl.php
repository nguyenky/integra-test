<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class MageUrl extends Command
{
    protected $name = 'mage:url';
    protected $description = 'Generates Magento URLs based on compatible vehicles';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        DB::disableQueryLog();
        $store = 'europortparts';

        $lastId = 0;

        while (true)
        {
            if ($this->argument('sku') != 'all')
            {
                $skuFilter = " AND cpe.sku = '" . strtoupper(trim($this->argument('sku'))) . "' ";
            }
            else $skuFilter = '';

            $rows = DB::select(<<<EOQ
SELECT cpe.sku, cpe.entity_id
FROM magento.catalog_product_entity cpe
WHERE cpe.entity_id > ?
{$skuFilter}
ORDER BY cpe.entity_id
LIMIT 50
EOQ
                , [$lastId]);

            if (empty($rows)) break;

            foreach ($rows as $row)
            {
                $sku = $row['sku'];
                $lastId = $row['entity_id'];

                $this->info('Updating ' . $sku);

                try {
                    $data = IntegraUtils::getMageUrl($sku);
                    if (empty($data)) continue;

                    DB::insert(<<<EOQ
INSERT INTO magento.catalog_product_entity_varchar
(entity_type_id, attribute_id, store_id, entity_id, value)
(SELECT 4, 97, cs.store_id, ?, ? FROM magento.core_store cs WHERE cs.code IN (?))
ON DUPLICATE KEY UPDATE value=VALUES(value)
EOQ
                        , [$lastId, $data['url'], $store]);

                    DB::insert(<<<EOQ
INSERT INTO magento.catalog_product_entity_varchar
(entity_type_id, attribute_id, store_id, entity_id, value)
(SELECT 4, 98, cs.store_id, ?, ? FROM magento.core_store cs WHERE cs.code IN (?))
ON DUPLICATE KEY UPDATE value=VALUES(value)
EOQ
                        , [$lastId, $data['url'], $store]);

                    DB::insert(<<<EOQ
INSERT INTO magento.catalog_product_entity_text
(entity_type_id, attribute_id, store_id, entity_id, value)
(SELECT 4, 83, cs.store_id, ?, ? FROM magento.core_store cs WHERE cs.code IN (?))
ON DUPLICATE KEY UPDATE value=VALUES(value)
EOQ
                        , [$lastId, $data['desc'], $store]);
                }
                catch (Exception $e)
                {
                    $this->error($e->getMessage());
                }
            }

            if ($this->argument('sku') != 'all') break;
        }
    }

    protected function getArguments()
    {
        return array
        (
            array('sku', InputArgument::REQUIRED, 'sku (or all)'),
        );
    }
}
