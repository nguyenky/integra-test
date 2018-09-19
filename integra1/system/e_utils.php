<?php

require_once('config.php');
require_once('utils.php');
require_once('counter_utils.php');
require_once('mage_utils.php');

class EbayUtils
{
    private $userId;
    private $itemIdsNeedCalculateShpCost = [];

    public static function scrapeItemsV2($itemIds) {
        $ebayApi = new EbayAPI(APP_ID, $queue);

        $ebayApi->getItems();
        $items = $ebayApi->getResponseItems();

        $responeItems = $ebayApi->searchMulitpleItems($items);
        $sellerItems = $responeItems['seller_items'];
        $competitorItems = $responeItems['competitor_items'];
        $activeItems = $responeItems['active_items'];

        $ebayGridModel = new EbayGridModel();
        $ebayGridModel->addOrUpdateListItems($sellerItems);
        $ebayGridModel->addOrUpdateListItems($competitorItems);

        $ebayGridModel->updateSummaryItems($activeItems);
    }

    public static function ScrapeItem($itemId)
    {
        mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
        mysql_select_db(DB_SCHEMA);
        $baseItem = self::GetItem($itemId);
/*        if (empty($baseItem['comp_mpn']) && empty($baseItem['comp_brand']) && empty($baseItem['keywords']))
        {
            self::InsertException($itemId);
            return "ERROR: Unable to find the correct search keywords based on the SKU/MPN/Brand";
        }
*/
//        $rawkeywords = trim(str_replace(';', '', $baseItem['comp_mpn'] . ' ' . $baseItem['comp_brand']));
$rawkeywords = str_replace(';', '', $baseItem['comp_mpn']); // temp
        $keywords = urlencode($rawkeywords);
        $activeIds = array();
        $ourTotal = 0;
        $lowTotal = 0;
        $lowTotalSeller = '';
        $ourSold = 0;
        $topSold = 0;
        $topSoldSeller = '';
        $myItems = self::SearchSellerItems(EBAY_SELLER, $keywords);

        if (empty($myItems))
        {
            self::InsertException($itemId);
            return "ERROR: Unable to find our listing using keywords: $rawkeywords";
        }
        foreach ($myItems as $i)
        {
            if ($i['id'] == $itemId)
            {
                $ourTotal = $i['price'] + $i['shipping_cost'];
                $ourSold = $i['num_sold'];
                $lowTotal = $ourTotal;
                $lowTotalSeller = EBAY_SELLER;
                $topSold = $ourSold;
                $topSoldSeller = EBAY_SELLER;
            }
            $activeIds[] = $i['id'];
            $q = <<<EOD

    INSERT INTO eoc.ebay_grid (item_id, this_item, active, title, image_url, big_image_url, price, shipping, seller, score, rating, top, pos, num_hit, num_sold, num_compat, num_avail, category, mpn, ipn, opn, placement, brand, comp_mpn, comp_brand, comp_name, part_notes, comp_weight)

    VALUES('%s', '%s', 1, '%s', %s, %s, '%s', '%s', '%s', '%s', '%s', '%s', 0, '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %s)

    ON DUPLICATE KEY UPDATE

        active = 1,

        title = VALUES(title),

        image_url = VALUES(image_url),

        big_image_url = VALUES(big_image_url),

        price = VALUES(price),

        shipping = VALUES(shipping),

        seller = VALUES(seller),

        score = VALUES(score),

        rating = VALUES(rating),

        top = VALUES(top),

        pos = VALUES(pos),

        num_hit = GREATEST(num_hit, VALUES(num_hit)),

        num_sold = VALUES(num_sold),

        num_compat = VALUES(num_compat),

        timestamp = NOW(),

        category = VALUES(category),

        mpn = VALUES(mpn),

        ipn = VALUES(ipn),

        opn = VALUES(opn),

        placement = VALUES(placement),

        brand = VALUES(brand),

        comp_mpn = VALUES(comp_mpn),

        comp_brand = VALUES(comp_brand),

        comp_name = VALUES(comp_name),

        part_notes = VALUES(part_notes),

        comp_weight = VALUES(comp_weight)

EOD;
            $qw = sprintf($q,
                cleanup($itemId),
                cleanup($i['id']),
                cleanup($i['title']),
                empty($i['picture_small']) ? 'NULL' : "'" . cleanup($i['picture_small']) . "'",
                empty($i['picture_big']) ? 'NULL' : "'" . cleanup($i['picture_big']) . "'",
                cleanup($i['price']),
                cleanup($i['shipping_cost']),
                cleanup($i['seller_id']),
                cleanup($i['seller_score']),
                cleanup($i['seller_rating']),
                cleanup($i['seller_top']),
                ($i['num_hit'] == '-1') ? 0 : $i['num_hit'],
                cleanup($i['num_sold']),
                cleanup($i['num_compat']),
                cleanup($i['num_avail']),
                cleanup($i['category']),
                cleanup($i['mpn']),
                cleanup($i['ipn']),
                cleanup($i['opn']),
                cleanup($i['placement']),
                cleanup($i['brand']),
                cleanup($i['comp_mpn']),
                cleanup($i['comp_brand']),
                cleanup($i['comp_name']),
                cleanup($i['part_notes']),
                empty($i['comp_weight']) ? 'NULL' : "'" . cleanup($i['comp_weight']) . "'");
            mysql_query($qw);
            /*MageUtils::CreateProduct(

                'qeautoparts',

                $i['title'],

                $i['sku'],

                $i['num_avail'],

                $i['price'],

                $i['comp_mpn'],

                $i['comp_brand'],

                $i['picture_big'],

                $i['comp_name'],

                $i['comp_name'],

                MageUtils::ConvertCategoryFromEbay($i['category']),

                $i['part_notes'],

                $i['comp_weight'],

                $i['id'],

                $i['fitment']);*/
        }
        $compItems = self::SearchCompetitorItems(EBAY_SELLER, $keywords);
/*        if (!empty($baseItem['keywords']))
        {
            $compItems2 = self::SearchSellerItems(EBAY_SELLER, urlencode($baseItem['keywords']));
            if (!empty($compItems2))
            {
                foreach ($compItems2 as $c2)
                {
                    $found = false;
                    if (!empty($compItems))
                    {
                        foreach ($compItems as $c1)
                        {
                            if ($c1['id'] == $c2['id'])
                            {
                                $found = true;
                                break;
                            }
                        }
                    }
                    if (!$found)
                        $compItems[] = $c2;
                }
            }
        }*/
        if (!empty($compItems))
        {
            $ctr = 1;
            foreach ($compItems as $i)
            {
                $total = $i['price'] + $i['shipping_cost'];
                if ($total < $lowTotal)
                {
                    $lowTotal = $total;
                    $lowTotalSeller = $i['seller_id'];
                }
                if ($topSold < $i['num_sold'])
                {
                    $topSold = $i['num_sold'];
                    $topSoldSeller = $i['seller_id'];
                }
                $activeIds[] = $i['id'];
                $q = <<<EOD

    INSERT INTO eoc.ebay_grid (item_id, this_item, active, title, image_url, big_image_url, price, shipping, seller, score, rating, top, pos, num_hit, num_sold, num_compat, num_avail, category, mpn, ipn, opn, placement, brand)

    VALUES('%s', '%s', 1, '%s', %s, %s, '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s')

    ON DUPLICATE KEY UPDATE

        active = 1,

        title = VALUES(title),

        image_url = VALUES(image_url),

        big_image_url = VALUES(big_image_url),

        price = VALUES(price),

        shipping = VALUES(shipping),

        seller = VALUES(seller),

        score = VALUES(score),

        rating = VALUES(rating),

        top = VALUES(top),

        pos = VALUES(pos),

        num_hit = GREATEST(num_hit, VALUES(num_hit)),

        num_sold = VALUES(num_sold),

        num_compat = VALUES(num_compat),

        num_avail = VALUES(num_avail),

        timestamp = NOW(),

        category = VALUES(category),

        category = VALUES(category),

        mpn = VALUES(mpn),

        ipn = VALUES(ipn),

        opn = VALUES(opn),

        placement = VALUES(placement),

        brand = VALUES(brand)

EOD;
                $qw = sprintf($q,
                    cleanup($itemId),
                    cleanup($i['id']),
                    cleanup($i['title']),
                    empty($i['picture_small']) ? 'NULL' : "'" . cleanup($i['picture_small']) . "'",
                    empty($i['picture_big']) ? 'NULL' : "'" . cleanup($i['picture_big']) . "'",
                    cleanup($i['price']),
                    cleanup($i['shipping_cost']),
                    cleanup($i['seller_id']),
                    cleanup($i['seller_score']),
                    cleanup($i['seller_rating']),
                    cleanup($i['seller_top']),
                    $ctr++,
                    ($i['num_hit'] == '-1') ? 0 : $i['num_hit'],
                    cleanup($i['num_sold']),
                    cleanup($i['num_compat']),
                    cleanup($i['num_avail']),
                    cleanup($i['category']),
                    cleanup($i['mpn']),
                    cleanup($i['ipn']),
                    cleanup($i['opn']),
                    cleanup($i['placement']),
                    cleanup($i['brand']));
                //echo $qw . "\n";
                mysql_query($qw);
            }
        }
        if (!empty($activeIds))
        {
            mysql_query(sprintf("UPDATE eoc.ebay_grid SET active = 0 WHERE item_id = '${itemId}' AND this_item NOT IN ('%s')",
                implode("','", $activeIds)));
            $priceDiff = $ourTotal - $lowTotal;
            if ($lowTotal > 0)
                $priceDiffPct = $priceDiff * 100 / $lowTotal;
            else

                $priceDiffPct = 0;
            $soldDiff = $topSold - $ourSold;
            if ($ourSold > 0)
                $soldDiffPct = $soldDiff * 100 / $ourSold;
            else

                $soldDiffPct = $soldDiff * 100;
            if ($topSold == 0)
                $topSoldSeller = '';
            $q = <<<EOD

    INSERT INTO eoc.ebay_grid_summary (item_id, mpn, brand, our_total, low_total, low_total_seller, price_diff, price_diff_pct, our_sold, top_sold, top_sold_seller, sold_diff, sold_diff_pct, active)

    VALUES('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 1)

    ON DUPLICATE KEY UPDATE

        mpn = VALUES(mpn),

        brand = VALUES(brand),

        our_total = VALUES(our_total),

        low_total = VALUES(low_total),

        low_total_seller = VALUES(low_total_seller),

        price_diff = VALUES(price_diff),

        price_diff_pct = VALUES(price_diff_pct),

        our_sold = VALUES(our_sold),

        top_sold = VALUES(top_sold),

        top_sold_seller = VALUES(top_sold_seller),

        sold_diff = VALUES(sold_diff),

        sold_diff_pct = VALUES(sold_diff_pct),

        timestamp = NOW(),

        active = 1

EOD;
            $qw = sprintf($q,
                cleanup($itemId),
                cleanup($baseItem['comp_mpn']),
                cleanup($baseItem['comp_brand']),
                cleanup($ourTotal),
                cleanup($lowTotal),
                cleanup($lowTotalSeller),
                cleanup($priceDiff),
                cleanup($priceDiffPct),
                cleanup($ourSold),
                cleanup($topSold),
                cleanup($topSoldSeller),
                cleanup($soldDiff),
                cleanup($soldDiffPct));
            //echo $qw;
            mysql_query($qw);
        }
        mysql_query("UPDATE eoc.ebay_grid_summary SET active = 0, timestamp = timestamp WHERE active = 1 AND item_id IN (SELECT item_id FROM eoc.ebay_listings WHERE active = 0)");
        mysql_query("UPDATE eoc.ebay_grid_summary SET active = 1, timestamp = timestamp WHERE active = 0 AND item_id IN (SELECT item_id FROM eoc.ebay_listings WHERE active = 1)");
    //    if (empty($baseItem['keywords']) || $baseItem['keywords'] == $rawkeywords)
            return "These listings are up to date based on keywords: $rawkeywords";
//        else

  //          return "These listings are up to date based on keywords: $rawkeywords OR " . $baseItem['keywords'];
    }

    public static function InsertException($itemId)
    {
        $q = <<<EOD

    INSERT INTO ebay_grid_summary (item_id, mpn, brand, our_total, low_total, low_total_seller, price_diff, price_diff_pct, our_sold, top_sold, top_sold_seller, sold_diff, sold_diff_pct)

    VALUES('%s', '', '', 0, 0, '', 0, 0, 0, 0, '', 0, 0)

    ON DUPLICATE KEY UPDATE

        mpn = VALUES(mpn),

        brand = VALUES(brand),

        our_total = VALUES(our_total),

        low_total = VALUES(low_total),

        low_total_seller = VALUES(low_total_seller),

        price_diff = VALUES(price_diff),

        price_diff_pct = VALUES(price_diff_pct),

        our_sold = VALUES(our_sold),

        top_sold = VALUES(top_sold),

        top_sold_seller = VALUES(top_sold_seller),

        sold_diff = VALUES(sold_diff),

        sold_diff_pct = VALUES(sold_diff_pct),

        timestamp = NOW(),

        active = 1

EOD;
        $qw = sprintf($q,
            cleanup($itemId));
        mysql_query($qw);
    }

    public static function EbayCompatToRanges($compatList)
    {
        if (is_string($compatList))
            $compatList = simplexml_load_string($compatList);

        $compats = [];

        try
        {
            if ($compatList) {
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

    public static function ListItem($title, $sku, $qty, $price, $shipping3d, $shipping2d, $mpn, $ipn, $opn, $placement, $brand, $surfaceFinish, $warranty, $picture, $description, $notes, $category, $compatibility, $conditionID)
    {
        $callName = 'AddFixedPriceItem';
        $version = '849';
        $url = EBAY_HOST . "wsapi?callname=${callName}&siteid=" . SITE_ID . "&appid=" . APP_ID . "&version=${version}&routing=default";
        $ebayToken = EBAY_TOKEN;

        $paymentProfile = EBAY_PAYMENT_PROFILE_ID;
        $returnProfile = EBAY_RETURN_PROFILE_ID;
        $shippingProfile = EBAY_SHIPPING_PROFILE_ID;

        if (is_array($picture) || stripos($picture, ',') !== false)
            $pictures = explode(',', $picture);
        else $pictures = [ $picture ];

        if (!empty($shipping3d))
            $shipping3dNode = <<< EOD

<ShippingServiceOptions>

    <ShippingServicePriority>2</ShippingServicePriority>

    <ShippingService>UPS3rdDay</ShippingService>

    <ShippingServiceCost currencyID="USD">${shipping3d}</ShippingServiceCost>

    <ShippingServiceAdditionalCost currencyID="USD">0.00</ShippingServiceAdditionalCost>

</ShippingServiceOptions>

EOD;
        if (!empty($shipping2d))
            $shipping2dNode = <<< EOD

<ShippingServiceOptions>

    <ShippingServicePriority>3</ShippingServicePriority>

    <ShippingService>UPS2ndDay</ShippingService>

    <ShippingServiceCost currencyID="USD">${shipping2d}</ShippingServiceCost>

    <ShippingServiceAdditionalCost currencyID="USD">0.00</ShippingServiceAdditionalCost>

</ShippingServiceOptions>

EOD;
        $partNumbers = [];

        if (!empty($mpn)) {
            $mpnNode = '<NameValueList><Name>Manufacturer Part Number</Name><Value><![CDATA[' . trim($mpn) . ']]></Value></NameValueList>';
            $pns = explode('/', $mpn);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }
        if (!empty($ipn)) {
            $ipnNode = '<NameValueList><Name>Interchange Part Number</Name><Value><![CDATA[' . trim($ipn) . ']]></Value></NameValueList>';
            $pns = explode('/', $ipn);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }
        if (!empty($opn)) {
            $opnNode = '<NameValueList><Name>Other Part Number</Name><Value><![CDATA[' . trim($opn) . ']]></Value></NameValueList>';
            $pns = explode('/', $opn);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }
	if (!empty($brand)) {
            $brandNode = '<NameValueList><Name>Part Brand</Name><Value><![CDATA[' . trim($brand) . ']]></Value></NameValueList>';
            $brandNode .= '<NameValueList><Name>Brand</Name><Value><![CDATA[' . trim($brand) . ']]></Value></NameValueList>';
        }
        if (!empty($surfaceFinish))
            $surfaceFinishNode = '<NameValueList><Name>Surface Finish</Name><Value><![CDATA[' . trim($surfaceFinish) . ']]></Value></NameValueList>';

        $warrantyNode = '<NameValueList><Name>Warranty</Name><Value><![CDATA[1 year]]></Value></NameValueList>';

        $placementNode = '<NameValueList><Name>Placement on Vehicle</Name>';
        if (!empty($placement))
        {
            $placements = explode(',', $placement);
            foreach ($placements as $p)
                $placementNode .= '<Value><![CDATA[' . trim($p) . ']]></Value>';
        }
        $placementNode .= '</NameValueList>';

        $pictureNode = '';

        foreach ($pictures as $p)
        {
            $pictureNode .= '<ExternalPictureURL><![CDATA[' . trim($p) . ']]></ExternalPictureURL>';
        }

        $ranges = implode("\n", self::EbayCompatToRanges($compatibility));
        $ch = curl_init('http://integra2.eocenterprise.com/api/ebay/raw_preview_v2');
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'title' => $title,
            'desc' => $description,
            'brand' => trim($brand),
            'condition' => $conditionID,
            'partNumbers' => implode("\n", $partNumbers),
            'notes' => $notes,
            'ranges' => $ranges
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $descNode = curl_exec($ch);
        curl_close($ch);

        $data = <<< EOD

<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">

    <s:Header>

        <h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>${ebayToken}</eBayAuthToken></h:RequesterCredentials>

    </s:Header>

    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">

    <${callName}Request xmlns="urn:ebay:apis:eBLBaseComponents">

        <Version>${version}</Version>

        <Item>
            <PrivateListing>true</PrivateListing>
            <Title><![CDATA[${title}]]></Title>

            <Description><![CDATA[${descNode}]]></Description>

            <PrimaryCategory>

                <CategoryID>${category}</CategoryID>

            </PrimaryCategory>

            <StartPrice>${price}</StartPrice>

            <InventoryTrackingMethod>SKU</InventoryTrackingMethod>

            <SKU>${sku}</SKU>

            <CategoryMappingAllowed>true</CategoryMappingAllowed>

            <ConditionID>${conditionID}</ConditionID>

            <Country>US</Country>

            <Currency>USD</Currency>

            <DispatchTimeMax>1</DispatchTimeMax>

            <ListingDuration>GTC</ListingDuration>

            <ListingType>FixedPriceItem</ListingType>

            <AutoPay>true</AutoPay>

            <PaymentMethods>PayPal</PaymentMethods>

            <PayPalEmailAddress>sales@qeautoparts.com</PayPalEmailAddress>

            <PictureDetails>

                ${pictureNode}

            </PictureDetails>

            <Location>Miami, FL, United States</Location>

            <Quantity>${qty}</Quantity>

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

                ${shipping3dNode}

                ${shipping2dNode}

                <ShippingServiceOptions>

                    <ShippingServicePriority>4</ShippingServicePriority>

                    <ShippingService>Pickup</ShippingService>

                    <ShippingServiceCost currencyID="USD">0.00</ShippingServiceCost>

                    <ShippingServiceAdditionalCost currencyID="USD">0.00</ShippingServiceAdditionalCost>

                </ShippingServiceOptions>

            </ShippingDetails>

            <ProductListingDetails>
                <UPC><![CDATA[N/A]]></UPC>
            </ProductListingDetails>

            <ItemSpecifics>
                ${mpnNode}

                ${ipnNode}

                ${opnNode}

                ${placementNode}

                ${brandNode}

                ${surfaceFinishNode}

                ${warrantyNode}

            </ItemSpecifics>

            ${compatibility}

            <Site>eBayMotors</Site>

        </Item>

    </${callName}Request>

    </s:Body>

</s:Envelope>

EOD;
        $headers = array
        (
            'Content-Type: text/xml',
            'SOAPAction: ""'
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        $date = date_create("now", new DateTimeZone('America/New_York'));
        file_put_contents(LOGS_DIR . "ebay_list/" . date_format($date, 'Y-m-d_H-i-s') . "_req.txt", $data);
        file_put_contents(LOGS_DIR . "ebay_list/" . date_format($date, 'Y-m-d_H-i-s') . "_res.txt", $res);
        $response['error'] = '';
        $response['id'] = '';
        $response['success'] = false;

        $xml = XMLtoArray($res);
        $response['id'] = asearch($xml, 'ITEMID');

        if (stripos($res, "success") !== false)
        {
            $response['success'] = true;
            return $response;
        }

        $response['error'] = asearch($xml, 'LONGMESSAGE');
        if (empty($response['error']))
            $response['error'] = asearch($xml, 'DETAILEDMESSAGE');
        if (empty($response['error']))
            $response['error'] = asearch($xml, 'FAULTSTRING');
        return $response;
    }

    public static function ReviseNode($itemId, $node)
    {
        file_put_contents(LOGS_DIR . "revise_node.txt", "====== START REVISE NODE ". $node ." ======== \n", FILE_APPEND);
        if (empty($node)) return;

        $callName = 'ReviseFixedPriceItem';
        $version = '845';
        $url = EBAY_HOST . "wsapi?callname=${callName}&siteid=" . SITE_ID . "&appid=" . APP_ID . "&version=${version}&routing=default";
        $ebayToken = EBAY_TOKEN;

        $data = <<< EOD
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Header>
        <h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>${ebayToken}</eBayAuthToken></h:RequesterCredentials>
    </s:Header>
    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <${callName}Request xmlns="urn:ebay:apis:eBLBaseComponents">
        <Version>${version}</Version>
        <Item>
            <ItemID>${itemId}</ItemID>
            ${node}
        </Item>
    </${callName}Request>
    </s:Body>
</s:Envelope>
EOD;
        $headers = array
        (
            'Content-Type: text/xml',
            'SOAPAction: ""'
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        $date = date_create("now", new DateTimeZone('America/New_York'));
        file_put_contents(LOGS_DIR . "ebay_match/" . date_format($date, 'Y-m-d_H-i-s') . "_req.txt", $data);
        file_put_contents(LOGS_DIR . "ebay_match/" . date_format($date, 'Y-m-d_H-i-s') . "_res.txt", $res);

        if (stripos($res, "success") !== false) return 'OK';
        $res = XMLtoArray($res);
        $error = asearch($res, 'LONGMESSAGE');
        if (empty($error))
            $error = asearch($res, 'DETAILEDMESSAGE');
        if (empty($response['error']))
            $error = asearch($res, 'FAULTSTRING');
        return $error;
    }

    public static function ReviseItem($itemId, $title, $price, $pictureUrl, $compatibility, $category, $mpn, $ipn, $opn, $placement, $brand, $part_brand, $surfaceFinish = null, $warranty = null, $others = null, $description, $notes, $oldItem)
    {
        $callName = 'ReviseFixedPriceItem';
        $version = '845';
        $url = EBAY_HOST . "wsapi?callname=${callName}&siteid=" . SITE_ID . "&appid=" . APP_ID . "&version=${version}&routing=default";
        $ebayToken = EBAY_TOKEN;
        $titleNode = '';
        $categoryNode = '';
        $priceNode = '';
        $pictureNode = '';
        $compatNode = '';
        $surfaceFinishNode = '';
        $partNumbers = [];

        if (!empty($title))
            $titleNode = '<Title><![CDATA[' . trim($title) . ']]></Title>';
        if (!empty($category))
            $categoryNode = '<PrimaryCategory><CategoryID><![CDATA[' . trim($category) . ']]></CategoryID></PrimaryCategory>';
        if (!empty($price))
            $priceNode = '<StartPrice>' . trim($price) . '</StartPrice>';
        if (!empty($pictureUrl))
            $pictureNode = '<PictureDetails>' . trim($pictureUrl) . '</PictureDetails>';
        if (!empty($mpn)) {
            $mpnNode = '<NameValueList><Name>Manufacturer Part Number</Name><Value><![CDATA[' . trim($mpn) . ']]></Value></NameValueList>';
            $pns = explode('/', $mpn);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }
        if (!empty($ipn)) {
            $ipnNode = '<NameValueList><Name>Interchange Part Number</Name><Value><![CDATA[' . trim($ipn) . ']]></Value></NameValueList>';
            $pns = explode('/', $ipn);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }
        if (!empty($opn)) {
            $opnNode = '<NameValueList><Name>Other Part Number</Name><Value><![CDATA[' . trim($opn) . ']]></Value></NameValueList>';
            $pns = explode('/', $opn);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }

        // use old values if no mpn/ipn/opn provided or changed
        $oldDescHtml = str_replace(' class="bold"', '', $oldItem['description']);
        $oldDescHtml = str_replace(' center vcenter', '', $oldDescHtml);
        preg_match("/label\">Part Number<\\/td>\\s*<td colspan=\"3\">(?<val>.+?)<\\/td/is", $oldDescHtml, $matches);
        $oldPartNumber = $matches['val'];

        if (empty($partNumbers) && !empty($oldPartNumber))
        {
            $pns = explode('/', $oldPartNumber);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }

        preg_match("/label\">Description<\\/td>\\s*<td colspan=\"3\">(?<val>.+?)<\\/td/is", $oldDescHtml, $matches);
        $oldDescription = str_replace('<br>', "\n", $matches['val']);

        preg_match("/label\">Notes<\\/td>\\s*<td colspan=\"3\">(?<val>.+?)<\\/td/is", $oldDescHtml, $matches);
        $oldNotes = str_replace('<br>', "\n", $matches['val']);

        if (stripos($oldDescHtml, 'Reman') !== false
            || stripos($oldDescHtml, 'Refurb') !== false)
        $oldCondition = 'Remanufactured';
        else $oldCondition = 'New';

        $newDescription = empty($description) ? $oldDescription : $description;
        if (empty($newDescription))
            return 'Please upgrade the listing to template v2 first.';

        $ranges = implode("\n", self::EbayCompatToRanges($compatibility));
        $ch = curl_init('http://integra2.eocenterprise.com/api/ebay/raw_preview_v2');
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'title' => $title,
            'desc' => $newDescription,
            'brand' => trim($brand),
            'condition' => $oldCondition,
            'partNumbers' => implode("\n", $partNumbers),
            'notes' => empty($notes) ? $oldNotes : $notes,
            'ranges' => $ranges
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $descNode = curl_exec($ch);
        curl_close($ch);

        if (!empty($compatibility))
            $compatNode = trim(str_replace('</ItemCompatibilityList>', '<ReplaceAll>true</ReplaceAll></ItemCompatibilityList>', $compatibility));

        if (!empty($brand))
            $brandNode = '<NameValueList><Name>Brand</Name><Value><![CDATA[' . trim($brand) . ']]></Value></NameValueList>';
        if (!empty($part_brand))
            $partBrandNode = '<NameValueList><Name>Part Brand</Name><Value><![CDATA[' . trim($part_brand) . ']]></Value></NameValueList>';
        if (!empty($surfaceFinish))
            $surfaceFinishNode = '<NameValueList><Name>Surface Finish</Name><Value><![CDATA[' . trim($surfaceFinish) . ']]></Value></NameValueList>';

        $warrantyNode = '<NameValueList><Name>Warranty</Name><Value><![CDATA[1 year]]></Value></NameValueList>';

        $placementNode = '<NameValueList><Name>Placement on Vehicle</Name>';
        if (!empty($placement))
        {
            $placements = explode(',', $placement);
            foreach ($placements as $p)
                $placementNode .= '<Value><![CDATA[' . trim($p) . ']]></Value>';
        }
        $placementNode .= '</NameValueList>';
        $otherNode = '';
        if (!empty($others))
        {
            foreach ($others as $o)
            {
                $otherNode .= '<NameValueList><Name>' . trim($o[0]) . '</Name><Value><![CDATA['
                    . trim($o[1]) . ']]></Value></NameValueList>';
            }
        }
        $data = <<< EOD
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Header>
        <h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>${ebayToken}</eBayAuthToken></h:RequesterCredentials>
    </s:Header>
    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <${callName}Request xmlns="urn:ebay:apis:eBLBaseComponents">
        <Version>${version}</Version>
        <DeletedField>Item.ProductListingDetails</DeletedField>
        <Item>
            <ItemID>${itemId}</ItemID>
            ${categoryNode}
            <ItemSpecifics>${mpnNode}${ipnNode}${opnNode}${placementNode}${brandNode}${partBrandNode}${otherNode}${surfaceFinishNode}${warrantyNode}</ItemSpecifics>${titleNode}${priceNode}${pictureNode}${compatNode}
            <Description><![CDATA[${descNode}]]></Description>
            <ProductListingDetails>
                <UPC><![CDATA[N/A]]></UPC>
            </ProductListingDetails>
        </Item>
    </${callName}Request>
    </s:Body>
</s:Envelope>
EOD;
        $headers = array
        (
            'Content-Type: text/xml',
            'SOAPAction: ""'
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        $date = date_create("now", new DateTimeZone('America/New_York'));
        file_put_contents(LOGS_DIR . "ebay_match/" . date_format($date, 'Y-m-d_H-i-s') . "_req.txt", $data);
        file_put_contents(LOGS_DIR . "ebay_match/" . date_format($date, 'Y-m-d_H-i-s') . "_res.txt", $res);
        if (stripos($res, "success") !== false) return 'OK';
        $res = XMLtoArray($res);
        $error = asearch($res, 'LONGMESSAGE');
        if (empty($error))
            $error = asearch($res, 'DETAILEDMESSAGE');
        if (empty($response['error']))
            $error = asearch($res, 'FAULTSTRING');
        return $error;
    }

    public static function ReviseBody($itemId)
    {
        $callName = 'ReviseFixedPriceItem';
        $version = '845';
        $url = EBAY_HOST . "wsapi?callname=${callName}&siteid=" . SITE_ID . "&appid=" . APP_ID . "&version=${version}&routing=default";
        $ebayToken = EBAY_TOKEN;

        $oldItem = self::GetItem($itemId);
        $partNumbers = [];
        $mpn = $oldItem['mpn'];
        $ipn = $oldItem['ipn'];
        $opn = $oldItem['opn'];

        if (!empty($mpn)) {
            $pns = explode('/', $mpn);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }
        if (!empty($ipn)) {
            $pns = explode('/', $ipn);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }
        if (!empty($opn)) {
            $pns = explode('/', $opn);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }

        $oldDescHtml = str_replace(' class="bold"', '', $oldItem['description']);
        $oldDescHtml = str_replace(' center vcenter', '', $oldDescHtml);
        preg_match("/label\">Part Number<\\/td>\\s*<td colspan=\"3\">(?<val>.+?)<\\/td/is", $oldDescHtml, $matches);
        $oldPartNumber = $matches['val'];

        if (empty($partNumbers) && !empty($oldPartNumber))
        {
            $pns = explode('/', $oldPartNumber);
            foreach ($pns as $pn) $partNumbers[] = trim($pn);
        }

        preg_match("/label\">Description<\\/td>\\s*<td colspan=\"3\">(?<val>.+?)<\\/td/is", $oldDescHtml, $matches);
        $oldDescription = str_replace('<br>', "\n", $matches['val']);

        preg_match("/label\">Notes<\\/td>\\s*<td colspan=\"3\">(?<val>.+?)<\\/td/is", $oldDescHtml, $matches);
        $oldNotes = str_replace('<br>', "\n", $matches['val']);

        if (stripos($oldDescHtml, 'Reman') !== false
            || stripos($oldDescHtml, 'Refurb') !== false)
            $oldCondition = 'Remanufactured';
        else $oldCondition = 'New';

        $ranges = implode("\n", self::EbayCompatToRanges($oldItem['compatibility']));
        $ch = curl_init('http://integra2.eocenterprise.com/api/ebay/raw_preview_v2');
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'title' => trim($oldItem['title']),
            'desc' => $oldDescription,
            'brand' => trim($oldItem['brand']),
            'condition' => $oldCondition,
            'partNumbers' => implode("\n", $partNumbers),
            'notes' => $oldNotes,
            'ranges' => $ranges
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $descNode = curl_exec($ch);
        curl_close($ch);

        $data = <<< EOD
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
    <s:Header>
        <h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>${ebayToken}</eBayAuthToken></h:RequesterCredentials>
    </s:Header>
    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <${callName}Request xmlns="urn:ebay:apis:eBLBaseComponents">
        <Version>${version}</Version>
        <DeletedField>Item.ProductListingDetails</DeletedField>
        <Item>
            <ItemID>${itemId}</ItemID>
            <Description><![CDATA[${descNode}]]></Description>
            <ProductListingDetails>
                <UPC><![CDATA[N/A]]></UPC>
            </ProductListingDetails>
        </Item>
    </${callName}Request>
    </s:Body>
</s:Envelope>
EOD;
        $headers = array
        (
            'Content-Type: text/xml',
            'SOAPAction: ""'
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        file_put_contents(LOGS_DIR . "ebay_match/{$itemId}_revise_body_req.txt", $data);
        file_put_contents(LOGS_DIR . "ebay_match/{$itemId}_revise_body_res.txt", $res);
        if (stripos($res, "success") !== false) return 'OK';
        $res = XMLtoArray($res);
        $error = asearch($res, 'LONGMESSAGE');
        if (empty($error))
            $error = asearch($res, 'DETAILEDMESSAGE');
        if (empty($response['error']))
            $error = asearch($res, 'FAULTSTRING');
        return $error;
    }

    public static function CopyCompat($ourItemId, $sourceItemId)
    {
        $source = self::GetItem($sourceItemId);
        if (empty($source))
            return 'Unable to load source listing.';
        $callName = 'ReviseFixedPriceItem';
        $version = '845';
        $url = EBAY_HOST . "wsapi?callname=${callName}&siteid=" . SITE_ID . "&appid=" . APP_ID . "&version=${version}&routing=default";
        $ebayToken = EBAY_TOKEN;
        $compatNode = trim(str_replace('</ItemCompatibilityList>', '<ReplaceAll>true</ReplaceAll></ItemCompatibilityList>', $source['compatibility']));
        $data = <<< EOD

<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">

    <s:Header>

        <h:RequesterCredentials xmlns:h="urn:ebay:apis:eBLBaseComponents" xmlns="urn:ebay:apis:eBLBaseComponents" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><eBayAuthToken>${ebayToken}</eBayAuthToken></h:RequesterCredentials>

    </s:Header>

    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">

    <${callName}Request xmlns="urn:ebay:apis:eBLBaseComponents">

        <Version>${version}</Version>

        <DeletedField>Item.ProductListingDetails</DeletedField>

        <Item>

            <ItemID>${ourItemId}</ItemID>
            ${compatNode}

        </Item>

    </${callName}Request>

    </s:Body>

</s:Envelope>

EOD;
        $headers = array
        (
            'Content-Type: text/xml',
            'SOAPAction: ""'
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        //$date = date_create("now", new DateTimeZone('America/New_York'));
        //file_put_contents(LOGS_DIR . "ebay_auto_compat/" . date_format($date, 'Y-m-d_H-i-s') . ".txt", $res);
        if (stripos($res, "success") !== false)
        {
            $i = self::GetItem($ourItemId);
            if (!empty($i))
            {
                /*MageUtils::CreateProduct(

                    'qeautoparts',

                    $i['title'],

                    $i['sku'],

                    $i['num_avail'],

                    $i['price'],

                    $i['comp_mpn'],

                    $i['comp_brand'],

                    $i['picture_big'],

                    $i['comp_name'],

                    $i['comp_name'],

                    MageUtils::ConvertCategoryFromEbay($i['category']),

                    $i['part_notes'],

                    $i['comp_weight'],

                    $i['id'],

                    $i['fitment']);*/
            }
            return 'OK';
        }
        $res = XMLtoArray($res);
        $error = asearch($res, 'LONGMESSAGE');
        if (empty($error))
            $error = asearch($res, 'DETAILEDMESSAGE');
        return $error;
    }

    public static function SearchSellerItems($seller, $keywords)
    {
        $res = file_get_contents("http://svcs.ebay.com/services/search/FindingService/v1?OPERATION-NAME=findItemsByKeywords&SERVICE-VERSION=1.0.0&SECURITY-APPNAME=" . APP_ID . "&RESPONSE-DATA-FORMAT=XML&REST-PAYLOAD&outputSelector(0)=SellerInfo&itemFilter(0).name=Seller&itemFilter(0).value(0)=${seller}&keywords=${keywords}");
        /*  start insert counter */
        CountersUtils::insertCounter('findItemsByKeywords','Ebay Monitor',APP_ID);
        /*  end insert counter */
        $xml = simplexml_load_string($res);
        if (!empty($xml))
            foreach ($xml->searchResult->item as $i)
                $items[] = self::GetItem($i->itemId);
        return $items;
    }


    public static function SearchCompetitorItems($seller, $keywords)
    {
        $res = file_get_contents("http://svcs.ebay.com/services/search/FindingService/v1?OPERATION-NAME=findItemsByKeywords&SERVICE-VERSION=1.0.0&SECURITY-APPNAME=" . APP_ID . "&RESPONSE-DATA-FORMAT=XML&REST-PAYLOAD&outputSelector(0)=SellerInfo&itemFilter(0).name=ExcludeSeller&itemFilter(0).value(0)=${seller}&itemFilter(1).name=Condition&itemFilter(1).value=New&keywords=${keywords}");
        /*  start insert counter */
        CountersUtils::insertCounter('findItemsByKeywords','Ebay Monitor',APP_ID);
        /*  end insert counter */
        $xml = simplexml_load_string($res);
        if (!empty($xml))
            foreach ($xml->searchResult->item as $i)
                $items[] = self::GetItem($i->itemId);
        return $items;
    }

    public static function SaveFitment($itemId, $fitment)
    {
        mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
        mysql_select_db(DB_SCHEMA);
        mysql_query(sprintf("DELETE FROM ebay_fitment WHERE item_id = '%s'", cleanup($itemId)));
        if (empty($fitment))
            return;
        foreach ($fitment as $fit)
        {
            $q = <<<EOD

            INSERT INTO ebay_fitment (item_id, make, model, year, trim, engine, notes)

            VALUES('%s', '%s', '%s', '%s', '%s', '%s', '%s')

EOD;
            mysql_query(sprintf($q,
                cleanup($itemId),
                cleanup($fit['make']),
                cleanup($fit['model']),
                cleanup($fit['year']),
                cleanup($fit['trim']),
                cleanup($fit['engine']),
                cleanup($fit['notes'])));
        }
    }

    public static function GetItem($itemId)
    {
        $res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${itemId}&IncludeSelector=Details,Compatibility,ShippingCosts,ItemSpecifics,Description");
        /*  start insert counter */
        CountersUtils::insertCounter('GetSingleItem','Ebay Monitor',APP_ID);
        /*  end insert counter */
        $xml = simplexml_load_string($res);
        if ($xml->Ack != 'Success' && $xml->Ack != 'Warning')
            return false;
        $item['id'] = (string)$xml->Item->ItemID;

        $item['title'] = (string)$xml->Item->Title;
        $item['description'] = (string)$xml->Item->Description;
        $item['category'] = (string)$xml->Item->PrimaryCategoryID;
        $allCat = (string)$xml->Item->PrimaryCategoryName;
        $cats = explode(':', $allCat);
        $catName = $allCat;
        if (count($cats) > 1)
            $catName = $cats[count($cats) - 1];
        $item['category_name'] = $catName;
        $item['num_avail'] = (string)$xml->Item->Quantity;
        $item['price'] = (string)$xml->Item->CurrentPrice;
        $item['num_sold'] = (string)$xml->Item->QuantitySold;
        $hits = (string)$xml->Item->HitCount;
        settype($hits, 'integer');
        $item['num_hit'] = $hits;
        $item['condition'] = (string)$xml->Item->ConditionDisplayName;
        $item['sku'] = (string)$xml->Item->SKU;
        $item['seller_id'] = (string)$xml->Item->Seller->UserID;
        $item['seller_score'] = (string)$xml->Item->Seller->FeedbackScore;
        $item['seller_rating'] = (string)$xml->Item->Seller->PositiveFeedbackPercent;
        $item['seller_top'] = ((string)$xml->Item->Seller->TopRatedSeller == 'true' ? 1 : 0);
        $item['picture_small'] = (string)$xml->Item->GalleryURL;
        $item['picture_big'] = (string)$xml->Item->PictureURL;
        $item['shipping_cost'] = (string)$xml->Item->ShippingCostSummary->ShippingServiceCost;
        $item['shipping_type'] = (string)$xml->Item->ShippingCostSummary->ShippingType;
        if ($item['shipping_type'] == 'Calculated')
        {
            $res = file_get_contents("http://www.ebay.com/itm/getrates?item=${itemId}&quantity=1&country=1&zipCode=77057&co=0&cb=j");
            /*  start insert counter */
            CountersUtils::insertCounter('getrates','Ebay Monitor',APP_ID);
            /*  end insert counter */
            unset($match);
            preg_match('/US \$(?P<shipping>[^<]+)/i', $res, $match);
            if (isset($match) && array_key_exists('shipping', $match))
                $item['shipping_cost'] = $match['shipping'];
        }
        $item['num_compat'] = (string)$xml->Item->ItemCompatibilityCount;
        if (empty($item['num_compat']))
            $item['num_compat'] = 0;
        $item['compatibility'] = str_replace('<NameValueList/>', '', (string)$xml->Item->ItemCompatibilityList->asXML());
        if (!empty($item['compatibility']))
        {
            $xml2 = simplexml_load_string($item['compatibility']);
            if (!empty($xml2->Compatibility))
            {
                foreach ($xml2->Compatibility as $c)
                {
                    $fit['make'] = '';
                    $fit['model'] = '';
                    $fit['year'] = '';
                    $fit['trim'] = '';
                    $fit['engine'] = '';
                    $fit['notes'] = '';
                    foreach ($c->NameValueList as $n)
                    {
                        if ($n->Name == 'Year') $fit['year'] = trim($n->Value);
                        else if ($n->Name == 'Make') $fit['make'] = trim($n->Value);
                        else if ($n->Name == 'Model') $fit['model'] = trim($n->Value);
                        else if ($n->Name == 'Trim') $fit['trim'] = trim($n->Value);
                        else if ($n->Name == 'Engine') $fit['engine'] = trim($n->Value);
                    }
                    $fit['notes'] = trim($c->CompatibilityNotes);
                    $item['fitment'][] = $fit;
                }
                self::SaveFitment($item['id'], $item['fitment']);
            }
        }
        $item['mpn'] = '';
        $item['ipn'] = '';
        $item['opn'] = '';
        $item['placement'] = '';
        $item['brand'] = '';
        $item['comp_mpn'] = '';
        $item['comp_brand'] = '';
        $item['comp_name'] = '';
        $item['part_notes'] = '';
        $item['comp_weight'] = '';
        if (!empty($xml->Item->ItemSpecifics) && !empty($xml->Item->ItemSpecifics->NameValueList))
        {
            $placements = array();
            $others = array();
            foreach ($xml->Item->ItemSpecifics->NameValueList as $pair)
            {
                if ($pair->Name == 'Manufacturer Part Number')
                    $item['mpn'] = (string)$pair->Value;
                else if ($pair->Name == 'Interchange Part Number')
                    $item['ipn'] = (string)$pair->Value;
                else if ($pair->Name == 'Other Part Number')
                    $item['opn'] = (string)$pair->Value;
                else if ($pair->Name == 'Placement on Vehicle')
                {
                    foreach ($pair->Value as $v)
                        $placements[] = $v;
                }
                else if ($pair->Name == 'Part Brand')
                    $item['brand'] = (string)$pair->Value;
                else if ($pair->Name == 'Brand')
                    $item['brand'] = (string)$pair->Value;
                else if ($pair->Name == 'Surface Finish')
                    $item['surface_finish'] = (string)$pair->Value;
                else if ($pair->Name == 'Warranty')
                    $item['warranty'] = (string)$pair->Value;
                else

                    $others[] = array((string)$pair->Name, (string)$pair->Value);
            }
            $item['placement'] = implode(', ', $placements);
            $item['other_attribs'] = $others;
        }
        mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
        mysql_select_db(DB_SCHEMA);

        if (!empty($item['sku']))
        {
            $parts = GetSKUParts(array($item['sku'] => 1));
            foreach ($parts as $sku => $qty)
            {
                if (stripos($sku, '/'))
                {
                    $sku = str_replace('/', '.', strtoupper($sku));
                    $sku = str_replace('EOCF', 'EOCS', strtoupper($sku));
                }
                if (stripos($sku, '.') !== false)
                {
                    //$mpn = substr($sku, 4);
                    $mpn = $sku;
                    $brandId = '';
                    $dotIdx = strpos($sku, '.');
                    if ($dotIdx)
                    {
                        $mpn = substr($sku, 0, $dotIdx);
                        $brandId = substr($sku, $dotIdx + 1);
                    }
                    if (!empty($brandId))
                        $res = mysql_query(sprintf("SELECT name, brand, weight, part_notes FROM ssf_items WHERE mpn = '%s' AND brand_id = '%s'", mysql_real_escape_string($mpn), mysql_real_escape_string($brandId)));
                    else

                        $res = mysql_query(sprintf("SELECT name, brand, weight, part_notes FROM ssf_items WHERE mpn = '%s' LIMIT 1", mysql_real_escape_string($mpn)));
                    $row = mysql_fetch_row($res);
                    $item['mpns'][] = $mpn;
                    $item['names'][] = $row[0];
                    $item['brands'][] = $row[1];
                    $item['weights'][] = $row[2] * $qty;
                    $item['notes'][] = $row[3];
                }
                else if (startsWith($sku, "EOCE"))
                {
                    continue;
                }
                else
                {
                    // ignore filler item for free shipping
                    if ($sku == IMC_FILLERITEM)
                        continue;
                    //$mpn = substr($sku, 3);
                    $mpn = $sku;
                    $res = mysql_query(sprintf("SELECT name, brand, weight, part_notes FROM imc_items WHERE mpn = '%s'", mysql_real_escape_string($mpn)));
                    $row = mysql_fetch_row($res);
                    $item['mpns'][] = $mpn;
                    $item['names'][] = $row[0];
                    $item['brands'][] = $row[1];
                    $item['weights'][] = $row[2] * $qty;
                    $item['notes'][] = $row[3];
                }
            }
            if (!empty($item['mpns']))
                $item['comp_mpn'] = trim(implode('; ', array_unique($item['mpns'])), '; ');
            if (!empty($item['brands']))
                $item['comp_brand'] = trim(implode('; ', array_unique($item['brands'])), '; ');
            if (!empty($item['names']))
                $item['comp_name'] = trim(implode('; ', array_unique($item['names'])), '; ');
            if (!empty($item['notes']))
                $item['part_notes'] = trim(implode('; ', array_unique($item['notes'])), '; ');
            if (!empty($item['weights']))
                $item['comp_weight'] = array_sum($item['weights']);
            //unset($item['mpns']);
            unset($item['brands']);
            unset($item['names']);
            unset($item['notes']);
            unset($item['weights']);
        }
        return $item;
    }

    private function callEbayAPIToGetXML($ids, $keyword) {
        $this->LogForJob("================ IN callEbayAPIToGetXML ================");
        $sumXmls = [];

        $itemIDs = array_chunk($ids, 20);

        foreach($itemIDs as $subIds) {
            $subIdStr = implode(',', $subIds);
            $this->LogForJob(" CALL API FOR :".$subIdStr);
            $res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetMultipleItems&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${subIdStr}&IncludeSelector=Details,ShippingCosts,Compatibility,ItemSpecifics");
            $xml = simplexml_load_string($res);
            /*  start insert counter */
            CountersUtils::insertCounter('GetMultipleItems','Ebay Monitor',APP_ID);
            /*  end insert counter */

            array_push($sumXmls, $xml);
        } 

        $this->LogForJob("NUMBER OF SUB XML: ".count($sumXmls));
        return $sumXmls;
    }

    private function researchAndUpdateDb($keywords, $keyword, $ids, $existedIds, $userId) {
        $this->LogForJob("================ IN researchAndUpdateDb ================");
        $xmls = $this->callEbayShoppingAPIToGetMultipleItems($ids);

        $items = $this->getItemsFromResponseXML($xmls, $existedIds);

        $addedItems = $items['added_items'];
        $updatedItems = $items['updated_items']; 

        $this->insertIntoDb($addedItems, $keyword, $userId);
        $this->updatedExistedItems($updatedItems, $keyword, $userId);
        $this->addIdsNeedCalculateShpCostToTempDatabase();
    }

    private function addIdsNeedCalculateShpCostToTempDatabase() {
        if(!empty($this->itemIdsNeedCalculateShpCost)) {
            $sql = "
                INSERT INTO ebay_item_need_calculate_shipping 
                (item_id, user_id, status) VALUES 
            ";

            foreach($this->itemIdsNeedCalculateShpCost as $itemId) {
                $sql .= " (".$itemId . ", ".$this->userId. ", 0),";
            }

            $sql = substr($sql, 0, -1);

            mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
            mysql_select_db(DB_SCHEMA);
            mysql_query($sql);
        }

    }

    private function LogForJob($message) {
        $filename = "egrid_research_user_".$this->userId.".log";
        file_put_contents(LOGS_DIR . $filename, $message."\r\n", FILE_APPEND);
    }

    private function getIdsInDb($keywords) {
        $strKeywords = implode(',', $keywords);

        mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
        mysql_select_db(DB_SCHEMA);
        $rows = mysql_query(sprintf("SELECT item_id FROM ebay_research WHERE keywords IN ('%s') AND user_id = %d", $strKeywords, $this->userId));

        $ids = [];
        while($row = mysql_fetch_row($rows)) {
            array_push($ids, $row[0]);
        }

        return $ids;
    }

    private function getItemsFromResponseXML($sumXml, $existedIds) {
        $this->LogForJob("================ IN getItemsFromResponseXML at ".date('Y-m-d H:i:s')." ================");
        $start = new DateTime();
        $updatedItems = [];
        $addedItems = [];
        try {

            foreach($sumXml as $xml) {
                $itemInfo = [];
                if ($xml->Ack != 'Success' && $xml->Ack != 'Warning')
                    continue;

                foreach($xml->Item as $item) {
                    $id = (integer)$item->ItemID;
                    $itemInfo['item_id'] = $id;
                    $itemInfo['title'] = (string)$item->Title;

                    $allCat = (string)$item->PrimaryCategoryName;
                    $cats = explode(':', $allCat);
                    $catName = $allCat;
                    if (count($cats) > 1)
                        $catName = $cats[count($cats) - 1];
                    $itemInfo['category_name'] = $catName;


                    $itemInfo['num_avail'] = (string)$item->Quantity;
                    $itemInfo['num_sold'] = (string)$item->QuantitySold;
                    $itemInfo['price'] = (string)$item->CurrentPrice;

                    $hits = (string)$item->HitCount;
                    settype($hits, 'integer');
                    $itemInfo['num_hit'] = $hits;

                    $itemInfo['seller_id'] = (string)$item->Seller->UserID;

                    $itemInfo['seller_score'] = (string)$item->Seller->FeedbackScore;
                    $itemInfo['seller_rating'] = (string)$item->Seller->PositiveFeedbackPercent;
                    $itemInfo['seller_top'] = ((string)$item->Seller->TopRatedSeller == 'true' ? 1 : 0);
                    $itemInfo['picture_small'] = (string)$item->GalleryURL;
                    $itemInfo['shipping_cost'] = (string)$item->ShippingCostSummary->ShippingServiceCost;
                    $itemInfo['shipping_type'] = (string)$item->ShippingCostSummary->ShippingType;

                    $this->LogForJob('SHIPPING TYPE: '.$itemInfo['shipping_type']. ' FOR ID: '. $id .' AT '.date('H:i:s'));

                    if ($itemInfo['shipping_type'] == 'Calculated') {
                        array_push($this->itemIdsNeedCalculateShpCost, $id);
                        $itemInfo['shipping_cost'] = 0; // SET TEMP VALUE, IT WILL BE UPDATED LATER
                    }

                    $this->LogForJob("END CALL API SHIPPING TYPE: ".date('H:i:s'));
                    $itemInfo['num_compat'] = (string)$item->ItemCompatibilityCount;
                    if (empty($itemInfo['num_compat']))
                        $itemInfo['num_compat'] = 0;
                    $itemInfo['mpn'] = '';
                    $itemInfo['ipn'] = '';
                    $itemInfo['opn'] = '';
                    $itemInfo['placement'] = '';
                    $itemInfo['brand'] = '';

                    if (!empty($item->ItemSpecifics) && !empty($item->ItemSpecifics->NameValueList)) {
                        foreach ($item->ItemSpecifics->NameValueList as $pair)
                        {
                            if ($pair->Name == 'Manufacturer Part Number')
                                $itemInfo['mpn'] = (string)$pair->Value;
                            else if ($pair->Name == 'Interchange Part Number')
                                $itemInfo['ipn'] = (string)$pair->Value;
                            else if ($pair->Name == 'Other Part Number')
                                $itemInfo['opn'] = (string)$pair->Value;
                            else if ($pair->Name == 'Placement on Vehicle')
                                $itemInfo['placement'] = (string)$pair->Value;
                            else if ($pair->Name == 'Part Brand')
                                $itemInfo['brand'] = (string)$pair->Value;
                            else if ($pair->Name == 'Brand')
                                $itemInfo['brand'] = (string)$pair->Value;
                        }
                    }

                    $itemInfo['sku'] = (string)$item->SKU;

                    if (!empty($itemInfo['sku'])) {
                        // translate duplicate
                        if (strpos($itemInfo['sku'], 'EDP') === 0)
                        {
                            $res = mysql_query(query("SELECT orig_sku FROM eoc.sku_translation WHERE new_sku = '%s' LIMIT 1", $itemInfo['sku']));
                            $row = mysql_fetch_row($res);
                            if (!empty($row) && !empty($row[0]))
                                $itemInfo['sku'] = $row[0];
                        }

                        // find kit definition
                        if (strpos($itemInfo['sku'], 'EK') === 0)
                        {
                            $res = mysql_query(query("SELECT kit_def FROM integra_prod.products WHERE sku = '%s' LIMIT 1", $itemInfo['sku']));
                            $row = mysql_fetch_row($res);
                            if (!empty($row) && !empty($row[0]))
                                $itemInfo['sku'] = $row[0];
                        }
                    }  


                    if(!empty($itemInfo)) {

                        //array_push($updatedItems, $itemInfo);
                        if($this->isExistedItem($itemInfo['item_id'], $existedIds)) {
                            array_push($updatedItems, $itemInfo);
                        } else {
                            array_push($addedItems, $itemInfo);
                        } 
                    }
                }
            }

            $this->LogForJob(" END GET ITEMS XML AT: ".date('H:i:s'));
            return array('added_items' => $addedItems, 'updated_items' => $updatedItems);

        } catch(Exception $ex) {
            $this->LogForJob("================ EXCEPTION ". $ex->getMessage()." ================");
        }
        return null;
        
    }

    private function isExistedItem($item_id, $existedIds) {
        return in_array($item_id, $existedIds);
    }

    private function updatedExistedItems($updatedItems, $keyword, $userId) {
        $this->LogForJob("============== updatedExistedItems ===============");
        if(!empty($updatedItems)) {
            foreach($updatedItems as $item) {
                $sql = "
                    INSERT INTO eoc.ebay_research (keywords, user_id, item_id, title, image_url, price, shipping, seller, score, rating, top, num_hit, num_sold, num_compat, num_avail, category, mpn, ipn, opn, placement, brand, sku)
                                    VALUES('%s', %d, '%s', '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                                    ON DUPLICATE KEY UPDATE
                                        keywords = VALUES(keywords),
                                        title = VALUES(title),
                                        image_url = VALUES(image_url),
                                        price = VALUES(price),
                                        shipping = VALUES(shipping),
                                        seller = VALUES(seller),
                                        score = VALUES(score),
                                        rating = VALUES(rating),
                                        top = VALUES(top),
                                        num_hit = GREATEST(num_hit, VALUES(num_hit)),
                                        num_sold = VALUES(num_sold),
                                        num_compat = VALUES(num_compat),
                                        num_avail = VALUES(num_avail),
                                        category = VALUES(category),
                                        mpn = VALUES(mpn),
                                        ipn = VALUES(ipn),
                                        opn = VALUES(opn),
                                        placement = VALUES(placement),
                                        brand = VALUES(brand),
                                        sku = VALUES(sku)
                ";

                $qw = sprintf($q,
                    $keyword,
                    $userId,
                    cleanup($item['id']),
                    cleanup($item['title']),
                    empty($item['picture_small']) ? 'NULL' : "'" . cleanup($item['picture_small']) . "'",
                    cleanup($item['price']),
                    cleanup($item['shipping_cost']),
                    cleanup($item['seller_id']),
                    cleanup($item['seller_score']),
                    cleanup($item['seller_rating']),
                    cleanup($item['seller_top']),
                    ($item['num_hit'] == '-1') ? 0 : $item['num_hit'],
                    cleanup($item['num_sold']),
                    cleanup($item['num_compat']),
                    cleanup($item['num_avail']),
                    cleanup($item['category_name']),
                    cleanup($item['mpn']),
                    cleanup($item['ipn']),
                    cleanup($item['opn']),
                    cleanup($item['placement']),
                    cleanup($item['brand']),
                    cleanup($item['sku']));
                mysql_query($qw);
            }
        }
        $this->LogForJob("============== FINISHED updatedExistedItems ===============");
    }

    private function insertIntoDb($addedItems, $keyword, $userId) {
        $this->LogForJob("================ insertIntoDb ================");
        $this->LogForJob(" INSERT FOR TOTALS ".count($addedItems));
        if(!empty($addedItems)) {
            $sql = "
                INSERT INTO ebay_research (keywords, user_id, item_id, title, image_url, price, shipping, seller, score, rating, top, 
                                num_hit, num_sold, num_compat, num_avail, category, mpn, ipn, opn, placement, brand, sku) VALUES 
            ";
        }
        foreach($addedItems as $item) {
            $sql .= "('".cleanup($keyword)."', ".cleanup($userId).", '".cleanup($item['item_id'])."', '".cleanup($item['title'])."', '".cleanup($item['picture_small'])
                    ."', ".cleanup($item['price']).", ".cleanup($item['shipping_cost']).", '".cleanup($item['seller_id'])."', ".cleanup($item['seller_score'])
                    .", ".cleanup($item['seller_rating']).", ".cleanup($item['seller_top']).", ".cleanup($item['num_hit']).", ".cleanup($item['num_sold'])
                    .", ".cleanup($item['num_compat']).", ".cleanup($item['num_avail']).", '".cleanup($item['category_name'])."', '".cleanup($item['mpn'])
                    ."', '".cleanup($item['ipn'])."', '".cleanup($item['opn'])."', '".cleanup($item['placement'])."', '".cleanup($item['brand'])."', '".cleanup($item['sku'])."' ),";

        }

        $sql = substr($sql, 0, -1);

        $this->LogForJob(" SQL COMMAND: ".$sql);

        mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
        mysql_select_db(DB_SCHEMA);
        mysql_query($sql);
        $this->LogForJob("============== FINISHED INSERTED ===============");
    }

    private function callEbayShoppingAPIToGetMultipleItems($ids) {
        $this->LogForJob("================ IN callEbayShoppingAPIToGetMultipleItems ================");
        $xmls = [];
        if(is_array($ids) && !empty($ids)) {

            $xmls = $this->callEbayAPIToGetXML($ids);

        }

        $this->LogForJob("================ FINISHED callEbayShoppingAPIToGetMultipleItems  ================");
        return $xmls;
    }

    public static function ResearchKeyword($keywords, $userId) {
        $res = file_get_contents("http://svcs.ebay.com/services/search/FindingService/v1?OPERATION-NAME=findItemsByKeywords&SERVICE-VERSION=1.0.0&SECURITY-APPNAME=" . APP_ID . "&RESPONSE-DATA-FORMAT=XML&REST-PAYLOAD&outputSelector(0)=SellerInfo&itemFilter(0).name=Condition&itemFilter(0).value=New&keywords=" . urlencode($keywords));
        $xml = simplexml_load_string($res);
        /*  start insert counter */
        CountersUtils::insertCounter('findItemsByKeywords','Ebay Monitor',APP_ID);
        /*  end insert counter */
        if (empty($xml))
            return;
        $ids = array();
        foreach ($xml->searchResult->item as $i)
            $ids[] = (string)$i->itemId;
        mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
        mysql_select_db(DB_SCHEMA);
        $ctr = 1;
        foreach ($ids as $id)
        {
            $res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${id}&IncludeSelector=Details,ShippingCosts,Compatibility,ItemSpecifics");
            /*  start insert counter */
            CountersUtils::insertCounterProd('GetSingleItem','Ebay Monitor',APP_ID);
            /*  end insert counter */
            $xml = simplexml_load_string($res);
            if ($xml->Ack != 'Success' && $xml->Ack != 'Warning')
                continue;
            $item = array();
            $item['id'] = (string)$xml->Item->ItemID;
            $item['title'] = (string)$xml->Item->Title;
            $allCat = (string)$xml->Item->PrimaryCategoryName;
            $cats = explode(':', $allCat);
            $catName = $allCat;
            if (count($cats) > 1)
                $catName = $cats[count($cats) - 1];
            $item['category_name'] = $catName;
            $item['num_avail'] = (string)$xml->Item->Quantity;
            $item['price'] = (string)$xml->Item->CurrentPrice;
            $item['num_sold'] = (string)$xml->Item->QuantitySold;
            $hits = (string)$xml->Item->HitCount;
            settype($hits, 'integer');
            $item['num_hit'] = $hits;
            $item['seller_id'] = (string)$xml->Item->Seller->UserID;
            $item['seller_score'] = (string)$xml->Item->Seller->FeedbackScore;
            $item['seller_rating'] = (string)$xml->Item->Seller->PositiveFeedbackPercent;
            $item['seller_top'] = ((string)$xml->Item->Seller->TopRatedSeller == 'true' ? 1 : 0);
            $item['picture_small'] = (string)$xml->Item->GalleryURL;
            $item['shipping_cost'] = (string)$xml->Item->ShippingCostSummary->ShippingServiceCost;
            $item['shipping_type'] = (string)$xml->Item->ShippingCostSummary->ShippingType;
            if ($item['shipping_type'] == 'Calculated')
            {
                $res = file_get_contents("http://www.ebay.com/itm/getrates?item=${id}&quantity=1&country=1&zipCode=77057&co=0&cb=j");
                /*  start insert counter */
                CountersUtils::insertCounterProd('getrates','Ebay Monitor',APP_ID);
                /*  end insert counter */
                unset($match);
                preg_match('/US \$(?P<shipping>[^<]+)/i', $res, $match);
                if (isset($match) && array_key_exists('shipping', $match))
                    $item['shipping_cost'] = $match['shipping'];
            }
            $item['num_compat'] = (string)$xml->Item->ItemCompatibilityCount;
            if (empty($item['num_compat']))
                $item['num_compat'] = 0;
            $item['mpn'] = '';
            $item['ipn'] = '';
            $item['opn'] = '';
            $item['placement'] = '';
            $item['brand'] = '';
            if (!empty($xml->Item->ItemSpecifics) && !empty($xml->Item->ItemSpecifics->NameValueList))
            {
                foreach ($xml->Item->ItemSpecifics->NameValueList as $pair)
                {
                    if ($pair->Name == 'Manufacturer Part Number')
                        $item['mpn'] = (string)$pair->Value;
                    else if ($pair->Name == 'Interchange Part Number')
                        $item['ipn'] = (string)$pair->Value;
                    else if ($pair->Name == 'Other Part Number')
                        $item['opn'] = (string)$pair->Value;
                    else if ($pair->Name == 'Placement on Vehicle')
                        $item['placement'] = (string)$pair->Value;
                    else if ($pair->Name == 'Part Brand')
                        $item['brand'] = (string)$pair->Value;
                    else if ($pair->Name == 'Brand')
                        $item['brand'] = (string)$pair->Value;
                }
            }

            $item['sku'] = (string)$xml->Item->SKU;

            if (!empty($item['sku']))
            {
                // translate duplicate
                if (strpos($item['sku'], 'EDP') === 0)
                {
                    $res = mysql_query(query("SELECT orig_sku FROM eoc.sku_translation WHERE new_sku = '%s' LIMIT 1", $item['sku']));
                    $row = mysql_fetch_row($res);
                    if (!empty($row) && !empty($row[0]))
                        $item['sku'] = $row[0];
                }

                // find kit definition
                if (strpos($item['sku'], 'EK') === 0)
                {
                    $res = mysql_query(query("SELECT kit_def FROM integra_prod.products WHERE sku = '%s' LIMIT 1", $item['sku']));
                    $row = mysql_fetch_row($res);
                    if (!empty($row) && !empty($row[0]))
                        $item['sku'] = $row[0];
                }
            }

            $q = <<<EOD
                INSERT INTO eoc.ebay_research (keywords, user_id, item_id, title, image_url, price, shipping, seller, score, rating, top, num_hit, num_sold, num_compat, num_avail, category, mpn, ipn, opn, placement, brand, sku)
                VALUES('%s', %d, '%s', '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                ON DUPLICATE KEY UPDATE
                    keywords = VALUES(keywords),
                    title = VALUES(title),
                    image_url = VALUES(image_url),
                    price = VALUES(price),
                    shipping = VALUES(shipping),
                    seller = VALUES(seller),
                    score = VALUES(score),
                    rating = VALUES(rating),
                    top = VALUES(top),
                    num_hit = GREATEST(num_hit, VALUES(num_hit)),
                    num_sold = VALUES(num_sold),
                    num_compat = VALUES(num_compat),
                    num_avail = VALUES(num_avail),
                    category = VALUES(category),
                    mpn = VALUES(mpn),
                    ipn = VALUES(ipn),
                    opn = VALUES(opn),
                    placement = VALUES(placement),
                    brand = VALUES(brand),
                    sku = VALUES(sku)
EOD;
            $qw = sprintf($q,
                $keywords,
                $userId,
                cleanup($item['id']),
                cleanup($item['title']),
                empty($item['picture_small']) ? 'NULL' : "'" . cleanup($item['picture_small']) . "'",
                cleanup($item['price']),
                cleanup($item['shipping_cost']),
                cleanup($item['seller_id']),
                cleanup($item['seller_score']),
                cleanup($item['seller_rating']),
                cleanup($item['seller_top']),
                ($item['num_hit'] == '-1') ? 0 : $item['num_hit'],
                cleanup($item['num_sold']),
                cleanup($item['num_compat']),
                cleanup($item['num_avail']),
                cleanup($item['category_name']),
                cleanup($item['mpn']),
                cleanup($item['ipn']),
                cleanup($item['opn']),
                cleanup($item['placement']),
                cleanup($item['brand']),
                cleanup($item['sku']));
            mysql_query($qw);
        }
    }

    public function ResearchKeywordV2($keywords, $userId)
    {
        $this->userId = $userId;

        $this->LogForJob("================= START RESEARCH V2 JOB =================");
        try {
            $existedIds = $this->getIdsInDb($keywords);
            foreach($keywords as $keyword) {
                $this->LogForJob("RESEARCH FOR Keyword: ".$keyword);
                if(empty($keyword)) {
                    continue;
                }
                $res = file_get_contents("http://svcs.ebay.com/services/search/FindingService/v1?OPERATION-NAME=findItemsByKeywords&SERVICE-VERSION=1.0.0&SECURITY-APPNAME=" . APP_ID . "&RESPONSE-DATA-FORMAT=XML&REST-PAYLOAD&outputSelector(0)=SellerInfo&itemFilter(0).name=Condition&itemFilter(0).value=New&keywords=" . urlencode($keyword));
                
                $xml = simplexml_load_string($res);
                /*  start insert counter */
                CountersUtils::insertCounterProd('findItemsByKeywords','Ebay Monitor',APP_ID);
                /*  end insert counter */
                if (empty($xml))
                    continue;

                $ids = array();
                foreach ($xml->searchResult->item as $i) {
                    $ids[] = (string)$i->itemId;
                }
                    
                $this->LogForJob("NUMBER OF IDS FOR ".$keyword." IS ".count($ids));
                $this->researchAndUpdateDb($keywords, $keyword, $ids, $existedIds, $userId);
                $this->LogForJob("====== END RESEARCH FOR Keyword: ".$keyword);

            }

        } catch(Exception $ex) {
            $this->LogForJob("EXCEPTION: ".$ex->getMessage());
        }
        

        $this->LogForJob("================= END RESEARCH JOB =================");
    }

    public static function MonitorResearchKeyword($keywords, $userId, $query_id)
    {

        
        $res = file_get_contents("http://svcs.ebay.com/services/search/FindingService/v1?OPERATION-NAME=findItemsByKeywords&SERVICE-VERSION=1.0.0&SECURITY-APPNAME=" . APP_ID . "&RESPONSE-DATA-FORMAT=XML&REST-PAYLOAD&outputSelector(0)=SellerInfo&itemFilter(0).name=Condition&itemFilter(0).value=New&keywords=" . urlencode($keywords));
        $xml = simplexml_load_string($res);
        /*  start insert counter */
        CountersUtils::insertCounter('findItemsByKeywords','Ebay Monitor',APP_ID);
        /*  end insert counter */      

        if (empty($xml)){          
            return;
        }
        $ids = array();
        foreach ($xml->searchResult->item as $i)
        $ids[] = (string)$i->itemId;

        mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD) or die(mysql_error());
        mysql_select_db(DB_SCHEMA) or die(mysql_error());
        $ctr = 1;
        foreach ($ids as $id)
        {
            $res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid=" . APP_ID . "&siteid=0&version=847&ItemID=${id}&IncludeSelector=Details,ShippingCosts,Compatibility,ItemSpecifics");
            $xml = simplexml_load_string($res);
            /*  start insert counter */
            CountersUtils::insertCounter('GetSingleItem','Ebay Monitor',APP_ID);
            /*  end insert counter */ 

             
            if ($xml->Ack != 'Success' && $xml->Ack != 'Warning')
                continue;
            $item = array();
            $item['id'] = (string)$xml->Item->ItemID;
            $item['title'] = (string)$xml->Item->Title;
            $allCat = (string)$xml->Item->PrimaryCategoryName;
            $cats = explode(':', $allCat);
            $catName = $allCat;
            if (count($cats) > 1)
                $catName = $cats[count($cats) - 1];
            $item['category_name'] = $catName;
            $item['num_avail'] = (string)$xml->Item->Quantity;
            $item['price'] = (string)$xml->Item->CurrentPrice;
            $item['num_sold'] = (string)$xml->Item->QuantitySold;
            $hits = (string)$xml->Item->HitCount;
            settype($hits, 'integer');
            $item['num_hit'] = $hits;
            $item['seller_id'] = (string)$xml->Item->Seller->UserID;
            $item['seller_score'] = (string)$xml->Item->Seller->FeedbackScore;
            $item['seller_rating'] = (string)$xml->Item->Seller->PositiveFeedbackPercent;
            $item['seller_top'] = ((string)$xml->Item->Seller->TopRatedSeller == 'true' ? 1 : 0);
            $item['picture_small'] = (string)$xml->Item->GalleryURL;
            $item['shipping_cost'] = (string)$xml->Item->ShippingCostSummary->ShippingServiceCost;
            $item['shipping_type'] = (string)$xml->Item->ShippingCostSummary->ShippingType;
            if ($item['shipping_type'] == 'Calculated')
            {
                $res = file_get_contents("http://www.ebay.com/itm/getrates?item=${id}&quantity=1&country=1&zipCode=77057&co=0&cb=j");
                /*  start insert counter */
                CountersUtils::insertCounter('getrates','Ebay Monitor',APP_ID);
                /*  end insert counter */
                unset($match);
                preg_match('/US \$(?P<shipping>[^<]+)/i', $res, $match);
                if (isset($match) && array_key_exists('shipping', $match))
                    $item['shipping_cost'] = $match['shipping'];
            }
            $item['num_compat'] = (string)$xml->Item->ItemCompatibilityCount;
            if (empty($item['num_compat']))
                $item['num_compat'] = 0;
            $item['mpn'] = '';
            $item['ipn'] = '';
            $item['opn'] = '';
            $item['placement'] = '';
            $item['brand'] = '';
            if (!empty($xml->Item->ItemSpecifics) && !empty($xml->Item->ItemSpecifics->NameValueList))
            {
                foreach ($xml->Item->ItemSpecifics->NameValueList as $pair)
                {
                    if ($pair->Name == 'Manufacturer Part Number')
                        $item['mpn'] = (string)$pair->Value;
                    else if ($pair->Name == 'Interchange Part Number')
                        $item['ipn'] = (string)$pair->Value;
                    else if ($pair->Name == 'Other Part Number')
                        $item['opn'] = (string)$pair->Value;
                    else if ($pair->Name == 'Placement on Vehicle')
                        $item['placement'] = (string)$pair->Value;
                    else if ($pair->Name == 'Part Brand')
                        $item['brand'] = (string)$pair->Value;
                    else if ($pair->Name == 'Brand')
                        $item['brand'] = (string)$pair->Value;
                }
            }
            $q = <<<EOD

                INSERT INTO eoc.ebay_research_monitor (query_id, user_id, item_id, title, image_url, price, shipping, seller, score, rating, top, num_hit, num_sold, num_compat, num_avail, category, mpn, ipn, opn, placement, brand)

                VALUES(%d, %d, '%s', '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
EOD;
        

            $qw = sprintf($q,
                $query_id,
                $userId,
                cleanup($item['id']),
                cleanup($item['title']),
                empty($item['picture_small']) ? 'NULL' : "'" . cleanup($item['picture_small']) . "'",
                cleanup($item['price']),
                cleanup($item['shipping_cost']),
                cleanup($item['seller_id']),
                cleanup($item['seller_score']),
                cleanup($item['seller_rating']),
                cleanup($item['seller_top']),
                ($item['num_hit'] == '-1') ? 0 : $item['num_hit'],
                cleanup($item['num_sold']),
                cleanup($item['num_compat']),
                cleanup($item['num_avail']),
                cleanup($item['category_name']),
                cleanup($item['mpn']),
                cleanup($item['ipn']),
                cleanup($item['opn']),
                cleanup($item['placement']),
                cleanup($item['brand']));           
            mysql_query($qw) or die(mysql_error());
            
           

        }
    }

}

?>
