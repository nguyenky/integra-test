<?php

class ProductController extends \BaseController
{
    public function index()
    {
        if (!Input::has('filter') || !isset(Input::get('filter')['sku'])) return [];
        $sku = strtoupper(trim(Input::get('filter')['sku']));
        return IntegraUtils::paginate(Product::where('sku', 'like', $sku . '%')->get()->toArray());
    }

    public function dedupe($sku)
    {
        $orig = DB::select("
SELECT new_sku
FROM eoc.sku_translation
WHERE orig_sku = ?", [$sku]);

        if (!empty($orig) && !empty($orig[0]['new_sku']))
            return ['mpn' => $orig[0]['new_sku']];
        else return ['mpn' => null];
    }

    public function expandKit($sku)
    {
        return DB::select("
SELECT kp.sku, kc.quantity
FROM integra_prod.products p, integra_prod.products kp, integra_prod.kit_components kc
WHERE p.sku = ?
AND p.is_kit = 1
AND p.id = kc.product_id
AND kp.id = kc.component_product_id
ORDER BY kc.sort", [$sku]);
    }

    public function dupeProduct($mpn = null, $supplier = null)
    {
        if (empty($mpn)) $mpn = Input::get('mpn');
        if (empty($supplier)) $supplier = Input::get('supplier');

        $next = DB::select("
SELECT IFNULL(MAX(SUBSTRING(orig_sku, 4) + 0), 0) + 1 AS next
FROM eoc.sku_translation
WHERE orig_sku LIKE 'EDP%'");

        $nextSku = 'EDP' . $next[0]['next'];

        DB::insert("
INSERT INTO eoc.sku_translation (orig_sku, new_sku, timestamp)
VALUES (?, ?, NOW())", [$nextSku, $mpn]);

        DB::insert("
INSERT INTO eoc.sku_mpn (sku, mpn, supplier, no_indiv_relist)
VALUES (?, ?, ?, 0)", [$nextSku, $mpn, $supplier]);

        return $nextSku;
    }

    public function pssInfo($sku)
    {
        $rows = DB::select("SELECT item_id, title FROM eoc.ebay_listings WHERE active = 1 AND sku = ?", [$sku]);

        if (empty($rows) || empty($rows[0]) || empty($rows[0]['item_id']))
        {
            return ['item_id' => '', 'title' => ''];
        }

        $itemId = $rows[0]['item_id'];

        if (!empty($rows[0]['title']))
        {
            $title = $rows[0]['title'];
        }
        else
        {
            $item = EbayUtils::GetListing($itemId);
            $title = $item->title;
            $image = $item->big_image;
            DB::update("UPDATE eoc.ebay_listings SET title = ?, big_image_url = ? WHERE item_id = ?", [$title, $image, $itemId]);
        }

        return ['item_id' => $itemId, 'title' => $title];
    }

    public function show($id)
    {
        $sku = $id;
        $product = DB::select("
SELECT entity_id
FROM magento.catalog_product_entity
WHERE sku = ?", [$sku]);

        if (empty($product))
            return null;

        $res['sku'] = $sku;
        $res['entity_id'] = $product[0]['entity_id'];

        $stores = DB::select("SELECT * FROM m_stores");
        $res['stores'] = $stores;
        $storeIds = [];
        foreach ($stores as $store)
            $storeIds[] = $store['store_id'];

        $attribs = DB::select("SElECT * FROM m_attributes");

        $types = ['decimal', 'int', 'text', 'varchar'];
        $values = [];

        foreach ($types as $type)
        {
            $vals = DB::select(DB::raw("
SELECT store_id, attribute_id, value
FROM magento.catalog_product_entity_{$type}
WHERE entity_id = ?
AND entity_type_id = 4
ORDER BY store_id"), [$res['entity_id']]);

            foreach ($vals as $val)
            {
                if (!array_key_exists($val['store_id'], $storeIds)) continue;
                $values[$val['attribute_id']][$val['store_id']] = $val['value'];
            }
        }

        foreach ($attribs as $attrib)
        {
            $a['attrib_id'] = $attrib['attribute_id'];
            $a['name'] = $attrib['frontend_label'];
            $a['type'] = $attrib['frontend_input'];
            $a['is_global'] = $attrib['is_global'];
            $a['is_required'] = $attrib['is_required'];
            $a['values'] = [];

            if ($attrib['attribute_code'] == 'status')
                $a['options'] = [['id' => 1, 'name' => 'Enabled'], ['id' => 2, 'name' => 'Disabled']];
            else if ($attrib['attribute_code'] == 'featured')
                $a['options'] = [['id' => 0, 'name' => 'No'], ['id' => 1, 'name' => 'Yes']];

            if ($a['is_global'])
            {
                if (array_key_exists($attrib['attribute_id'], $values) && array_key_exists(0, $values[$attrib['attribute_id']]))
                {
                    $v = $values[$attrib['attribute_id']][0];
                    if ($a['type'] == 'price') $v = round($v, 2);
                    $a['values'][] = ['store_id' => 0, 'value' => $v, 'override' => false];
                }
                else $a['values'][] = ['store_id' => 0, 'value' => null, 'override' => false];
            }
            else
            {
                foreach ($stores as $store)
                {
                    if (array_key_exists($attrib['attribute_id'], $values) && array_key_exists($store['store_id'], $values[$attrib['attribute_id']]))
                    {
                        $v = $values[$attrib['attribute_id']][$store['store_id']];
                        if ($a['type'] == 'price') $v = round($v, 2);
                        $a['values'][] = ['store_id' => $store['store_id'], 'value' => $v, 'override' => true];
                    }
                    else $a['values'][] = ['store_id' => $store['store_id'], 'value' => null, 'override' => false];
                }
            }

            $res['attribs'][] = $a;
        }

        $res['images'][] = $sku;

        $config = Config::get('integra');
        $splitDir = $config['split_img_dir'];
        $files = glob("{$splitDir}/{$sku}_*.jpg");

        if (count($files) > 0)
        {
            foreach ($files as $f)
                $res['images'][] = basename($f, ".jpg");
        }

        return $res;
    }

    public function setPrimaryImage()
    {
        $mpn = Input::get('mpn');
        $id = Input::get('id');

        DB::update('UPDATE integra_prod.product_images SET sort = 100 WHERE mpn = ?', [$mpn]);
        DB::update('UPDATE integra_prod.product_images SET sort = 0 WHERE mpn = ? AND id = ?', [$mpn, $id]);
    }

    public function listImages($mpn)
    {
        $rows = DB::select('SELECT id, mpn, suffix FROM integra_prod.product_images WHERE mpn = ? ORDER BY sort, id', [trim(strtoupper($mpn))]);

        foreach ($rows as &$row)
            $row['name'] = $row['mpn'] . '_' .$row['suffix'];

        $rows[] = ['id' => 0, 'name' => $mpn, 'mpn' => $mpn, 'suffix' => ''];

        $brands = DB::select('SELECT value FROM magento.catalog_product_entity cpe, magento.catalog_product_entity_varchar cpev WHERE cpe.entity_id = cpev.entity_id AND cpev.store_id = 0 AND cpev.attribute_id = 135 AND cpe.sku = ?', [trim(strtoupper($mpn))]);
        if (!empty($brands) && !empty($brands[0])) $brand = strtoupper(preg_replace('/[^\da-z \\-+]/i', ' ', $brands[0]['value']));
        else $brand = null;

        return ['brand' => $brand, 'images' => $rows];
    }

    public function storeBrandImage()
    {
        $errorMsg = 'Unknown error while uploading file.';

        $config = Config::get('integra');
        $brandDir = $config['logos_dir'];

        $brand = trim(strtoupper(Input::get('brand')));
        if (empty($brand)) return Response::json('Missing brand', 500);

        $file = Input::file('file');
        if (empty($file) || !$file->isValid()) return Response::json($errorMsg, 500);

        $ext = trim(strtoupper($file->getClientOriginalExtension()));
        if ($ext != 'PNG' && $ext != 'GIF' && $ext != 'JPG') return Response::json('Only PNG/GIF/JPG files allowed.', 500);

        $brand = trim(strtoupper(preg_replace('/[^\da-z]/i', '', $brand)));

        foreach (glob("{$brandDir}/_{$brand}.*") as $old)
        {
            try
            {
                unlink($old);
            }
            catch (Exception $e)
            {

            }
        }

        if (Input::file('file')->move($brandDir, "_{$brand}.{$ext}"))
            return Response::json('OK', 200);
        else return Response::json($errorMsg, 500);
    }

    public function storeImage()
    {
        $errorMsg = 'Unknown error while uploading file.';
        $sku = trim(strtoupper(Input::get('sku')));
        if (empty($sku)) return Response::json($errorMsg, 500);
        if (strpos($sku, '..') !== false) return Response::json($errorMsg, 500);

        $file = Input::file('file');
        if (empty($file)) return Response::json($errorMsg, 500);

        $config = Config::get('integra');
        $newDir = $config['split_img_dir'];
        if (!is_dir($newDir)) return Response::json($errorMsg, 500);

        while (true)
        {
            $i = rand(10, 99);
            $newName = "{$sku}_{$i}.jpg";
            if (!file_exists("{$newDir}/{$newName}"))
                break;
        }

        if (Input::file('file')->move($newDir, $newName))
        {
            DB::insert('INSERT IGNORE INTO integra_prod.product_images (mpn, suffix) VALUES (?, ?)', [$sku, $i]);
            return Response::json(['name' => basename($newName, '.jpg'), 'id' => DB::getPdo()->lastInsertId(), 'mpn' => $sku], 200);
        }

        return Response::json($errorMsg, 500);
    }

    public function deleteImage($name)
    {
        $fields = explode('_', $name);
        if (count($fields) == 2)
        {
            DB::delete('DELETE FROM integra_prod.product_images WHERE mpn = ? AND suffix = ?', $fields);
        }

        $config = Config::get('integra');
        $splitDir = $config['split_img_dir'];
        $fullPath = "{$splitDir}/{$name}.jpg";

        if (strpos($fullPath, '..') === false && strpos($fullPath, '~') === false)
        {
            try
            {
                unlink($fullPath);
            }
            catch (Exception $e)
            {
            }
        }
    }

    public function update($id)
    {
        set_time_limit(0);
        $entityId = $id;

        try
        {
            $sku = Input::get('sku');
            $attribs = Input::get('attribs');
            DB::update("UPDATE magento.catalog_product_entity SET sku = ? WHERE entity_id = ?", [$sku, $entityId]);

            $done = [];
            $allStores = DB::select(DB::raw("SELECT store_id FROM m_stores"));
            $allAttribs = DB::select(DB::raw("SElECT * FROM m_attributes"));

            $rows = DB::select("SELECT attribute_id, store_id, field_name FROM integra_prod.log_product_edit");
            $logEdits = [];
            foreach ($rows as $r)
            {
                $logEdits[$r['attribute_id'] . '-' . $r['store_id']] = $r['field_name'];
            }

            foreach ($attribs as $attrib)
            {
                $attribId = $attrib['id'];
                $type = '';

                foreach ($allAttribs as $aa)
                {
                    if ($aa['attribute_id'] == $attribId)
                    {
                        $type = $aa['backend_type'];
                        break;
                    }
                }

                if (empty($type)) continue;

                foreach ($attrib['values'] as $val)
                {
                    $storeId = $val['store'];
                    $value = $val['value'];

                    $doLog = false;
                    $field = '';
                    $beforeValue = null;

                    $logKey = $attribId . '-' . $storeId;
                    if (array_key_exists($logKey, $logEdits))
                    {
                        $field = $logEdits[$logKey];

                        $rows = DB::select("SELECT value FROM magento.catalog_product_entity_{$type} WHERE entity_id = ? AND attribute_id = ? AND entity_type_id = 4 AND store_id = ?", [$entityId, $attribId, $storeId]);
                        if (!empty($rows))
                            $beforeValue = $rows[0]['value'];

                        // log only if value changed
                        if ($beforeValue != $value)
                            $doLog = true;
                    }

                    $done[$attribId][$storeId] = 1;
                    DB::update("INSERT INTO magento.catalog_product_entity_{$type} (entity_id, attribute_id, entity_type_id, store_id, value) VALUES (?, ?, 4, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)", [$entityId, $attribId, $storeId, $value]);

                    if ($doLog)
                    {
                        DB::insert("
INSERT INTO integra_prod.google_feed_log_edit
(edited_on, edited_by, item_id, edited_field, before_value, after_value)
(SELECT NOW(), ?, gf.id, ?, ?, ? FROM integra_prod.google_feed gf WHERE gf.mpn = ?)",
                            [ Cookie::get('user'), $field, $beforeValue, $value, $sku ]);
                    }
                }
            }

            foreach ($allAttribs as $aa)
            {
                $type = $aa['backend_type'];
                $attribId = $aa['attribute_id'];

                foreach ($allStores as $as)
                {
                    $storeId = $as['store_id'];

                    if ($aa['is_global'] && $storeId != 0)
                    {
                        DB::delete("DELETE FROM magento.catalog_product_entity_{$type} WHERE entity_id = ? AND entity_type_id = 4 AND attribute_id = ? AND store_id = ?", [$entityId, $attribId, $storeId]);
                        continue;
                    }

                    // no value provided
                    if (!array_key_exists($attribId, $done) || !array_key_exists($storeId, $done[$attribId]))
                    {
                        if ($aa['is_required'] && $storeId == 0)
                        {
                            DB::update("INSERT INTO magento.catalog_product_entity_{$type} (entity_id, attribute_id, entity_type_id, store_id, value) VALUES (?, ?, 4, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)", [$entityId, $attribId, $storeId, $aa['default_value']]);
                        }
                        else
                        {
                            // delete value to avoid override
                            DB::delete("DELETE FROM magento.catalog_product_entity_{$type} WHERE entity_id = ? AND entity_type_id = 4 AND attribute_id = ? AND store_id = ?", [$entityId, $attribId, $storeId]);
                        }
                    }
                }
            }

            DB::update("INSERT INTO magento.reindex_queue (entity_id, queue_date) VALUES (?, NOW())", [$entityId]);

            return ["msg" => 'It might take a few minutes for the changes to reflect in Magento.'];
        }
        catch (Exception $e)
        {
            return Response::json('Unable to save product changes. ' . $e->getMessage(), 500);
        }
    }

    public function compatibilities($sku)
    {
        return IntegraUtils::paginate(Compatibility::where('sku', $sku)->get(['make', 'model', 'year', 'notes'])->toArray());
    }

    public function sales($sku)
    {
        $sales = DB::select("SELECT s.id, s.record_num, s.order_date, s.store FROM eoc.sales s, eoc.sales_items si WHERE si.sales_id = s.id AND si.sku_noprefix = ?", [$sku]);
        return IntegraUtils::paginate($sales);
    }

    public function listKits()
    {
        if (!Input::has('filter') || !isset(Input::get('filter')['sku'])) return [];
        $sku = strtoupper(trim(Input::get('filter')['sku']));
        return IntegraUtils::paginate(Product::with('components')->where('is_kit', true)->where('sku', 'like', $sku . '%')->get()->toArray());
    }

    public function uploadKits() {
        $file = Input::file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension != 'csv')
            return Response::json('Only CSV files are supported.', 500);

        $directory = public_path() . "/uploads/kits";
        if (!is_dir($directory)) mkdir($directory);

        $filename = sha1(time() . '_' . $file->getClientOriginalName()) . ".${extension}";
        $upload_success = Input::file('file')->move($directory, $filename);   
        if($upload_success) {
            Artisan::call('kit:updating');

        }
        return array(
                        'success' => $upload_success, 
                        'message' => ($upload_success ? "File uploaded successfully. A Job has called for updating kits." : "Upload file failed.")
                    );
    }

    public function uploadInventory()
    {
        try
        {
            $file = Input::file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            if ($extension != 'csv')
                return Response::json('Only CSV files are supported.', 500);

            $directory = public_path() . "/uploads/inventory";
            if (!is_dir($directory)) mkdir($directory);

            $filename = sha1(time() . '_' . $file->getClientOriginalName()) . ".${extension}";
            $upload_success = Input::file('file')->move($directory, $filename);

            // TODO: support multiple warehouses, remove hardcoded supplier name
            $warehouseId = Supplier::name('EOC')->warehouses()->pluck('id');

            $inserted = 0;

            if ($upload_success)
            {
                $lines = file("{$directory}/$filename", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                if (count($lines) < 2)
                    return Response::json('Make sure that your file follows the template and includes the header.', 500);

                $lineCount = count($lines);

                for ($i = 1; $i < $lineCount; $i++)
                {
                    $cols = str_getcsv(trim($lines[$i]), ",");

                    // if 2 columns cannot be extracted, try semicolon
                    if (count($cols) != 2)
                    {
                        $cols = str_getcsv(trim($lines[$i]), ";");

                        // skip lines with insufficient columns
                        if (count($cols) != 2)
                            continue;
                    }

                    $quantity = intval($cols[1]);

                    try
                    {
                        if (DB::insert("
INSERT INTO product_warehouse (product_id, warehouse_id, quantity)
(SELECT id, {$warehouseId}, {$quantity} FROM products WHERE sku = ?)
ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)", [trim($cols[0])]))
                            $inserted++;
                    }
                    catch (Exception $e)
                    {
                    }
                }

                return array("newCount" => $inserted);
            }
        }
        catch (Exception $e)
        {
            return Response::json('Make sure that your file follows the template. Duplicates will be skipped.', 500);
        }
    }

    public function downloadInventory()
    {
        $headers = [
            'Content-type' => 'application/csv',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=inventory.csv',
            'Expires' => '0',
            'Pragma' => 'public'
        ];

        // TODO: add support for downloading inventory for individual warehouses
        $list = DB::select(DB::raw("SELECT sku, quantity FROM products p INNER JOIN product_warehouse pw ON p.id = pw.product_id"));

        // add headers for each column in the CSV download
        array_unshift($list, array_keys($list[0]));

        $callback = function() use ($list)
        {
            $FH = fopen('php://output', 'w');
            foreach ($list as $row) {
                fputcsv($FH, $row);
            }
            fclose($FH);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function downloadInventory2()
    {
        $headers = [
            'Content-type' => 'application/csv',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=inventory.csv',
            'Expires' => '0',
            'Pragma' => 'public'
        ];

        // TODO: add support for downloading inventory for individual warehouses
        $list = DB::select(DB::raw("SELECT sku, brand, name, isle, row, `column`, quantity, GROUP_CONCAT(DISTINCT code) AS codes
FROM product_warehouse pw, products p LEFT JOIN product_codes pc ON p.id = pc.product_id
WHERE p.id = pw.product_id
AND pw.warehouse_id = 1
GROUP BY 1
ORDER BY isle, `column`, row"));

        // add headers for each column in the CSV download
        array_unshift($list, array_keys($list[0]));

        $callback = function() use ($list)
        {
            $FH = fopen('php://output', 'w');
            foreach ($list as $row) {
                fputcsv($FH, $row);
            }
            fclose($FH);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function getSkus() {
        $search = Input::get('search');
        $skus = Product::searchBySku($search);
        return Response::json([
            'success' => true,
            'status' => 200,
            'message' => "GET SUCCESSFULLY",
            'data' => $skus
        ]);
    }

    public function updateKit($id)
    {
        return IntegraUtils::tryFunc(function() use($id)
            {
                foreach (Input::get('components') as $component)
                {
                    if (empty($component['sku']) || empty($component['pivot']['quantity'])) continue;
                    $p = Product::bySku($component['sku']);
                    if (empty($p)) throw new Exception('Invalid SKU: ' . $component['sku']);
                    if ($p->is_kit) throw new Exception('Other kits cannot be used as components: ' . $component['sku']);
                    $comps[$p->id] = ['quantity' => $component['pivot']['quantity']];
                }

                if (empty($comps)) throw new Exception('No components were entered.');

                $entry = Product::find($id);

                if (empty($entry))
                {
                    $entry = new Product();
                    $entry['is_kit'] = true;
                    $entry['name'] = Input::get('name');
                    $nextCount = DB::select(DB::raw("SELECT (MAX(CAST(REPLACE(sku, '" . Config::get('constants.KIT_PREFIX') . "', '') AS UNSIGNED INTEGER)) + 1) AS next_kit FROM products WHERE is_kit = 1"))[0]['next_kit'];
                    if (empty($nextCount)) $nextCount = 1;
                    $entry['sku'] = Config::get('constants.KIT_PREFIX') . $nextCount;
                    $entry->save();
                    $entry->components()->sync($comps);
                    return ["msg" => 'The kit was successfully created.<br>Assigned SKU: ' . $entry['sku'], "sku" => $entry['sku']];
                }
                else
                {
                    $entry['name'] = Input::get('name');
                    $entry->save();
                    $entry->components()->sync($comps);
                    return ["msg" => 'The kit was successfully updated.'];
                }
            });
    }

    public function destroyKit($id)
    {
        return IntegraUtils::tryFunc(function() use ($id)
            {
                Product::find($id)->components()->sync([]);
                Product::destroy($id);
            },
            "The kit was successfully deleted.",
            "Make sure that it is not in use in the system.");
    }

    private function getQtyRequiredAndPosition($sku)
    {
        $rows = DB::select(<<<EOQ
SELECT GROUP_CONCAT(DISTINCT em.qty_required SEPARATOR ', ') AS qtys, GROUP_CONCAT(DISTINCT em.position SEPARATOR ', ') AS positions
FROM magento.elite_1_mapping em, magento.catalog_product_entity cpe
WHERE em.entity_id = cpe.entity_id
AND cpe.sku = ?
EOQ
            , [$sku]);

        if (!empty($rows)) return $rows[0];
        else return ['qtys' => '', 'positions' => ''];
    }
    /*public function lookupV2(){
        $mpn = 4417;
        $integraImcUtils = new IntegraIMCUtils;
        $skus[] = $mpn;
        $test = $integraImcUtils->QueryItems($skus);
        dd($test);
    } */

    public function lookup($mpn)
    { 

        set_time_limit(0);
        $res = [];

        try
        {
            $noBrandMpn = explode('.', $mpn)[0];
            var_dump($noBrandMpn);
            $json = file_get_contents("http://integra.eocenterprise.com/imc_ajax.php?sku={$noBrandMpn}");
            $json = json_decode($json, true);

            dd($json);

            if ($json['price'] != '?' && !empty($json['brand']))
            {
                $json['supplier'] = 1;
                $json['img_sku'] = $json['mpn'];
                $res[] = $json + $this->getQtyRequiredAndPosition($json['mpn']);
                // dd($res);
            }

            if (array_key_exists('alt', $json))
            {
                foreach ($json['alt'] as $alt)
                {
                    $jAlt = file_get_contents("http://integra.eocenterprise.com/imc_ajax.php?sku={$alt}");
                    $jAlt = json_decode($jAlt, true);
                    if ($jAlt['price'] != '?' && !empty($jAlt['brand']))
                    {
                        $jAlt['supplier'] = 1;
                        $jAlt['img_sku'] = $jAlt['mpn'];
                        $res[] = $jAlt + $this->getQtyRequiredAndPosition($jAlt['mpn']);
                    }
                }
            }
        }
        catch (Exception $e)
        {
        }

        try
        {
            $noBrandMpn = explode('.', $mpn)[0];
            $json = file_get_contents("http://integra.eocenterprise.com/ssf_ajax.php?sku={$noBrandMpn}");
            $json = json_decode($json, true);

            if ($json['price'] != '?' && !empty($json['brand']))
            {
                $json['supplier'] = 2;
                if (stripos($json['sku'], '.')) $json['mpn'] = $json['sku'];
                else $json['mpn'] = $json['sku'] . '.' . $json['brand_id'];
                $json['img_sku'] = $json['mpn'];
                $res[] = $json + $this->getQtyRequiredAndPosition($json['mpn']);
            }

            if (array_key_exists('options', $json))
            {
                foreach ($json['options'] as $jAlt)
                {
                    if ($jAlt['price'] != '?' && !empty($jAlt['brand']))
                    {
                        $jAlt['supplier'] = 2;
                        if (stripos($jAlt['sku'], '.')) $jAlt['mpn'] = $jAlt['sku'];
                        else $jAlt['mpn'] = $jAlt['sku'] . '.' . $jAlt['brand_id'];
                        $jAlt['img_sku'] = $jAlt['mpn'];
                        $res[] = $jAlt + $this->getQtyRequiredAndPosition($jAlt['mpn']);
                    }
                }
            }
        }
        catch (Exception $e)
        {
        }

        return $res;
    }
    public function lookupProgress(){
        $progress = EbayQuickLookupCsvfile::with('EbayQuickLookupPendings')
        ->whereHas('EbayQuickLookupPendings', function ($query) 
        {
            $query->whereIn('status',[0,1]);
            
        })->get()->toArray();

        foreach ($progress as $key => $value) {
            $count = 0;
            $sum = 0;
            foreach ($value['ebay_quick_lookup_pendings'] as $key_item => $item) {
                $status = $item['status']== 1 ? 1 : 0;
                $sum = $sum + $status;
                $count = $count + 1;
            }
            $progress[$key]['ebay_quick_lookup_pendings'] = $count;
            $progress[$key]['progress'] = CEIL($sum * 100/$count);
            

        }

        $progress = $this->addLink($progress);

        return $progress;
    }
    public function addLink($progress){

        foreach ($progress as $key => $value) {
            $progress[$key]['links'] = url('').'/'.$value['links'];
        }
        return $progress;
    }

    public function lookupWithFile(){

        $input = Input::all();
        if(Input::file('file')){
           $this->importKeywords($input);
        }

        $mpn = $input['mpn'];

        $mpn = strtoupper(preg_replace('/[^0-9A-Z.]/is','', $mpn));
        $res = $this->lookup($mpn);
        return $res;
    }

    public function importKeywords($input){

        $file = $input['file'];

        $name = trim(str_replace(" ","_",$input['remarks']));

        $link = $this->createLink($input);

        $csv = EbayQuickLookupCsvfile::create(array('name' => $name,'links'=>$link));

        $fileName = time().'.'.$file->getClientOriginalExtension();
        
        $pathPublic = public_path().'/files/'; 
               

        if(!\File::exists($pathPublic)) {

            \File::makeDirectory($pathPublic, $mode = 0777, true, true);

        }

        $file->move($pathPublic, $fileName);  

        $csvPath = $pathPublic.'/'.$fileName;

        $fopen = fopen($csvPath,"r");
        
        $arrayData =[];
        $row=1;
        while (($data = fgetcsv($fopen, 1000, ",")) !== FALSE) {
            $num = count($data);
            $row++;
            for ($c=0; $c < $num; $c++) {
                array_push($arrayData,$data[$c]);
            }
        }
        
       
        foreach ($arrayData as $value) {
           
        EbayQuickLookupPending::create(array('mpn' => $value, 'status' => 0, 'csv_id' => $csv->id));

        }

        fclose($fopen);

        unlink($pathPublic.'/'.$fileName); 
    }
    public function createLink($attribute){

        $file = $attribute['file'];

        $pathPublic = public_path().'/csv_files';
        $fileName = trim(str_replace(" ","_",$attribute['remarks'])).'_'.time().'.'.$file->getClientOriginalExtension();
        $csvPath = $pathPublic.'/'.$fileName;

        

        if(!\File::exists($pathPublic)) {

            \File::makeDirectory($pathPublic, $mode = 0777, true, true);

        }

        $file = fopen($csvPath,"a+");
        $keys = ['Warehouse','Part Number','Other Numbers','Description','Brand','Weight(lb)','Available Stock','Quantity Required','Position'];
        fputcsv($file,$keys);
        fclose($file); 
        return 'csv_files/'.$fileName;
    }
    public function deleteProductLookup($id){

        EbayQuickLookupPending::where('csv_id',$id)->delete();

        $lookup = EbayQuickLookupCsvfile::find($id);

        if(\File::exists(public_path().'/'.$lookup['links'])) {
            
            unlink(public_path().'/'.$lookup['links']); 
        }
        $lookup->delete();

        return [
            'status'=>true
            ];


    }



    public function amazonListByAsin()
    {
        $asin = Input::get('asin');
        if (empty($asin)) return Response::json('Invalid input data', 500);

        $sku = Input::get('sku');
        if (empty($sku)) return Response::json('Invalid input data', 500);

        $price = Input::get('price');
        if (empty($price)) return Response::json('Invalid input data', 500);

        $quantity = Input::get('quantity');
        if (empty($quantity)) return Response::json('Invalid input data', 500);

        $dupeSku = $this->dupeProduct($sku, strpos($sku, '.') ? 2 : 1);

        DB::insert("
INSERT INTO integra_prod.amazon_listing_queue (asin, sku, price, quantity, queue_date)
VALUES (?, ?, ?, ?, NOW())", [$asin, $dupeSku, $price, $quantity]);

        DB::insert(<<<EOQ
INSERT INTO integra_prod.amazon_monitor_log (asin, edited_by, edited_on, our_new_price, is_new)
VALUES (?, ?, NOW(), ?, 1)
EOQ
            , [$asin, Cookie::get('user'), $price]);

        return '1';
    }

    public function amazonSearch()
    {
        set_time_limit(0);
        $results = [];

        $keywords = Input::get('keywords');
        if (empty($keywords)) return $results;

        $pages = Input::get('pages');
        if (empty($pages)) $pages = 1;

        $config = Config::get('integra');
        $amazonEcs = new AmazonECS($config['amazon_ad']['access_key_id'], $config['amazon_ad']['secret_access_key'], 'com', $config['amazon_ad']['associate_tag']);
        $amazonEcs->returnType(AmazonECS::RETURN_TYPE_ARRAY);

        $merchants = [];
        $res = DB::select("SELECT id, name FROM eoc.amazon_merchants");
        foreach ($res as $r) $merchants[$r['id']] = $r['name'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_PROXY, 'switchproxy.proxify.net:7496');
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            [
                'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.118 Safari/537.36',
            ]);

        for ($i = 1; $i <= $pages; $i++)
        {
            $response = $amazonEcs->responseGroup('Small,Images,OfferFull')->category('Automotive')->page($i)->search($keywords);

            if (array_key_exists('Items', $response) && array_key_exists('Item', $response['Items']))
            {
                if (array_key_exists('ASIN', $response['Items']['Item']))
                    $response['Items']['Item'] = [$response['Items']['Item']];

                foreach ($response['Items']['Item'] as $item)
                {
                    try
                    {
                        $result['asin'] = $item['ASIN'];
                        $result['url'] = $item['DetailPageURL'];
                        $result['small_img'] = array_key_exists('SmallImage', $item) ? $item['SmallImage']['URL'] : null;
                        $result['medium_img'] = array_key_exists('SmallImage', $item) ? $item['MediumImage']['URL'] : null;
                        $result['large_img'] = array_key_exists('SmallImage', $item) ? $item['LargeImage']['URL'] : null;
                        $result['title'] = $item['ItemAttributes']['Title'];
                        $result['brand'] = $item['ItemAttributes']['Manufacturer'];
                        $result['sellers'] = [];
                        $lowest = 9999999;
                        $result['our_price'] = 0;

                        $queue = DB::select("SELECT status FROM integra_prod.amazon_listing_queue WHERE asin = ? ORDER BY queue_date DESC LIMIT 1", [$result['asin']]);
                        if (!empty($queue)) $result['queue_status'] = $queue[0]['status'];
                        else $result['queue_status'] = -1;

                        try
                        {
                            for ($page = 0; $page < 5; $page++)
                            {
                                curl_setopt($ch, CURLOPT_URL, "http://www.amazon.com/gp/offer-listing/" . $item['ASIN'] . '/?condition=new&startIndex=' . ($page * 10));
                                $data = curl_exec($ch);
                                if (!empty($data) && stripos($data, 'captcha') === false && stripos($data, 'OfferListing') && stripos($data, 'We encountered an error due to invalid parameters') === false)
                                {
                                    $re = '/olpOfferPrice[^$]+(?P<price>[^<]+)<.+?((olpShippingPrice[^$]+(?P<shipping>[^<]+)<)|(?P<free>FREE)).+?olpCondition[^>]+>(?P<condition>[^<]+)<.+?seller=(?P<seller>[^"]+)"><b>(?P<rating>[^\)]+\))/is';
                                    preg_match_all($re, $data, $matches, PREG_SET_ORDER);
                                    if (!empty($matches))
                                    {
                                        foreach ($matches as $m)
                                        {
                                            $seller = trim($m['seller']);
                                            if (array_key_exists($seller, $merchants)) $seller = $merchants[$seller];
                                            else
                                            {
                                                $sp = file_get_contents("https://www.amazon.com/sp?seller={$seller}");
                                                preg_match("/Seller Profile:\\s*(?<name>[^<]+)/", $sp, $matches);
                                                if (isset($matches['name']))
                                                {
                                                    if (stripos($matches['name'], '&&') !== false) continue; // invalid seller
                                                    if (stripos($matches['name'], '//') !== false) continue; // invalid seller
                                                    DB::insert("INSERT INTO eoc.amazon_merchants (id, name) VALUES (?, ?)", [$seller, $matches['name']]);
                                                    $seller = $matches['name'];
                                                }
                                            }
                                            $price = str_replace('$', '', str_replace(',', '', trim($m['price'])));
                                            $shipping = (!empty($m['free'])) ? '0' : trim($m['shipping']);
                                            $shipping = str_replace('$', '', str_replace(',', '', trim($shipping)));
                                            $total = $price + $shipping;
                                            if ($total < $lowest) $lowest = $total;
                                            if ($seller == $config['amazon_ad']['merchant_name'])
                                                $result['our_price'] = $total;
                                            $result['sellers'][] =
                                                [
                                                    'seller' => $seller,
                                                    'price' => $price,
                                                    'shipping' => $shipping,
                                                    'total' => $total
                                                ];
                                        }
                                    }
                                }
                                else {
                                    file_put_contents(storage_path($item['ASIN'] . '.htm'), $data);
                                }
                            }
                        }
                        catch (Exception $e3)
                        {}

                        if (empty($result['sellers']) && array_key_exists('Offers', $item) && array_key_exists('Offer', $item['Offers']))
                        {
                            $result['lowest_price'] = str_replace('$', '', str_replace(',', '', $item['OfferSummary']['LowestNewPrice']['FormattedPrice']));

                            if (array_key_exists('Merchant', $item['Offers']['Offer']))
                                $item['Offers']['Offer'] = [$item['Offers']['Offer']];

                            foreach ($item['Offers']['Offer'] as $offer)
                            {
                                try
                                {
                                    $seller = $offer['Merchant']['Name'];
                                    $price = str_replace('$', '', str_replace(',', '', $offer['OfferListing']['Price']['FormattedPrice']));

                                    if ($seller == $config['amazon_ad']['merchant_name'])
                                        $result['our_price'] = $price;

                                    $result['sellers'][] =
                                        [
                                            'seller' => $seller,
                                            'price' => $price,
                                            'shipping' => '?'
                                        ];
                                }
                                catch (Exception $e1)
                                {
                                }
                            }
                        }
                        else
                        {
                            $result['lowest_price'] = $lowest;
                            usort($result['sellers'], function ($a, $b) { return $a["total"] - $b["total"]; });
                        }

                        $results[] = $result;
                    }
                    catch (Exception $e1)
                    {
                    }
                }
            }
        }

        return $results;
    }

    public function qtyRequired($sku)
    {
        $rows = DB::select(<<<EOQ
SELECT GROUP_CONCAT(DISTINCT qty_required ORDER BY qty_required) AS qtys
FROM magento.elite_1_mapping em, magento.catalog_product_entity cpe
WHERE em.entity_id = cpe.entity_id
AND cpe.sku = ?
EOQ
            , [$sku]);

        if (empty($rows) || empty($rows[0])) return '';
        else return $rows[0]['qtys'];
    }

    public function calc()
    {
        $res = [
            'supplier' => '',
            'cost_nonexport' => 0,
            'cost_export' => 0,
            'core' => 0,
            'weight' => 0,
            'shipping_nonexport' => 0,
            'shipping_export' => 0,
            'profit_nonexport' => 0,
            'profit_export' => 0,
        ];
        
        $numMpns = 0;
        $singleMpn = '';
        $singleQty = 0;

        foreach (Input::get('components') as $component)
        {
            if (empty($component['mpn'])) continue;
            if (empty($component['qty'])) continue;
            if (empty($component['cost_nonexport'])) continue; // must be present in ipo at least
            if (empty($component['supplier'])) continue;
            
            $numMpns++;
            $singleMpn = $component['mpn'];
            $singleQty = $component['qty'];

            if (empty($res['supplier']))
                $res['supplier'] = $component['supplier'];
            else if ($res['supplier'] != $component['supplier'])
                $res['supplier'] = 'mix';

            $res['cost_nonexport'] += (floatval($component['cost_nonexport']) * $component['qty']);
            $res['cost_export'] += (floatval($component['cost_export']) * $component['qty']);
            $res['core'] += (floatval($component['core']) * $component['qty']);
            $res['weight'] += (floatval($component['weight']) * $component['qty']);
        }
        
        if ($numMpns == 1)
        {
            $res['profit_nonexport'] = 0;

            // check for predefined profit and shipping for single MPN query
            // export

            $profShip = DB::select(<<<EOQ
                SELECT IFNULL(
                    (
                        SELECT MAX(profit)
                        FROM eoc.e_shipping_rates
                        WHERE mpn = ?
                        AND min_qty <= ?
                        AND max_qty >= ?
                    ),
                    (
                        SELECT MAX(profit)
                        FROM eoc.e_profit_percentage
                        WHERE min_cost <= ?
                        AND max_cost >= ?
                    )
                ) AS profit,
                IFNULL(
                    (
                        SELECT MIN(shipping)
                        FROM eoc.e_shipping_rates
                        WHERE mpn = ?
                        AND min_qty <= ?
                        AND max_qty >= ?
                    ),
                    (
                        SELECT MAX(rate)
                        FROM eoc.e_shipping_rate
                        WHERE weight_from <= ?
                        AND weight_to >= ?
                    )
                ) AS shipping
EOQ
                , [$singleMpn, $singleQty, $singleQty, $res['cost_export'], $res['cost_export'], $singleMpn, $singleQty, $singleQty, $res['weight'], $res['weight']]);

            $res['profit_export'] = $profShip[0]['profit'];
            $res['shipping_export'] = $profShip[0]['shipping'];
        }
        else
        {
            $rows = DB::select(<<<EOQ
                SELECT MAX(profit) as profit
                FROM eoc.e_profit_percentage
                WHERE min_cost <= ?
                AND max_cost >= ?
EOQ
                , [$res['cost_nonexport'], $res['cost_nonexport']]);

            if (!empty($rows)) $res['profit_nonexport'] = $rows[0]['profit'];

            $rows = DB::select(<<<EOQ
                SELECT MAX(profit) as profit
                FROM eoc.e_profit_percentage
                WHERE min_cost <= ?
                AND max_cost >= ?
EOQ
                , [$res['cost_export'], $res['cost_export']]);

            if (!empty($rows)) $res['profit_export'] = $rows[0]['profit'];

            $rows = DB::select(<<<EOQ
                SELECT MAX(rate) as shipping
                FROM eoc.e_shipping_rate
                WHERE weight_from <= ?
                AND weight_to >= ?
EOQ
                , [$res['weight'], $res['weight']]);

            if (!empty($rows))
            {
                $res['shipping_export'] = $rows[0]['shipping'];
            }
        }

        if ($res['supplier'] == 'imc')
        {
            if ($res['cost_nonexport'] >= 50)
            {
                $res['shipping_nonexport'] = 0;
            }
            else $res['shipping_nonexport'] = min(50 - $res['cost_nonexport'], 5);
        }
        else if ($res['supplier'] == 'ssf')
        {
            if ($res['cost_nonexport'] >= 100)
            {
                $res['shipping_nonexport'] = 0;
            }
            else
            {
                $rows = DB::select(<<<EOQ
                SELECT shipping
                FROM integra_prod.ssf_shipping_rates
                WHERE weight_from <= ?
                AND weight_to > ?
                AND cost_from <= ?
                AND cost_to > ?
EOQ
                    , [$res['weight'], $res['weight'], $res['cost_nonexport'], $res['cost_nonexport']]);

                if (!empty($rows)) $res['shipping_nonexport'] = min(11.5, $rows[0]['shipping'], 100 - $res['cost_nonexport']);
                else $res['shipping_nonexport'] = min(11.5, 100 - $res['cost_nonexport']);
            }
        }

        return $res;
    }

    public function getExportPrice($mpn)
    {
        //ImcUtils::ScrapeExportPrice([$mpn]);
        $rows = DB::select("SELECT unit_price FROM integra_prod.imc_export_items WHERE mpn_unspaced = ? ", [$mpn]);
        if (empty($rows)) return 0;
        return $rows[0]['unit_price'];
    }

    public function downloadProductLinking()
    {
        $headers = [
            'Content-type' => 'application/csv',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=product_linking.csv',
            'Expires' => '0',
            'Pragma' => 'public'
        ];

        $list = DB::select(DB::raw(<<<EOQ
SELECT cpe1.sku AS main_sku, cpe2.sku AS related_sku
FROM magento.catalog_product_entity cpe1, magento.catalog_product_entity cpe2,
magento.catalog_product_link cpl
WHERE cpl.product_id = cpe1.entity_id
AND cpl.linked_product_id = cpe2.entity_id
ORDER BY 1, 2
EOQ
        ));

        // add headers for each column in the CSV download
        array_unshift($list, array_keys($list[0]));

        $callback = function() use ($list)
        {
            $FH = fopen('php://output', 'w');
            foreach ($list as $row) {
                fputcsv($FH, $row);
            }
            fclose($FH);
        };

        return Response::stream($callback, 200, $headers);
    }
}
