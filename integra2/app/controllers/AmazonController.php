<?php

class AmazonController extends \BaseController
{
    public function graph()
    {
        if (Input::has('days'))
            $days = Input::get('days');
        else $days = 2;

        $edited = DB::select(<<<EOQ
SELECT DATE_FORMAT( edited_on,  '%m/%d %H:00' ) AS d, edited_by AS u, COUNT(DISTINCT asin) AS c
FROM integra_prod.amazon_monitor_log
WHERE edited_on >= DATE_SUB( CURDATE( ) , INTERVAL ? DAY )
AND is_new = 0
GROUP BY 1, 2
ORDER BY edited_on
EOQ
            , [$days]);

        DB::statement("CALL integra_prod.compute_amazon_monitor_productivity(CURDATE())");

        $statsEdited = DB::select(<<<EOQ
SELECT DATE(hr) AS date, REPLACE(email, '@eocenterprise.com', '') AS user, FORMAT(AVG(output), 2) AS average, FORMAT(STD(output), 2) AS variance, SUM(output) AS total, MIN(output) AS min, MAX(output) AS max, ROUND(SUM(IF(output >= 18, 1, 0)) * 100 / COUNT(*)) AS hit_pct, SUM(IF(output < 18, -1, 0)) + ROUND(SQRT(AVG(output) * SUM(output) / (STD(output)+1))) AS score
FROM productivity
WHERE task = 4
AND hr >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
GROUP BY 1, 2
ORDER BY 1 DESC, 8 DESC
EOQ
            , [$days]);



        $created = DB::select(<<<EOQ
SELECT DATE_FORMAT( edited_on,  '%m/%d %H:00' ) AS d, edited_by AS u, COUNT(DISTINCT asin) AS c
FROM integra_prod.amazon_monitor_log
WHERE edited_on >= DATE_SUB( CURDATE( ) , INTERVAL ? DAY )
AND is_new = 1
GROUP BY 1, 2
ORDER BY edited_on
EOQ
            , [$days]);

        DB::statement("CALL integra_prod.compute_amazon_list_productivity(CURDATE())");

        $statsCreated = DB::select(<<<EOQ
SELECT DATE(hr) AS date, REPLACE(email, '@eocenterprise.com', '') AS user, FORMAT(AVG(output), 2) AS average, FORMAT(STD(output), 2) AS variance, SUM(output) AS total, MIN(output) AS min, MAX(output) AS max, ROUND(SUM(IF(output >= 18, 1, 0)) * 100 / COUNT(*)) AS hit_pct, SUM(IF(output < 18, -1, 0)) + ROUND(SQRT(AVG(output) * SUM(output) / (STD(output)+1))) AS score
FROM productivity
WHERE task = 5
AND hr >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
GROUP BY 1, 2
ORDER BY 1 DESC, 8 DESC
EOQ
            , [$days]);

        return ['edited' => $edited, 'stats_edited' => $statsEdited, 'created' => $created, 'stats_created' => $statsCreated];
    }

    public function changePrice($asin)
    {
        DB::insert(<<<EOQ
INSERT INTO integra_prod.amazon_repricer_queue (asin, price, queue_date, proc_date, feed_id)
VALUES (?, ?, NOW(), NULL, '') ON DUPLICATE KEY
UPDATE price = VALUES(price)
EOQ
            , [$asin, Input::get('price')]);

        DB::insert(<<<EOQ
INSERT INTO integra_prod.amazon_monitor_log (asin, edited_by, edited_on, our_new_price, is_new)
VALUES (?, ?, NOW(), ?, 0)
EOQ
            , [$asin, Cookie::get('user'), Input::get('price')]);

        return ['success' => 1];
    }

