<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class ImcCreateProducts extends Command
{
    protected $name = 'imc:create_products';
    protected $description = 'Creates or updates products based on previously loaded ACES data';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        DB::disableQueryLog();

        $rows = DB::select('SELECT DISTINCT sku FROM magento.tmp_new_product_codes');
        $skuCount = count($rows);

        for ($skuCtr = 0; $skuCtr < $skuCount; $skuCtr++)
        {
            try
            {
                $this->info('[' . ($skuCtr + 1) . "/{$skuCount}] Processing SKU " . $rows[$skuCtr]['sku']);
                DB::statement("CALL magento.create_product(?, NULL, 1, 1, 0)", [$rows[$skuCtr]['sku']]);
            }
            catch (Exception $e)
            {
                $this->error($e->getMessage());
            }
        }
    }
}
