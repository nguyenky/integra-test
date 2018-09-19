<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class ImcScrapeReturns extends Command
{
    protected $name = 'imc:scrape_returns';
    protected $description = 'Scrapes the status of IMC returns.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        DB::disableQueryLog();

        $url = 'http://www.imcparts.net/webapp/wcs/stores/servlet/';

        $config = Config::get('integra');
        $username = $config['imc_web']['username'];
        $password = $config['imc_web']['password'];
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

        $supplierId = Supplier::where('name', 'IMC')->pluck('id');

        $returns = SupplierReturn::where('supplier_id', $supplierId)->where('credit_num', '')->whereNotNull('return_num')->where('return_date', '>=', strtotime('-30 day'))->get()->toArray();

        $scrapeDates = [];

        foreach ($returns as $return)
        {
            $scrapeDates[$return['return_date']] = 1;
        }

        $returnUrls = [];

        foreach ($scrapeDates as $scrapeDate => $x)
        {
            $this->info("Scraping returns on {$scrapeDate}...");

            curl_setopt($ch, CURLOPT_URL, "${url}TrackReturnsView?storeId={$store}&catalogId={$store}&langId=-1&status=&pageNo=1&searchOrderStartDate={$scrapeDate}&searchOrderEndDate={$scrapeDate}&searchBy=RA_DATE");
            $output = curl_exec($ch);

            preg_match_all("/hideLinks\\('(?<num>[^']+)'\\)\" href=\"(?<url>[^\"]+)/i", $output, $results, PREG_SET_ORDER);

            foreach ($results as $result)
            {
                $returnUrls[trim($result['num'])] = trim($result['url']);
            }
        }

        foreach ($returnUrls as $returnNum => $returnUrl)
        {
            $return = SupplierReturn::where('return_num', $returnNum)->first();
            if (empty($return)) continue;

            $this->info("Scraping return {$returnNum}...");

            curl_setopt($ch, CURLOPT_URL, "${url}{$returnUrl}");
            $output = curl_exec($ch);

            preg_match("/creditMemo=(?<credit>[\\d]+)/i", $returnUrl, $results);

            $return->items()->update(['quantity_credited' => null, 'unit_price_credited' => null]);

            try
            {
                $raDoc = public_path() . "/ra_doc/" . $return->id . ".htm";

                if (!file_exists($raDoc))
                {
                    curl_setopt($ch, CURLOPT_URL, "{$url}ReturnDetailPrintableCmd?raNumber={$returnNum}");
                    $output = curl_exec($ch);
                    file_put_contents($raDoc, $output);
                }
            }
            catch (Exception $e)
            {
                $this->error("Error while downloading RA document: " . $e->getMessage());
            }

            if (!isset($results['credit']))
            {
                $return->credit_num = '';
                $return->total_credited = null;
                $return->status = 'Processing';
                $return->save();
                continue;
            }

            $return->credit_num = trim($results['credit']);

            curl_setopt($ch, CURLOPT_URL, "${url}InvoiceDetailCmd?returnPage=RA&orderId={$returnNum}&invoiceId=" . $return->credit_num);
            $output = curl_exec($ch);

            preg_match("/<h1>Credit Issue Date<\\/h1>\\s+<h2>(?<m>[\\d]+)\\/(?<d>[\\d]+)\\/(?<y>[\\d]+)/i", $output, $results);

            if (isset($results['m']) && isset($results['d']) && isset($results['y']))
                $return->credit_date = $results['y'] . '-' . $results['m'] . '-' . $results['d'];
            else $return->credit_date = null;

            preg_match("/Credit Total[^$]+\\$(?<total>[\\d,.]+)/is", $output, $results);
            if (isset($results['total']))
            {
                $return->total_credited = trim(str_replace(',', '', $results['total']));
                $return->status = 'Full Credit';
            }

            $return->save();

            preg_match_all("/Ordered As #:<\\/strong>(?<sku>[^<]+).+?<td>.+?<td>\\D*(?<qty>[\\d,]+)<\\/d.+?\\$(?<price>[\\d.,]+)/is", $output, $results, PREG_SET_ORDER);

            foreach ($results as $result)
            {
                $item = SupplierReturnItem::where('supplier_return_id', $return->id)
                    ->where('sku', str_replace('-', '', str_replace(' ', '', trim($result['sku']))))->first();

                if (empty($item)) continue;

                $item->quantity_credited = trim(str_replace(',', '', $result['qty']));
                $item->unit_price_credited = trim(str_replace(',', '', $result['price']));
                $item->save();
            }

            $partial = SupplierReturnItem::where('supplier_return_id', $return->id)
                ->where('quantity_requested', '>', 'quantity_credited')
                ->count();

            if ($partial)
            {
                $return->status = 'Partial Credit';
                $return->save();
            }
        }

        unlink($cookie);
    }
}
