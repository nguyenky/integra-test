<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateEbayInventory extends Command
{
    protected $name = 'ebay:update_inventory';
    protected $description = 'Updates eBay inventory quantities.';

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

        $tmp_endlist = "${tmp}/kit_endlist";
        $tmp_revise = "${tmp}/kit_revise";
        $tmp_relist = "${tmp}/kit_relist";

        if (is_file($tmp_endlist)) unlink($tmp_endlist);
        if (is_file($tmp_revise)) unlink($tmp_revise);
        if (is_file($tmp_relist)) unlink($tmp_relist);

        file_put_contents($tmp_endlist, '');
        file_put_contents($tmp_revise, '');
        file_put_contents($tmp_relist, '');

        $lastId = 0;

        while (true)
        {
            $kits = DB::select(<<<EOQ
SELECT DISTINCT p.id, p.sku
FROM products p, ebay_listings el
WHERE p.is_kit = 1
AND p.sku = el.sku
AND p.id > ?
ORDER BY p.id
LIMIT 50
EOQ
                , [$lastId]);

            if (empty($kits)) break;

            foreach ($kits as $kit)
            {
                $lastId = $kit['id'];

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
) 
EOQ
                , [$lastId]);

                if (empty($avQuery)) $avail = 0;
                else $avail = $avQuery[0]['avail'];

                $ebay = EbayListing::where('sku', $kit['sku'])->orderBy('always_list', 'desc')->orderBy('id', 'desc')->first();
                $itemId = $ebay['item_id'];
                $sku = $ebay['sku'];
                $oldAvail = $ebay['quantity'];

                if ($ebay['quantity'] == $avail)
                {
                    $this->info("Quantity for ${sku} is already up to date.");
                    continue;
                }

                if ($avail == 0)
                {
                    if ($ebay['always_list']) $action = 'revise';
                    else $action = 'endlist';
                }
                else
                {
                    if ($ebay['active']) $action = 'revise';
                    else $action = 'relist';
                }

                if ($action == 'endlist')
                {
                    $this->info("End listing ${sku}.");
                    $feed = <<<EOD
                    <EndFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                        <ItemID>${itemId}</ItemID>
                        <EndingReason>NotAvailable</EndingReason>
                        <Version>801</Version>
                    </EndFixedPriceItemRequest>

EOD;
                    file_put_contents($tmp_endlist, $feed, FILE_APPEND );
                }
                else if ($action == 'revise')
                {
                    $this->info("Revising ${sku} from ${oldAvail} to ${avail}.");
                    $feed = <<<EOD
                    <ReviseInventoryStatusRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                        <Version>739</Version>
                        <InventoryStatus>
                        <ItemID>${itemId}</ItemID>
                        <Quantity>${avail}</Quantity>
                        </InventoryStatus>
                    </ReviseInventoryStatusRequest>

EOD;
                    file_put_contents($tmp_revise, $feed, FILE_APPEND );
                }
                else if ($action == 'relist')
                {
                    $this->info("Relisting ${sku} via item ID ${itemId} with quantity ${avail}.");
                    $feed = <<<EOD
                    <RelistFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                        <Item>
                            <ItemID>${itemId}</ItemID>
                                <SKU>${sku}</SKU>
                                <Quantity>${avail}</Quantity>
                                <InventoryTrackingMethod>SKU</InventoryTrackingMethod>
                                <CategoryMappingAllowed>true</CategoryMappingAllowed>
                            <PrivateListing>true</PrivateListing>
                        </Item>
                        <Version>801</Version>
                    </RelistFixedPriceItemRequest>

EOD;
                    file_put_contents($tmp_relist, $feed, FILE_APPEND );
                }
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
