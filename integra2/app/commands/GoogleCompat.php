<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class GoogleCompat extends Command
{
    protected $name = 'google:compat';
    protected $description = 'Generates Google feed based on provided target number of entries';

    public $tableName = 'magento.google_compat_';

    public function __construct()
    {
        parent::__construct();
    }

    public function generateDesc($sku, $compats, $brand, $name)
    {
        usort($compats, function($a, $b)
        {
            return strcmp($a["make"], $b["make"]);
        });

        $title = $brand . ' ' . $name . ' ';
        $lastMake = '';

        foreach ($compats as $c)
        {
            if ($c['make'] != $lastMake)
            {
                $lastMake = trim($c['make']);
                $title .= $lastMake . ' ';
            }

            $title .= trim($c['model']) . ' ';
        }

        $title .= $sku;


        $lastMake = '';
        $models = [];
        $makeGroups = [];

        foreach ($compats as $c)
        {
            if ($c['make'] != $lastMake)
            {
                if (!empty($models))
                {
                    $makeGroups[] = $lastMake . ' ' . implode(', ', $models);
                    $models = [];
                }

                $lastMake = trim($c['make']);
            }

            $models[] = trim($c['model']) . ' ' . trim($c['years']);
        }

        if (!empty($models))
            $makeGroups[] = $lastMake . ' ' . implode(', ', $models);

        $desc = implode('; ', $makeGroups);

        DB::insert('INSERT INTO ' . $this->tableName . ' (sku, title, description, desc_len) VALUES (?, ?, ?, ?)', [$sku, trim($title), $desc, strlen($desc)]);

        echo $title . "\r\n";
        echo strlen($desc) . "\r\n";
        echo $desc . "\r\n";
    }

    public function fire()
    {
        DB::disableQueryLog();

        $targetEntries = $this->argument('max_entries');
        $targetLen = ceil(DB::select('SELECT SUM(total_len) / ? AS c FROM magento.compats', [$targetEntries])[0]['c']);
        echo "Target length: {$targetLen}\r\n";


        $this->tableName .= time();
        DB::statement('CREATE TABLE ' . $this->tableName . ' LIKE magento.google_compat_tmp');
        echo "Output table: " . $this->tableName . "\r\n";

        $sku = '';

        while (true) {

            $rows = DB::select('SELECT sku FROM magento.compats WHERE sku > ? ORDER BY sku LIMIT 1', [$sku]);
            if (empty($rows))
            {
                DB::statement('DROP VIEW IF EXISTS magento.v_google_compat');
                DB::statement('CREATE VIEW magento.v_google_compat AS SELECT * FROM ' . $this->tableName);
                $this->info('Done!');
                break;
            }

            $sku = $rows[0]['sku'];

            $rows = DB::select(<<<EOQ
SELECT value
FROM magento.catalog_product_entity cpe, magento.catalog_product_entity_varchar cpev
WHERE cpe.sku = ?
AND cpe.entity_id = cpev.entity_id
AND cpev.attribute_id = 71
AND cpev.store_id = 0
AND cpe.inactive = 0
LIMIT 1
EOQ
                , [$sku]);
            if (empty($rows)) continue;
            $name = trim($rows[0]['value']);

            $rows = DB::select(<<<EOQ
SELECT value
FROM magento.catalog_product_entity cpe, magento.catalog_product_entity_varchar cpev
WHERE cpe.sku = ?
AND cpe.entity_id = cpev.entity_id
AND cpev.attribute_id = 135
AND cpev.store_id = 0
AND cpe.inactive = 0
LIMIT 1
EOQ
                , [$sku]);
            if (empty($rows)) continue;
            $brand = trim($rows[0]['value']);

            $allCompats = DB::select('SELECT make, model, years, total_len AS len, 0 AS used FROM magento.compats WHERE sku = ? ORDER BY total_len DESC', [$sku]);

            while (true) {
                $curCompats = [];
                $max = 99999;

                while (true) {
                    $found = null;

                    // find an used compatibility within max length
                    for ($i = 0; $i < count($allCompats); $i++) {
                        if ($allCompats[$i]['used'] || $allCompats[$i]['len'] > $max) continue;
                        $allCompats[$i]['used'] = 1;
                        $found = $allCompats[$i];
                        break;
                    }

                    if (empty($found)) break; // no matches or all used
                    $curCompats[] = $found;

                    $curLen = 0;

                    // compute current length
                    foreach ($curCompats as $c)
                        $curLen += $c['len'];

                    $max = $targetLen - $curLen; // compute next length limit
                    if ($max <= 0) break; // target length reached or exceeded
                }

                if (empty($curCompats)) break; // all used
                else {
                    echo "\r\n-----------------------------------------------------\r\n";

                    $this->generateDesc($sku, $curCompats, $brand, $name);

                    $curLen = 0;
                    foreach ($curCompats as $c)
                        $curLen += $c['len'];

                    echo count($curCompats) . ", total: " . $curLen . "\n";
                }

                // proceed to next batch
            }
        }
    }

    protected function getArguments()
    {
        return array
        (
            array('max_entries', InputArgument::REQUIRED, 'Max number of items to scrape')
        );
    }
}
