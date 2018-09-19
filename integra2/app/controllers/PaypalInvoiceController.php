<?php

use \Carbon\Carbon;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Invoice;
use PayPal\Api\CustomAmount;
use PayPal\Api\Currency;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Api\BillingInfo;
use PayPal\Api\InvoiceAddress;
use PayPal\Api\InvoiceItem;
use PayPal\Api\MerchantInfo;
use PayPal\Api\Phone;
use PayPal\Api\ShippingCost;

class PaypalInvoiceController extends \BaseController
{
    public function index()
    {
        return IntegraUtils::paginate(PaypalInvoice::with(array
            (
                'order' => function ($query)
                {
                    $query->select('id', 'record_num');
                })
        )->get()->toArray());
    }

    public function store()
    {
        try
        {
            $config = Config::get('paypal');

            $ppInvoice = new Invoice();
            $items = [];

            foreach (Input::get('items') as $item)
            {
                if (empty($item['sku']) || empty($item['quantity']))
                    continue;

                $price = new Currency();
                $price->setCurrency('USD');
                $price->setValue($item['unit_price']);

                $ii = new InvoiceItem();
                $ii->setName($item['sku'])->setQuantity($item['quantity'])->setUnitPrice($price);
                $items[] = $ii;
            }

            $ppInvoice->setItems($items);

            $cost = Input::get('shipping_cost');
            if (empty($cost)) $cost = 0;

            $price = new Currency();
            $price->setCurrency('USD');
            $price->setValue($cost);

            $shippingCost = new ShippingCost();
            $shippingCost->setAmount($price);
            $ppInvoice->setShippingCost($shippingCost);

            $customLabel = Input::get('misc_item');
            if (!empty($customLabel))
            {
                $customAmount = Input::get('misc_amount');
                if (empty($customAmount)) $customAmount = 0;

                $price = new Currency();
                $price->setCurrency('USD');
                $price->setValue($customAmount);

                $custom = new CustomAmount();
                $custom->setLabel($customLabel);
                $custom->setAmount($price);
                $ppInvoice->setCustom($custom);
            }


            $merchantCode = Input::get('merchant.code');
            $merchantInfo = $config['merchants'][$merchantCode];

            if ($config['settings']['mode'] == 'sandbox')
            {
                $merchantInfo['email'] = $config['sandbox_merchant'];
                $clientId = $config['sandbox_client_id'];
                $secret = $config['sandbox_secret'];
            }
            else
            {
                $clientId = $config["{$merchantCode}_client_id"];
                $secret = $config["{$merchantCode}_secret"];
            }

            $phone = new Phone();
            $phone->setCountryCode($merchantInfo['phone']['country_code']);
            $phone->setNationalNumber($merchantInfo['phone']['national_number']);

            $address = new InvoiceAddress();
            $address->setLine1($merchantInfo['address']['line1']);
            $address->setLine2($merchantInfo['address']['line2']);
            $address->setCity($merchantInfo['address']['city']);
            $address->setCountryCode($merchantInfo['address']['country_code']);
            $address->setPostalCode($merchantInfo['address']['postal_code']);
            $address->setState($merchantInfo['address']['state']);

            $merchant = new MerchantInfo();
            $merchant->setEmail($merchantInfo['email']);
            $merchant->setFirstName($merchantInfo['first_name']);
            $merchant->setAddress($address);
            $merchant->setPhone($phone);
            $merchant->setWebsite($merchantInfo['website']);
            $ppInvoice->setMerchantInfo($merchant);

            $billingInfo = new BillingInfo();
            $billingInfo->setEmail((($config['settings']['mode'] == 'sandbox') ? $config['sandbox_buyer'] : Input::get('email')));
            $ppInvoice->setBillingInfo([$billingInfo]);

            $api = new ApiContext(new OAuthTokenCredential($clientId, $secret));
            $api->setConfig($config['settings']);

            $result = $ppInvoice->create($api);
            $ppInvoice->send($api);

            Input::merge([
                'invoice_date' => Carbon::now(),
                'invoice_num' => $result->getNumber(),
                'paypal_invoice_id' => $result->getId()
            ]);

            $related = Input::get('related_sales_id');

            $invoice = new PaypalInvoice();
            $invoice->invoice_date = Carbon::now();
            $invoice->invoice_num = $result->getNumber();
            $invoice->paypal_invoice_id = $result->getId();
            $invoice->email = Input::get('email');
            $invoice->merchant = $merchantCode;
            $invoice->shipping_speed = Input::get('shipping_speed.id');
            $invoice->fulfillment = 0; //Input::get('fulfillment.id');
            $invoice->remarks = Input::get('remarks');
            $invoice->related_sales_id = $related;
            $invoice->agent = Input::get('agent');
            $invoice->misc_amount = Input::get('misc_amount');
            $invoice->misc_item = Input::get('misc_item');
            $invoice->shipping_cost = Input::get('shipping_cost');
            if (empty($invoice->shipping_cost)) $invoice->shipping_cost = 0;
            $invoice->total = Input::get('total');
            $invoice->save();

            foreach (Input::get('items') as $item)
            {
                if (empty($item['sku']) || empty($item['quantity']))
                    continue;

                $invItem = new PaypalInvoiceItem();
                $invItem->quantity = $item['quantity'];
                $invItem->sku = $item['sku'];
                $invItem->description = $item['description'];
                $invItem->unit_price = $item['unit_price'];
                $invItem->weight = $item['weight'];
                $invItem->supplier = $item['supplier'];
                $invItem->supplier_cost = $item['supplier_cost'];

                $invoice->items()->save($invItem);
            }

            // set to payment pending if there is a linked order
            if (!empty($related))
                DB::update('UPDATE eoc.sales SET status = 91 WHERE id = ?', [$related]);

            return $invoice->id;
        }
        catch (PayPalConnectionException $e)
        {
            return Response::json($e->getData(), 500);
        }
    }

    public function show($id)
    {
        return PaypalInvoice::with(array('order' => function ($query)
        {
            $query->select('id', 'record_num');
        }, 'items'))->find($id);
    }

    public function findOrder($num)
    {
        $rows = DB::select('SELECT id, total FROM eoc.sales WHERE record_num = ? ORDER BY id DESC LIMIT 1', [trim(strtoupper($num))]);
        if (!empty($rows)) return $rows[0];
        else return '';
    }
}
