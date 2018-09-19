<?php

use \Carbon\Carbon;

class IntegraUtils
{
    public static function getFulfillmentCodes()
    {
        return
        [
            0 => 'Unspecified',
            1 => 'Direct',
            2 => 'Pickup',
            3 => 'EOC'
        ];
    }

    public static function getStatusCodes()
    {
        
        return
            [
                0 => 'Unspecified',
                1 => 'Scheduled',
                2 => 'Item Ordered',
                3 => 'Ready for Dispatch',
                4 => 'Order Complete',
                90 => 'Cancelled',
                91 => 'Payment Pending',
                92 => 'Return Pending',
                93 => 'Return Complete',
                94 => 'Refund Pending',
                99 => 'Error',
                100 => 'Reshipment',
                101 => 'Exchange',
                102 => 'Non-Delivered',
                103 => 'Pending Refund 1',
                104 => 'Pending Refund 2',
                105 => 'Pending Refund 3'
            ];
    }

    public static function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    public static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    public static function getPartsInfo($parts)
    {
        
        $res = [];

        foreach ($parts as $sku => $qty)
        {
            $item = [];
            $item['sku'] = $sku;
            $item['qty'] = $qty;

            $rows = DB::select(<<<EOQ
    SELECT p.name, p.brand, CONCAT('W', s.id) as supplier
    FROM integra_prod.products p
    INNER JOIN integra_prod.suppliers s ON p.supplier_id = s.id
    WHERE p.sku = ?
    LIMIT 1
EOQ
                , [$sku]);

            if (!empty($rows))
            {
                $item['name'] = $rows[0]['name'];
                $item['brand'] = $rows[0]['brand'];
                $item['supplier'] = $rows[0]['supplier'];
            }
            else
            {
                $item['name'] = '';
                $item['brand'] = '';
                $item['supplier'] = '';
            }

            $res[] = $item;
        }

        return $res;
    }

    public static function getKitParts($sku)
    {
        $items = [];

        $rows = DB::select(<<<EOQ
SELECT c.sku, k.quantity
FROM integra_prod.products p, integra_prod.products c, integra_prod.kit_components k
WHERE p.sku = ?
AND p.is_kit = 1
AND p.id = k.product_id
AND k.component_product_id = c.id
EOQ
            , [$sku]);

        foreach ($rows as $row)
            $items[$row['sku']] = $row['quantity'];

        return $items;
    }

    public static function getSKUParts($items)
    {
        $parts = array();

        if (!empty($items))
        {
            foreach ($items as $sku => $qty)
            {
                if (empty($sku))
                    continue;

                if (self::startsWith($sku, 'EK'))
                {
                    if (self::endsWith($sku, '$D') || self::endsWith($sku, '$W'))
                        $kitSku = substr($sku, 0, strlen($sku) - 2);
                    else $kitSku = $sku;

                    $kitParts = self::getKitParts($kitSku);

                    foreach ($kitParts as $compSku => $compQty)
                    {
                        $existingQty = 0;
                        if (array_key_exists($compSku, $parts))
                            $existingQty = $parts[$compSku];

                        $parts[$compSku] = $existingQty + ($compQty * $qty);
                    }

                    if (count($kitParts) > 0)
                        continue;
                }

                $sku = str_replace('/', '.', strtoupper($sku));

                $components = explode('$', $sku);

                foreach ($components as $component)
                {
                    if ($component == 'D' || $component == 'W')
                        continue;

                    // ignore dash for pu, wp, tr
                    if (strpos($component, 'PU') === 0
                        || strpos($component, 'WP') === 0
                        || strpos($component, 'TR') === 0)
                        $pair = [$component];
                    else
                        $pair = explode('-', $component);

                    if (count($pair) == 2)
                    {
                        $sku = $pair[0];
                        if (is_numeric($pair[1]) && $pair[1] > 0)
                            $totalQty = $qty * $pair[1];
                        else
                            $totalQty = $qty;
                    }
                    else
                    {
                        $sku = $component;
                        $totalQty = $qty;
                    }

                    if (self::startsWith($sku, 'EOCS'))
                        $sku = substr($sku, 4);

                    if (self::startsWith($sku, 'EOC'))
                        $sku = substr($sku, 3);

                    $existingQty = 0;
                    if (array_key_exists($sku, $parts))
                        $existingQty = $parts[$sku];

                    $parts[$sku] = $existingQty + $totalQty;
                }
            }
        }

        return $parts;
    }

    public static function tokenTruncate($string, $your_desired_width)
    {
        $parts = preg_split('/([\s\n\r]+)/', $string, null, PREG_SPLIT_DELIM_CAPTURE);
        $parts_count = count($parts);

        $length = 0;
        $last_part = 0;
        for (; $last_part < $parts_count; ++$last_part) {
            $length += strlen($parts[$last_part]);
            if ($length > $your_desired_width) { break; }
        }

        return implode(array_slice($parts, 0, $last_part));
    }

    public static function array_chunk_fixed($input, $num, $preserve_keys = FALSE)
    {
        $count = count($input) ;
        if ($count)
            $input = array_chunk($input, ceil($count/$num), $preserve_keys) ;
        $input = array_pad($input, $num, array()) ;
        return $input ;
    }

