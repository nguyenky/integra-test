<?php

use Illuminate\Console\Command;

class KitLister extends Command
{
	protected $name = 'kit:list';
    protected $description = 'Lists approved kits.';

	public function __construct()
	{
		parent::__construct();
	}

	public function fire()
    {
        DB::disableQueryLog();

        try {
            $q = <<<EOQ
SELECT p.id, p.sku, p.name, p.ebay_price
FROM integra_prod.products p
WHERE p.is_kit = 1
AND p.kit_hunter_id IS NOT NULL
AND p.publish_status = 1
AND p.ebay_id IS NULL
ORDER BY p.id
LIMIT 1
EOQ;
            $rows = DB::select($q);
            // empty queue
            if (empty($rows)) {
                $this->info("Queue is now empty");
                return;
            }

            $productId = $rows[0]['id'];
            $sku = $rows[0]['sku'];
            $title = $rows[0]['name'];
            $price = $rows[0]['ebay_price'];

            DB::update('UPDATE integra_prod.products SET publish_status = 1 WHERE id = ?', [$productId]);
            $this->info("Preparing kit {$sku} for listing...");

            $pictures[] = "http://catalog.eocenterprise.com/img/kit.php?sku=" . str_replace('-', '', $sku);

            $q = <<<EOQ
SELECT p.sku, p.name, p.brand, k.quantity
FROM integra_prod.products p, integra_prod.kit_components k
WHERE p.id = k.component_product_id
AND k.product_id = ?
EOQ;
            $elements = DB::select($q, [$productId]);
            $lines = ['This kit contains:'];
            $baseMpns = [];
            $mpns = [];

            $kitAvail = 999;

            foreach ($elements as $element) {
                $pictures[] = 'http://catalog.eocenterprise.com/img/'
                    . str_replace('-', '', $element['sku'])
                    . '/cl1,loqe,boqe,br1'
                    . ($element['quantity'] > 1 ? (',qt' . $element['quantity']) : '')
                    . '.jpg';

                $lines[] = sprintf("%dx %s %s (%s)",
                    $element['quantity'],
                    (strlen($element['brand']) > 1 ? $element['brand'] : ''),
                    (strlen($element['name']) > 1 ? $element['name'] : ''),
                    $element['sku']);

                $rows = DB::select("SELECT qty FROM integra_prod.supplier_quantities WHERE mpn = ?", [$element['sku']]);
                if (empty($rows) || empty($rows[0])) $partAvail = 0;
                else $partAvail = $rows[0]['qty'];

                if ($element['quantity'] == 0) {
                    $this->error('Quantity required for ' . $element['sku'] . ' is zero!');
                    $kitAvail = 0;
                } else {
                    $kitAvail = min($kitAvail, floor($compAvail = $partAvail / $element['quantity']));
                }

                if (!in_array($element['sku'], $baseMpns))
                    $baseMpns[] = $element['sku'];

                $rows = DB::select("SELECT code FROM magento.part_numbers WHERE sku = ?", [$element['sku']]);
                foreach ($rows as $row) {
                    if (!in_array($row['code'], $mpns) && !in_array($row['code'], $baseMpns))
                        $mpns[] = $row['code'];
                }
            }

            $description = implode("\n", $lines);

            $baseMpnAttrib = implode(' / ', $baseMpns);
            if (strlen($baseMpnAttrib) > 65) {
                $baseMpnAttrib = trim(substr($baseMpnAttrib, 0, 65));
                $pos = strrpos($baseMpnAttrib, '/');
                if ($pos !== false) $baseMpnAttrib = trim(substr($baseMpnAttrib, 0, $pos));
            }

            $mpnAttrib = implode(' / ', $mpns);
            if (strlen($mpnAttrib) > 65) {
                $mpnAttrib = trim(substr($mpnAttrib, 0, 65));
                $pos = strrpos($mpnAttrib, '/');
                if ($pos === false) $mpnAttrib = trim(substr($mpnAttrib, 0, $pos));
            }

            $attribs['mpn'] = $baseMpnAttrib;
            $attribs['ipn'] = $mpnAttrib;
            $attribs['opn'] = $mpnAttrib;
            $attribs['brand'] = 'QE Auto Parts';
            $conditionId = EbayUtils::$newConditionId;

            // load compatibility and format
            $compats = DB::select(<<<EOQ
SELECT year, make, model
FROM integra_prod.kit_compatibility kc, magento.elite_1_definition ed
WHERE kc.kit_product_id = ?
AND kc.vehicle_id = ed.id
ORDER BY make, model, year
EOQ
                , [$productId]);

            $compatibility = '<ItemCompatibilityList>';

            foreach ($compats as $compat) {
                $compatibility .= '<Compatibility><NameValueList/><NameValueList><Name>Year</Name><Value>' . $compat['year'] . '</Value></NameValueList>';
                $compatibility .= '<NameValueList><Name>Make</Name><Value><![CDATA[' . $compat['make'] . ']]></Value></NameValueList>';
                $compatibility .= '<NameValueList><Name>Model</Name><Value><![CDATA[' . $compat['model'] . ']]></Value></NameValueList></Compatibility>';
            }

            $compatibility .= '</ItemCompatibilityList>';

            $this->info('Submitting to eBay...');
            $res = EbayUtils::ListItem($sku, $title, $kitAvail, $price, $description, $pictures, $conditionId, $attribs, $compatibility, 'N/A');

            if (!empty($res['item_id'])) {
                DB::update('UPDATE integra_prod.products SET publish_status = 2, publish_date = NOW(), ebay_id = ?, is_active = 1 WHERE id = ?', [$res['item_id'], $productId]);
                $this->info('Listed ' . $sku . ' under ' . $res['item_id']);
            } else {
                DB::update('UPDATE integra_prod.products SET publish_status = 3, publish_date = NULL, ebay_id = NULL, is_active = 0 WHERE id = ?', [$productId]);
                $this->error('Error while listing ' . $sku . ': ' . $res['error']);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
