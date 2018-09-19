<?php

use \Carbon\Carbon;

class PaypalIpnController extends \BaseController
{
    public function processIpn()
    {
        $config = Config::get('paypal');
        $logFile = $config['settings']['log.FileName'];

        try
        {
            $raw_post_data = file_get_contents('php://input');
            $raw_post_array = explode('&', $raw_post_data);
            $myPost = array();

            foreach ($raw_post_array as $keyval)
            {
                $keyval = explode('=', $keyval);
                if (count($keyval) == 2)
                    $myPost[$keyval[0]] = urldecode($keyval[1]);
            }

            $req = 'cmd=_notify-validate';
            if (function_exists('get_magic_quotes_gpc'))
                $get_magic_quotes_exists = true;

            foreach ($myPost as $key => $value)
            {
                $value = ($get_magic_quotes_exists && get_magic_quotes_gpc() == 1) ? urlencode(stripslashes($value)) : urlencode($value);
                $req .= "&$key=$value";
            }

            $ch = curl_init($config['url'] . "/cgi-bin/webscr");
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

            $res = curl_exec($ch);
            if (curl_errno($ch) != 0)
            {
                error_log(date('[Y-m-d H:i e] ') . "Can't connect to PayPal to validate IPN message: " . curl_error($ch) . PHP_EOL, 3, $logFile);
                curl_close($ch);
                exit;
            }

            curl_close($ch);

            if (strcmp($res, "VERIFIED") == 0)
            {
                if (!isset($_POST['invoice_id']))
                {
                    error_log(date('[Y-m-d H:i e] ') . "No invoice ID in IPN: {$raw_post_data}" . PHP_EOL, 3, $logFile);
                    exit;
                }

                $invoice = PaypalInvoice::with('items')->where('paypal_invoice_id', $_POST['invoice_id'])->first();

                if (empty($invoice))
                {
                    error_log(date('[Y-m-d H:i e] ') . "Invoice not registered in Integra: {$raw_post_data}" . PHP_EOL, 3, $logFile);
                    exit;
                }

                if ($invoice->status == 'Paid')
                {
                    error_log(date('[Y-m-d H:i e] ') . "Invoice is already paid: {$raw_post_data}" . PHP_EOL, 3, $logFile);
                    exit;
                }

                $status = $_POST['payment_status'];
                if ($status != 'Completed')
                {
                    error_log(date('[Y-m-d H:i e] ') . "Payment not completed: {$raw_post_data}" . PHP_EOL, 3, $logFile);
                    exit;
                }

                $currency = $_POST['mc_currency'];
                if ($currency != 'USD')
                {
                    error_log(date('[Y-m-d H:i e] ') . "Currency is not USD: {$raw_post_data}" . PHP_EOL, 3, $logFile);
                    exit;
                }

                $amount = $_POST['mc_gross'];
                if ($amount < $invoice->total)
                {
                    error_log(date('[Y-m-d H:i e] ') . "Paid amount incorrect: {$raw_post_data}" . PHP_EOL, 3, $logFile);
                    exit;
                }

                $receiver_email = $_POST['receiver_email'];
                $correct_email = (($config['settings']['mode'] == 'sandbox') ? $config['sandbox_merchant'] : $config['merchants'][$invoice->merchant]['email']);
                if (strtolower($receiver_email) != strtolower($correct_email))
                {
                    error_log(date('[Y-m-d H:i e] ') . "Receiving email incorrect: {$raw_post_data}" . PHP_EOL, 3, $logFile);
                    exit;
                }

                $invoice->status = 'Paid';
                $invoice->save();

                try
                {
                    $totalSupplierCost = 0;
                    $totalWeight = 0;
                    $supplier = 0;

                    foreach ($invoice->items as $item)
                    {
                        if (empty($item->sku) || empty($item->quantity)) continue;

                        $totalSupplierCost += $item->supplier_cost * $item->quantity;
                        $totalWeight += $item->weight * $item->quantity;

                        if ($supplier == 0)
                        {
                            $supplier = $item->supplier;
                        }
                        else
                        {
                            if ($supplier != $item->supplier)
                                $supplier = -2;
                        }
                    }

                    $entry = new Order();
                    $entry->store = 'Manual';
                    $entry->record_num = $invoice->invoice_num;
                    $entry->order_date = Carbon::now();
                    $entry->total = $invoice->total;
                    $entry->email = $invoice->email;
                    $entry->buyer_name = $_POST['first_name'] . ' ' . $_POST['last_name'];
                    $entry->buyer_id = $_POST['first_name'] . ' ' . $_POST['last_name'];
                    $entry->street = $_POST['address_street'];
                    $entry->city = $_POST['address_city'];
                    $entry->state = $_POST['address_state'];
                    $entry->country = $_POST['address_country_code'];
                    $entry->zip = $_POST['address_zip'];

                    if (isset($_POST['address_contact_phone']))
                        $entry->phone = $_POST['address_contact_phone'];
                    else $entry->phone = '';

                    $entry->speed = $invoice->shipping_speed;
                    $entry->tracking_num = '';
                    $entry->carrier = '';
                    $entry->related_sales_id = $invoice->related_sales_id;

                    // set related order to refund complete if there is a linked order
                    if (!empty($invoice->related_sales_id))
                    {
                        DB::update('UPDATE eoc.sales SET status = 93 WHERE id = ?', [$invoice->related_sales_id]);
                        DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 0, 1, 1, 1)', [$invoice->related_sales_id, '', 'Refund complete']);
                    }

                    $entry->agent = $invoice->agent;
                    $entry->fulfilment = 0; // $invoice->fulfillment;
                    $entry->status = 0; // OrderStatus::Scheduled;
                    $entry->supplier_cost = $totalSupplierCost;
                    $entry->weight = $totalWeight;
                    $entry->supplier = $supplier;
                    $entry->save();

                    $orderId = Order::where('email', $entry->email)->where('record_num', $entry->record_num)->orderBy('id', 'desc')->pluck('id');
                    DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 0, 1, 1, 1)', [$orderId, '', 'Invoice paid, order created for fulfillment']);

                    if (!empty($invoice->remarks))
                    {
                        DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 0, 1, 1, 0)', [$orderId, '', 'Invoice remarks: ' . $invoice->remarks]);
                    }
                    if (isset($_POST['memo']))
                    {
                        DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 0, 1, 1, 0)', [$orderId, '', 'Note from customer: ' . $_POST['memo']]);
                    }

