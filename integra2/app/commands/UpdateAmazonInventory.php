<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateAmazonInventory extends Command
{
    protected $name = 'amazon:update_inventory';
    protected $description = 'Updates Amazon inventory quantities.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        set_time_limit(0);
        ini_set('memory_limit', '768M');
        DB::disableQueryLog();

        $tmp = '/tmp';

        if (App::environment() == 'dev')
            $tmp = 'C:\\temp';

        $tmp_revise = "${tmp}/kit_amazon";
        if (is_file($tmp_revise)) unlink($tmp_revise);
        file_put_contents($tmp_revise, '');
        $ctr = 1;

        $q = <<<EOD
SELECT a.sku, a.quantity AS a_quantity, FLOOR(m.qty / a.kit_qty) AS avail
FROM eoc.amazon_listings a, eoc.inventory_map m
WHERE a.active = 1
AND a.kit_base = m.sku
AND a.quantity <> FLOOR(m.qty / a.kit_qty)
EOD;
        $rows = DB::select($q);
        foreach ($rows as $row)
        {
            $sku = $row['sku'];
            $aQuantity = $row['a_quantity'];
            $avail = $row['avail'];

            $feed = <<<EOD
<Message>
<MessageID>${ctr}</MessageID>
<OperationType>Update</OperationType>
<Inventory>
<SKU>${sku}</SKU>
<Quantity>${avail}</Quantity>
</Inventory>
</Message>
EOD;
            $this->info("Revising ${sku} from ${aQuantity} to ${avail}.");

            file_put_contents($tmp_revise, $feed, FILE_APPEND);
            $ctr++;
        }

        $lastId = 0;

        while (true)
        {
            $kits = DB::select(<<<EOQ
SELECT DISTINCT p.id, p.sku, a.quantity
FROM integra_prod.products p, eoc.amazon_listings a
WHERE p.is_kit = 1
AND p.sku = a.kit_base
AND p.id > ?
AND a.active = 1
ORDER BY p.id
LIMIT 50
EOQ
                , [$lastId]);

            if (empty($kits)) break;

            foreach ($kits as $kit)
            {
                $lastId = $kit['id'];
                $sku = $kit['sku'];
                $aQuantity = $kit['quantity'];

                $avQuery = DB::select(<<<EOQ
SELECT IFNULL(MIN(comp_qty), 0) AS avail
FROM
(
    SELECT sq.mpn, FLOOR(SUM(sq.qty) / kc.quantity) AS comp_qty
FROM products p, kit_components kc, supplier_quantities sq
WHERE p.id = kc.component_product_id
AND kc.product_id = ?
AND p.sku = sq.mpn
GROUP BY 1
) x
EOQ
                , [$lastId]);

                if (empty($avQuery)) $avail = 0;
                else $avail = $avQuery[0]['avail'];

                if ($aQuantity == $avail)
                {
                    $this->info("Quantity for ${sku} is already up to date.");
                    continue;
                }

                $this->info("Revising ${sku} from ${aQuantity} to ${avail}.");
                $feed = <<<EOD
<Message>
<MessageID>${ctr}</MessageID>
<OperationType>Update</OperationType>
<Inventory>
<SKU>${sku}</SKU>
<Quantity>${avail}</Quantity>
</Inventory>
</Message>
EOD;

                file_put_contents($tmp_revise, $feed, FILE_APPEND);
                $ctr++;
            }
        }
    }

    protected function getArguments()
    {
        return [];
    }

    protected function getOptions()
    {
        return [];
    }
}
