<?php

use \Carbon\Carbon;

class EbayUtils
{
    public static $token = 'AgAAAA**AQAAAA**aAAAAA**zJZMWg**nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6wCloGgCpaEqQidj6x9nY+seQ**NosBAA**AAMAAA**7+MiDKWT7B1VlFXExufh4Eedx5WzkgWlmTjSN5YaxKXihyfE0dUkla6bpc47+xZH5YzR+E04ZCyinwnCH4iOYXDpgZMnovv34x13OzalirA7MMwwjlx9qT2+3l0m42I9t6j+ZdEhWURMA/47/kbgt5k6baA5cXn4Syy7kiDyzdjLORubXP49K9ip59kJIZ5b8J4DnWslV8fSkkR2DkfiZ6+WlvLBxxv1KuVB59TZvbOARoRugOZAgG0iJHc+2faJhtHVt0m9JR+TmOvoMT6a5y9Mf2W4+shmeK5ena63rZ4p7aeMY0YLYHy1xVkphWTmI8j5qRJEsYIVmOLMCAedYbMKejFKHZOJqNjLtoBd4egz4P0mZkveGmF6PeYAvr/w+V+1fSPpdnU6KMUhiNGTrtWgi/dRBdylC+XhS/OwvbMdYtZXg1eaIbR6eVANcRlQuW93+Wm5YNtqUIIfD5ej6JEoVk8EOMijzq/DUT2Q1Op8sLNvpZWMvSiOeP+ccpxsWdh9aIzRq73N3X8Z3o4T7uudbnaDzeu3QZqzCYWUf0r+wK1uab8WP36NcsvAEafJyacnrpdgRvPLLkFv5A+CQWtb9HddciIaXoCsKMv6RwnrxQrvkdpndCYdgasljB355enjXJDnjP3fwAFMO3vCnbPsdJ44jk6g2oaRkcPsFOA36f4Ddbd9cPJRgdzveZhsXdEphMDmJA5fJ6TX68uFkT0964l6C1IXP+xRN02cioYcrEQlt/n5xwDY3junWBxU';
    public static $appId = "KBCwareT-fa69-4a39-a049-60ca79a5fff2";
    public static $otherCategoryId = '6763'; // Other Parts
    public static $newConditionId = '1000'; // New
    public static $remanConditionId = '2500'; // Re-manufactured
    public static $returnProfileId = '93290710014';
    public static $paymentProfileId = '93290711014';
    public static $shippingProfileId = '102557276014';

    public static function GetAvailable($itemId)
    {
        $res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid="
            . self::$appId . "&siteid=0&version=847&ItemID={$itemId}&IncludeSelector=Details");
        EbayApiCallCounter::create(['ebay_service_name'=>'GetSingleItem']);
        $xml = simplexml_load_string($res);

        if ($xml->Ack != 'Success' && $xml->Ack != 'Warning')
            return 0;

