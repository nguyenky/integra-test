<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class ImcScrapeInvoices extends Command
{
    protected $name = 'imc:scrape_invoices';
    protected $description = 'Scrapes the IMC invoices for the past 5 days.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        DB::disableQueryLog();

        $url = 'http://www.imcparts.net/webapp/wcs/stores/servlet/';

        $config = Config::get('integra');
        $username = $this->argument('username');
        $password = $this->argument('password');
        $store = $config['imc_web']['store'];

        $cookie = tempnam("/tmp", "imcweb");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_URL, "{$url}Logon");
        curl_setopt($ch, CURLOPT_POSTFIELDS, "storeId={$store}&catalogId={$store}&langId=-1&reLogonURL=LogonForm&URL=HomePageView%3Flogon*%3D&postLogonPage=HomePageView&logonId={$username}&logonPassword={$password}&logonImg.x=32&logonImg.y=15");
        $output = curl_exec($ch);

        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_POST, false);

        $invoiceNums = [];

        $supplierId = Supplier::where('name', 'IMC')->pluck('id');

        for ($i = 0; $i <= 5; $i++)
        {
            $dt = strtotime("-{$i} day");
            $date = date('m/d/Y', $dt);
            $orderDate = date('Y-m-d', $dt);

            curl_setopt($ch, CURLOPT_URL, "${url}ViewAvailableReturns?tbReturnRadioGrp=&searchBy=INVOICE_DATE&searchNumber=&searchDate=&returnCode=&keyedAsNumber=&searchToken={$date}");
            $output = curl_exec($ch);

            preg_match_all("/<tr>\\s+<td>(?<sku>[^<]+)<\\/td>\\s+<td>(?<invoice>[^<]+)/i", $output, $results, PREG_SET_ORDER);

            foreach ($results as $result)
            {
                $invoiceNums[trim($result['invoice'])] = $orderDate;
            }
        }

        foreach ($invoiceNums as $invoiceNum => $orderDate)
        {
            $this->info("Scraping invoice {$invoiceNum}...");

            curl_setopt($ch, CURLOPT_URL, "${url}InvoiceDetailCmd?returnPage=ZSTD&invoiceId={$invoiceNum}");
            $output = curl_exec($ch);

            preg_match("/<h1>Order #<\\/h1>\\s+<h2>(?<val>[^<]+)/i", $output, $results);
            if (!isset($results['val']))
            {
                $this->error("Empty order # for invoice {$invoiceNum}...");
                continue;
            }
            $orderNum = $results['val'];

            preg_match("/<h1>Customer P\\.O\\. #<\\/h1>\\s+<h2>(?<val>[^<]+)/i", $output, $results);
            if (!isset($results['val'])) $poNum = '';
            else $poNum = $results['val'];

            preg_match("/Invoice Total[^$]+\\$(?<total>[\\d,.]+)/is", $output, $results);
            $total = trim(str_replace(',', '', $results['total']));

            $invoice = SupplierInvoice::where('invoice_num', $invoiceNum)->first();
            if (empty($invoice)) $invoice = new SupplierInvoice();

            $invoice->supplier_id = $supplierId;
            $invoice->invoice_num = $invoiceNum;
            $invoice->order_num = $orderNum;
            $invoice->po_num = $poNum;
            $invoice->order_date = $orderDate;
            $invoice->total = $total;
            $invoice->save();

            preg_match_all("/Ordered As #:<\\/strong>(?<sku>[^<]+).+?<td>.+?<td>\\D*(?<qty>[\\d,]+)<\\/d.+?\\$.+?\\$(?<price>[\\d.,]+)/is",
                $output, $items, PREG_SET_ORDER);

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
                $sii->quantity_shipped = str_replace(',', '', trim($item['qty'])); // TODO: add extra regex if needed for IMC
                $sii->unit_price = str_replace(',', '', trim($item['price']));
                $sii->save();
            }

            try
            {
                curl_setopt($ch, CURLOPT_URL, "${url}OrderDetailCmd?orderId=" . $orderNum);
                $output = curl_exec($ch);
                preg_match_all("/href=\"[^\"]+track[^\"]+?\"[^>]+?>(?<track>[^<]+)/i", $output, $matches, PREG_SET_ORDER);
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

        unlink($cookie);
    }

    protected function getArguments()
    {
        return array
        (
            array('username', InputArgument::REQUIRED, 'IMC username'),
            array('password', InputArgument::REQUIRED, 'IMC password'),
        );
    }
}
