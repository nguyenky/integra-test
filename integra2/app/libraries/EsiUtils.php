<?php

use \Carbon\Carbon;

class EsiUtils
{
    public static $userId = "55004";
    public static $custNo = "55004";
    public static $password = "55004";

    public static function UpdateSKU($sku)
    {
        $re = "/<B>([^:<]+):<.+?Size=\"\\d+\">([^<]+)</is";
        $data = file_get_contents("http://67.228.201.103/cgi-bin/e3catalog.exe?w3exec=ken.completec&w3serverpool=e3commerce1&E3Bridge=108.166.4.4&E3Port=1505&formname=ProdMaster&submit={$sku}&userid=" . self::$userId . "&custno=" . self::$custNo);
        preg_match_all($re, $data, $matches, PREG_SET_ORDER);

        $attributes = [];

        foreach ($matches as $match)
            $attributes[trim($match[1])] = trim($match[2]);

        if ($attributes['Our Part#'] != $sku)
            return;

        $product = EsiProduct::where('sku', $sku)->first();

        if (empty($product))
        {
            $product = new EsiProduct();
            $product->sku = $sku;
        }

        $product->name = $attributes['Description'];
        $product->quantity = $attributes['Available Quantity'];
        $product->list_price = explode('/', str_replace(',', '', str_replace('$', '', $attributes['List Price'])))[0];
        $product->unit_price = explode('/', str_replace(',', '', str_replace('$', '', $attributes['Your Price'])))[0];
        $mpn = trim(str_replace('&nbsp;', '', $attributes['Mfg Part#']));
        $product->mpn = empty($mpn) ? null : $mpn;
        $product->last_scraped = Carbon::now();
        $product->save();

        $sku = strtolower($sku);

        try
        {
            file_put_contents(public_path() . "/esi_img/thumb/{$sku}.jpg", file_get_contents("http://67.228.201.103/e3commerce1/timages/{$sku}.jpg"));
            file_put_contents(public_path() . "/esi_img/full/{$sku}.jpg", file_get_contents("http://67.228.201.103/e3commerce1/pimages/{$sku}.jpg"));
        }
        catch (Exception $e)
        {
        }

        return $product->id;
    }

    public static function UpdateCategories()
    {
        $data = file_get_contents("http://67.228.201.103/cgi-bin/e3catalog.exe?w3exec=ken.completec&w3serverpool=e3commerce1&E3Bridge=108.166.4.4&E3Port=1505&formname=login&submit=login&userid=" . self::$userId . "&custno=" . self::$custNo . "&password=" . self::$password);
        $idx = stripos($data, "//cat.grp.xref-start");
        $idx2 = stripos($data, "//cat.grp.xref-end");
        $cats = explode('|', substr($data, $idx, $idx2 - $idx));

        unset($cats[0]);

        foreach ($cats as $cat)
        {
            $fields = explode('*', $cat);

            if (!isset($fields[1]) || !isset($fields[3]))
                continue;

            EsiCategory::firstOrCreate(['id' => $fields[1], 'title' => $fields[3]]);
        }
    }

    public static function UpdateVehicles()
    {
        $re = "/<option[^>]+>([^<]+)/i";
        $re2 = "/<option\\s+value=\"([^\"]+)\"\\s*>([^<]+)/i";

        $data = file_get_contents("http://67.228.201.103/e3commerce1/xrefcontent.htm");

        $idx = stripos($data, 'name="Year"');
        $idx2 = stripos($data, '</select>');
        preg_match_all($re, substr($data, $idx, $idx2 - $idx), $yearMatches, PREG_SET_ORDER);
        unset($yearMatches[0]);

        $idx = stripos($data, 'name="Mfg"');
        $idx2 = stripos($data, '</select>');
        preg_match_all($re, substr($data, $idx, $idx2 - $idx), $makeMatches, PREG_SET_ORDER);
        unset($makeMatches[0]);

        foreach ($yearMatches as $yearMatch)
        {
            $year = trim($yearMatch[1]);

            foreach ($makeMatches as $makeMatch)
            {
                $make = trim($makeMatch[1]);
                $shortMake = explode(' ', $make)[0];
                $data = '';

                try
                {
                    $data = file_get_contents("http://67.228.201.103/e3commerce1/YearMakeModel/{$year}_{$shortMake}.htm");
                }
                catch (Exception $e)
                {

                }

                if (empty($data))
                    continue;

                $idx = stripos($data, 'All Models');
                $idx2 = stripos($data, '</select>');
                preg_match_all($re2, substr($data, $idx, $idx2 - $idx), $modelMatches, PREG_SET_ORDER);

                foreach ($modelMatches as $modelMatch)
                {
                    $modelId = trim($modelMatch[1]);
                    $model = trim($modelMatch[2]);

                    if (!ctype_digit($modelId))
                        continue;

                    EsiVehicle::firstOrCreate(['year' => $year, 'make' => $make, 'model' => $model, 'model_id' => $modelId]);
                }
            }
        }
    }
}