                    $invoice->order_id = $orderId;
                    $invoice->save();

                    foreach ($invoice->items as $item)
                    {
                        if (empty($item->sku) || empty($item->quantity)) continue;

                        $si = new OrderItem();
                        $si->sales_id = $orderId;
                        $si->sku = $item->sku;
                        $si->description = $item->description;
                        $si->quantity = $item->quantity;
                        $si->unit_price = $item->unit_price;
                        $si->total = $item->unit_price * $item->quantity;
                        $si->save();
                    }

                    error_log(date('[Y-m-d H:i e] ') . "Payment received for: " . $invoice->paypal_invoice_id . PHP_EOL, 3, $logFile);
                }
                catch (Exception $e)
                {
                    error_log(date('[Y-m-d H:i e] ') . "Error while creating the order for: " . $invoice->paypal_invoice_id . " - " . $e->getMessage() . PHP_EOL, 3, $logFile);
                }
            }
            else
            {
                error_log(date('[Y-m-d H:i e] ') . "Invalid IPN: {$raw_post_data}" . PHP_EOL, 3, $logFile);
            }
        }
        catch (Exception $e)
        {
            error_log(date('[Y-m-d H:i e] ') . "Exception: " . $e->getMessage() . PHP_EOL, 3, $logFile);
        }
    }
}