    public static function loadCSV($table, $fields, $postQuery = null)
    {
        try
        {
            $file = Input::file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            if ($extension != 'csv')
                return Response::json('Only CSV files are supported.', 500);

            $directory = public_path() . "/uploads/{$table}";
            if (!is_dir($directory)) mkdir($directory);

            $filename = sha1(time() . '_' . $file->getClientOriginalName()) . ".${extension}";
            $upload_success = Input::file('file')->move($directory, $filename);

            $inserted = 0;

            if ($upload_success)
            {
                $lines = file("{$directory}/$filename", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                if (count($lines) < 2)
                    return Response::json('Make sure that your file follows the template and includes the header.', 500);

                $qs = [];
                $fieldCount = count($fields);

                for ($i = 0; $i < $fieldCount; $i++)
                    $qs[] = '?';

                $lineCount = count($lines);

                for ($i = 1; $i < $lineCount; $i++)
                {
                    $cols = str_getcsv(trim($lines[$i]), ",");

                    if ($fieldCount != count($cols))
                    {
                        $cols = str_getcsv(trim($lines[$i]), ";");
                        if ($fieldCount != count($cols))
                            continue;
                    }

                    $cols = array_map('trim', $cols);

                    $query = sprintf("INSERT INTO %s (%s) VALUES (%s)", $table, implode(',', $fields), implode(',', $qs));

                    try
                    {
                        if (DB::insert($query, $cols)) $inserted++;
                    }
                    catch (Exception $e)
                    {
                    }
                }

                if (!empty($postQuery))
                    DB::connection()->getPdo()->exec($postQuery);

                $res = array("newCount" => $inserted);

                return $res;
            }
        }
        catch (Exception $e)
        {
            return Response::json('Make sure that your file follows the template. Duplicates will be skipped.', 500);
        }
    }

    public static function tryFunc($func, $successMsg = null, $errorMsg = null)
    {
        try
        {
            $ret = call_user_func($func);

            if (empty($ret))
                $ret = [];

            if (!empty($successMsg))
                $ret["msg"] = $successMsg;

            return $ret;
        }
        catch (Exception $e)
        {
            return Response::json(array
            (
                "msg" => empty($errorMsg) ? $e->getMessage() : $errorMsg,
            ), 500);
        }
    }

    public static function addKeys($data)
    {
        $res = [];

        foreach ($data as $d)
            $res[$d['id']] = $d;

        return $res;
    }

    public static function getNestedVar($array, $name)
    {
        $parts = explode('.', $name);

        foreach($parts as $part)
        {
            if (!isset($array[$part])) return null;
            $array = $array[$part];
        }

        return $array;
    }

    public static function paginate($data)
    {
        Log::info("============== In paginate ===============");
        $filtered = [];
        $res = [];
        try {

            if (Input::has('filter'))
            {
                foreach ($data as $d)
                {
                    $include = true;

                    foreach (Input::get('filter') as $fields => $value)
                    {
                        // internal fields
                        if (substr($fields, 0, 2) == '__')
                            continue;

                        $fs = explode('|', urldecode($fields));

                        $sub = false;

                        foreach ($fs as $f)
                        {
                            $tmp = explode('@', $f);
                            $f = $tmp[0];

                            if (count($tmp) > 1)
                                $ft = $tmp[1];
                            else $ft = 'like';

                            if ($ft == 'num')
                            {
                                $filters = Input::get('filter');
                                if (array_key_exists('__' . $f, $filters))
                                    $ft = $filters['__' . $f];
                            }

                            if ($ft == 'num')
                                $ft = '=';

                            if ($ft == 'like' && (stripos(static::getNestedVar($d, $f), $value) !== false))
                            {
                                $sub = true;
                                break;
                            }
                            else if ($ft == '=' && (static::getNestedVar($d, $f) == $value))
                            {
                                $sub = true;
                                break;
                            }
                            else if ($ft == '>' && (static::getNestedVar($d, $f) > $value))
                            {
                                $sub = true;
                                break;
                            }
                            else if ($ft == '>=' && (static::getNestedVar($d, $f) >= $value))
                            {
                                $sub = true;
                                break;
                            }
                            else if ($ft == '<' && (static::getNestedVar($d, $f) < $value))
                            {
                                $sub = true;
                                break;
                            }
                            else if ($ft == '<=' && (static::getNestedVar($d, $f) <= $value))
                            {
                                $sub = true;
                                break;
                            }
                            else if ($ft == 'fromdate')
                            {
                                $dt = new Carbon(static::getNestedVar($d, $f));
                                $dtf = new Carbon(str_replace('"', '', $value));
                                if (Input::has('tz')) $dtf->subMinutes(Input::get('tz'));
                                $dtf2 = new Carbon($dtf->toDateString(), $dt->getTimezone());

                                if ($dt->gte($dtf2))
                                {
                                    $sub = true;
                                    break;
                                }
                            }
                            else if ($ft == 'todate')
                            {
                                $dt = new Carbon(static::getNestedVar($d, $f));
                                $dtf = new Carbon(str_replace('"', '', $value));
                                if (Input::has('tz')) $dtf->subMinutes(Input::get('tz'));
                                $dtf2 = new Carbon($dtf->toDateString(), $dt->getTimezone());
                                $dtf2->addDay();

                                if ($dt->lt($dtf2))
                                {
                                    $sub = true;
                                    break;
                                }
                            }
                        }

                        if (!$sub)
                        {
                            $include = false;
                            break;
                        }
                    }

                    if ($include)
                        $filtered[] = $d;
                }
            }
            else $filtered = $data;

            $res['total'] = count($filtered);

            if (Input::has('sorting'))
            {
                foreach (Input::get('sorting') as $field => $dir)
                {
                    usort($filtered, function($a, $b) use ($field, $dir)
                    {
                        $x = static::getNestedVar($a, $field);
                        $y = static::getNestedVar($b, $field);

                        if ($dir == 'asc')
                        {
                            if ($x > $y)
                                return 1;
                            else if ($x < $y)
                                return -1;
                            else return 0;
                        }
                        else if ($dir == 'desc')
                        {
                            if ($x < $y)
                                return 1;
                            else if ($x > $y)
                                return -1;
                            else return 0;
                        }
                        else return 0;
                    });
                }
            }

            if (Input::has('count'))
            {
                $offset = 0;

                if (Input::has('page'))
                    $offset = (Input::get('page') - 1) * Input::get('count');

                $res['result'] = array_slice($filtered, $offset, Input::get('count'));
            }
            else $res['result'] = $filtered;

        } catch(Exception $ex) {
            Log::error($ex->message());
        }

        return $res;
    }

    public static function humanFileSize($bytes, $decimals = 2)
    {
        $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
        $factor = (int)floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    public static function emptyResults()
    {
        $res['result'] = [];
        $res['total'] = 0;

        return $res;
    }

    public static function getSupplier($mpn)
    {
        $supplierRow = DB::select("SELECT value FROM magento.catalog_product_entity_varchar cpev, magento.catalog_product_entity cpe WHERE cpe.entity_id = cpev.entity_id AND cpe.sku = ? AND cpev.store_id = 0 AND cpev.attribute_id = 150", [$mpn]);
        if (!empty($supplierRow) && !empty($supplierRow[0]['value']))
        {
            return trim($supplierRow[0]['value']);
        }
        else
        {
            if (strpos($mpn, 'PU') === 0)
                return '7';
            else if (strpos($mpn, 'WP') === 0)
                return '8';
            else if (strpos($mpn, 'TR') === 0)
                return '9';
            else if (stripos($mpn, '.') !== false)
                return '2';
            else if (stripos($mpn, 'EW') === 0)
                return '4';
            else if (stripos($mpn, 'EK') === 0)
                return '0';
            else return '1';
        }
    }

    public static function queryMpn($mpn)
    {
        $ret['entity_id'] = '';
        $ret['brand'] = '';
        $ret['name'] = '';
        $ret['notes'] = '';
        $ret['codes'] = '';

        if (empty($mpn)) return $ret;

        $entity = DB::select("SELECT cpe.entity_id, cpev.value FROM magento.catalog_product_entity cpe, magento.catalog_product_entity_varchar cpev WHERE cpe.sku = ? AND cpe.entity_id = cpev.entity_id AND cpev.store_id = 0 AND cpev.attribute_id = 71", [$mpn]);
        if (!empty($entity))
        {
            $ret['entity_id'] = $entity[0]['entity_id'];
            $ret['name'] = trim($entity[0]['value']);

            $brandRow = DB::select("SELECT value FROM magento.catalog_product_entity_varchar cpev WHERE entity_id = ? AND store_id = 0 AND attribute_id = 135", [$ret['entity_id']]);
            if (!empty($brandRow))
            {
                $ret['brand'] = trim($brandRow[0]['value']);
            }

            $notesRow = DB::select("SELECT value FROM magento.catalog_product_entity_text cpev WHERE entity_id = ? AND store_id = 0 AND attribute_id = 145", [$ret['entity_id']]);
            if (!empty($notesRow))
            {
                $ret['notes'] = trim($notesRow[0]['value']);
            }

            $codesRow = DB::select("SELECT GROUP_CONCAT(code SEPARATOR ' / ') AS codes FROM magento.part_numbers WHERE sku = ?", [$mpn]);
            if (!empty($codesRow))
            {
                $ret['codes'] = trim($codesRow[0]['codes']);
            }
        }

        return $ret;
    }

    public static function getPrice($sku, $maxDays = 3)
    {
        $ret['cost'] = 0;
        $ret['core'] = 0;
        $ret['name'] = '';
        $ret['brand'] = '';
        $ret['supplier'] = '';
        $ret['inactive'] = 0;

        // load from database if at loaded within the last maxDays
        $supplierId = IntegraUtils::getSupplier($sku);
        if ($supplierId == 1)
        {
            $supplier = 'imc';
            $rows = DB::select("SELECT unit_price, core_price, name, brand, inactive FROM eoc.imc_items WHERE mpn = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$sku, $maxDays]);
            if (!empty($rows) && !empty($rows[0]))
            {
                $ret['cost'] = $rows[0]['unit_price'];
                $ret['core'] = $rows[0]['core_price'];
                $ret['name'] = $rows[0]['name'];
                $ret['brand'] = $rows[0]['brand'];
                $ret['inactive'] = $rows[0]['inactive'];
            }
        }
        else if ($supplierId == 2)
        {
            $supplier = 'ssf';
            $rows = DB::select("SELECT unit_price, core_unit_price, name, brand, inactive FROM eoc.ssf_items WHERE CONCAT(mpn, '.', brand_id) = ? AND mpn >= ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$sku, $sku, $maxDays]);
            if (!empty($rows) && !empty($rows[0]))
            {
                $ret['cost'] = $rows[0]['unit_price'];
                $ret['core'] = $rows[0]['core_unit_price'];
                $ret['name'] = $rows[0]['name'];
                $ret['brand'] = $rows[0]['brand'];
                $ret['inactive'] = $rows[0]['inactive'];
            }
        }
        else return $ret;

        $ret['supplier'] = $supplierId;

        // try to load from IPO
        if (empty($ret['cost'])) {
            $json = json_decode(file_get_contents("http://integra.eocenterprise.com/{$supplier}_ajax.php?sku={$sku}"), true);
            $ret['cost'] = str_replace(',', '', $json['price']) * 1;
            $ret['core'] = str_replace(',', '', $json['core']) * 1;

            if ($json['desc'] == 'Not found') $ret['inactive'] = 1;
            else
            {
                $ret['name'] = $json['desc'];
                $ret['brand'] = $json['brand'];
            }
        }

        return $ret;
    }

    public static function generateTitles($mpn, $qty, $versions, $entityId, $name, $brand)
    {
        $titles = [];
        if ($qty < 1) return $titles;
        if ($versions < 1) return $titles;

        if ($qty == 1) $prefixes = [''];
        else $prefixes = ["Set of $qty", "$qty pcs", "Kit of $qty"];

        foreach ($prefixes as $prefix)
        {
            $compats = [];
            $last = '';

            // test raw prefix and name only
            $res = self::previewTitle($mpn, $prefix, $name, $compats);
            if (!$res['ok']) continue; // try different prefix is this one doesn't fit already

            $cs = DB::select("SELECT make, model, COUNT(*) as years FROM magento.elite_1_mapping em WHERE ebay_ok = 1 AND entity_id = ? AND universal = 0 GROUP BY 1, 2 ORDER BY 3 DESC", [$entityId]);

            foreach ($cs as $c)
            {
                $make = trim($c['make']);
                $model = trim($c['model']);
                file_put_contents(storage_path('titles.txt'), "{$mpn}: adding {$make} {$model} (" . $c['years'] . " years)\n", FILE_APPEND);

                // insert make once
                if (!array_key_exists($make, $compats))
                    $compats[$make] = [];

                // insert model once
                if (!in_array($model, $compats[$make]))
                    $compats[$make][] = $model;

                // try with current compats
                $res = self::previewTitle($mpn, $prefix, $name, $compats);
                if (!$res['ok'])
                {
                    // too long, add last candidate to list
                    if (!empty($last))
                    {
                        // try with brand
                        if (!empty($brand) && $brand != $mpn && strlen("$last $brand") <= 80)
                            $last .= " $brand";
                        else file_put_contents(storage_path('titles.txt'), "{$mpn}: will not include brand, too long\n", FILE_APPEND);

                        // try with mpn
                        if (strlen("$last $mpn") <= 80)
                            $last .= " $mpn";
                        else file_put_contents(storage_path('titles.txt'), "{$mpn}: will not include mpn, too long\n", FILE_APPEND);

                        $titles[$last] = 1;
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                        $last = '';

                        if (count($titles) >= $versions) return array_keys($titles);
                    }

                    // clear compats
                    $compats = [];
                    continue;
                }
                $last = $res['title'];
            }

            // add last entry
            if (!empty($last))
            {
                // try with brand
                if (!empty($brand) && $brand != $mpn && strlen("$last $brand") <= 80)
                    $last .= " $brand";

                // try with mpn
                if (strlen("$last $mpn") <= 80)
                    $last .= " $mpn";

                $titles[$last] = 1;
                file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                if (count($titles) >= $versions) return array_keys($titles);
            }
        }

        ///////// more techniques to produce more titles below: random compatibility order

        if (count($titles) < $versions) {
            for ($tries = 1; $tries < 10; $tries++) {
                file_put_contents(storage_path('titles.txt'), "{$mpn}: trying random compatibility order\n", FILE_APPEND);

                foreach ($prefixes as $prefix) {
                    $compats = [];
                    $last = '';

                    // test raw prefix and name only
                    $res = self::previewTitle($mpn, $prefix, $name, $compats);
                    if (!$res['ok']) continue; // try different prefix is this one doesn't fit already

                    $cs = DB::select("SELECT make, model, COUNT(*) as years FROM magento.elite_1_mapping em WHERE ebay_ok = 1 AND entity_id = ? AND universal = 0 GROUP BY 1, 2 ORDER BY RAND()", [$entityId]);

                    foreach ($cs as $c) {
                        $make = trim($c['make']);
                        $model = trim($c['model']);
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: adding {$make} {$model} (" . $c['years'] . " years)\n", FILE_APPEND);

                        // insert make once
                        if (!array_key_exists($make, $compats))
                            $compats[$make] = [];

                        // insert model once
                        if (!in_array($model, $compats[$make]))
                            $compats[$make][] = $model;

                        // try with current compats
                        $res = self::previewTitle($mpn, $prefix, $name, $compats);
                        if (!$res['ok']) {
                            // too long, add last candidate to list
                            if (!empty($last)) {
                                // try with brand
                                if (!empty($brand) && $brand != $mpn && strlen("$last $brand") <= 80)
                                    $last .= " $brand";
                                else file_put_contents(storage_path('titles.txt'), "{$mpn}: will not include brand, too long\n", FILE_APPEND);

                                // try with mpn
                                if (strlen("$last $mpn") <= 80)
                                    $last .= " $mpn";
                                else file_put_contents(storage_path('titles.txt'), "{$mpn}: will not include mpn, too long\n", FILE_APPEND);

                                $titles[$last] = 1;
                                file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                                $last = '';

                                if (count($titles) >= $versions) return array_keys($titles);
                            }

                            // clear compats
                            $compats = [];
                            continue;
                        }
                        $last = $res['title'];
                    }

                    // add last entry
                    if (!empty($last)) {
                        // try with brand
                        if (!empty($brand) && $brand != $mpn && strlen("$last $brand") <= 80)
                            $last .= " $brand";

                        // try with mpn
                        if (strlen("$last $mpn") <= 80)
                            $last .= " $mpn";

                        $titles[$last] = 1;
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                        if (count($titles) >= $versions) return array_keys($titles);
                    }
                }
            }
        }

        ///////// more techniques to produce more titles below: add brand first

        if (count($titles) < $versions) {
            for ($tries = 1; $tries < 10; $tries++) {
                file_put_contents(storage_path('titles.txt'), "{$mpn}: trying brand as prefix + random compatibility\n", FILE_APPEND);

                foreach ($prefixes as $prefix) {
                    $prefixBrand = trim($prefix . ' ' . $brand);
                    $compats = [];
                    $last = '';

                    // test raw prefix and name only
                    $res = self::previewTitle($mpn, $prefixBrand, $name, $compats);
                    if (!$res['ok']) continue; // try different prefix is this one doesn't fit already

                    $cs = DB::select("SELECT make, model, COUNT(*) as years FROM magento.elite_1_mapping em WHERE ebay_ok = 1 AND entity_id = ? AND universal = 0 GROUP BY 1, 2 ORDER BY RAND()", [$entityId]);

                    foreach ($cs as $c) {
                        $make = trim($c['make']);
                        $model = trim($c['model']);
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: adding {$make} {$model} (" . $c['years'] . " years)\n", FILE_APPEND);

                        // insert make once
                        if (!array_key_exists($make, $compats))
                            $compats[$make] = [];

                        // insert model once
                        if (!in_array($model, $compats[$make]))
                            $compats[$make][] = $model;

                        // try with current compats
                        $res = self::previewTitle($mpn, $prefixBrand, $name, $compats);
                        if (!$res['ok']) {
                            // too long, add last candidate to list
                            if (!empty($last)) {

                                // try with mpn
                                if (strlen("$last $mpn") <= 80)
                                    $last .= " $mpn";
                                else file_put_contents(storage_path('titles.txt'), "{$mpn}: will not include mpn, too long\n", FILE_APPEND);

                                $titles[$last] = 1;
                                file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                                $last = '';

                                if (count($titles) >= $versions) return array_keys($titles);
                            }

                            // clear compats
                            $compats = [];
                            continue;
                        }
                        $last = $res['title'];
                    }

                    // add last entry
                    if (!empty($last)) {
                        // try with mpn
                        if (strlen("$last $mpn") <= 80)
                            $last .= " $mpn";

                        $titles[$last] = 1;
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                        if (count($titles) >= $versions) return array_keys($titles);
                    }
                }
            }
        }

        ///////// more techniques to produce more titles below: add mpn first

        if (count($titles) < $versions) {
            for ($tries = 1; $tries < 10; $tries++) {
                file_put_contents(storage_path('titles.txt'), "{$mpn}: trying mpn as prefix + random compatibility\n", FILE_APPEND);

                foreach ($prefixes as $prefix) {
                    $prefixMpn = trim($prefix . ' ' . $mpn);
                    $compats = [];
                    $last = '';

                    // test raw prefix and name only
                    $res = self::previewTitle($mpn, $prefixMpn, $name, $compats);
                    if (!$res['ok']) continue; // try different prefix is this one doesn't fit already

                    $cs = DB::select("SELECT make, model, COUNT(*) as years FROM magento.elite_1_mapping em WHERE ebay_ok = 1 AND entity_id = ? AND universal = 0 GROUP BY 1, 2 ORDER BY RAND()", [$entityId]);

                    foreach ($cs as $c) {
                        $make = trim($c['make']);
                        $model = trim($c['model']);
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: adding {$make} {$model} (" . $c['years'] . " years)\n", FILE_APPEND);

                        // insert make once
                        if (!array_key_exists($make, $compats))
                            $compats[$make] = [];

                        // insert model once
                        if (!in_array($model, $compats[$make]))
                            $compats[$make][] = $model;

                        // try with current compats
                        $res = self::previewTitle($mpn, $prefixMpn, $name, $compats);
                        if (!$res['ok']) {
                            // too long, add last candidate to list
                            if (!empty($last)) {
                                // try with brand
                                if (!empty($brand) && $brand != $mpn && strlen("$last $brand") <= 80)
                                    $last .= " $brand";
                                else file_put_contents(storage_path('titles.txt'), "{$mpn}: will not include brand, too long\n", FILE_APPEND);

                                $titles[$last] = 1;
                                file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                                $last = '';

                                if (count($titles) >= $versions) return array_keys($titles);
                            }

                            // clear compats
                            $compats = [];
                            continue;
                        }
                        $last = $res['title'];
                    }

                    // add last entry
                    if (!empty($last)) {
                        // try with brand
                        if (!empty($brand) && $brand != $mpn && strlen("$last $brand") <= 80)
                            $last .= " $brand";

                        $titles[$last] = 1;
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                        if (count($titles) >= $versions) return array_keys($titles);
                    }
                }
            }
        }

        ///////// more techniques to produce more titles below: add mpn + brand first

        if (count($titles) < $versions) {
            for ($tries = 1; $tries < 10; $tries++) {
                file_put_contents(storage_path('titles.txt'), "{$mpn}: trying mpn + brand as prefix + random compatibility\n", FILE_APPEND);

                foreach ($prefixes as $prefix) {
                    $prefixMpn = trim($prefix . ' ' . $mpn);
                    $prefixMpn = trim($prefixMpn . ' ' . $brand);
                    $compats = [];
                    $last = '';

                    // test raw prefix and name only
                    $res = self::previewTitle($mpn, $prefixMpn, $name, $compats);
                    if (!$res['ok']) continue; // try different prefix is this one doesn't fit already

                    $cs = DB::select("SELECT make, model, COUNT(*) as years FROM magento.elite_1_mapping em WHERE ebay_ok = 1 AND entity_id = ? AND universal = 0 GROUP BY 1, 2 ORDER BY RAND()", [$entityId]);

                    foreach ($cs as $c) {
                        $make = trim($c['make']);
                        $model = trim($c['model']);
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: adding {$make} {$model} (" . $c['years'] . " years)\n", FILE_APPEND);

                        // insert make once
                        if (!array_key_exists($make, $compats))
                            $compats[$make] = [];

                        // insert model once
                        if (!in_array($model, $compats[$make]))
                            $compats[$make][] = $model;

                        // try with current compats
                        $res = self::previewTitle($mpn, $prefixMpn, $name, $compats);
                        if (!$res['ok']) {
                            // too long, add last candidate to list
                            if (!empty($last)) {
                                $titles[$last] = 1;
                                file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                                $last = '';

                                if (count($titles) >= $versions) return array_keys($titles);
                            }

                            // clear compats
                            $compats = [];
                            continue;
                        }
                        $last = $res['title'];
                    }

                    // add last entry
                    if (!empty($last)) {
                        $titles[$last] = 1;
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                        if (count($titles) >= $versions) return array_keys($titles);
                    }
                }
            }
        }

        ///////// more techniques to produce more titles below: add brand + mpn first

        if (count($titles) < $versions) {
            for ($tries = 1; $tries < 10; $tries++) {
                file_put_contents(storage_path('titles.txt'), "{$mpn}: trying brand + mpn as prefix + random compatibility\n", FILE_APPEND);

                foreach ($prefixes as $prefix) {
                    $prefixMpn = trim($prefix . ' ' . $brand);
                    $prefixMpn = trim($prefixMpn . ' ' . $mpn);
                    $compats = [];
                    $last = '';

                    // test raw prefix and name only
                    $res = self::previewTitle($mpn, $prefixMpn, $name, $compats);
                    if (!$res['ok']) continue; // try different prefix is this one doesn't fit already

                    $cs = DB::select("SELECT make, model, COUNT(*) as years FROM magento.elite_1_mapping em WHERE ebay_ok = 1 AND entity_id = ? AND universal = 0 GROUP BY 1, 2 ORDER BY RAND()", [$entityId]);

                    foreach ($cs as $c) {
                        $make = trim($c['make']);
                        $model = trim($c['model']);
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: adding {$make} {$model} (" . $c['years'] . " years)\n", FILE_APPEND);

                        // insert make once
                        if (!array_key_exists($make, $compats))
                            $compats[$make] = [];

                        // insert model once
                        if (!in_array($model, $compats[$make]))
                            $compats[$make][] = $model;

                        // try with current compats
                        $res = self::previewTitle($mpn, $prefixMpn, $name, $compats);
                        if (!$res['ok']) {
                            // too long, add last candidate to list
                            if (!empty($last)) {
                                $titles[$last] = 1;
                                file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                                $last = '';

                                if (count($titles) >= $versions) return array_keys($titles);
                            }

                            // clear compats
                            $compats = [];
                            continue;
                        }
                        $last = $res['title'];
                    }

                    // add last entry
                    if (!empty($last)) {
                        $titles[$last] = 1;
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                        if (count($titles) >= $versions) return array_keys($titles);
                    }
                }
            }
        }

        ///////// more techniques to produce more titles below: brand + name + mpn

        if (count($titles) < $versions) {
            for ($tries = 1; $tries < 10; $tries++) {
                file_put_contents(storage_path('titles.txt'), "{$mpn}: trying brand + name + mpn + random compatibility\n", FILE_APPEND);

                foreach ($prefixes as $prefix) {
                    $prefixMpn = trim($prefix . ' ' . $brand);
                    $compats = [];
                    $last = '';

                    // test raw prefix and name only
                    $res = self::previewTitle($mpn, $prefixMpn, $name . ' ' . $mpn, $compats);
                    if (!$res['ok']) continue; // try different prefix is this one doesn't fit already

                    $cs = DB::select("SELECT make, model, COUNT(*) as years FROM magento.elite_1_mapping em WHERE ebay_ok = 1 AND entity_id = ? AND universal = 0 GROUP BY 1, 2 ORDER BY RAND()", [$entityId]);

                    foreach ($cs as $c) {
                        $make = trim($c['make']);
                        $model = trim($c['model']);
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: adding {$make} {$model} (" . $c['years'] . " years)\n", FILE_APPEND);

                        // insert make once
                        if (!array_key_exists($make, $compats))
                            $compats[$make] = [];

                        // insert model once
                        if (!in_array($model, $compats[$make]))
                            $compats[$make][] = $model;

                        // try with current compats
                        $res = self::previewTitle($mpn, $prefixMpn, $name . ' ' . $mpn, $compats);
                        if (!$res['ok']) {
                            // too long, add last candidate to list
                            if (!empty($last)) {
                                $titles[$last] = 1;
                                file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                                $last = '';

                                if (count($titles) >= $versions) return array_keys($titles);
                            }

                            // clear compats
                            $compats = [];
                            continue;
                        }
                        $last = $res['title'];
                    }

                    // add last entry
                    if (!empty($last)) {
                        $titles[$last] = 1;
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                        if (count($titles) >= $versions) return array_keys($titles);
                    }
                }
            }
        }

        ///////// more techniques to produce more titles below: mpn + name + brand

        if (count($titles) < $versions) {
            for ($tries = 1; $tries < 10; $tries++) {
                file_put_contents(storage_path('titles.txt'), "{$mpn}: trying mpn + name + brand + random compatibility\n", FILE_APPEND);

                foreach ($prefixes as $prefix) {
                    $prefixMpn = trim($prefix . ' ' . $mpn);
                    $compats = [];
                    $last = '';

                    // test raw prefix and name only
                    $res = self::previewTitle($mpn, $prefixMpn, trim($name . ' ' . $brand), $compats);
                    if (!$res['ok']) continue; // try different prefix is this one doesn't fit already

                    $cs = DB::select("SELECT make, model, COUNT(*) as years FROM magento.elite_1_mapping em WHERE ebay_ok = 1 AND entity_id = ? AND universal = 0 GROUP BY 1, 2 ORDER BY RAND()", [$entityId]);

                    foreach ($cs as $c) {
                        $make = trim($c['make']);
                        $model = trim($c['model']);
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: adding {$make} {$model} (" . $c['years'] . " years)\n", FILE_APPEND);

                        // insert make once
                        if (!array_key_exists($make, $compats))
                            $compats[$make] = [];

                        // insert model once
                        if (!in_array($model, $compats[$make]))
                            $compats[$make][] = $model;

                        // try with current compats
                        $res = self::previewTitle($mpn, $prefixMpn, trim($name . ' ' . $brand), $compats);
                        if (!$res['ok']) {
                            // too long, add last candidate to list
                            if (!empty($last)) {
                                $titles[$last] = 1;
                                file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                                $last = '';

                                if (count($titles) >= $versions) return array_keys($titles);
                            }

                            // clear compats
                            $compats = [];
                            continue;
                        }
                        $last = $res['title'];
                    }

                    // add last entry
                    if (!empty($last)) {
                        $titles[$last] = 1;
                        file_put_contents(storage_path('titles.txt'), "{$mpn}: current titles:\n" . print_r(array_keys($titles), true) . "\n", FILE_APPEND);
                        if (count($titles) >= $versions) return array_keys($titles);
                    }
                }
            }
        }

        file_put_contents(storage_path('titles.txt'), "{$mpn}: target number of versions not reached: " . count($titles) . " out of {$versions}\n", FILE_APPEND);
        return array_keys($titles);
    }

    public static function previewTitle($mpn, $prefix, $name, $compats)
    {
        $res['ok'] = false;
        $res['title'] = trim("$prefix $name");

        if (strlen($res['title']) > 80) return $res;

        foreach ($compats as $make => $models)
        {
            $res['title'] .= " $make";

            foreach ($models as $model)
            {
                $res['title'] .= " $model";
            }
        }

        $res['title'] = trim($res['title']);

        if (strlen($res['title']) <= 80) $res['ok'] = true;
        file_put_contents(storage_path('titles.txt'), $mpn . ': testing title "' . $res['title'] . '" [' . ($res['ok'] ? 'OK' : 'TOO LONG') . "]\n", FILE_APPEND);
        return $res;
    }

    public static function getCombos(array $data, array &$all = [], array $group = [], $value = null, $i = 0, $key = null)
    {
        $keys = array_keys($data);
        if (isset($value) === true)
            $group[$key] = $value;

        if ($i >= count($data))
            array_push($all, $group);
        else
        {
            $currentKey = $keys[$i];
            $currentElement = $data[$currentKey];

            if (count($data[$currentKey]) <= 0)
                self::getCombos($data, $all, $group, null, $i+1, $currentKey);
            else
            {
                foreach ($currentElement as $val)
                    self::getCombos($data, $all, $group, $val, $i+1, $currentKey);
            }
        }

        return $all;
    }

    public static function getCommonCompatibility(array $combo)
    {
        $defs = [];

        foreach ($combo as $desc => $data)
        {
            $rows = DB::select("SELECT definition_id FROM magento.elite_1_mapping WHERE entity_id = ? AND universal = 0 ORDER BY 1", [$data['entity_id']]);
            $ds = [];

            foreach ($rows as $row)
                $ds[] = $row['definition_id'];

            $defs[] = $ds;
        }

        return call_user_func_array('array_intersect', $defs);
    }


    public static function saveKit($combo, $kitHunterId, $vehicleIds)
    {
        $supplierId = '';
        $vars = [];
        $varCtr = 1;

        foreach ($combo as $desc => $data)
        {
            $supplierId = $data['supplier'];
            break;
        }

        $rows = DB::select('SELECT base_title, profit_pct, shipping, addons, versions, job_type FROM integra_prod.kit_hunter_queue WHERE id = ?', [$kitHunterId]);
        $baseTitle = $rows[0]['base_title'];
        $profitPct = $rows[0]['profit_pct'];
        $shipping = $rows[0]['shipping'];
        $versions = $rows[0]['versions'];
        $jobType = $rows[0]['job_type'];
        $addons = explode('|', $rows[0]['addons']);

        if (empty($versions))
            $versions = 1;

        foreach ($addons as $addon)
        {
            $fields = explode('~', $addon);
            if ($fields[0] != $supplierId) continue; // skip addon MPNs for other suppliers

            $qty = $fields[1];
            $sku = $fields[2];

            $combo[$sku] = ['sku' => $sku, 'qty' => $qty, 'supplier' => $fields[0]];
        }

        // no duplicates for KitHunter
        if ($jobType == 1)
        {
            $elements = [];
            foreach ($combo as $desc => $data)
                $elements[$data['sku']] = $data['qty'];

            ksort($elements);
            $elements2 = [];
            foreach ($elements as $sku => $qty)
                $elements2[] = $sku . '~' . $qty;

            $elementStr = implode(',', $elements2);

            // check if kit is already defined in the database
            $rows = DB::select("SELECT id FROM integra_prod.products WHERE is_kit = 1 AND kit_def = ? LIMIT 1", [$elementStr]);
            if (!empty($rows) && !empty($rows[0]))
            {
                echo "Kit already defined: $elementStr\n";
                return;
            }
        }

        $cost = 0;
        $core = 0;

        unset($data);

        foreach ($combo as $desc => &$data)
        {
            if (!isset($data['cost']) || empty($data['cost']))
            {
                $skuData = self::getPrice($data['sku']);
                $data['cost'] = $skuData['cost'];
                $data['core'] = $skuData['core'];

                if (empty($data['cost']))
                {
                    echo 'Unable to retrieve item cost of ' . $data['sku'] . " (possibly discontinued)\n";
                    return;
                }

                if (!isset($data['name']))
                    $data['name'] = $skuData['name'];

                if (!isset($data['brand']))
                    $data['brand'] = $skuData['brand'];
            }

            $cost += ($data['cost'] * $data['qty']);
            $core += ($data['core'] * $data['qty']);

            if (isset($data['brand'])) $vars["brand{$varCtr}"] = $data['brand'];
            if (isset($data['sku'])) $vars["mpn{$varCtr}"] = $data['sku'];
            if (isset($data['position'])) $vars["position{$varCtr}"] = $data['position'];
            $varCtr++;
        }

        unset($data);

        preg_match_all("/{(\\w+)}/i", $baseTitle, $matches, PREG_SET_ORDER);
        foreach ($matches as $match)
        {
            if (isset($vars[$match[1]]))
                $baseTitle = str_replace($match[0], $vars[$match[1]], $baseTitle);
        }

        $baseTitle = str_replace('  ', ' ', $baseTitle);

        $ebayPrice = round((($profitPct / 100) * $cost + $cost + $core + $shipping) / 0.936, 2);

        $kitNames = [];

        // try x10 number of target versions
        for ($i = 0; $i < ($versions * 10); $i++) {
            $vehicleIds[] = 0; // add dummy to make sure resulting definition list is not empty
            $defIds = implode(',', $vehicleIds);

            if ($i == 0) $order = "ORDER BY LENGTH(make), LENGTH(model)"; // shortest first to maximize number of vehicles in title
            else $order = "ORDER BY RAND()"; // random for the succeeding tries

            $defs = DB::select("SELECT make, model FROM magento.elite_1_definition WHERE make_id > 0 AND model_id > 0 AND year_id > 0 AND id IN ({$defIds}) {$order}");
            $last = $baseTitle;
            $compats = [];

            foreach ($defs as $c) {
                $make = trim($c['make']);
                $model = trim($c['model']);
                file_put_contents(storage_path('titles.txt'), "Kit Hunter: adding {$make} {$model}\n", FILE_APPEND);

                // insert make once
                if (!array_key_exists($make, $compats))
                    $compats[$make] = [];

                // insert model once
                if (!in_array($model, $compats[$make]))
                    $compats[$make][] = $model;

                // try with current compats
                $res = self::previewTitle('Kit Hunter', '', $baseTitle, $compats);

                if (!$res['ok']) break; // too long, stop trying
                else $last = $res['title']; // save this one
            }

            if (in_array($last, $kitNames))
                continue;

            $kitNames[] = $last;

            $kitName = $last;

            if (DB::insert(<<<EOQ
INSERT INTO integra_prod.products
(sku, name, is_kit, is_active, publish_status, ebay_price, kit_hunter_id, supplier_id)
(SELECT CONCAT(?, (IFNULL(MAX(CAST(REPLACE(sku, ?, '') AS UNSIGNED INTEGER)), 0) + 1)) AS sku, ?, 1, 0, 0, ?, ?, ?
FROM integra_prod.products WHERE is_kit = 1)
EOQ
                , [Config::get('constants.KIT_PREFIX'), Config::get('constants.KIT_PREFIX'), $kitName, $ebayPrice, $kitHunterId, $supplierId])
            ) {
                $kitId = DB::getPdo()->lastInsertId();

                $rows = DB::select("SELECT sku FROM integra_prod.products WHERE id = ?", [$kitId]);
                $kitSku = $rows[0]['sku'];

                foreach ($combo as $desc => $data) {
                    $rows = DB::select("SELECT id FROM products WHERE sku = ?", [$data['sku']]);

                    if (!empty($rows) && !empty($rows[0]))
                        $elementId = $rows[0]['id'];
                    else {
                        // insert product if MPN doesn't exist
                        if (DB::insert("INSERT INTO integra_prod.products (sku, name) VALUES (?, ?)",
                            [$data['sku'], $data['name']])
                        ) {
                            $elementId = DB::getPdo()->lastInsertId();
                        } else $elementId = 0;
                    }

                    DB::insert("INSERT INTO integra_prod.kit_components (product_id, component_product_id, quantity, sort, rotation) VALUES (?, ?, ?, ?, ?)",
                        [
                            $kitId,
                            $elementId,
                            $data['qty'],
                            ($i == 0) ? 0 : rand(0, 100),
                            ($i == 0) ? 0 : rand(0, 350)
                        ]);
                }

                if (!empty($vehicleIds)) {
                    foreach ($vehicleIds as $vehicleId) {
                        if (empty($vehicleId)) continue;
                        DB::insert("INSERT INTO integra_prod.kit_compatibility (kit_product_id, vehicle_id) VALUES (?, ?)", [$kitId, $vehicleId]);
                    }
                }
            }

            if (count($kitNames) >= $versions)
                break; // target versions reached

            echo 'Kit created: ' . $kitSku . ' (' . $kitName . ")\n";
        }
    }

    public static function getMageUrl($mpn)
    {
        $rows = DB::select(<<<EOQ
SELECT value
FROM magento.catalog_product_entity cpe, magento.catalog_product_entity_varchar cpev
WHERE cpe.sku = ?
AND cpe.entity_id = cpev.entity_id
AND cpev.attribute_id = 71
AND cpev.store_id = 0
LIMIT 1
EOQ
            , [$mpn]);
        if (empty($rows))
        {
            echo "Cannot find product name\n";
            return null;
        }
        $name = $rows[0]['value'];

        $rows = DB::select(<<<EOQ
SELECT COUNT(*) AS c
FROM magento.elite_1_mapping em, magento.catalog_product_entity cpe
WHERE cpe.sku = ?
AND cpe.entity_id = em.entity_id
EOQ
            , [$mpn]);
        $numCompat = $rows[0]['c'];
        if (empty($numCompat))
        {
            echo "No compatible vehicles\n";
            return null;
        }

        $rows = DB::select(<<<EOQ
SELECT value
FROM magento.catalog_product_entity cpe, magento.catalog_product_entity_varchar cpev
WHERE cpe.sku = ?
AND cpe.entity_id = cpev.entity_id
AND cpev.attribute_id = 135
AND cpev.store_id = 0
LIMIT 1
EOQ
            , [$mpn]);
        if (empty($rows))
        {
            echo "Cannot find product brand\n";
            return null;
        }
        $brand = $rows[0]['value'];

        $compat = DB::select(<<<EOQ
SELECT em.make, em.model, em.year
FROM magento.elite_1_mapping em, magento.catalog_product_entity cpe
WHERE cpe.sku = ?
AND cpe.entity_id = em.entity_id
ORDER BY 1, 2, 3
EOQ
            , [$mpn]);

        $title = "{$brand} {$name} ";
        $desc = "";

        $lastMake = '';
        $lastModel = '';

        foreach ($compat as $c)
        {
            if ($lastMake != $c['make'])
            {
                $title .= $c['make'] . ' ';
                $desc .= $c['make'] . ' ';
            }

            if ($lastModel != $c['model'])
            {
                $title .= $c['model'] . ' ';
                if ($lastModel != '') $desc .= ', ';
                $desc .= $c['model'] . ' ';
                $desc = str_replace(' ,', ',', $desc);
            }

            $desc .= $c['year'] . ' ';
            $lastMake = $c['make'];
            $lastModel = $c['model'];
        }

        $title = trim(str_replace('  ', ' ', IntegraUtils::tokenTruncate("{$mpn} {$title}", 255)));
        $desc = trim(str_replace('  ', ' ', IntegraUtils::tokenTruncate($desc, 5000)));

        return ['url' => self::slugify($title), 'desc' => $desc];
    }

    public static function slugify($text)
    {
        // Swap out Non "Letters" with a -
        $text = preg_replace('/[^\\pL\d]+/u', '-', $text);

        // Trim out extra -'s
        $text = trim($text, '-');

        // Convert letters that we have left to the closest ASCII representation
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // Make text lowercase
        $text = strtolower($text);

        // Strip out anything we haven't been able to convert
        $text = preg_replace('/[^-\w]+/', '', $text);

        return $text;
    }

    public static function array_column(array $input, $columnKey, $indexKey = null)
    {
        $array = array();
        foreach ($input as $value) {
            if ( !array_key_exists($columnKey, $value)) {
                trigger_error("Key \"$columnKey\" does not exist in array");
                return false;
            }
            if (is_null($indexKey)) {
                $array[] = $value[$columnKey];
            }
            else {
                if ( !array_key_exists($indexKey, $value)) {
                    trigger_error("Key \"$indexKey\" does not exist in array");
                    return false;
                }
                if ( ! is_scalar($value[$indexKey])) {
                    trigger_error("Key \"$indexKey\" does not contain scalar value");
                    return false;
                }
                $array[$value[$indexKey]] = $value[$columnKey];
            }
        }
        return $array;
    }
}