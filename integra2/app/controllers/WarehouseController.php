<?php

class WarehouseController extends \BaseController
{


    public function findBin($warehouseId, $code)
    {
        $products = Product::byCode(ProductCode::clean($code))->select(['id', 'sku', 'name', 'brand'])->get();
        if (empty($products)) return null;

        $ret = [];

        foreach ($products as $product)
        {
            $ret['product'] = $product->toArray();
            $ret['product']['codes'] = $product->codes()->lists('code');

            $bin = ProductWarehouse::where('warehouse_id', $warehouseId)->where('product_id', $product->id)->first();
            if (!empty($bin))
            {
                $ret['bin']['isle'] = $bin->isle;
                $ret['bin']['row'] = $bin->row;
                $ret['bin']['column'] = $bin->column;
                break;
            }
        }

        return $ret;
    }

    public function upsertSku()
    {
        $warehouseId = trim(Input::get('warehouse_id'));
        $warehouse = Warehouse::find($warehouseId);
        if (empty($warehouse)) return null;

        $ret = $this->findOrCreateBin(ProductCode::clean(Input::get('code')), $warehouseId);
        if (!empty($ret)) return $ret;

        if (Input::has('sku'))
        {
            $ret = $this->findOrCreateBin(Input::get('sku'), $warehouseId, ProductCode::clean(Input::get('code')));
            if (!empty($ret)) return $ret;
        }

        return null;
    }

    public function findOrCreateBin($code, $warehouseId, $linkWith = null)
    {
        $warehouse = Warehouse::find($warehouseId);
        if (empty($warehouse)) return null;

        // search existing product by code
        $bin = $this->findBin($warehouseId, $code);
        if (!empty($bin))
        {
            if (!empty($linkWith))
            {
                if (ProductCode::where('code', $linkWith)->count() == 0)
                {
                    ProductCode::firstOrCreate(['product_id' => $bin['product']['id'], 'code' => $linkWith]);
                }
            }

            return $bin;
        }

        // no existing code, try ipo search
        $code = ProductCode::clean($code);
        $ipoProduct = null;

        try
        {
            $rows = DB::select("SELECT title, brand, price FROM eoc.ebay_listings WHERE sku LIKE CONCAT(?, '%') AND REPLACE(sku, '-', '') = ? ORDER BY active DESC, `timestamp` DESC LIMIT 1", [ substr($code, 0, 2), $code ]);

            if (!empty($rows) && !empty($rows[0]))
            {
                $ipoProduct =
                    [
                        'sku' => $code,
                        'desc' => $rows[0]['title'],
                        'brand' => $rows[0]['brand'],
                        'price' => $rows[0]['price']
                    ];
            }
            else if (stripos($code, '.') !== false)
            {
                $ipoProduct = json_decode(file_get_contents("http://integra.eocenterprise.com/ssf_ajax.php?sku={$code}"), true);
            }
            else
            {
                $ipoProduct = json_decode(file_get_contents("http://integra.eocenterprise.com/imc_ajax.php?sku={$code}"), true);

                if (empty($ipoProduct) || !isset($ipoProduct['price']) || $ipoProduct['price'] == '?' || empty($ipoProduct['desc']))
                {
                    $ipoProduct = json_decode(file_get_contents("http://integra.eocenterprise.com/ssf_ajax.php?sku={$code}"), true);

                    if (!empty($ipoProduct) && isset($ipoProduct['price']) && $ipoProduct['price'] != '?' && !empty($ipoProduct['desc']))
                        if (isset($ipoProduct['sku']) && strpos($ipoProduct['sku'], '.') === false)
                        {
                            $ipoProduct['sku'] .= '.' . $ipoProduct['brand_id'];

                            $bin = $this->findBin($warehouseId, $ipoProduct['sku']);
                            if (!empty($bin))
                            {
                                if (!empty($linkWith))
                                    ProductCode::firstOrCreate(['product_id' => $bin['product']['id'], 'code' => $linkWith]);

                                return $bin;
                            }
                        }
                }
            }
        }
        catch (Exception $e)
        {
        }

        if (empty($ipoProduct) || !isset($ipoProduct['price']) || $ipoProduct['price'] == '?' || empty($ipoProduct['desc']))
            return null;

        // ipo product found, create new product
        $product = new Product();
        $product->sku = $ipoProduct['sku'];
        $product->name = $ipoProduct['desc'];
        $product->brand = $ipoProduct['brand'];
        $product->supplier_id = $warehouse->supplier_id;
        $product->save();

        if ($product->sku != $code)
            ProductCode::firstOrCreate(['product_id' => $product->id, 'code' => $code]);

        if (!empty($linkWith) && $linkWith != $product->sku)
            ProductCode::firstOrCreate(['product_id' => $product->id, 'code' => $linkWith]);

        return $this->findBin($warehouseId, $code);
    }