    public function updateMonitor($asin)
    {
        DB::delete(<<<EOQ
DELETE FROM integra_prod.tmp_amazon_monitor
WHERE asin = ?
EOQ
            , [$asin]);

        DB::insert(<<<EOQ
INSERT INTO integra_prod.tmp_amazon_monitor (asin, seller, fba, ts, latest_price, below_min, into_buybox, outof_buybox)
(SELECT asin, seller, fba, ts, latest_price, below_min, into_buybox, outof_buybox
FROM integra_prod.amazon_monitor
WHERE asin = ?)
EOQ
            , [$asin]);

        DB::delete(<<<EOQ
DELETE FROM integra_prod.amazon_monitor
WHERE asin = ?
EOQ
            , [$asin]);

        foreach (Input::get('competitors') as $c)
        {
            if (!$c['enabled']) continue;

            DB::insert(<<<EOQ
INSERT INTO integra_prod.amazon_monitor (asin, seller, fba, strategy, min_price)
VALUES (?, ?, ?, ?, ?)
EOQ
                , [$asin, $c['seller_id'], $c['fba'], $c['strategy'], $c['min_price']]);
        }

        DB::update(<<<EOQ
UPDATE integra_prod.amazon_monitor am, integra_prod.tmp_amazon_monitor tam
SET am.ts = tam.ts,
am.latest_price = tam.latest_price,
am.below_min = tam.below_min,
am.into_buybox = tam.into_buybox,
am.outof_buybox = tam.outof_buybox
WHERE am.asin = tam.asin
AND am.seller = tam.seller
AND am.fba = tam.fba
AND am.asin = ?
EOQ
            , [$asin]);

        DB::delete(<<<EOQ
DELETE FROM integra_prod.tmp_amazon_monitor
WHERE asin = ?
EOQ
            , [$asin]);

        DB::insert(<<<EOQ
INSERT INTO integra_prod.amazon_monitor_log (asin, edited_by, edited_on, our_new_price, is_new)
VALUES (?, ?, NOW(), NULL, 0)
EOQ
            , [$asin, Cookie::get('user')]);

        return ['success' => 1];
    }

