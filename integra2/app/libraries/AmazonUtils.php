<?php

class AmazonUtils
{
    public static function ListByASIN($items)
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');
        $config = Config::get('integra');
        $merchantId = $config['amazon_mws']['merchant_id'];

        foreach ($items as $item)
        {
            DB::update('UPDATE integra_prod.amazon_listing_queue SET status = 1, start_date = NOW() WHERE id = ?', [$item['id']]);
        }

        $serviceUrl = "https://mws.amazonservices.com";
        $settings = array
        (
            'ServiceURL' => $serviceUrl,
            'ProxyHost' => null,
            'ProxyPort' => -1,
            'MaxErrorRetry' => 3,
        );

        $service = new MarketplaceWebService_Client(
            $config['amazon_mws']['access_key_id'],
            $config['amazon_mws']['secret_access_key'],
            $settings,
            $config['amazon_mws']['application_name'],
            $config['amazon_mws']['application_version']);

        $amazonFeed = <<<EOD
<?xml version="1.0"?>
<AmazonEnvelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="amzn-envelope.xsd">
<Header><DocumentVersion>1.01</DocumentVersion><MerchantIdentifier>${merchantId}</MerchantIdentifier></Header>
<MessageType>Product</MessageType>
<PurgeAndReplace>false</PurgeAndReplace>
EOD;
        $ctr = 1;
        foreach ($items as $item)
        {
            $amazonFeed .= "<Message><MessageID>{$ctr}</MessageID><OperationType>Update</OperationType><Product>";
            $amazonFeed .= '<SKU>' . $item['sku'] . '</SKU>';
            $amazonFeed .= '<StandardProductID><Type>ASIN</Type><Value>' . $item['asin'] . '</Value></StandardProductID>';
            $amazonFeed .= '<Condition><ConditionType>' . (isset($item['condition']) ? $item['condition'] : 'New') . '</ConditionType></Condition></Product></Message>';
            $ctr++;
        }
        $amazonFeed .= '</AmazonEnvelope>';

        $feedHandle = @fopen('php://temp', 'rw+');
        fwrite($feedHandle, $amazonFeed);
        rewind($feedHandle);
        $request = new MarketplaceWebService_Model_SubmitFeedRequest();
        $request->setMerchant($merchantId);
        $request->setMarketplaceIdList(array("Id" => array($config['amazon_mws']['marketplace_id'])));
        $request->setFeedType('_POST_PRODUCT_DATA_');
        $request->setContentMd5(base64_encode(md5(stream_get_contents($feedHandle), true)));
        rewind($feedHandle);
        $request->setPurgeAndReplace(false);
        $request->setFeedContent($feedHandle);
        rewind($feedHandle);
        $id = 0;

        try
        {
            $response = $service->submitFeed($request);
            @fclose($feedHandle);

            $id = $response->getSubmitFeedResult()->getFeedSubmissionInfo()->getFeedSubmissionId();
        }
        catch (Exception $e)
        {
            // throttled, try again later
            foreach ($items as $item)
            {
                DB::update("UPDATE integra_prod.amazon_listing_queue SET status = 0, start_date = NOW(), last_message = 'Throttled'  WHERE id = ?", [$item['id']]);
            }

            echo "Throttled\n";
            return;
        }

        sleep(180);

        while (true)
        {
            try
            {
                $handle = fopen('php://temp', 'rw+');
                $request = new MarketplaceWebService_Model_GetFeedSubmissionResultRequest();
                $request->setMerchant($merchantId);
                $request->setFeedSubmissionId($id);
                $request->setFeedSubmissionResult($handle);
                $service->getFeedSubmissionResult($request);
                $xml = simplexml_load_string(stream_get_contents($request->getFeedSubmissionResult()));
                @fclose($handle);
                $status = $xml->Message[0]->ProcessingReport->StatusCode;
            }
            catch (Exception $e)
            {
                $status = 'Processing';
            }

            foreach ($items as $item)
            {
                DB::update('UPDATE integra_prod.amazon_listing_queue SET last_message = ?, feed_id = ? WHERE id = ?',
                    [$status, $id, $item['id']]);
            }

            sleep(180);

            if ($status == 'Complete') break;
        }

        $amazonFeed = <<<EOD
<?xml version="1.0"?>
<AmazonEnvelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="amzn-envelope.xsd">
<Header><DocumentVersion>1.01</DocumentVersion><MerchantIdentifier>${merchantId}</MerchantIdentifier></Header>
<MessageType>Inventory</MessageType>
EOD;
        $ctr = 1;
        foreach ($items as $item)
        {
            $amazonFeed .= "<Message><MessageID>{$ctr}</MessageID><OperationType>Update</OperationType><Inventory>";
            $amazonFeed .= '<SKU>' . $item['sku'] . '</SKU>';
            $amazonFeed .= '<Quantity>' . $item['quantity'] . '</Quantity></Inventory></Message>';
            $ctr++;
        }
        $amazonFeed .= '</AmazonEnvelope>';