    public function recount()
    {
        $binId = trim(Input::get('bin_id'));
        $bin = ProductWarehouse::find($binId);
        if (empty($bin)) return '0';

        $bin->quantity = trim(Input::get('quantity'));
        $bin->save();
        return '1';
    }

    public function saveCodes()
    {
        $productId = Input::get('product_id');
        ProductCode::where('product_id', $productId)->delete();
        $hasError = false;

        foreach (Input::get('codes') as $code)
        {
            try
            {
                ProductCode::firstOrCreate(['product_id' => $productId, 'code' => ProductCode::clean($code)]);
            }
            catch (Exception $e)
            {
                $hasError = true;
            }
        }

        $ret = [];
        $ret['success'] = !$hasError;
        $ret['codes'] = ProductCode::where('product_id', $productId)->lists('code');

        return $ret;
    }

    public function relocate()
    {
        $re = "/(?<isle>\\d+)(?<row>[A-Z]+)(?<column>\\d+)/i";
        preg_match($re, strtoupper(trim(ltrim(Input::get('new_bin'), '0'))), $matches);

        if (!isset($matches['isle']) || !isset($matches['row']) || !isset($matches['column']))
            return 'Please enter a valid bin location.';

        $isle = $matches['isle'];
        $row = $matches['row'];
        $column = $matches['column'];

        if (empty($isle) || empty($column) || empty($row))
            return 'Please enter a valid bin location.';

        $currentBin = ProductWarehouse::find(Input::get('bin_id'));
        if (empty($currentBin))
            return 'Bin not found';

        // check any current occupants in the same warehouse
        $bin = ProductWarehouse::byBin($currentBin->warehouse_id, $isle, $row, $column);

        // bin is already occupied and has quantity
        if (!empty($bin) && $bin->quantity > 0)
        {
            $other = Product::find($bin->product_id);
            return $other->name . ' (' . $other->sku . ') is currently assigned to this bin.' . $bin->quantity;
        }

        // remove any existing occupant that is now out of stock
        if (!empty($bin))
            $bin->delete();

        // assign product to new bin
        $currentBin->isle = $isle;
        $currentBin->row = $row;
        $currentBin->column = $column;
        $currentBin->save();
        return '1';
    }

    public function map($id)
    {
        $res =
            [
                'width' => 0,
                'height' => 0,
                'rows' => []
            ];
        $warehouse = Warehouse::find($id);
        if (empty($warehouse)) return $res;
        $warehouse->map_passage = $warehouse->map_width - $warehouse->map_col;
        $res['width'] = $warehouse->map_width;
        $res['height'] = $warehouse->map_height;
        $map = WarehouseMap::where('warehouse_id', $id)->get()->toArray();
        $allBins = ProductWarehouse::with('product')->where('warehouse_id', $id)->orderBy('row')->get()->toArray();

        for ($y = 0; $y < $warehouse->map_height; $y++)
        {
            $cols = [];

            for ($x = 0; $x < $warehouse->map_width; $x++)
            {
                $col['label'] = '';
                $bins = [];

                foreach ($map as $m)
                {
                    if ($m['x'] == $x && $m['y'] == $y)
                    {
                        $col['label'] = $m['isle'] . '-' . $m['column'];

                        foreach ($allBins as $bin)
                        {
                            if ($bin['isle'] == $m['isle'] && $bin['column'] == $m['column'])
                            {
                                $bins[] =
                                    [
                                        'id' => $bin['id'],
                                        'bin' => $bin['isle'] . $bin['row'] . $bin['column'],
                                        'quantity' => $bin['quantity'],
                                        'sku' => $bin['product']['sku'],
                                        'name' => $bin['product']['name'],
                                        'brand' => $bin['product']['brand'],
                                        'product_id' => $bin['product']['id'],
                                        'codes' => ProductCode::where('product_id', $bin['product']['id'])->lists('code')
                                    ];
                            }
                        }

                        break;
                    }
                }

                $col['bins'] = $bins;
                $cols[] = $col;
            }

            $res['rows'][] = $cols;
        }

        return [$res,$warehouse];
    }