    public function monitorSettings($q)
    {
        // find matching listings
        $choices = DB::select(<<<EOQ
SELECT asin, sku
FROM eoc.amazon_listings al
WHERE (al.asin = ? OR al.sku LIKE CONCAT('%', ?, '%')) AND al.active = 1
ORDER BY 2
EOQ
            , [$q, $q]);

        if (count($choices) > 1) return ['choices' => $choices];
        else if (!count($choices)) return ['nomatch' => true];

        if (Input::has('days'))
            $days = Input::get('days');
        else $days = 2;

        // get our current price
        $tmp = DB::select(<<<EOQ
SELECT AVG((min_price + max_price) / 2) AS price
FROM integra_prod.amazon_price_summary
WHERE asin = ?
AND seller = ?
AND fba = 0
AND ts >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
EOQ
            , [$choices[0]['asin'], Config::get('integra.amazon_mws.merchant_id'), $days]);

        if (empty($tmp)) return [];
        else $ourPrice = $tmp[0]['price'];

        if (empty($ourPrice))
            $ourPrice = 9999999;

        // get competitors that we don't know yet
        $anons = DB::select(<<<EOQ
SELECT DISTINCT seller
FROM integra_prod.amazon_price_summary
WHERE asin = ?
AND max_price <= ?
AND ts >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
AND seller NOT IN (SELECT id FROM eoc.amazon_merchants)
EOQ
            , [$choices[0]['asin'], $ourPrice * 1.2, $days]);

        // scrape competitor names
        foreach ($anons as $anon)
        {
            $seller = $anon['seller'];
            $sp = file_get_contents("https://www.amazon.com/sp?seller={$seller}");
            preg_match("/Seller Profile:\\s*(?<name>[^<]+)/", $sp, $matches);

            if (isset($matches['name']))
            {
                if (stripos($matches['name'], '&&') !== false) continue; // invalid seller
                if (stripos($matches['name'], '//') !== false) continue; // invalid seller
                DB::insert("INSERT INTO eoc.amazon_merchants (id, name) VALUES (?, ?)", [$seller, $matches['name']]);
            }
        }

        // get current settings for competitors
        $competitors = DB::select(<<<EOQ
SELECT IFNULL((SELECT name FROM eoc.amazon_merchants am WHERE am.id = m.seller LIMIT 1), m.seller) AS seller_name, seller AS seller_id, fba,
IFNULL(latest_price, (SELECT min_price FROM integra_prod.amazon_price_summary aps WHERE aps.asin = m.asin AND aps.seller = m.seller AND aps.fba = m.fba ORDER BY aps.ts DESC LIMIT 1)) AS latest_price,
strategy, min_price
FROM integra_prod.amazon_monitor m
WHERE m.asin = ?
AND m.seller != ?
EOQ
            , [$choices[0]['asin'], Config::get('integra.amazon_mws.merchant_id')]);

        foreach ($competitors as &$c)
        {
            $c['enabled'] = true;
        }

        // get prices (for graph) and buybox status
        $prices = DB::select(<<<EOQ
SELECT ts, IFNULL((SELECT name FROM eoc.amazon_merchants am WHERE am.id = aps.seller LIMIT 1), aps.seller) AS seller_name, aps.seller AS seller_id, fba, aps.min_price AS price, aps.buybox
FROM integra_prod.amazon_price_summary aps
WHERE aps.asin = ?
AND aps.max_price <= ?
AND aps.ts >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
ORDER BY 1, 2, 3, 4
EOQ
            , [$choices[0]['asin'], $ourPrice * 1.2, $days]);


        $sellersToAdd = [];
        $sellers = [];
        $times = [];
        $rp = [];

        // construct data structure for graph
        foreach ($prices as $price)
        {
            $mustAdd = true;

            foreach ($competitors as $comp)
            {
                if ($comp['seller_id'] == $price['seller_id'] && $comp['fba'] == $price['fba'])
                {
                    $mustAdd = false;
                    break;
                }
            }

            if ($mustAdd)
                $sellersToAdd[$price['seller_id'] . '|' . ($price['fba'] ? '1' : '0')] = $price['seller_name'] . '|' . $price['price'] . '|' . ($price['buybox'] ? '1' : '0');

            $sellerKey = $price['seller_name'] . ($price['fba'] ? ' (FBA)' : '');
            $sellers[$sellerKey] = 1;
            $times[$price['ts']] = 1;
        }

        $sellers = array_keys($sellers);
        $times = array_keys($times);

        foreach ($times as $time)
        {
            foreach ($sellers as $s)
            {
                $found = false;

                foreach ($prices as $price)
                {
                    $sellerKey = $price['seller_name'] . ($price['fba'] ? ' (FBA)' : '');
                    if ($sellerKey == $s && $price['ts'] == $time)
                    {
                        $found = true;
                        $rp[$sellerKey][] = $price['price'];
                        break;
                    }
                }

                if (!$found) $rp[$s][] = null;
            }
        }

        $pd = [];

        foreach ($rp as $seller => $p)
            $pd[] = $p;

        // construct data structure for competitor settings table
        foreach ($sellersToAdd as $idFba => $namePrice)
        {
            $tmp = explode('|', $idFba);
            $id = $tmp[0];
            $fba = ($tmp[1] == '1' ? 1 : 0);

            $tmp = explode('|', $namePrice);
            $name = $tmp[0];
            $price = $tmp[1];
            $buybox = $tmp[2];
            $competitors[] = ['enabled' => false, 'seller_id' => $id, 'seller_name' => $name, 'fba' => $fba, 'buybox' => $buybox, 'latest_price' => $price, 'strategy' => 1, 'min_price' => null];
        }

        return ['us' => Config::get('integra.amazon_mws.merchant_id'), 'prices' => $pd, 'sellers' => $sellers, 'times' => $times, 'competitors' => $competitors];
    }

