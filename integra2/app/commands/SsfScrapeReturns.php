<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class SsfScrapeReturns extends Command
{
    protected $name = 'ssf:scrape_returns';
    protected $description = 'Scrapes the status of SSF returns.';

    public function __construct()
    {
        parent::__construct();
    }

    public function saveRa($id, $html)
    {
        try
        {
            file_put_contents(public_path() . "/ra_doc/{$id}.htm", str_replace('/public/Images/logo_ssf.png', 'https://www.ssfautoparts.com/public/Images/logo_ssf.png', $html));
        }
        catch (Exception $e)
        {
            $this->error("Error while downloading RA document: " . $e->getMessage());
        }
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

        $fromDate = urlencode(date('m/d/Y', strtotime("-60 day")));
        $toDate = urlencode(date('m/d/Y'));

        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/storefront/myreport/OrderReportlist.aspx?sort=Requested_ship_fm+desc%2Cas400_id&filter=order_statusLabel+like+'*re*'&DateRangeLowText={$fromDate}&DateRangeHighText={$toDate}&OpenEndDateText=");
        $output = curl_exec($ch);

        preg_match_all("/window\\.open\\((?<url>[^,]+)/is", $output, $results, PREG_SET_ORDER);

        foreach ($results as $result)
        {
            $url = trim(str_replace("'", '', htmlspecialchars_decode($result['url'], ENT_QUOTES)));

            curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com{$url}");
            $output = curl_exec($ch);

            preg_match("/Credit #:<\\/em>(?<val>[^<]+)/i", $output, $results);
            if (!isset($results['val'])) continue;
            $returnNum = trim($results['val']);

            $return = SupplierReturn::where('return_num', $returnNum)->first();
            if (empty($return)) continue;

            $this->info("Scraping return {$returnNum}...");

            preg_match("/<em>Total[^$]+?\\$\\D+?(?<val>[\\d.,]+)/is", $output, $results);

            $return->items()->update(['quantity_credited' => null, 'unit_price_credited' => null]);

            if (stripos($output, 'Credited') === false)
            {
                $return->credit_num = '';
                $return->total_credited = null;
                $return->status = 'Processing';
                $return->save();
                $this->saveRa($return->id, $output);
                continue;
            }

            $return->credit_num = $return->return_num;
            $return->credit_date = Carbon::now();

            if (isset($results['val']))
                $return->total_credited = trim(str_replace(',', '', $results['val']));

            $return->status = 'Full Credit';
            $return->save();
            $this->saveRa($return->id, $output);

            preg_match_all("/<tr [^<]+?<td[^<]+<\\/td>\\s*<td[^>]+?>(?<sku>[^<]+)<\\/td>\\s*<td[^<]+<\\/td>\\s*<td[^>]+?>(?<qty>[^<]+)<\\/td>\\s*<td[^<]+<\\/td>\\s*<td[^<]+>(?<price>[^<]+)<\\/td>/is",
                $output, $results2, PREG_SET_ORDER);

            foreach ($results2 as $result2)
            {
                $item = SupplierReturnItem::where('supplier_return_id', $return->id)
                    ->where('sku', str_replace('-', '', str_replace(' ', '', trim($result['sku']))))->first();

                if (empty($item)) continue;

                $item->quantity_credited = trim(str_replace(',', '', $result2['qty']));
                $item->unit_price_credited = trim(str_replace('-', '', str_replace(',', '', $result2['price'])));
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

        curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/storefront/AbandonSession.aspx?act=lf&lid=0&t=" . time());
        $output = curl_exec($ch);

        unlink($cookie);
    }
}