    public function newStock()
    {
        $product = Product::find(Input::get('product_id'));
        if (empty($product))
            return Response::json(['msg' => 'Product not found.'], 500);

        $warehouseId = trim(Input::get('warehouse_id'));
        $quantity = trim(Input::get('quantity'));

        $bin = ProductWarehouse::where('warehouse_id', $warehouseId)->where('product_id', $product->id)->first();

        // product is already in the warehouse
        if (!empty($bin))
        {
            $bin->quantity += $quantity;
            $bin->save();
            return ['new_quantity' => $bin->quantity];
        }

        // assign bin to product

        $re = "/(?<isle>\\d+)(?<row>[A-Z]+)(?<column>\\d+)/i";
        preg_match($re, strtoupper(trim(ltrim(Input::get('bin'), '0'))), $matches);

        if (!isset($matches['isle']) || !isset($matches['row']) || !isset($matches['column']))
            return Response::json(['msg' => 'Please enter a valid bin location.'], 500);

        $isle = $matches['isle'];
        $row = $matches['row'];
        $column = $matches['column'];

        if (empty($isle) || empty($column) || empty($row))
            return Response::json(['msg' => 'Please enter a valid bin location.'], 500);

        $bin = ProductWarehouse::byBin($warehouseId, $isle, $row, $column);

        // bin is already occupied and has quantity
        if (!empty($bin) && $bin->quantity > 0)
        {
            // occupied by another product
            $other = Product::find($bin->product_id);
            $ret['other_sku'] = $other->sku;
            $ret['other_name'] = $other->name;
            return $ret;
        }

        // bin is unoccupied
        else
        {
            if (empty($bin))
                $bin = new ProductWarehouse();

            $bin->product_id = $product->id;
            $bin->warehouse_id = $warehouseId;
            $bin->isle = $isle;
            $bin->row = $row;
            $bin->column = $column;
            $bin->quantity = $quantity;
            $bin->save();
            return ['new_quantity' => $quantity];
        }
    }

    public function unfinishedPicklist()
    {
        //$warehouseId = trim(Input::get('warehouse_id'));
        //$warehouse = Warehouse::find($warehouseId);
        //if (empty($warehouse)) return null;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_URL, "http://integra.eocenterprise.com/unfinished_picklist.php");
        return curl_exec($ch);
    }

    public function newPicklist()
    {
        $warehouseId = trim(Input::get('warehouse_id'));
        $warehouse = Warehouse::find($warehouseId);
        if (empty($warehouse)) return null;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "warehouse_id={$warehouseId}");
        curl_setopt($ch, CURLOPT_URL, "http://integra.eocenterprise.com/new_picklist.php");
        $output = curl_exec($ch);

        $list = json_decode($output, true);
        $res['warehouse_id'] = $warehouseId;
        $res['number'] = $list['number'];
        $res['items'] = [];

        $bins = [];

        foreach ($list['items'] as $sku => $obj)
        {
            $bin = $this->findBin($warehouseId, $sku);

            if (empty($bin) || !isset($bin['bin']))
            {
                file_put_contents(storage_path('no_bin.txt'), "No matching bin for sku {$sku} of picklist " . $list['number'] . "\n", FILE_APPEND);
                continue;
            }

            $key = str_pad($bin['bin']['isle'], 3, '0', STR_PAD_LEFT) . str_pad($bin['bin']['column'], 3, '0', STR_PAD_LEFT) . str_pad($bin['bin']['row'], 3, '0', STR_PAD_LEFT);
            $bins[$key] =
                [
                    'bin' => $bin['bin']['isle'] . $bin['bin']['row'] . $bin['bin']['column'],
                    'quantity' => $obj['quantity'],
                    'brand' => $bin['product']['brand'],
                    'name' => $bin['product']['name'],
                    'sku' => $bin['product']['sku'],
                    'product_id' => $bin['product']['id'],
                    'status' => 0,
                    'reason' => null,
                    'sales' => $obj['sales']
                ];
        }

        ksort($bins);
        $res['items'] = array_values($bins);