    public function listMonitor($page = 1)
    {
        $where = '';

        if (Input::has('search'))
        {
            $search = trim(str_replace('%', '', str_replace("'", '', Input::get('search'))));
            if (!empty($search)) $where = " AND (asin LIKE '{$search}%' OR sku LIKE '{$search}%') ";
        }

        $rows = DB::select("SELECT COUNT(DISTINCT asin) AS c FROM integra_prod.amazon_monitor WHERE 1=1 {$where}");
        $kwCount = $rows[0]['c'];

        $pageSize = 50;
        $numPages = max(ceil($kwCount / $pageSize), 1);

        if ($page <= 0) $page = 1;
        else if ($page > $numPages) $page = $numPages;

        $offset = $pageSize * ($page - 1);

        $kwList = ["'~'"]; // dummy entry to avoid empty IN query
        $kws = DB::select(<<<EOQ
SELECT asin, MAX(below_min), MAX(into_buybox), MAX(outof_buybox), MAX(ts)
FROM integra_prod.amazon_monitor
WHERE 1=1 {$where}
GROUP BY asin
ORDER BY MAX(below_min) DESC, MAX(into_buybox) DESC, MAX(outof_buybox) DESC, MAX(ts) DESC
LIMIT ?, ?
EOQ
            , [$offset, $pageSize]);
        foreach ($kws as $k) $kwList[] = "'" . trim($k['asin']) . "'";

        $kws = implode(', ', $kwList);

        $rows = DB::select(<<<EOQ
SELECT id, asin, sku, seller, seller_name, fba, ts, latest_price, below_min, into_buybox, outof_buybox
FROM integra_prod.amazon_monitor
WHERE asin IN ({$kws})
ORDER BY below_min DESC, into_buybox DESC, outof_buybox DESC, ts DESC
EOQ
        );

        $keywords = [];

        foreach ($rows as $row)
        {
            $item = [];
            $item['id'] = $row['id'];
            $item['url'] = 'https://www.amazon.com/gp/offer-listing/' . $row['asin'];
            $item['seller'] = $row['seller_name'];
            $item['fba'] = $row['fba'];
            $item['ts'] = $row['ts'];
            $item['latest_price'] = $row['latest_price'];
            $item['below_min'] = $row['below_min'];
            $item['into_buybox'] = $row['into_buybox'];
            $item['outof_buybox'] = $row['outof_buybox'];

            $kw = trim($row['asin']);

            if (!array_key_exists($kw, $keywords))
                $keywords[$kw] = ['asin' => $kw, 'sku' => $row['sku'], 'items' => [], 'below_min' => false, 'into_buybox' => false, 'outof_buybox' => false];

            $keywords[$kw]['items'][] = $item;
            $keywords[$kw]['below_min'] |= $item['below_min'];
            $keywords[$kw]['into_buybox'] |= $item['into_buybox'];
            $keywords[$kw]['outof_buybox'] |= $item['outof_buybox'];
        }

        return ['asins' => array_values($keywords), 'pages' => $numPages];
    }

    public function ackMonitor($id)
    {
        DB::update(<<<EOQ
UPDATE integra_prod.amazon_monitor
SET below_min = 0,
into_buybox = 0,
outof_buybox = 0
WHERE id = ?
EOQ
        , [$id]);
    }

    public function downloadCostWeight()
    {
        set_time_limit(0);

        $rows = DB::select(<<<EOQ
SELECT asin, sku, REPLACE(REPLACE(REPLACE(components, ',', ' / '), '$', ' / '), '~', '-') AS components, total_weight, total_cost, price AS selling_price
FROM eoc.amazon_listings
WHERE active = 1
EOQ
        );

        $file = storage_path('amazon_cost_weight.csv');

        file_put_contents($file, "asin, sku, components, total_weight, total_cost, selling_price\n");

        foreach ($rows as $row)
        {
            file_put_contents($file,
                $row['asin'] . ',' .
                $row['sku'] . ',' .
                $row['components'] . ',' .
                $row['total_weight'] . ',' .
                $row['total_cost'] . ',' .
                $row['selling_price'] . "\n", FILE_APPEND);
        }

        return Response::download($file, 'amazon_cost_weight.csv', ['Content-Type' => 'text/csv']);
    }

    public function queueList()
    {
        return DB::select(<<<EOQ
SELECT id, asin, sku, price, quantity, queue_date, end_date
FROM integra_prod.amazon_listing_queue
WHERE queue_date > DATE_SUB(CURDATE(), INTERVAL 7 DAY)
ORDER BY queue_date DESC
EOQ
        );
    }
}
