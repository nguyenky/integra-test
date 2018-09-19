<?php

use \Carbon\Carbon;

class ImcUtils
{
    public static function RequestRa($items)
    {
        if (empty($items)) return null;

        $invoiceNums = [];

        foreach ($items as $item)
            $invoiceNums[$item['invoice_num']] = 1;

        $url = 'http://www.imcparts.net/webapp/wcs/stores/servlet/';

        $config = Config::get('integra');
        $username = $config['imc_web']['username'];
        $password = $config['imc_web']['password'];
        $accountNum = $config['imc_web']['account_num'];
        $store = $config['imc_web']['store'];

        $cookie = tempnam("/tmp", "imcweb");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($ch, CURLOPT_URL, "{$url}Logon");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "storeId={$store}&catalogId={$store}&langId=-1&reLogonURL=LogonForm&URL=HomePageView%3Flogon*%3D&postLogonPage=HomePageView&logonId={$username}&logonPassword={$password}&logonImg.x=32&logonImg.y=15");
        $output = curl_exec($ch);

        curl_setopt($ch, CURLOPT_URL, "{$url}SearchAvailableReturns?storeId={$store}&catalogId={$store}&langId=-1&custNum={$accountNum}");
        curl_setopt($ch, CURLOPT_POST, false);
        $output = curl_exec($ch);

