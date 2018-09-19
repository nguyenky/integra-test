<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class ImcLoadAces extends Command
{
    protected $name = 'imc:load_aces';
    protected $description = 'Loads given ACES XML file';

    public function __construct()
    {
        parent::__construct();
    }

    protected function getArguments()
    {
        return array
        (
            array('file', InputArgument::REQUIRED, 'ACES XML file'),
        );
    }

    public function fire()
    {
        DB::disableQueryLog();

        $rows = DB::select('SELECT title FROM magento.elite_level_1_make ORDER BY LENGTH(title) DESC');
        $makes = [];

        foreach ($rows as $row)
            $makes[] = $row['title'];

        $file = $this->argument('file');
        $this->info('Loading ' . basename($file));
        $xml = simplexml_load_file($file);
        $apps = $xml->App;
        $appCount = count($apps);

        for ($appCtr = 0; $appCtr < $appCount; $appCtr++)
        {
            try
            {
                $app = $apps[$appCtr];
                $this->info('Application ' . ($appCtr + 1) . "/{$appCount}");
                $nodes = $app->children();
                $jpn = null;
                $name = null;
                $brand = null;
                $vehicle = null;
                $miscTags = [];
                $notes = [];
                $universal = true;
                $year = '';
                $make = '';
                $model = '';
                foreach ($nodes as $node)
                {
                    $key = trim($node->getName());
                    $val = trim(preg_replace('!\s+!', ' ', htmlspecialchars_decode(str_replace("\n", " ", str_replace("\r", " ", (string)$node)))));
                    if ($key == 'Part')
                        $jpn = preg_replace('/[^\da-z]/i', '', $val);
                    else if ($key == 'PartType')
                        $name = $val;
                    else if ($key == 'MfrLabel')
                        $brand = $val;
                    else if ($key == 'BaseVehicle')
                        $vehicle = $val;
                    else if ($key == 'Qty')
                        $notes[] = "Qty: (${val})";
                    else if ($key == 'Notes' || $key == 'Note')
                    {
                        if ($val != '.') $notes[] = $val;
                    }
                    else
                        $miscTags[] = "{$key}: {$val}";
                }
                if (empty($jpn))
                {
                    $this->error('Unable to parse JPN');
                    continue;
                }
                $allNotes = implode('; ', array_merge($miscTags, $notes));
                if (!empty($vehicle))
                {
                    $year = substr($vehicle, 0, 4);
                    if (ctype_digit($year))
                    {
                        $universal = false;
                        $vehicle = trim(substr($vehicle, 4));
                        foreach ($makes as $m)
                        {
                            if (strpos($vehicle, $m) === 0)
                            {
                                $make = $m;
                                break;
                            }
                        }
                        // no match found -- first word is a new make
                        if (empty($make))
                        {
                            $make = explode(' ', $vehicle)[0];
                            $this->info("New make detected: {$make}");
                        }
                        $model = trim(substr($vehicle, strlen($make)));
                    }
                    else
                    {
                        $this->error("Unable to parse vehicle: {$vehicle}");
                        continue;
                    }
                }
                $rows = DB::select('SELECT sku FROM magento.part_numbers WHERE code = ?', [$jpn]);
                if (empty($rows))
                {
                    $this->error("Unable to locate SKU for JPN {$jpn}");
                    continue;
                }
                foreach ($rows as $row)
                {
                    $sku = $row['sku'];
                    DB::insert("INSERT IGNORE INTO magento.tmp_new_product_codes (sku, code) VALUES (?, ?)", [$sku, $jpn]);
                    if (!empty($name))
                        DB::insert("INSERT IGNORE INTO magento.tmp_new_product_attribs (sku, attribute_code, value) VALUES (?, 'name', ?)", [$sku, $name]);
                    if (!empty($brand))
                        DB::insert("INSERT IGNORE INTO magento.tmp_new_product_attribs (sku, attribute_code, value) VALUES (?, 'brand', ?)", [$sku, $brand]);
                    if ($universal)
                        DB::insert("INSERT IGNORE INTO magento.tmp_new_product_compats (sku, universal, make, model, year, note) VALUES (?, 1, '', '', '', ?)", [$sku, $allNotes]);
                    else
                        DB::insert("INSERT IGNORE INTO magento.tmp_new_product_compats (sku, universal, make, model, year, note) VALUES (?, 0, ?, ?, ?, ?)", [$sku, $make, $model, $year, $allNotes]);
                }
            }
            catch (Exception $e)
            {
                $this->error($e->getMessage());
            }
        }
    }
}