        return $res;
    }

    public function resumePicklist($id)
    {
        $warehouseId = trim(Input::get('warehouse_id'));
        $warehouse = Warehouse::find($warehouseId);
        if (empty($warehouse)) return null;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "warehouse_id={$warehouseId}");
        curl_setopt($ch, CURLOPT_URL, "http://integra.eocenterprise.com/resume_picklist.php?id={$id}");
        $output = curl_exec($ch);

        $list = json_decode($output, true);
        $res['warehouse_id'] = $warehouseId;
        $res['number'] = $list['number'];
        $res['items'] = [];

        $bins = [];

        foreach ($list['items'] as $sku => $obj)
        {
            $bin = $this->findBin($warehouseId, $sku);

            if (empty($bin) || !isset($bin['bin']))
            {
                // TODO: email notification to admin
                file_put_contents(storage_path('no_bin.txt'), "No matching bin for sku {$sku} of picklist " . $list['number'] . "\n", FILE_APPEND);
                continue;
            }

            $key = str_pad($bin['bin']['isle'], 3, '0', STR_PAD_LEFT) . str_pad($bin['bin']['column'], 3, '0', STR_PAD_LEFT) . str_pad($bin['bin']['row'], 3, '0', STR_PAD_LEFT);
            $bins[$key] =
                [
                    'bin' => $bin['bin']['isle'] . $bin['bin']['row'] . $bin['bin']['column'],
                    'quantity' => $obj['quantity'],
                    'brand' => $bin['product']['brand'],
                    'name' => $bin['product']['name'],
                    'sku' => $bin['product']['sku'],
                    'product_id' => $bin['product']['id'],
                    'status' => 0,
                    'reason' => null,
                    'sales' => $obj['sales']
                ];
        }

        ksort($bins);
        $res['items'] = array_values($bins);

        return $res;
    }

    public function confirmPicklist()
    {
        $warehouseId = trim(Input::get('warehouse_id'));
        if (empty($warehouseId)) return null;

        $picklistNum = trim(Input::get('number'));
        if (empty($picklistNum)) return null;

        $items = Input::get('items');
        if (empty($items)) return null;

        $json = json_encode(['number' => $picklistNum, 'items' => $items]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($json)]);
        curl_setopt($ch, CURLOPT_URL, "http://integra.eocenterprise.com/confirm_picklist.php");
        $output = curl_exec($ch);

        foreach ($items as $item)
        {
            if ($item['status'] == 1)
            {
                $bin = ProductWarehouse::where('warehouse_id', $warehouseId)->where('product_id', $item['product_id'])->first();
                if (!empty($bin))
                {
                    $bin->quantity -= $item['quantity'];
                    $bin->save();
                }
            }
        }
    }

    public function getSupplier()
    {
        $results = Supplier::all()->toArray();
        return $results;
    }

    public function store()
    {
        $input = Input::except('country', 'is_active', 'can_drop_ship', 'has_truck');
        $input['country'] = Input::get('country', 'US');
        $input['is_active'] = Input::get('is_active', 1);
        $input['can_drop_ship'] = Input::get('can_drop_ship', 1);
        $input['has_truck'] = Input::get('has_truck', 0);
        $row = Warehouse::where('code', $input['code'])->first();
        if($row)
        {
            return;
        }
        $warehouse = new Warehouse;
        if($input['map_col'] && $input['map_passage']) $input['map_width'] = $input['map_col'] + $input['map_passage'];
        $warehouse->supplier_id = $input['supplier_id'];
        $warehouse->name = $input['name'];
        $warehouse->code = $input['code'];
        $warehouse->city = $input['city'];
        $warehouse->state = $input['state'];
        $warehouse->country = $input['country'];
        $warehouse->can_drop_ship = $input['can_drop_ship'];
        $warehouse->has_truck = $input['has_truck'];
        $warehouse->is_active = $input['is_active'];
        $warhouse->map_col = $input['map_col'];
        if($input['map_width'] && $input['map_height'])
        {
            $warehouse->map_width = $input['map_width'];
            $warehouse->map_height = $input['map_height'];
        }

        $warehouse->save();

        return $warehouse->id;

    }

    public function update()
    {
        $input = Input::except('country', 'is_active', 'can_drop_ship', 'has_truck');
        $input['country'] = Input::get('country', 'US');
        $input['is_active'] = Input::get('is_active', 1);
        $input['can_drop_ship'] = Input::get('can_drop_ship', 1);
        $input['has_truck'] = Input::get('has_truck', 0);
        if($input['map_col'] && $input['map_passage']) $input['map_width'] = $input['map_col'] + $input['map_passage'];
        $warehouse = Warehouse::find($input['id']);
        $warehouse->supplier_id = $input['supplier_id'];
        $warehouse->name = $input['name'];
        $warehouse->code = $input['code'];
        $warehouse->city = $input['city'];
        $warehouse->state = $input['state'];
        $warehouse->country = $input['country'];
        $warehouse->can_drop_ship = $input['can_drop_ship'];
        $warehouse->has_truck = $input['has_truck'];
        $warehouse->is_active = $input['is_active'];
        $warehouse->map_col = $input['map_col'];

        if($input['map_width'] && $input['map_height'])
        {
            $warehouse->map_width = $input['map_width'];
            $warehouse->map_height = $input['map_height'];
        }
        $warehouse->save();

        return $warehouse->id;
    }
}
