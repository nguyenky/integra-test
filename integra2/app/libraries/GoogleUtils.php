<?php

use \Carbon\Carbon;

class GoogleUtils
{
    static $clientId = '943117101369-85b2thb807rod7lh8q1mtfe8ssi68o67.apps.googleusercontent.com';
    static $clientSecret = 'I4cIvu73yQDBSGYl0EiAglgv';
    static $refreshToken = '1/BlE_bU7TUtSTkood06aJ4qgrInVy4MlkA-J5dnqidb8';
    static $merchantId = '9199475';

    public static function upsertFeed($id, $store)
    {
        try {
            $client = new Google_Client();
            $client->setApplicationName('Integra 2');
            $client->setClientId(self::$clientId);
            $client->setClientSecret(self::$clientSecret);
            $client->refreshToken(self::$refreshToken);

            $product = new Google_Service_ShoppingContent_Product();
            $product->setContentLanguage('en');
            $product->setTargetCountry('US');
            $product->setChannel('online');

            $rows = DB::select('SELECT * FROM integra_prod.google_feed WHERE id = ? AND store = ?', [$id, $store]);
            if (empty($rows)) return false;
            $row = $rows[0];

            $product->setOfferId($row['id']);
            $product->setAvailability($row['availability']);
            $product->setMpn($row['custom_mpn']);
            $product->setTitle($row['title']);
            $product->setDescription($row['description']);
            $product->setLink($row['link']);

            $price = new Google_Service_ShoppingContent_Price();
            $price->setValue($row['price']);
            $price->setCurrency('USD');
            $product->setPrice($price);

            $product->setImageLink($row['image_link']);
            $product->setBrand($row['brand']);
            $product->setCondition($row['item_condition']);
            $product->setProductType('Vehicles & Parts > Automotive Parts');

            $shipping_price = new Google_Service_ShoppingContent_Price();
            $shipping_price->setValue($row['shipping']);
            $shipping_price->setCurrency('USD');

            $shipping = new Google_Service_ShoppingContent_ProductShipping();
            $shipping->setPrice($shipping_price);
            $shipping->setCountry('US');
            $product->setShipping([$shipping]);

            $product->setGoogleProductCategory($row['category']);

            if (!empty($row['custom_label0']))
                $product->setCustomLabel0($row['custom_label0']);

            if (!empty($row['custom_label1']))
                $product->setCustomLabel1($row['custom_label1']);

            if (!empty($row['custom_label2']))
                $product->setCustomLabel2($row['custom_label2']);

            if (!empty($row['custom_label3']))
                $product->setCustomLabel3($row['custom_label3']);

            if (!empty($row['custom_label4']))
                $product->setCustomLabel4($row['custom_label4']);

            $service = new Google_Service_ShoppingContent($client);
            $service->products->insert(self::$merchantId, $product);
            return ['success' => true, 'msg' => ''];
        }
        catch (Exception $e)
        {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public static function destroyFeed($id, $store)
    {
        try {
            $client = new Google_Client();
            $client->setApplicationName('Integra 2');
            $client->setClientId(self::$clientId);
            $client->setClientSecret(self::$clientSecret);
            $client->refreshToken(self::$refreshToken);

            $service = new Google_Service_ShoppingContent($client);
            $service->products->delete(self::$merchantId, sprintf('%s:%s:%s:%s', 'online', 'en', 'US', $id));

            DB::delete('DELETE FROM integra_prod.google_feed WHERE id = ? AND store = ?', [$id, $store]);
            return ['success' => true, 'msg' => ''];
        }
        catch (Exception $e)
        {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }
}