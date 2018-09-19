<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class SsfScrapeCompatibility extends Command
{
    protected $name = 'ssf:scrape_compat';
    protected $description = 'Scrapes the vehicle compatibility of queued SSF products.';

    public function __construct()
    {
        parent::__construct();
    }

    protected function getArguments()
    {
        return array
        (
            array('sku', InputArgument::REQUIRED, 'SKU'),
        );
    }

    public function fire()
    {
        $sku = strtoupper($this->argument('sku'));
        $idx = strpos($sku, '.');
        if ($idx !== false) $mpn = substr($sku, 0, $idx);
        else $mpn = $sku;

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

        DB::delete("DELETE FROM magento.tmp_new_product_compats WHERE sku = ?", [$sku]);

        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/storefront/CatalogSearch/CatalogResultAJAX.aspx?e=4&i={$mpn}&ret=");
        $output = curl_exec($ch);

        preg_match("/sequence=(?<seq>\\d+)/", $output, $matches);
        $seq = $matches['seq'];

        curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/storefront/CatalogSearch/getViewAllApps.aspx?s=4&u=N&i={$seq}");
        $output = curl_exec($ch);

        $re = "/(?<all>(<tr id=\\\"allAppsRepeat\\S+AllAppsNoteRow.+?)<\\/tr>)/is";
        preg_match($re, $output, $match);

        if (isset($match['all'])) {
            $partNotes = str_replace('&nbsp;', ' ', $match['all']);
            $partNotes = strip_tags($partNotes);
            $partNotes = trim(preg_replace('!\s+!', ' ', $partNotes));
        }
        else $partNotes = '';

        if (stripos($output, 'Universal Item') !== false)
        {
            // universal item
            preg_match_all("/<tr id=\\\"univ\\w+Row[^<]+(?<note>.+?)<\\/tr>/is", $output, $matches, PREG_SET_ORDER);

            $notes = [];

            foreach ($matches as $match)
            {
                $note = str_replace('&nbsp;', ' ', $match['note']);
                $note = strip_tags($note);
                $note = trim(preg_replace('!\s+!', ' ', $note));
                $notes[] = $note;
            }

            print_r($notes);

            if (!empty($notes))
                $notes = implode('; ', $notes);
            else $notes = '';

            DB::insert("INSERT IGNORE INTO magento.tmp_new_product_compats (sku, universal, make, model, year, note) VALUES (?, 1, '', '', '', ?)", [$sku, $notes]);
        }
        else
        {
            $lastIdx = 0;
            $rows = [];
            while (true)
            {
                $idx = stripos($output, '<tr class="allAppsDescripBold">', $lastIdx + 1);
                if ($idx === false) break;
                $lastIdx = $idx;
                $idx2 = stripos($output, '<tr class="allAppsDescripBold">', $lastIdx + 1);
                if ($idx2 === false) $idx2 = strlen($output);
                $chunk = substr($output, $idx, $idx2 - $idx);
                preg_match("/AllAppspMakelb\">(?<make>[^<]+)/i", $chunk, $matches);
                $make = trim($matches['make']);
                if (empty($make)) continue;
                $re = "/<tr id=\\\"allAppsRepeat[^<]+<td[^>]+>(?<f1>[^<]+)<[^<]+<td[^>]+>(?<f2>[^<]+)<[^<]+<td[^>]+>(?<f3>[^<]+)<[^<]+<td[^>]+>(?<f4>[^<]+)<[^<]+<td[^>]+>(?<f5>[^<]+)<[^<]+<td[^>]+>(?<f6>[^<]+)<\\/td>\\s*<\\/tr>\\s*(<tr id=\"allAppsRepeat[^<]+<td colspan=\"6[^>]+>(?<misc>.+?)<\\/tr>)*/is";
                preg_match_all($re, $chunk, $matches, PREG_SET_ORDER);
                $fields = [];

                for ($j = 0; $j < count($matches); $j++)
                {
                    $match = $matches[$j];
                    $vals = [];
                    for ($i = 1; $i <= 6; $i++) $vals[] = trim($match["f{$i}"]);
                    if (empty($fields))
                    {
                        $fields = $vals;
                        continue;
                    }
                    $row = [];
                    $row['Make'] = $make;
                    for ($i = 0; $i < 6; $i++)
                    {
                        if (!empty($fields[$i]) && !empty($vals[$i]))
                            $row[$fields[$i]] = $vals[$i];
                    }

                    $year = $row['Year'];
                    // required
                    if (empty($year)) continue;
                    if (empty($row['Model'])) continue;
                    $notes = [];
                    foreach ($row as $field => $value)
                    {
                        if (empty($field)) continue;
                        if ($field == 'Year' || $field == 'Model' || $field == 'Make') continue;
                        $notes[] = "{$field}: {$value}";
                    }

                    $currentRow = strpos($output, $match[0]);
                    if ($j == count($matches) - 1) $end = strlen($output);
                    else $end = strpos($output, $matches[$j+1][0]);
                    $rowChunk = substr($output, $currentRow, $end - $currentRow);

                    preg_match_all("/<tr id=\\\"allAppsRepeat\\S+AllApps((Detail)?Notes?|position)Row[^>]+>(?<misc>.+?)<\\/tr>/is", $rowChunk, $noteMatches, PREG_SET_ORDER);

                    foreach ($noteMatches as $noteMatch)
                    {
                        $note = str_replace('&nbsp;', ' ', $noteMatch['misc']);
                        $note = strip_tags($note);
                        $note = trim(preg_replace('!\s+!', ' ', $note));
                        $notes[] = $note;
                    }

                    if (!empty($notes))
                        $row['Note'] = implode('; ', $notes);
                    else $row['Note'] = '';
                    $idx3 = stripos($year, '-');
                    if ($idx3 === false) // single year
                        $rows[] = $row;
                    else // year range
                    {
                        $fromYear = intval(trim(substr($year, 0, $idx3 - 1)));
                        $toYear = intval(trim(substr($year, $idx3 + 1)));
                        if (empty($fromYear) || empty($toYear)) continue;
                        if ($toYear < $fromYear)
                        {
                            $tmp = $toYear;
                            $toYear = $fromYear;
                            $fromYear = $tmp;
                        }
                        for ($i = $fromYear; $i <= $toYear; $i++)
                        {
                            $row['Year'] = $i;
                            $rows[] = $row;
                        }
                    }
                }
            }

            foreach ($rows as $row)
            {
                DB::insert("INSERT IGNORE INTO magento.tmp_new_product_compats (sku, universal, make, model, year, note) VALUES (?, 0, ?, ?, ?, ?)", [$sku, $row['Make'], $row['Model'], $row['Year'], $row['Note']]);
            }
        }

        if (!empty($partNotes))
        {
            DB::insert("INSERT INTO magento.tmp_new_product_attribs (sku, attribute_code, value) VALUES (?, 'part_notes', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)", [$sku, $partNotes]);
        }

        curl_setopt($ch, CURLOPT_URL, "https://www.ssfautoparts.com/storefront/AbandonSession.aspx?act=lf&lid=0&t=" . time());
        $output = curl_exec($ch);

        unlink($cookie);
    }
}