        foreach ($invoiceNums as $invoiceNum => $x)
        {
            $postArray = ["searchNumber={$invoiceNum}", "returnCode=", "searchByType=INVOICE_NUMBER"];

            curl_setopt($ch, CURLOPT_URL, "{$url}ViewAvailableReturns?tbReturnRadioGrp=&searchBy=INVOICE_NUMBER&searchNumber={$invoiceNum}&searchDate=&searchToken={$invoiceNum}&returnCode=&keyedAsNumber=");
            curl_setopt($ch, CURLOPT_POST, false);
            $output = curl_exec($ch);

            preg_match_all("/tr>\\s*<td>(?<sku>[^<]+)<.+?availableLines\\[(?<idx>\\d+)/is", $output, $results, PREG_SET_ORDER);

            foreach ($results as $result)
            {
                $idx = trim($result['idx']);
                $sku = trim($result['sku']);
                $qty = 0;
                $comment = '';
                $reasonId = "";
                $found = false;

                foreach ($items as $item)
                {
                    if ($item['invoice_num'] == $invoiceNum && $item['sku'] == $sku)
                    {
                        $qty = $item['quantity'];
                        $reasonId = $item['reason_id'];
                        $comment = (strpos($item['reason'], 'Defective') === false) ? 'NO' : 'YES';
                        $found = true;
                    }
                }

                if ($found)
                {
                    $postArray[] = "availableLines%5B{$idx}%5D.comment={$comment}";
                    $postArray[] = "availableLines%5B{$idx}%5D.reasonId={$reasonId}";
                    $postArray[] = "addId={$idx}";
                }

                $postArray[] = "availableLines%5B{$idx}%5D.returnQty={$qty}";
                $postArray[] = "returnComment{$idx}={$comment}";
            }

            curl_setopt($ch, CURLOPT_URL, "{$url}RmaAddToBin");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $postArray));
            $output = curl_exec($ch);
        }

        $postArray = ["searchNumber=", "returnCode="];

        preg_match_all("/details\\[(?<idx>\\d+)\\]\\.returnQty\" size=\"1\" value=\"(?<qty>\\d+)\".+?value=\"(?<reason>\\d+)\" selected/is", $output, $results, PREG_SET_ORDER);

        foreach ($results as $result)
        {
            $idx = trim($result['idx']);
            $qty = trim($result['qty']);
            $reason = trim($result['reason']);

            $postArray[] = "bin.details[{$idx}].returnQty={$qty}";
            $postArray[] = "bin.details[{$idx}].reasonId={$reason}";
        }

        curl_setopt($ch, CURLOPT_URL, "{$url}RmaSubmitBin");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $postArray));
        $output = curl_exec($ch);

        preg_match("/raNumber=(?<ra>\\d+)/i", $output, $results);
        if (!isset($results['ra']))
            return null;

        $return = new SupplierReturn();
        $return->supplier_id = Supplier::where('name', 'IMC')->pluck('id');
        $return->return_num = trim($results['ra']);
        $return->return_date = Carbon::now();
        $return->status = 'Processing';
        $return->credit_num = '';
        $return->credit_date = null;
        $return->total_credited = null;
        $return->save();

        foreach ($items as $item)
        {
            $ri = new SupplierReturnItem();
            $ri->supplier_return_id = $return->id;
            $ri->invoice_num = $item['invoice_num'];
            $ri->sku = $item['sku'];
            $ri->reason = $item['reason'];
            $ri->quantity_requested = $item['quantity'];
            $ri->quantity_credited = null;
            $ri->unit_price_credited = null;
            $ri->save();
        }

        try
        {
            $raDoc = public_path() . "/ra_doc/" . $return->id . ".htm";

            if (!file_exists($raDoc))
            {
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_URL, "{$url}ReturnDetailPrintableCmd?raNumber=" . $return->return_num);
                $output = curl_exec($ch);
                file_put_contents($raDoc, $output);
            }
        }
        catch (Exception $e)
        {
        }

        return $return;
    }

    public static function ScrapeExportPrice($mpns, $username = null, $password = null)
    {
        $url = 'http://www.imcparts.net/webapp/wcs/stores/servlet/';

        $config = Config::get('integra');
        $username = empty($username) ? $config['imc_export']['username'] : $username;
        $password = empty($password) ? $config['imc_export']['password'] : $password;
        $store = $config['imc_export']['store'];

        $cookie = tempnam("/tmp", "imcexport");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        //curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888');

        curl_setopt($ch, CURLOPT_URL, "{$url}Logon");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "storeId={$store}&catalogId={$store}&langId=-1&reLogonURL=LogonForm&URL=HomePageView%3Flogon*%3D&postLogonPage=HomePageView&logonId={$username}&logonPassword={$password}&logonImg.x=0&logonImg.y=0");
        $loginOutput = curl_exec($ch);

        curl_setopt($ch, CURLOPT_POST, false);

        foreach ($mpns as $mpn)
        {
            if (!is_string($mpn) && is_array($mpn)) $mpn = $mpn['mpn'];
            $mpn = strtoupper(trim(str_replace(' ', '', $mpn)));
            echo "Scraping $mpn\n";
            $rows = DB::select('SELECT catentry_id FROM integra_prod.imc_export_items WHERE mpn_unspaced = ?', [$mpn]);

            if (empty($rows))
            {
                curl_setopt($ch, CURLOPT_URL, "{$url}AjaxFastOrderPartSearch?partNumber={$mpn}");
                $json = curl_exec($ch);
                if (strpos($json, 'USER_AUTHORITY') !== false)
                {
                    print_r($json);
                    print_r($loginOutput);
                    continue;
                }
                $json = json_decode($json, true);

                $thisPart = null;

                foreach ($json['parts'] as $part)
                {
                    $thisMpn = str_replace(' ', '', $part['partNumber']);

                    if ($thisMpn == $mpn)
                    {
                        $thisPart = $part;
                        break;
                    }
                }

                if (empty($thisPart))
                {
                    echo "Part not found!\n";
                    DB::insert("INSERT INTO integra_prod.imc_export_items (mpn_unspaced,last_scraped) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE mpn_unspaced=VALUES(mpn_unspaced), last_scraped=VALUES(last_scraped)", [$mpn]);
                    continue;
                }
                $catentryId = $thisPart['catentryId'];
            }
            else $catentryId = $rows[0]['catentry_id'];

            curl_setopt($ch, CURLOPT_URL, "{$url}AjaxFastOrderPartSearch?catentryId={$catentryId}");
            $json = json_decode(curl_exec($ch), true);

            if (empty($json['partInfoBean']))
            {
                echo "No part information!\n";
                DB::insert("INSERT INTO integra_prod.imc_export_items (mpn_unspaced,last_scraped) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE mpn_unspaced=VALUES(mpn_unspaced), last_scraped=VALUES(last_scraped)", [$mpn]);
                continue;
            }
            $json = $json['partInfoBean'];
            if (empty($json['catentryId']))
            {
                echo "Catalog ID empty!\n";
                DB::insert("INSERT INTO integra_prod.imc_export_items (mpn_unspaced,last_scraped) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE mpn_unspaced=VALUES(mpn_unspaced), last_scraped=VALUES(last_scraped)", [$mpn]);
                continue;
            }

            $desc = [];
            if (!empty($json['outsideNote']) && $json['outsideNote'] != '.') $desc[] = $json['outsideNote'];
            if (!empty($json['description']) && $json['description'] != '.') $desc[] = $json['description'];

            $json['price'] = str_replace('n/a', '0', str_replace('$', '', str_replace(',', '', $json['price'])));
            $json['minimumSellingPrice'] = str_replace('n/a', '0', str_replace('$', '', str_replace(',', '', $json['minimumSellingPrice'])));
            $json['basePrice'] = str_replace('n/a', '0', str_replace('$', '', str_replace(',', '', $json['basePrice'])));
            $json['listPrice'] = str_replace('n/a', '0', str_replace('$', '', str_replace(',', '', $json['listPrice'])));

            if (empty($json['unspacedPartNumber']))
                $json['unspacedPartNumber'] = str_replace(' ', '', $json['partNumber']);

            DB::insert(<<<EOQ
INSERT INTO integra_prod.imc_export_items
(catentry_id,mpn_unspaced,mpn_spaced,jpn,brand,name,unit_price,min_price,base_price,list_price,core_price,weight,qty_required,total_inventory,mover_code,battery,notes,margin,min_margin,msp_diff,last_scraped)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
ON DUPLICATE KEY UPDATE
catentry_id=VALUES(catentry_id),
mpn_unspaced=VALUES(mpn_unspaced),
mpn_spaced=VALUES(mpn_spaced),
jpn=VALUES(jpn),
brand=VALUES(brand),
name=VALUES(name),
unit_price=VALUES(unit_price),
min_price=VALUES(min_price),
base_price=VALUES(base_price),
list_price=VALUES(list_price),
core_price=VALUES(core_price),
weight=VALUES(weight),
qty_required=VALUES(qty_required),
total_inventory=VALUES(total_inventory),
mover_code=VALUES(mover_code),
battery=VALUES(battery),
notes=VALUES(notes),
margin=VALUES(margin),
min_margin=VALUES(min_margin),
msp_diff=VALUES(msp_diff),
last_scraped=VALUES(last_scraped)
EOQ
            , [
                    $catentryId,
                    $json['unspacedPartNumber'],
                    $json['partNumber'],
                    $json['jobberPartNumber'],
                    $json['manufacturerName'],
                    $json['name'],
                    $json['price'],
                    $json['minimumSellingPrice'],
                    $json['basePrice'],
                    $json['listPrice'],
                    $json['corePrice'] == 'n/a' ? 0 : str_replace('$', '', str_replace(',', '', $json['corePrice'])),
                    $json['unitWeight'],
                    $json['qtyRequired'],
                    $json['totalInventory'],
                    $json['moverCode'],
                    $json['batteryFlag'] == '1' ? 1 : 0,
                    implode('; ', $desc),
                    $json['basePrice'] == 0 ? 0 : (100 * ($json['price'] - $json['basePrice']) / $json['basePrice']),
                    $json['basePrice'] == 0 ? 0 : (100 * ($json['minimumSellingPrice'] - $json['basePrice']) / $json['basePrice']),
                    $json['minimumSellingPrice'] == 0 ? 0 : (100 * ($json['price'] - $json['minimumSellingPrice']) / $json['minimumSellingPrice'])]);
        }

        curl_setopt($ch, CURLOPT_URL, "{$url}Logoff?langId=-1&storeId={$store}&catalogId={$store}&URL=LogonForm&isLogOff=0");
        curl_exec($ch);
        curl_close($ch);
        unlink($cookie);
    }

    public static function OrderWebExport()
    {
        // max 20 batches only, 50 lines each
        for ($i = 0; $i < 20; $i++) {
            $date = date_create("now", new DateTimeZone('America/New_York'));
            $poNum = date_format($date, 'YmdHi') . "_web";
            $comments = '';
            $subtotal = null;
            $core = null;
            $orderNum = null;

            $items = DB::select(<<<EOQ
SELECT mpn, SUM(qty) AS qty, GROUP_CONCAT(DISTINCT id ORDER BY sales_id) AS ids
FROM eoc.imc_order_queue
WHERE order_id = ''
AND remarks != 'OUT OF STOCK'
GROUP BY 1
LIMIT 50
EOQ
            );

            /*$items = [
                ['mpn' => '4417', 'qty' => 1, 'ids' => '']
            ];*/

            if (empty($items)) return;

            $url = 'http://www.imcparts.net/webapp/wcs/stores/servlet/';

            $config = Config::get('integra');
            $username = $config['imc_export_order']['username'];
            $password = $config['imc_export_order']['password'];
            $store = $config['imc_export_order']['store'];

            $cookie = tempnam("/tmp", "imcorderwebexport");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 600);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            //curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888');

            // login
            curl_setopt($ch, CURLOPT_URL, "{$url}Logon");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "storeId={$store}&catalogId={$store}&langId=-1&reLogonURL=LogonForm&URL=HomePageView%3Flogon*%3D&postLogonPage=HomePageView&logonId={$username}&logonPassword={$password}&logonImg.x=32&logonImg.y=15");
            $output = curl_exec($ch);

            if (stripos($output, 'Quick Cart') && stripos($output, '(0 item)') === false) {
                // existing cart not empty, load and clear existing cart
                curl_setopt($ch, CURLOPT_POSTFIELDS, '');
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_URL, "${url}RequisitionListDisplay?langId=-1&storeId=${store}&catalogId=${store}&orderId=.");
                $output = curl_exec($ch);
                $start = stripos($output, 'id="ShopCartForm"');
                $end = stripos($output, '</form', $start);
                preg_match_all("/type=\"?hidden\"? name=\"(?<key>[^\"]+)\" value=\"(?<val>[^\"]*)\"/i", substr($output, $start, $end - $start), $matches, PREG_SET_ORDER);
                $args = [];
                foreach ($matches as $match) {
                    $key = $match['key'];

                    if (stripos($key, 'drpShip') === 0) continue;

                    if ($key == 'branch')
                        $args[$key] = $match['val'];

                    if (!array_key_exists($key, $args))
                        $args[$key] = $match['val'];

                    if (stripos($key, 'branchName_') === 0) {
                        $idx = explode('_', $key)[1];
                        $args["quantity_{$idx}"] = '0';
                        $args["comment_{$idx}"] = '';
                    }
                }
                $args['removeAllFlag'] = '0';
                $args['salesRepRelease'] = '';
                $args['orderComment'] = '';

                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_URL, "${url}RequisitionListItemUpdate");
                $output = curl_exec($ch);
            }

            $cartItems = [];

            // check each item against IMC catalog
            foreach ($items as &$item) {
                $item['whs'] = [];
                $mpn = trim(str_replace(' ', '', strtoupper($item['mpn'])));
                if (stripos($mpn, 'EOC') === 0)
                    $mpn = substr($mpn, 3);
                curl_setopt($ch, CURLOPT_URL, "${url}AjaxFastOrderPartSearch?partNumber={$mpn}");
                $output = json_decode(curl_exec($ch), true);
                if (count($output['parts']) == 0) {
                    $item['oos'] = true;
                    continue;
                }
                $cartItem = [];
                $cartItem['orderedAsPartNumber'] = $mpn;
                $cartItem['quantity'] = $item['qty'];
                $cartItem['catentryId'] = $output['parts'][0]['catentryId'];
                $cartItem['partNumber'] = $output['parts'][0]['partNumber'];
                curl_setopt($ch, CURLOPT_URL, "${url}AjaxFastOrderPartSearch?partNumber={$mpn}&catentryId=" . $cartItem['catentryId']);
                $output = curl_exec($ch);
                $output = json_decode($output, true);
                $totalAvailable = intval($output['partInfoBean']['totalInventory']);

                // insufficient stock, skip item
                if ($totalAvailable < $item['qty']) {
                    $item['oos'] = true;
                    continue;
                }

                $item['oos'] = false;

                $cartItem['batteryFlag'] = $output['partInfoBean']['batteryFlag'];
                $cartItems[] = $cartItem;
            }

            // add items to cart
            $params = [];
            $params['catalogId'] = '';
            $params['storeId'] = $store;
            $params['langId'] = '-1';
            $params['redirecturl'] = '';
            $params['URL'] = '';
            $params['viewTaskName'] = 'FastOrderView';
            $params['fromPage'] = 'FastOrder';

            for ($ctr = 1; $ctr <= count($cartItems); $ctr++) {
                $params["quantity_{$ctr}"] = $cartItems[$ctr - 1]['quantity'];
                $params["orderedAsPartNumber_{$ctr}"] = $cartItems[$ctr - 1]['orderedAsPartNumber'];
                $params["partNumber_{$ctr}"] = $cartItems[$ctr - 1]['partNumber'];
                $params["catEntryId_{$ctr}"] = $cartItems[$ctr - 1]['catentryId'];
                $params["batteryFlag_{$ctr}"] = $cartItems[$ctr - 1]['batteryFlag'];
            }

            $params["quantity_{$ctr}"] = '';

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_URL, "${url}FastOrderEntries");
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            $output = curl_exec($ch);

            file_put_contents(storage_path($poNum . '.txt'), $output . "\n--------------------------------------------------\n", FILE_APPEND);

            $start = stripos($output, 'id="ShopCartForm"');
            $end = stripos($output, '</form', $start);
            preg_match_all("/type=\"?hidden\"? name=\"(?<key>[^\"]+)\" value=\"(?<val>[^\"]*)\"/i", substr($output, $start, $end - $start), $matches, PREG_SET_ORDER);
            $args = [];
            foreach ($matches as $match) {
                $key = $match['key'];

                if (stripos($key, 'drpShip') === 0) continue;

                if (!array_key_exists($key, $args))
                    $args[$key] = $match['val'];

                if (stripos($key, 'qty_') === 0) {
                    $idx = explode('_', $key)[1];
                    $args["quantity_{$idx}"] = $match['val'];
                    $args["comment_{$idx}"] = '';
                }
            }

            $args['salesRepRelease'] = '0';
            $args['orderOnHold'] = 'off';
            $args['orderComment'] = '';
            $args['dropShipperQuickCheckOut'] = '0';
            $args['quickCheckOut'] = '0';

            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_URL, "${url}ShippingOptionsView");
            $output = curl_exec($ch);

            preg_match_all("/title=\"(?<val>[^\"]+)\" id=\"OrderCutoff_/i", $output, $matches, PREG_SET_ORDER);
            $departures = [];

            foreach ($matches as $match) {
                $departures[] = explode('.', $match['val'])[0];
            }

            $start = stripos($output, 'form name="DropShipForm"');
            $end = stripos($output, '</form', $start);

            $args = [];
            $args['conFreightTotal'] = '0.0';
            $args['changedDeliveryDate'] = 'false';
            $args['field2_1'] = $poNum;
            $args['orderComment'] = $comments;

            preg_match_all("/type=\"?hidden\"? name=\"(?<key>[^\"]+)\" value=\"(?<val>[^\"]*)\"/i", substr($output, $start, $end - $start), $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $key = $match['key'];

                if (!array_key_exists($key, $args))
                    $args[$key] = $match['val'];

                if (stripos($key, 'totCore_') === 0) {
                    $idx = explode('_', $key)[1];

                    if ($idx == '8' || $idx == '15') {
                        $args["freightOption_{$idx}"] = 'OUR TRUCK';
                        $args["shipVia_{$idx}"] = 'OUR TRUCK';
                    } else if ($idx == '1' || $idx == '2' || $idx == '3' || $idx == '5' || $idx == '6') {
                        $args["freightOption_{$idx}"] = 'NEXT DAY SAVER';
                        $args["shipVia_{$idx}"] = 'NEXT DAY SAVER';
                    } else {
                        $args["freightOption_{$idx}"] = 'GROUND';
                        $args["shipVia_{$idx}"] = 'GROUND';
                    }
                    $args["freight_{$idx}"] = '0';
                }
            }

            asort($departures);
            if (count($departures) > 0) $actual = $departures[0];
            else $actual = '';

            $args['actualDeliveryDate'] = $actual;
            $args['deliveryDate'] = $actual;

            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_URL, "${url}RequisitionListSubmit");
            $output = curl_exec($ch);

            file_put_contents(storage_path($poNum . '.txt'), $output, FILE_APPEND);

            preg_match("/Order # (?<val>\\d+)/i", $output, $matches);
            $orderNum = $matches['val'];

            preg_match("/Core Charge<[^$]+\\$(?<val>[^<]+)/i", $output, $matches);
            $core = $matches['val'];

            preg_match("/Sub Total<[^$]+\\$(?<val>[^<]+)/i", $output, $matches);
            $subtotal = $matches['val'];

            curl_close($ch);
            unlink($cookie);

            $sections = explode('<h2 class="cartTitle" style="padding-bottom: 15px;"><span', $output);
            foreach ($sections as $section) {
                preg_match("/>(?<wh>[A-Za-z, ]+) via/i", $section, $matches);
                if (!isset($matches['wh']))
                    continue;

                $wh = $matches['wh'];

                foreach ($items as &$item) {
                    if (stripos($section, $item['mpn']) !== false) {
                        $item['whs'][] = $wh;
                    }
                }
            }

            DB::insert("INSERT IGNORE INTO eoc.direct_shipments (sales_id, order_id, supplier, is_bulk, subtotal, core, shipping, total, order_date) VALUES (0, ?, 1, 1, ?, ?, 0, ?, NOW())",
                [$orderNum, $subtotal, $core, $subtotal + $core]);

            foreach ($items as $item) {
                $ids = explode(',', $item['ids']);
                $remarks = implode(' / ', $item['whs']);

                foreach ($ids as $id) {
                    $rows = DB::select("SELECT sales_id, extra_id FROM eoc.imc_order_queue WHERE id = ?", [$id]);
                    $salesId = $rows[0]['sales_id'];
                    $extraId = $rows[0]['extra_id'];

                    if ($item['oos']) // out of stock
                    {
                        DB::update("UPDATE eoc.imc_order_queue SET order_id = ?, remarks = ? WHERE id = ?", ['', 'OUT OF STOCK', $id]);
                        DB::update("UPDATE eoc.sales SET fulfilment = 3, status = 99 WHERE id = ?", [$salesId]);
                        DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 1, 1, 1, 1)', [$salesId, '', 'W1: Out of stock']);
                        if (!empty($extraId)) DB::update("UPDATE eoc.extra_orders SET order_id = 'OUT OF STOCK' WHERE id = ?", [$extraId]);
                    } else {
                        DB::update("UPDATE eoc.imc_order_queue SET order_id = ?, remarks = ? WHERE id = ?", [$orderNum, $remarks, $id]);
                        DB::update("UPDATE eoc.sales SET fulfilment = 3, status = 2 WHERE id = ?", [$salesId]);
                        DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 1, 1, 1, 0)', [$salesId, '', 'Included in W1 order #' . $orderNum . ' via ' . $remarks]);
                        DB::update("INSERT IGNORE INTO eoc.direct_shipments_sales (sales_id, order_id) VALUES (?, ?)", [$salesId, $orderNum]);
                        if (!empty($extraId)) DB::update("UPDATE eoc.extra_orders SET order_id = ? WHERE id = ?", [$orderNum, $extraId]);
                    }
                }
            }
        }
    }
}