        $feedHandle = @fopen('php://temp', 'rw+');
        fwrite($feedHandle, $amazonFeed);
        rewind($feedHandle);
        $request = new MarketplaceWebService_Model_SubmitFeedRequest();
        $request->setMerchant($merchantId);
        $request->setMarketplaceIdList(array("Id" => array($config['amazon_mws']['marketplace_id'])));
        $request->setFeedType('_POST_INVENTORY_AVAILABILITY_DATA_');
        $request->setContentMd5(base64_encode(md5(stream_get_contents($feedHandle), true)));
        rewind($feedHandle);
        $request->setPurgeAndReplace(false);
        $request->setFeedContent($feedHandle);
        rewind($feedHandle);
        $service->submitFeed($request);
        @fclose($feedHandle);

        sleep(180);

$amazonFeed = <<<EOD
<?xml version="1.0"?>
<AmazonEnvelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="amzn-envelope.xsd">
<Header><DocumentVersion>1.01</DocumentVersion><MerchantIdentifier>${merchantId}</MerchantIdentifier></Header>
<MessageType>Price</MessageType>
EOD;
        $ctr = 1;
        foreach ($items as $item)
        {
            $amazonFeed .= "<Message><MessageID>{$ctr}</MessageID><OperationType>Update</OperationType><Price>";
            $amazonFeed .= '<SKU>' . $item['sku'] . '</SKU>';
            $amazonFeed .= '<StandardPrice currency="' . (isset($item['currency']) ? $item['currency'] : 'USD') . '">' . $item['price'] . '</StandardPrice></Price></Message>';
            $ctr++;
        }
        $amazonFeed .= '</AmazonEnvelope>';

        $feedHandle = @fopen('php://temp', 'rw+');
        fwrite($feedHandle, $amazonFeed);
        rewind($feedHandle);
        $request = new MarketplaceWebService_Model_SubmitFeedRequest();
        $request->setMerchant($merchantId);
        $request->setMarketplaceIdList(array("Id" => array($config['amazon_mws']['marketplace_id'])));
        $request->setFeedType('_POST_PRODUCT_PRICING_DATA_');
        $request->setContentMd5(base64_encode(md5(stream_get_contents($feedHandle), true)));
        rewind($feedHandle);
        $request->setPurgeAndReplace(false);
        $request->setFeedContent($feedHandle);
        rewind($feedHandle);
        $service->submitFeed($request);
        @fclose($feedHandle);

        foreach ($items as $item)
        {
            DB::update('UPDATE integra_prod.amazon_listing_queue SET status = 3, end_date = NOW() WHERE id = ?', [$item['id']]);
        }
    }

    public static function Reprice($items)
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');
        $config = Config::get('integra');
        $merchantId = $config['amazon_mws']['merchant_id'];

        $serviceUrl = "https://mws.amazonservices.com";
        $settings = array
        (
            'ServiceURL' => $serviceUrl,
            'ProxyHost' => null,
            'ProxyPort' => -1,
            'MaxErrorRetry' => 3,
        );

        try {
            $service = new MarketplaceWebService_Client(
                $config['amazon_mws']['access_key_id'],
                $config['amazon_mws']['secret_access_key'],
                $settings,
                $config['amazon_mws']['application_name'],
                $config['amazon_mws']['application_version']);

            $amazonFeed = <<<EOD
<?xml version="1.0"?>
<AmazonEnvelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="amzn-envelope.xsd">
<Header><DocumentVersion>1.01</DocumentVersion><MerchantIdentifier>${merchantId}</MerchantIdentifier></Header>
<MessageType>Price</MessageType>
EOD;
            $ctr = 1;
            foreach ($items as $item) {
                $amazonFeed .= "<Message><MessageID>{$ctr}</MessageID><OperationType>Update</OperationType><Price>";
                $amazonFeed .= '<SKU>' . $item['sku'] . '</SKU>';
                $amazonFeed .= '<StandardPrice currency="USD">' . $item['price'] . '</StandardPrice></Price></Message>';
                $ctr++;
            }
            $amazonFeed .= '</AmazonEnvelope>';

            $feedHandle = @fopen('php://temp', 'rw+');
            fwrite($feedHandle, $amazonFeed);
            rewind($feedHandle);
            $request = new MarketplaceWebService_Model_SubmitFeedRequest();
            $request->setMerchant($merchantId);
            $request->setMarketplaceIdList(array("Id" => array($config['amazon_mws']['marketplace_id'])));
            $request->setFeedType('_POST_PRODUCT_PRICING_DATA_');
            $request->setContentMd5(base64_encode(md5(stream_get_contents($feedHandle), true)));
            rewind($feedHandle);
            $request->setPurgeAndReplace(false);
            $request->setFeedContent($feedHandle);
            rewind($feedHandle);
            $response = $service->submitFeed($request);
            @fclose($feedHandle);
            $feedId = $response->getSubmitFeedResult()->getFeedSubmissionInfo()->getFeedSubmissionId();
            echo $feedId;

            return $feedId;
        }
        catch (Exception $e)
        {
            echo $e->getMessage();
            error_log($e->getMessage());
            return null;
        }
    }
}