        return intval((string)$xml->Item->Quantity) - intval((string)$xml->Item->QuantitySold);
    }

    public static function GetListing($itemId)
    {
        $res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid="
            . self::$appId . "&siteid=0&version=847&ItemID={$itemId}&IncludeSelector=Details,Compatibility,ShippingCosts,ItemSpecifics");
        EbayApiCallCounter::create(['ebay_service_name'=>'GetSingleItem']);
        $xml = simplexml_load_string($res);

        if ($xml->Ack != 'Success' && $xml->Ack != 'Warning')
            return false;

        $listing = EbayScrapedListing::where('item_id', $itemId)->first();

        if (empty($listing))
        {
            $listing = new EbayScrapedListing();
            $listing->item_id = $itemId;
        }

        $listing->title = (string)$xml->Item->Title;
        $listing->category_id = (string)$xml->Item->PrimaryCategoryID;
        $allCat = (string)$xml->Item->PrimaryCategoryName;
        $cats = explode(':', $allCat);
        $catName = $allCat;
        if (count($cats) > 1)
            $catName = $cats[count($cats)-1];
        $listing->category = $catName;
        $listing->big_image = (string)$xml->Item->PictureURL;
        $listing->small_image = (string)$xml->Item->GalleryURL;
        $listing->price = (string)$xml->Item->CurrentPrice;

        $listing->shipping = (string)$xml->Item->ShippingCostSummary->ShippingServiceCost;
        $listing->shipping_type = (string)$xml->Item->ShippingCostSummary->ShippingType;

        if ($listing->shipping_type == 'Calculated')
        {
            $res = file_get_contents("http://www.ebay.com/itm/getrates?item={$itemId}&quantity=1&country=1&zipCode=77057&co=0&cb=j");
            EbayApiCallCounter::create(['ebay_service_name'=>'getrates']);
            preg_match('/US \$(?P<shipping>[^<]+)/i', $res, $match);
            if (isset($match) && array_key_exists('shipping', $match))
                $listing->shipping = $match['shipping'];
        }

        $listing->seller = (string)$xml->Item->Seller->UserID;
        $listing->score = intval((string)$xml->Item->Seller->FeedbackScore);
        $listing->rating = (string)$xml->Item->Seller->PositiveFeedbackPercent;
        $listing->top = ((string)$xml->Item->Seller->TopRatedSeller == 'true' ? 1 : 0);
        $listing->hits = intval((string)$xml->Item->HitCount);
        $listing->sold = intval((string)$xml->Item->QuantitySold);
        $listing->available = intval((string)$xml->Item->Quantity) - $listing->sold;
        $listing->compatible = intval((string)$xml->Item->ItemCompatibilityCount);
        $listing->condition = (string)$xml->Item->ConditionDisplayName;
        $listing->sku = (string)$xml->Item->SKU;

        $listing->mpn = '';
        $listing->ipn = '';
        $listing->opn = '';
        $listing->placement = '';
        $listing->brand = '';
        $listing->surface_finish = '';
        $listing->warranty = '';
        $listing->others = '';

        if (!empty($xml->Item->ItemSpecifics) && !empty($xml->Item->ItemSpecifics->NameValueList))
        {
            $placements = array();
            $others = array();

            foreach ($xml->Item->ItemSpecifics->NameValueList as $pair)
            {
                if ($pair->Name == 'Manufacturer Part Number')
                    $listing->mpn = (string)$pair->Value;
                else if ($pair->Name == 'Interchange Part Number')
                    $listing->ipn = (string)$pair->Value;
                else if ($pair->Name == 'Other Part Number')
                    $listing->opn = (string)$pair->Value;
                else if ($pair->Name == 'Placement on Vehicle')
                {
                    foreach ($pair->Value as $v)
                        $placements[] = $v;
                }
                else if ($pair->Name == 'Part Brand')
                    $listing->brand = (string)$pair->Value;
                else if ($pair->Name == 'Brand')
                    $listing->brand = (string)$pair->Value;
                else if ($pair->Name == 'Surface Finish')
                    $listing->surface_finish = (string)$pair->Value;
                else if ($pair->Name == 'Warranty')
                    $listing->warranty = (string)$pair->Value;
                else
                    $others[] = (string)$pair->Name . '=' . (string)$pair->Value;
            }

            $listing->placement = implode(', ', $placements);
            $listing->others = implode(', ', $others);
        }

        $listing->save();

        EbayScrapedCompatibility::where('item_id', $itemId)->delete();

        if ($xml->Item->ItemCompatibilityList) {
            foreach ($xml->Item->ItemCompatibilityList->children() as $comp) {
                $fit = new EbayScrapedCompatibility();
                $fit->item_id = $itemId;
                $fit->make = '';
                $fit->model = '';
                $fit->year = '';
                $fit->trim = '';
                $fit->engine = '';
                $fit->notes = '';

                foreach ($comp->children() as $n) {
                    $node = $n->getName();

                    if ($node == 'CompatibilityNotes')
                        $fit->notes = trim($n->CompatibilityNotes);
                    else if ($node == 'NameValueList') {
                        if ($n->Name == 'Year') $fit->year = trim($n->Value);
                        else if ($n->Name == 'Make') $fit->make = trim($n->Value);
                        else if ($n->Name == 'Model') $fit->model = trim($n->Value);
                        else if ($n->Name == 'Trim') $fit->trim = trim($n->Value);
                        else if ($n->Name == 'Engine') $fit->engine = trim($n->Value);
                    }
                }

                $fit->save();
            }
        }

        return $listing;
    }

    public static function GetSellerListings($sellerId, $keywords)
    {
        $pages = 1;

        for ($page = 1; $page <= $pages; $page++)
        {
            try
            {
                echo "Scraping page {$page}/{$pages} for {$sellerId} ({$keywords})\n";

                $res = file_get_contents("http://svcs.ebay.com/services/search/FindingService/v1?OPERATION-NAME=findItemsByKeywords&SERVICE-VERSION=1.0.0&SECURITY-APPNAME="
                    . self::$appId . "&RESPONSE-DATA-FORMAT=XML&REST-PAYLOAD&paginationInput.pageNumber={$page}&itemFilter(0).name=Seller&itemFilter(0).value(0)={$sellerId}&keywords=" . urlencode($keywords));
                EbayApiCallCounter::create(['ebay_service_name'=>'findItemsByKeywords']);
                $xml = simplexml_load_string($res);

                if ($xml->ack != 'Success' && $xml->ack != 'Warning')
                    return false;

                $pages = intval((string)$xml->paginationOutput->totalPages);

                if (!empty($xml->searchResult))
                {
                    foreach ($xml->searchResult->children() as $item)
                    {
                        try
                        {
                            echo "Scraping " . (string)$item->itemId . "\n";
                            self::GetListing((string)$item->itemId);
                        }
                        catch (Exception $e)
                        {
                            echo "ERROR: " . $e->getMessage() . "\n";
                        }
                    }
                }
            }
            catch (Exception $e2)
            {
                echo "ERROR: " . $e2->getMessage() . "\n";
            }
        }
    }

    public static function ListItem($listingSku, $title, $qty, $price, $description, $picture, $conditionId, $attribs, $compatibility, $notes)
    {
        if (is_array($picture))
            $pictures = $picture;
        else {
            if (stripos($picture, '/img/') === false)
                $pictures = explode(',', $picture);
            else $pictures = [$picture];
        }

        $pictureNode = '';
        foreach ($pictures as $p)
            $pictureNode .= '<ExternalPictureURL><![CDATA[' . trim($p) . ']]></ExternalPictureURL>';

        $attribNodes = '';
        $brand = 'N/A';
        $partNumbers = [];

        if (isset($attribs['mpn']) && !empty($attribs['mpn'])) {
            $attribNodes .= '<NameValueList><Name>Manufacturer Part Number</Name><Value><![CDATA[' . trim($attribs['mpn']) . ']]></Value></NameValueList>';
            $pns = explode('/', $attribs['mpn']);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }

        if (isset($attribs['ipn']) && !empty($attribs['ipn'])) {
            $attribNodes .= '<NameValueList><Name>Interchange Part Number</Name><Value><![CDATA[' . trim($attribs['ipn']) . ']]></Value></NameValueList>';
            $pns = explode('/', $attribs['ipn']);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }

        if (isset($attribs['opn']) && !empty($attribs['opn'])) {
            $attribNodes .= '<NameValueList><Name>Other Part Number</Name><Value><![CDATA[' . trim($attribs['opn']) . ']]></Value></NameValueList>';
            $pns = explode('/', $attribs['opn']);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }

        if (isset($attribs['brand']) && !empty($attribs['brand'])) {
            $attribNodes .= '<NameValueList><Name>Part Brand</Name><Value><![CDATA[' . trim($attribs['brand']) . ']]></Value></NameValueList>';
	    $attribNodes .= '<NameValueList><Name>Brand</Name><Value><![CDATA[' . trim($attribs['brand']) . ']]></Value></NameValueList>';
            $brand = trim($attribs['brand']);
        }

        if (isset($attribs['surface']) && !empty($attribs['surface']))
            $attribNodes .= '<NameValueList><Name>Surface Finish</Name><Value><![CDATA[' . trim($attribs['surface']) . ']]></Value></NameValueList>';

        $attribs['warranty'] = '1 year';
        if (isset($attribs['warranty']) && !empty($attribs['warranty']))
            $attribNodes .= '<NameValueList><Name>Warranty</Name><Value><![CDATA[' . trim($attribs['warranty']) . ']]></Value></NameValueList>';

        if (isset($attribs['placement']) && !empty($attribs['placement']))
        {
            $attribNodes .= '<NameValueList><Name>Placement on Vehicle</Name>';
            foreach (explode(',', $attribs['placement']) as $p)
                $attribNodes .= '<Value><![CDATA[' . trim($p) . ']]></Value>';
            $attribNodes .= '</NameValueList>';
        }

        $ranges = EbayUtils::EbayCompatToRanges($compatibility);
        $descNode = EbayUtils::RenderTemplateV2($title, $description, $brand, $conditionId, $partNumbers, $notes, $ranges);

        $ebayToken = self::$token;
        $categoryId = self::$otherCategoryId;

        $paymentProfile = self::$paymentProfileId;
        $returnProfile = self::$returnProfileId;
        $shippingProfile = self::$shippingProfileId;

        $data = <<< EOD
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Header>
        <h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>{$ebayToken}</eBayAuthToken></h:RequesterCredentials>
    </s:Header>
    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <AddFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <Version>849</Version>
        <Item>
            <PrivateListing>true</PrivateListing>
            <Title><![CDATA[${title}]]></Title>
            <Description><![CDATA[${descNode}]]></Description>
            <PrimaryCategory><CategoryID>{$categoryId}</CategoryID></PrimaryCategory>
            <StartPrice>{$price}</StartPrice>
            <InventoryTrackingMethod>SKU</InventoryTrackingMethod>
            <SKU>{$listingSku}</SKU>
            <CategoryMappingAllowed>true</CategoryMappingAllowed>
            <ConditionID>${conditionId}</ConditionID>
            <Country>US</Country>
            <Currency>USD</Currency>
            <DispatchTimeMax>1</DispatchTimeMax>
            <ListingDuration>GTC</ListingDuration>
            <ListingType>FixedPriceItem</ListingType>
            <AutoPay>true</AutoPay>
            <PaymentMethods>PayPal</PaymentMethods>
            <PayPalEmailAddress>sales@qeautoparts.com</PayPalEmailAddress>
            <PictureDetails>{$pictureNode}</PictureDetails>
            <Location>Miami, FL, United States</Location>
            <Quantity>{$qty}</Quantity>
            <ReturnPolicy>
                <ReturnsAcceptedOption>ReturnsAccepted</ReturnsAcceptedOption>
                <RefundOption>MoneyBackOrExchange</RefundOption>
                <ReturnsWithinOption>Days_30</ReturnsWithinOption>
            </ReturnPolicy>
            <SellerProfiles>
                <SellerPaymentProfile>
                    <PaymentProfileID>{$paymentProfile}</PaymentProfileID>
                </SellerPaymentProfile>
                <SellerReturnProfile>
                    <ReturnProfileID>{$returnProfile}</ReturnProfileID>
                </SellerReturnProfile>
                <SellerShippingProfile>
                    <ShippingProfileID>{$shippingProfile}</ShippingProfileID>
                </SellerShippingProfile>
            </SellerProfiles>
            <ShippingDetails>
                <ShippingType>Flat</ShippingType>
                <ShippingServiceOptions>
                    <ShippingServicePriority>1</ShippingServicePriority>
                    <ShippingService>ShippingMethodStandard</ShippingService>
                    <FreeShipping>true</FreeShipping>
                    <ShippingServiceCost currencyID="USD">0.00</ShippingServiceCost>
                    <ShippingServiceAdditionalCost currencyID="USD">0.00</ShippingServiceAdditionalCost>
                </ShippingServiceOptions>
            </ShippingDetails>
            <ProductListingDetails>
                <UPC><![CDATA[N/A]]></UPC>
            </ProductListingDetails>
            <ItemSpecifics>
                {$attribNodes}
            </ItemSpecifics>
            {$compatibility}
            <Site>eBayMotors</Site>
        </Item>
    </AddFixedPriceItemRequest>
    </s:Body>
</s:Envelope>
EOD;

        $ch = curl_init("https://api.ebay.com/wsapi?callname=AddFixedPriceItem&siteid=100&appid=" . self::$appId . "&version=849&routing=default");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/xml', 'SOAPAction: ""']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        $date = date_format(date_create("now", new DateTimeZone('America/New_York')), 'Y-m-d_H-i-s');
        file_put_contents(storage_path("logs/ebay_list/{$date}_req.txt"), $data);
        file_put_contents(storage_path("logs/ebay_list/{$date}_res.txt"), $res);

        $xml = explode("\n", $res);
        if (count($xml) > 5)
            $xml = json_decode(json_encode(simplexml_load_string('<x>' . implode("\n", array_slice($xml, 4, count($xml) - 7)) . '</x>')), true);
        else $xml = [];

        $response['ack'] = 'Error';
        $response['error'] = '';
        $response['item_id'] = '';
        $response['sku'] = '';

        if (isset($xml['Ack']))
        {
            $response['ack'] = $xml['Ack'];

            if (isset($xml['Errors']['LongMessage']))
                $response['error'] = $xml['Errors']['LongMessage'];
            else if (isset($xml['Errors']['ShortMessage']))
                $response['error'] = $xml['Errors']['ShortMessage'];
            else if (isset($xml['Errors'][0]['LongMessage']))
                $response['error'] = $xml['Errors'][0]['LongMessage'];
            else if (isset($xml['Errors'][0]['ShortMessage']))
                $response['error'] = $xml['Errors'][0]['ShortMessage'];

            if (isset($xml['ItemID'])) {
                $response['item_id'] = $xml['ItemID'];

                DB::insert("INSERT INTO eoc.ebay_edit_log (is_new, item_id, created_by, edited_field, before_value, after_value) VALUES (1, ?, ?, '', '', '')",
                    [$response['item_id'], Cookie::get('user')]);
            }

            if (isset($xml['SKU']))
                $response['sku'] = $xml['SKU'];
        }
        else if (isset($xml['detail']['FaultDetail']['DetailedMessage']))
        {
            $response['error'] = $xml['detail']['FaultDetail']['DetailedMessage'];
        }
        else if (isset($xml['faultstring']))
        {
            $response['error'] = $xml['faultstring'];
        }

        return $response;
    }

    public static function Suspend($itemId, $reason)
    {
        $ebayToken = self::$token;

        $data = <<< EOD
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Header>
        <h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>{$ebayToken}</eBayAuthToken></h:RequesterCredentials>
    </s:Header>
    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <ReviseInventoryStatusRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <Version>983</Version>
        <InventoryStatus>
            <ItemID>${itemId}</ItemID>
            <Quantity>0</Quantity>
        </InventoryStatus>
    </ReviseInventoryStatusRequest>
    </s:Body>
</s:Envelope>
EOD;

        $ch = curl_init("https://api.ebay.com/wsapi?callname=ReviseInventoryStatus&siteid=100&appid=" . self::$appId . "&version=983&routing=default");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/xml', 'SOAPAction: ""']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        $date = date_format(date_create("now", new DateTimeZone('America/New_York')), 'Y-m-d_H-i-s');
        file_put_contents(storage_path("logs/ebay_suspend/{$date}_req.txt"), $data);
        file_put_contents(storage_path("logs/ebay_suspend/{$date}_res.txt"), $res);

        $xml = explode("\n", $res);
        if (count($xml) > 5)
            $xml = json_decode(json_encode(simplexml_load_string('<x>' . implode("\n", array_slice($xml, 4, count($xml) - 7)) . '</x>')), true);
        else $xml = [];

        $response['ack'] = 'Error';
        $response['error'] = '';

        if (isset($xml['Ack']))
        {
            $response['ack'] = $xml['Ack'];

            if (isset($xml['Errors']['LongMessage']))
                $response['error'] = $xml['Errors']['LongMessage'];
            else if (isset($xml['Errors']['ShortMessage']))
                $response['error'] = $xml['Errors']['ShortMessage'];
            else if (isset($xml['Errors'][0]['LongMessage']))
                $response['error'] = $xml['Errors'][0]['LongMessage'];
            else if (isset($xml['Errors'][0]['ShortMessage']))
                $response['error'] = $xml['Errors'][0]['ShortMessage'];
        }
        else if (isset($xml['detail']['FaultDetail']['DetailedMessage']))
        {
            $response['error'] = $xml['detail']['FaultDetail']['DetailedMessage'];
        }
        else if (isset($xml['faultstring']))
        {
            $response['error'] = $xml['faultstring'];
        }

        DB::insert(<<<EOQ
INSERT IGNORE INTO eoc.ebay_listings (item_id, active)
VALUES (?, 1)
EOQ
            , [$itemId]);

        DB::update(<<<EOQ
UPDATE eoc.ebay_listings
SET suspended = 1, suspended_on = NOW(), suspended_by = ?, suspend_reason = ?, resumed_on = NULL
WHERE item_id = ?
EOQ
            , [Cookie::get('user'), $reason, $itemId]);

        return $response;
    }

    public static function ReviseTemplate($itemId, $template)
    {
        $ebayToken = self::$token;

        $data = <<< EOD
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Header>
        <h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>{$ebayToken}</eBayAuthToken></h:RequesterCredentials>
    </s:Header>
    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <Version>849</Version>
        <Item>
            <ItemID>${itemId}</ItemID>
            <Description><![CDATA[{$template}]]></Description>
        </Item>
    </ReviseFixedPriceItemRequest>
    </s:Body>
</s:Envelope>
EOD;

        $ch = curl_init("https://api.ebay.com/wsapi?callname=ReviseFixedPriceItem&siteid=100&appid=" . self::$appId . "&version=849&routing=default");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/xml', 'SOAPAction: ""']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        $date = date_format(date_create("now", new DateTimeZone('America/New_York')), 'Y-m-d_H-i-s');
        file_put_contents(storage_path("logs/ebay_upgrade/{$date}_req.txt"), $data);
        file_put_contents(storage_path("logs/ebay_upgrade/{$date}_res.txt"), $res);

        $xml = explode("\n", $res);
        if (count($xml) > 5)
            $xml = json_decode(json_encode(simplexml_load_string('<x>' . implode("\n", array_slice($xml, 4, count($xml) - 7)) . '</x>')), true);
        else $xml = [];

        $response['ack'] = 'Error';
        $response['error'] = '';

        if (isset($xml['Ack']))
        {
            $response['ack'] = $xml['Ack'];

            if (isset($xml['Errors']['LongMessage']))
                $response['error'] = $xml['Errors']['LongMessage'];
            else if (isset($xml['Errors']['ShortMessage']))
                $response['error'] = $xml['Errors']['ShortMessage'];
            else if (isset($xml['Errors'][0]['LongMessage']))
                $response['error'] = $xml['Errors'][0]['LongMessage'];
            else if (isset($xml['Errors'][0]['ShortMessage']))
                $response['error'] = $xml['Errors'][0]['ShortMessage'];
        }
        else if (isset($xml['detail']['FaultDetail']['DetailedMessage']))
        {
            $response['error'] = $xml['detail']['FaultDetail']['DetailedMessage'];
        }
        else if (isset($xml['faultstring']))
        {
            $response['error'] = $xml['faultstring'];
        }

        return $response;
    }

    public static function RenderTemplateV2($title, $desc, $brand, $condition, $partNumbers, $notes, $ranges)
    {
        if (is_string($ranges)) $ranges = explode("\n", $ranges);
        if (is_string($partNumbers)) $partNumbers = explode("\n", str_replace('/', "\n", str_replace(',', "\n", $partNumbers)));
        if (is_string($desc)) $desc = explode("\n", $desc);
        if (is_string($notes)) $notes = explode("\n", $notes);

        if ($condition == EbayUtils::$newConditionId) $condition = 'New';
        else if ($condition == EbayUtils::$remanConditionId) $condition = 'Remanufactured';

        $mpns = [];
        foreach ($partNumbers as $pn) $mpns[trim($pn)] = 1;

        return View::make('ebay_v2', compact('mpns', 'title', 'brand', 'condition', 'desc', 'notes', 'ranges'))->render();
    }

    public static function EbayCompatToRanges($compatList)
    {
        if (is_string($compatList))
            $compatList = simplexml_load_string($compatList);

        $compats = [];

        try
        {
            foreach ($compatList->children() as $comp) {
                $year = '';
                $make = '';
                $model = '';

                foreach ($comp->children() as $n) {
                    $node = $n->getName();

                    if ($node == 'NameValueList') {
                        if ($n->Name == 'Year') $year = trim($n->Value);
                        else if ($n->Name == 'Make') $make = trim($n->Value);
                        else if ($n->Name == 'Model') $model = trim($n->Value);
                    }
                }

                $compats[htmlentities("{$make} {$model}")][$year] = 1;
            }
        }
        catch (Exception $e)
        {
        }

        ksort($compats);
        $ranges = [];

        foreach ($compats as $makeModel => $compat)
        {
            if (strlen(trim($makeModel)) == 0) continue;
            ksort($compat);

            $years = array_keys($compat);
            $start = 0;
            $last = 0;

            for ($i = 0; $i < count($years); $i++)
            {
                $current = intval($years[$i]);

                // not adjacent and not first entry
                if ($start && $i && $last + 1 != $current)
                {
                    $ranges[] = "{$start}-{$last} {$makeModel}";
                    $start = 0;
                }

                // new range start
                if (!$start)
                {
                    $start = $current;

                    // last entry, add lone year
                    if ($i + 1 == count($years))
                    {
                        $ranges[] = "{$start} {$makeModel}";
                        $start = 0;
                    }

                    $last = $current;
                    continue;
                }

                // adjacent. add range if last entry
                // last entry, add range
                if ($i + 1 == count($years))
                {
                    $ranges[] = "{$start}-{$current} {$makeModel}";
                    $start = 0;
                }

                $last = $current;
            }
        }

        return $ranges;
    }
}
