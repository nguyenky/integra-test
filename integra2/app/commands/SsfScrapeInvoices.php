<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class SsfScrapeInvoices extends Command
{
    protected $name = 'ssf:scrape_invoices';
    protected $description = 'Scrapes SSF invoices.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        DB::disableQueryLog();

        $config = Config::get('integra');
        $username = $config['ssf_web']['username'];
        $password = $config['ssf_web']['password'];

        $cookie = tempnam("/tmp", "ssfweb");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            [
                'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.118 Safari/537.36',
            ]);

        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/");
        $output = curl_exec($ch);

        sleep(3);

        $re = "/\"__VIEWSTATE\" value=\"(?<vs>[^\"]+)/i";
        preg_match($re, $output, $matches);
        $vs = urlencode($matches['vs']);

        $re = "/\"__VIEWSTATEGENERATOR\" value=\"(?<vsg>[^\"]+)/i";
        preg_match($re, $output, $matches);
        $vsg = urlencode($matches['vsg']);

        $re = "/\"__EVENTVALIDATION\" value=\"(?<ev>[^\"]+)/i";
        preg_match($re, $output, $matches);
        $ev = urlencode($matches['ev']);

        curl_setopt($ch, CURLOPT_POST, true);
        $post = "ctl00_ScriptManager1_HiddenField=&__EVENTTARGET=ctl00%24HTMLcontent%24LoginButton_clickctl00%24myshop&__EVENTARGUMENT={$username}%7C%21%7C{$password}&__VIEWSTATE={$vs}&__VIEWSTATEGENERATOR={$vsg}&__EVENTVALIDATION={$ev}";

        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/");
        $output = curl_exec($ch);

        $supplierId = Supplier::where('name', 'SSF')->pluck('id');

        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/storefront/CatalogSearch/getOrderSummary.aspx?type=D");
        $output = curl_exec($ch);

        preg_match_all("/open\\('(?<url>[^']+)/is", $output, $results, PREG_SET_ORDER);

        foreach ($results as $result)
        {
            $url = trim($result['url']);

            curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com{$url}");
            $output = curl_exec($ch);

            preg_match("/Order #:<\\/em>(?<val>[^<]+)/i", $output, $results);
            if (!isset($results['val'])) continue;
            $orderNum = trim($results['val']);

            $this->info("Scraping order {$orderNum}...");

            preg_match("/PO #:<\\/em>(?<val>[^<]+)/i", $output, $results);
            if (!isset($results['val'])) $poNum = '';
            $poNum = trim($results['val']);

            preg_match("/Ordered:<\\/em>\\s*(?<m>\\d+)\\/(?<d>\\d+)\\/(?<y>\\d+)/i", $output, $results);
            if (!isset($results['m']))
            {
                $this->error("Empty order date for order {$orderNum}...");
                continue;
            }
            $orderDate = $results['y'] . '-' . $results['m'] . '-' . $results['d'];

            preg_match("/<em>Total[^$]+?\\$\\D+?(?<val>[\\d.,]+)/is", $output, $results);
            $total = trim(str_replace(',', '', $results['val']));

            $invoice = SupplierInvoice::where('invoice_num', $orderNum)->first();
            if (empty($invoice)) $invoice = new SupplierInvoice();

            $invoice->supplier_id = $supplierId;
            $invoice->invoice_num = $orderNum;
            $invoice->order_num = $orderNum;
            $invoice->po_num = $poNum;
            $invoice->order_date = $orderDate;
            $invoice->total = $total;
            $invoice->save();

            $re = "/<tr [^<]+?<td[^<]+<\\/td>\\s*<td[^>]+?>(?<sku>[^<]+)<\\/td>\\s*<td[^<]+<\\/td>\\s*<td[^>]+?>(?<qty>[^<]+)<\\/td>\\s*<td[^>]+?>(?<shipped>[^<]+)<\\/td>\\s*<td[^<]+<\\/td>\\s*<td[^<]+>(?<price>[^<]+)<\\/td>/is";
            preg_match_all($re, $output, $items, PREG_SET_ORDER);

            foreach ($items as $item)
            {
                $sku = str_replace('-', '', str_replace(' ', '', trim($item['sku'])));
                $sii = SupplierInvoiceItem::where('supplier_invoice_id', $invoice->id)->where('sku', $sku)->first();

                if (empty($sii))
                {
                    $sii = new SupplierInvoiceItem();
                    $sii->supplier_invoice_id = $invoice->id;
                    $sii->sku = $sku;
                }

                $sii->quantity = str_replace(',', '', trim($item['qty']));
                $sii->quantity_shipped = str_replace(',', '', trim($item['shipped']));
                $sii->unit_price = str_replace(',', '', trim($item['price']));
                $sii->save();
            }

            try
            {
                preg_match_all("/Tracking Info\"\\s*\\/>\\s*(?<track>[^<]+)/i", $output, $matches, PREG_SET_ORDER);
                $tracking = [];
                foreach ($matches as $match)
                {
                    if (!empty($match['track']))
                        $tracking[] = $match['track'];
                }

                $invoice->tracking_num = implode(', ', $tracking);
                $invoice->save();
            }
            catch (Exception $e)
            {
                $this->error("Error while parsing tracking number for order {$orderNum}...");
            }
        }

        curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/storefront/AbandonSession.aspx?act=lf&lid=0&t=" . time());
        $output = curl_exec($ch);

        unlink($cookie);
    }
}
