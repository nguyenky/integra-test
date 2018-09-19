<?php

use \Carbon\Carbon;

class OrderController extends \BaseController
{
    public function statusGraph()
    {
        if (Input::has('days'))
            $days = Input::get('days');
        else $days = 5;

        DB::statement("CALL integra_prod.take_status_snapshot()");

        $stats = DB::select(<<<EOQ
SELECT snap_date AS d, os.status AS u, COUNT(*) AS c
FROM integra_prod.status_snapshots ss, integra_prod.order_statuses os
WHERE snap_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
AND ss.status = os.id
GROUP BY 1, 2
ORDER BY 1, 2
EOQ
            , [$days]);

        return ['stats' => $stats];
    }

    public function statusGraphOrders()
    {
        if (!Input::has('snap_date')) return;
        if (!Input::has('status')) return;

        $snapDate = Input::get('snap_date');
        $status = Input::get('status');

        $orders = DB::select(<<<EOQ
SELECT s.id, order_date, record_num, store, agent, fulfilment, speed, tracking_num,
    (SELECT oh.remarks
    FROM integra_prod.order_history oh, integra_prod.users u
    WHERE oh.order_id = s.id
    AND u.email = ?
    AND NOT (u.group_name = 'Sales' AND oh.hide_sales = 1)
    AND NOT (u.group_name = 'Data' AND oh.hide_data = 1)
    AND NOT (u.group_name = 'Pricing' AND oh.hide_pricing = 1)
    AND NOT (u.group_name = 'Shipping' AND oh.hide_shipping = 1)
    AND oh.remarks > ''
    ORDER BY oh.ts DESC
    LIMIT 1) AS last_remarks
FROM eoc.sales s, integra_prod.status_snapshots ss, integra_prod.order_statuses os
WHERE s.id = ss.order_id
AND os.status = ?
AND os.id = ss.status
AND ss.snap_date = ?
ORDER BY 2
EOQ
            , [Cookie::get('user'), $status, $snapDate]);

        return ['orders' => $orders];
    }


    public function getSearchDetailOrder($keyword) {
        $sql = "
            SELECT store,
            id,
            internal_id,
            record_num,
            order_date,
            total,
            buyer_id,
            email,
            buyer_name,
            street,
            city,
            state,
            country,
            zip,
            phone,
            speed,
            tracking_num,
            UCASE(carrier) AS carrier,
            agent,
            fulfilment,
            status,
            weight,
            (SELECT DATE_FORMAT(print_date, '%Y-%m-%d %H:%i')
                FROM eoc.stamps st
                WHERE st.sales_id = s.id
                ORDER BY 1 DESC
                LIMIT 1) AS label_date,
            (SELECT CONCAT(p.id, '~', p.record_num) FROM eoc.sales p WHERE p.id = s.related_sales_id) AS parent_order,
            (SELECT GROUP_CONCAT(CONCAT(r.id, '~', r.record_num) SEPARATOR '|') FROM eoc.sales r WHERE r.related_sales_id = s.id) AS sub_orders
            FROM eoc.sales s
            WHERE record_num LIKE CONCAT('%', ?, '%') OR internal_id LIKE CONCAT('%', ?, '%')
            LIMIT 1
        ";
        $search = DB::select($sql, array($keyword, $keyword));
        $order = null;
        if(isset($search) && !empty($search)) {
            $order['id'] = $search[0]['id'];
        }
        //return View::make('view_order', compact('order'));
        return $order;
    }


    public function getActualShipGraph() {

        $created = DB::select(<<<EOQ
SELECT DATE_FORMAT(print_date, '%m/%d %H:00') AS d, email AS u, COUNT(*) AS c
FROM eoc.stamps
WHERE print_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
AND email IN (SELECT email FROM integra_prod.users WHERE shipper = 1)
GROUP BY 1, 2
ORDER BY 1
EOQ
            , [1]);

        DB::statement("CALL integra_prod.compute_shipping_productivity(CURDATE())");

        $stats = DB::select(<<<EOQ
SELECT DATE(hr) AS date, REPLACE(email, '@eocenterprise.com', '') AS user, FORMAT(AVG(output), 2) AS average,
FORMAT(STD(output), 2) AS variance, SUM(output) AS total, MIN(output) AS min, MAX(output) AS max,
ROUND(SUM(IF(output >= 30, 1, 0)) * 100 / COUNT(*)) AS hit_pct,
SUM(IF(output < 30, -1, 0)) + ROUND(SQRT(AVG(output) * SUM(output) / (STD(output)+1))) AS score
FROM productivity
WHERE task = 1
AND hr >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
GROUP BY 1, 2
ORDER BY 1 DESC, 8 DESC
EOQ
            , [1]);


        $chartData = $this->buildDataForChart($created, 1);

        return array_merge($chartData, array('stats' => $stats));

    }


    private function buildDataForChart($created, $days) {
        $now = date_create("now", new DateTimeZone('America/New_York'));

        $axisX = $this->buildAxisX($now, $days);
        $chartSeries = $this->chartSeries($created, $axisX);
        $chartData = $this->chartData($chartSeries[1], $axisX, $created);
        return array('axisX' => $axisX, 'series' => $chartSeries[0], 'chartData' => $chartData);
    }

    private function chartSeries($created, $axisX) {
        $arrInit = $this->initChartSeries($created);
        $series = $arrInit[0];
        $seriesData = $arrInit[1];
        foreach($created as $userData) {

            $index = array_search($userData['u'], $seriesData);
            $inAxisXIndex = array_search($userData['d'], $axisX);
            if($inAxisXIndex !== FALSE) {
                if($index === FALSE) {
                    array_push($series, $userData['u'].' ('.$userData['c'].')');
                    array_push($seriesData, $userData['u']);
                } else {
                    $series[$index] = $this->updateSeriesCount($series[$index], (int)$userData['c']);
                }
            }
        }
        return [$series, $seriesData];
    }

    private function initChartSeries($created) {
        $series = array();
        $seriesData = array();
        foreach($created as $userData) {
            $index = array_search($userData['u'], $seriesData);
            if($index === FALSE) {
                array_push($seriesData, $userData['u']);
                array_push($series, $userData['u'].'(0)');
            }
        }
        return [$series, $seriesData];
    }

    private function updateSeriesCount($value, $additionCount) {
        $v_arr = explode('(', $value);
        $count = $v_arr[1];
        $count = (int)explode(')', $count)[0];
        return $v_arr[0].'('.($count + $additionCount).')';
    }

    private function chartData($series, $axisX, $created) {
        $chartData = array();
        foreach($series as $ser) {
            $current = array();
            foreach($axisX as $time) {
                $val = 0;
                foreach ($created as $userData) {
                    if ($userData['u'] == $ser && $userData['d'] == $time) {
                        $val = $userData['c'];
                        break;
                    }
                }
                array_push($current, $val);
            }
            array_push($chartData, $current);
        }
        return $chartData;
    }


    private function buildAxisX($now, $days) {
        $axisX = array();
        for($t = 24*$days; $t >=0 ; $t--) {
            $cur = new DateTime("now", new DateTimeZone('America/New_York'));
            $cur = $cur->modify(-($t).' hours');
            $time = $cur->format('m/d H:00');
            $h = (int)$cur->format('H');
            if($h >= 7 && $h <= 19) {
                array_push($axisX, $time);
            }
        }
        return $axisX;
    }


    public function shipGraph()
    {
        if (Input::has('days'))
            $days = Input::get('days');
        else $days = 2;

        $created = DB::select(<<<EOQ
SELECT DATE_FORMAT(print_date, '%m/%d %H:00') AS d, email AS u, COUNT(*) AS c
FROM eoc.stamps
WHERE print_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
AND email IN (SELECT email FROM integra_prod.users WHERE shipper = 1)
GROUP BY 1, 2
ORDER BY 1
EOQ
            , [$days]);

        DB::statement("CALL integra_prod.compute_shipping_productivity(CURDATE())");

        $stats = DB::select(<<<EOQ
SELECT DATE(hr) AS date, REPLACE(email, '@eocenterprise.com', '') AS user, FORMAT(AVG(output), 2) AS average,
FORMAT(STD(output), 2) AS variance, SUM(output) AS total, MIN(output) AS min, MAX(output) AS max,
ROUND(SUM(IF(output >= 30, 1, 0)) * 100 / COUNT(*)) AS hit_pct,
SUM(IF(output < 30, -1, 0)) + ROUND(SQRT(AVG(output) * SUM(output) / (STD(output)+1))) AS score
FROM productivity
WHERE task = 1
AND hr >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
GROUP BY 1, 2
ORDER BY 1 DESC, 8 DESC
EOQ
            , [$days]);

        return ['created' => $created, 'stats' => $stats];
    }

    public function salesGraph($store)
    {
        $fields = explode('_', $store);
        $store = $fields[0];

        $typeWhere = '';

        if (count($fields) > 1)
        {
            if ($fields[1] == 'automotive')
                $typeWhere = " AND has_automotive = 1 ";
            else if ($fields[1] == 'motorcycle')
                $typeWhere = " AND has_motorcycle = 1 ";
        }

        $d7 = DB::select(<<<EOQ
SELECT DATE(order_date) AS d, SUM(total) AS s
FROM eoc.sales
WHERE DATE(order_date) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
AND store = ?
{$typeWhere}
GROUP BY 1
ORDER BY 1
EOQ
            , [$store]);

        $mtd = DB::select(<<<EOQ
SELECT DATE(order_date) AS d, SUM(total) AS s
FROM eoc.sales
WHERE order_date >= CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m'), '-01')
AND store = ?
{$typeWhere}
GROUP BY 1
ORDER BY 1
EOQ
            , [$store]);

        $m3 = DB::select(<<<EOQ
SELECT DATE_FORMAT(order_date, '%Y-%m') AS d, SUM(total) AS s
FROM eoc.sales
WHERE DATE(order_date) >= CONCAT(DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 3 MONTH), '%Y-%m'), '-01')
AND store = ?
{$typeWhere}
GROUP BY 1
ORDER BY 1
EOQ
            , [$store]);

        return ['d7' => $d7, 'mtd' => $mtd, 'm3' => $m3];
    }

    public function show($id)
    {
        $rows = DB::select(<<<EOQ
SELECT store,
internal_id,
record_num,
order_date,
total,
buyer_id,
email,
buyer_name,
street,
city,
state,
country,
zip,
phone,
speed,
tracking_num,
UCASE(carrier) AS carrier,
agent,
fulfilment,
status,
weight,
(SELECT DATE_FORMAT(print_date, '%Y-%m-%d %H:%i')
    FROM eoc.stamps st
    WHERE st.sales_id = s.id
    ORDER BY 1 DESC
    LIMIT 1) AS label_date,
(SELECT CONCAT(p.id, '~', p.record_num) FROM eoc.sales p WHERE p.id = s.related_sales_id) AS parent_order,
(SELECT GROUP_CONCAT(CONCAT(r.id, '~', r.record_num) SEPARATOR '|') FROM eoc.sales r WHERE r.related_sales_id = s.id) AS sub_orders
FROM eoc.sales s
WHERE id = ?
EOQ
        , [$id]);

        if (empty($rows)) return null;
        $ret = $rows[0];

        if (!empty($ret['sub_orders']))
            $ret['sub_orders'] = explode('|', $ret['sub_orders']);

        $rows = DB::select(<<<EOQ
SELECT ebay_item_id, amazon_asin, sku, description, quantity, unit_price, total
FROM eoc.sales_items
WHERE sales_id = ?
EOQ
            , [$id]);

        $subtotal = 0;
        $items = [];

        foreach ($rows as $row)
        {
            $subtotal += $row['total'];
            if (array_key_exists($row['sku'], $items))
                $items[$row['sku']] += $row['quantity'];
            else $items[$row['sku']] = $row['quantity'];
        }

        $ret['items'] = $rows;
        $ret['subtotal'] = $subtotal;
        $ret['shipping'] = $ret['total'] - $subtotal;
        $ret['components'] = IntegraUtils::getPartsInfo(IntegraUtils::getSKUParts($items));

        $rows = DB::select(<<<EOQ
SELECT ds.id, ds.supplier, ds.order_id, ds.tracking_num, IFNULL(s.etd, ds.etd) AS etd
FROM eoc.direct_shipments ds, eoc.direct_shipments_sales dss, eoc.sales s
WHERE ds.order_id = dss.order_id
AND s.id = dss.sales_id
AND dss.sales_id = ?
EOQ
            , [$id]);

        $ret['sources'] = $rows;

        $ret['history'] = $this->history($id);

        $pounds = floor($ret['weight']);
        $ounces = round(($ret['weight'] - $pounds) * 16);
        if (!empty($ounces) && !empty($pounds))
            $ret['weight_str'] = "$pounds lb $ounces oz";
        else if (empty($ounces) && !empty($pounds))
            $ret['weight_str'] = "$pounds lb";
        else if (!empty($ounces) && empty($pounds))
            $ret['weight_str'] = "$ounces oz";

        return $ret;
    }

    public function initDataForm() {
        $savedAddresses = SavedAddress::get()->toArray();
        $suppliers = Supplier::get()->toArray();

        return array('savedAddresses' => $savedAddresses, 'suppliers' => $suppliers);
    }

    public function searchInOrderDetail() {
        $keywords = Input::get('keywords');
        $keywordsWhere = '';
        if (!empty($keywords))
        {
            $result = SphinxSearch::search(str_replace('@', ' ', $keywords), 'sales')->limit(500)->setMatchMode(\Sphinx\SphinxClient::SPH_MATCH_EXTENDED)->query();
            if (!isset($result['matches'])) $v = [];
            else $v = array_keys($result['matches']);
            if (count($v) > 0)
                $keywordsWhere = " AND id IN (" . implode(',', $v) . ") ";
        }

        $sql = "
            SELECT id, order_date, record_num, buyer_name, fulfilment, status, tracking_num, total
            FROM eoc.sales s
            WHERE s.store in (SELECT store FROM integra_prod.order_access oa, integra_prod.users u
                                            WHERE u.group_name = oa.group_name AND u.email = ? AND oa.visible = 1)
            {$keywordsWhere}
            ORDER BY order_date DESC LIMIT ?
        ";

        $email = (Input::get('email') != null) ? Input::get('email') : Cookie::get('user');

        $orders = DB::select($sql, [$email, 5]);
        return ['orders' => $orders];
    }

    public function search()
    {
        $keywords = Input::get('keywords');
        $keywordsWhere = '';
        if (!empty($keywords))
        {
            $result = SphinxSearch::search(str_replace('@', ' ', $keywords), 'sales')->limit(500)->setMatchMode(\Sphinx\SphinxClient::SPH_MATCH_EXTENDED)->query();
            if (!isset($result['matches'])) $v = [];
            else $v = array_keys($result['matches']);
            if (count($v) > 0)
                $keywordsWhere = " AND id IN (" . implode(',', $v) . ") ";
        }

        $stores = Input::get('storeFilters');
        $storesWhere = '';
        if (!empty($stores))
        {
            $v = [];
            foreach ($stores as $val)
            {
                if ($val['include']) $v[] = "'" . str_replace("'", '', $val['label']) . "'";
            }
            if (count($v) > 0)
                $storesWhere = " AND store IN (" . implode(',', $v) . ") ";
        }

        $fulfilment = Input::get('fulfilmentFilters');
        $fulfilmentWhere = '';
        if (!empty($fulfilment))
        {

            $v = [];
            foreach ($fulfilment as $val)
            {
                if ($val['include']) $v[] = $val['value'];
            }
            if (count($v) > 0)
                $fulfilmentWhere = " AND fulfilment IN (" . implode(',', $v) . ") ";
        }

        $status = Input::get('statusFilters');
        $statusWhere = '';
        if (!empty($status))
        {
            $v = [];
            foreach ($status as $val)
            {
                if ($val['include']) $v[] = $val['value'];
            }
            if (count($v) > 0)
                $statusWhere = " AND status IN (" . implode(',', $v) . ") ";
        }

        $dateRange = Input::get('dateFilters');
        $dateWhere = '';
        if (!empty($dateRange))
        {
            $from = $dateRange['from'];
            $to = $dateRange['to'];

            if (!empty($from) && !empty($to))
            {
                if ($from > $to) {
                    $tmp = $from;
                    $from = $to;
                    $to = $tmp;
                }

                $from = date('Y-m-d', strtotime($from)) . ' 00:00:00';
                $to = date('Y-m-d', strtotime($to)) . ' 23:59:59';

                $dateWhere = " AND order_date >= '{$from}' AND order_date <= '{$to}' ";
            }
        }

        $speed = Input::get('speedFilters');
        $speedWhere = '';
        if (!empty($speed))
        {
            $v = [];
            foreach ($speed as $val)
            {
                if ($val['include']) $v[] = "'" . str_replace("'", '', $val['label']) . "'";
            }
            if (count($v) > 0)
                $speedWhere = " AND speed IN (" . implode(',', $v) . ") ";
        }

        $rows = DB::select(<<<EOQ
SELECT COUNT(*) as c
FROM eoc.sales s
WHERE s.store in (SELECT store FROM integra_prod.order_access oa, integra_prod.users u WHERE u.group_name = oa.group_name AND u.email = ? AND oa.visible = 1)
{$keywordsWhere}
{$storesWhere}
{$fulfilmentWhere}
{$statusWhere}
{$speedWhere}
{$dateWhere}
EOQ
            , [Cookie::get('user')]);

        $orderCount = $rows[0]['c'];
        $pageSize = Input::get('ps');
        if (empty($pageSize)) $pageSize = 50;
        $numPages = max(ceil($orderCount / $pageSize), 1);

        $page = Input::get('page');
        if (empty($page) || $page <= 0) $page = 1;
        else if ($page > $numPages) $page = $numPages;

        $offset = $pageSize * ($page - 1);

        $orders = DB::select(<<<EOQ
SELECT id, order_date, record_num, store, internal_id, agent, buyer_name, buyer_id, fulfilment, status, speed, tracking_num, total,
    (SELECT oh.remarks
    FROM integra_prod.order_history oh, integra_prod.users u
    WHERE oh.order_id = s.id
    AND u.email = ?
    AND NOT (u.group_name = 'Sales' AND oh.hide_sales = 1)
    AND NOT (u.group_name = 'Data' AND oh.hide_data = 1)
    AND NOT (u.group_name = 'Pricing' AND oh.hide_pricing = 1)
    AND NOT (u.group_name = 'Shipping' AND oh.hide_shipping = 1)
    AND oh.remarks > ''
    ORDER BY oh.ts DESC
    LIMIT 1) AS last_remarks
FROM eoc.sales s
WHERE s.store in (SELECT store FROM integra_prod.order_access oa, integra_prod.users u
                                WHERE u.group_name = oa.group_name AND u.email = ? AND oa.visible = 1)
{$keywordsWhere}
{$storesWhere}
{$fulfilmentWhere}
{$statusWhere}
{$speedWhere}
{$dateWhere}
ORDER BY order_date DESC LIMIT ?, ?
EOQ
            , [Cookie::get('user'), Cookie::get('user'), $offset, $pageSize]);

        return ['orders' => $orders, 'pages' => $numPages];
    }

    public function getSaleRecords() {
        $search = Input::get('search');
        Log::info("Test search: ".$search);
        $saleRecords = Sale::getSaleRecord($search);

        return Response::json([
            'success' => true,
            'status' => 200,
            'message' => "GET SUCCESSFULLY",
            'data' => $saleRecords
        ]);
    }

    public function getItemIDAsin() {
        $search = Input::get('search');
        Log::info("TEst search: ".$search);
        $ids = SalesItem::getItemIdAsin($search);

        return Response::json([
            'success' => true,
            'status' => 200,
            'message' => "GET SUCCESSFULLY",
            'data' => $ids
        ]);
    }

    public function downloadShipList()
    {
        set_time_limit(0);

        $headers = [
            'Content-type' => 'application/csv',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=ship_grid.csv',
            'Expires' => '0',
            'Pragma' => 'public'
        ];

        $params = array_merge([
            'keywords' => '',
            'statusFilters' => [ ],
            'speedFilters' => [ ],
        ], json_decode(Input::get('params'), true));

        $orders = $this->searchShipList(true, $params);
        $list = $orders['orders'];

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

    public function searchShipList($getAll = false, $params = null)
    {
        $keywords = $params ? $params['keywords'] : Input::get('keywords');
        $keywordsWhere = '';
        if (!empty($keywords))
        {
            $result = SphinxSearch::search(str_replace('@', ' ', $keywords), 'sales')->limit(500)->setMatchMode(\Sphinx\SphinxClient::SPH_MATCH_EXTENDED)->query();
            if (!isset($result['matches'])) $v = [];
            else $v = array_keys($result['matches']);
            if (count($v) > 0)
                $keywordsWhere = " AND id IN (" . implode(',', $v) . ") ";
        }

        $status = $params ? $params['statusFilters'] : Input::get('statusFilters');
        $statusWhere = '';
        if (!empty($status))
        {
            $v = [];
            foreach ($status as $val)
            {
                if ($val['include']) $v[] = $val['value'];
            }
            if (count($v) > 0)
                $statusWhere = " AND status IN (" . implode(',', $v) . ") ";
        }

        $dateRange = $params ? $params['dateFilters'] : Input::get('dateFilters');
        $dateWhere = '';
        if (!empty($dateRange))
        {
            $from = $dateRange['from'];
            $to = $dateRange['to'];

            if (!empty($from) && !empty($to))
            {
                if ($from > $to) {
                    $tmp = $from;
                    $from = $to;
                    $to = $tmp;
                }

                $from = date('Y-m-d', strtotime($from)) . ' 00:00:00';
                $to = date('Y-m-d', strtotime($to)) . ' 23:59:59';

                $dateWhere = " AND order_date >= '{$from}' AND order_date <= '{$to}' ";
            }
        }

        $speed = $params ? $params['speedFilters'] : Input::get('speedFilters');
        $speedWhere = '';
        if (!empty($speed))
        {
            $v = [];
            foreach ($speed as $val)
            {
                if ($val['include']) $v[] = "'" . str_replace("'", '', $val['label']) . "'";
            }
            if (count($v) > 0)
                $speedWhere = " AND speed IN (" . implode(',', $v) . ") ";
        }

        $limits = '';

        if (!$getAll) {
            $rows = DB::select(<<<EOQ
SELECT COUNT(*) as c
FROM eoc.sales s
WHERE s.fulfilment = 3
AND s.status > 1
AND s.status < 90
{$keywordsWhere}
{$statusWhere}
{$speedWhere}
{$dateWhere}
EOQ
            );

            $orderCount = $rows[0]['c'];
            $pageSize = $params ? $params['ps'] : Input::get('ps');
            if (empty($pageSize)) $pageSize = 50;
            $numPages = max(ceil($orderCount / $pageSize), 1);

            $page = $params ? $params['page'] : Input::get('page');
            if (empty($page) || $page <= 0) $page = 1;
            else if ($page > $numPages) $page = $numPages;

            $offset = $pageSize * ($page - 1);

            $limits = " LIMIT {$offset}, {$pageSize} ";
        }
        else $numPages = 'all';

        $orders = DB::select(<<<EOQ
SELECT s.id AS id,
s.order_date AS order_date,
s.record_num AS record_num,
s.speed AS speed,
s.status AS status,
IF((s.supplier < 1),
	(SELECT GROUP_CONCAT(DISTINCT CONCAT('W', d.supplier) ORDER BY d.supplier)
	FROM eoc.direct_shipments d, eoc.direct_shipments_sales ds
	WHERE d.order_id = ds.order_id
	AND ds.sales_id = s.id),
	CONCAT('W', s.supplier)) AS supplier,
IF(s.etd > '0000-00-00', s.etd,
	(SELECT d.etd
	FROM eoc.direct_shipments d, eoc.direct_shipments_sales ds
	WHERE d.order_id = ds.order_id
	AND ds.sales_id = s.id
	ORDER BY d.is_bulk DESC, d.id LIMIT 1)) AS etd,
(   SELECT DATE_FORMAT(create_date, '%Y-%m-%d %H:%i')
    FROM eoc.stamps st
	WHERE st.sales_id = s.id
	ORDER BY 1 DESC
	LIMIT 1) AS validation_date,
(   SELECT DATE_FORMAT(print_date, '%Y-%m-%d %H:%i')
    FROM eoc.stamps st
	WHERE st.sales_id = s.id
	ORDER BY 1 DESC
	LIMIT 1) AS label_date,
(SELECT oh.remarks
    FROM integra_prod.order_history oh, integra_prod.users u
    WHERE oh.order_id = s.id
    AND u.email = ?
    AND NOT (u.group_name = 'Sales' AND oh.hide_sales = 1)
    AND NOT (u.group_name = 'Data' AND oh.hide_data = 1)
    AND NOT (u.group_name = 'Pricing' AND oh.hide_pricing = 1)
    AND NOT (u.group_name = 'Shipping' AND oh.hide_shipping = 1)
    AND oh.remarks > ''
    ORDER BY oh.ts DESC
    LIMIT 1) AS last_remarks
FROM eoc.sales s
WHERE s.fulfilment = 3
AND s.status > 1
AND s.status < 90
{$keywordsWhere}
{$statusWhere}
{$speedWhere}
{$dateWhere}
ORDER BY order_date DESC
{$limits}
EOQ
            , [Cookie::get('user')]);

        return ['orders' => $orders, 'pages' => $numPages];
    }

    public function history($orderId)
    {
        return DB::select(<<<EOQ
SELECT oh.ts, REPLACE(oh.email, '@eocenterprise.com', '') AS email, oh.remarks
FROM integra_prod.order_history oh, integra_prod.users u
WHERE oh.order_id = ?
AND u.email = ?
AND NOT (u.group_name = 'Sales' AND oh.hide_sales = 1)
AND NOT (u.group_name = 'Data' AND oh.hide_data = 1)
AND NOT (u.group_name = 'Pricing' AND oh.hide_pricing = 1)
AND NOT (u.group_name = 'Shipping' AND oh.hide_shipping = 1)
AND oh.remarks > ''
ORDER BY oh.ts
EOQ
            , [$orderId, Cookie::get('user')]);
    }

    public function addHistory($orderId)
    {
        $rows = DB::select('SELECT group_name FROM integra_prod.users WHERE email = ?', [Cookie::get('user')]);

        if (empty($rows)) return;
        $group = $rows[0]['group_name'];

        $now = date('Y-m-d H:i:s');

        DB::insert(<<<EOQ
INSERT INTO integra_prod.order_history (order_id, ts, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)
EOQ
            , [$orderId, $now, Cookie::get('user'), Input::get('remarks'),
                (!Input::get('show_sales') && $group != 'Sales') ? 1 : 0,
                (!Input::get('show_data') && $group != 'Data') ? 1 : 0,
                (!Input::get('show_pricing') && $group != 'Pricing') ? 1 : 0,
                (!Input::get('show_shipping') && $group != 'Shipping') ? 1 : 0
            ]);

        return ['ts' => $now, 'email' => str_replace('@eocenterprise.com', '', Cookie::get('user')), 'remarks' => Input::get('remarks')];
    }

    public function customerServiceCreateOrder() {
        try
        {
            $totalSupplierCost = 0;
            $totalWeight = 0;
            $supplier = Supplier::getSupplierByName('EOC');

            foreach (Input::get('items') as $item)
            {
                if (empty($item['sku']) || empty($item['quantity'])) continue;

                $totalSupplierCost += 0; //$item['price'] * $item['quantity'];
                $totalWeight += 0; //$item['weight'] * $item['quantity'];


            }

            $merchantCode = Input::get('merchant.code');
            if (empty($merchantCode)) $merchantCode = 'Manual';

            $entry = new Order();
            $entry->store = $merchantCode;
            $entry->record_num = Input::get('record_num');
            $entry->order_date = Carbon::now();
            $entry->total = Input::get('total');
            $entry->email = Input::get('email');
            $entry->buyer_name = Input::get('name');
            $entry->buyer_id = Input::get('name');
            $entry->street = Input::get('address');
            $entry->city = Input::get('city');
            $entry->state = Input::get('state');
            $entry->country = Input::get('country');
            $entry->zip = Input::get('zip');
            $entry->phone = Input::get('phone');
            $entry->speed= Input::get('speed')['id'];
            if (Input::get('related_sales_id')) $entry->related_sales_id = Input::get('related_sales_id');
            $entry->tracking_num = '';
            $entry->carrier = '';
            $entry->agent = Input::get('agent');
            $entry->fulfilment = Input::get('fulfillment')['id'];
            $entry->status = Input::get('status')['id'];
            $entry->supplier_cost = $totalSupplierCost;
            $entry->weight = $totalWeight;
            $entry->supplier = $supplier;
            $entry->save();

            $orderId = Order::where('email', $entry->email)->where('record_num', $entry->record_num)->orderBy('id', 'desc')->pluck('id');

            foreach (Input::get('items') as $item)
            {
                if (empty($item['sku']) || empty($item['quantity'])) continue;

                $si = new OrderItem();
                $si->sales_id = $orderId;
                $si->sku = $item['sku'];
                //$si->description = $item['description'];
                $si->quantity = $item['quantity'];
                //$si->unit_price = $item['price'];
                //$si->total = $item['price'] * $item['quantity'];
                $si->save();
            }

            $email = (Cookie::get('user') != null && Cookie::get('user') != '') ? Cookie::get('user') : 'customerservice@eocenterprise.com';

            DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 0, 1, 1, 1)', [$orderId, $email, Input::get('remarks')]);

            return ["order_id" => $orderId];
        }
        catch (Exception $e)
        {
            return Response::json(array
            (
                "msg" => 'Error while saving the order. Please check your data. ' . $e->getMessage()
            ), 500);
        }
    }

    public function store()
    {
        try
        {
            $totalSupplierCost = 0;
            $totalWeight = 0;
            $supplier = 0;

            foreach (Input::get('items') as $item)
            {
                if (empty($item['sku']) || empty($item['quantity'])) continue;

                $totalSupplierCost += $item['price'] * $item['quantity'];
                $totalWeight += $item['weight'] * $item['quantity'];

                if ($supplier == 0)
                {
                    $supplier = $item['supplier'];
                }
                else
                {
                    if ($supplier != $item['supplier'])
                        $supplier = -2;
                }

            }

            $merchantCode = Input::get('merchant.code');
            if (empty($merchantCode)) $merchantCode = 'Manual';

            $entry = new Order();
            $entry->store = $merchantCode;
            $entry->record_num = Input::get('record_num');
            $entry->order_date = Carbon::now();
            $entry->total = Input::get('total');
            $entry->email = Input::get('email');
            $entry->buyer_name = Input::get('name');
            $entry->buyer_id = Input::get('name');
            $entry->street = Input::get('address');
            $entry->city = Input::get('city');
            $entry->state = Input::get('state');
            $entry->country = Input::get('country');
            $entry->zip = Input::get('zip');
            $entry->phone = Input::get('phone');
            $entry->speed= Input::get('speed')['id'];
            if (Input::get('related_sales_id')) $entry->related_sales_id = Input::get('related_sales_id');
            $entry->tracking_num = '';
            $entry->carrier = '';
            $entry->agent = Input::get('agent');
            $entry->fulfilment = Input::get('fulfillment')['id'];
            $entry->status = Input::get('status')['id'];
            $entry->supplier_cost = $totalSupplierCost;
            $entry->weight = $totalWeight;
            $entry->supplier = $supplier;
            $entry->save();

            $orderId = Order::where('email', $entry->email)->where('record_num', $entry->record_num)->orderBy('id', 'desc')->pluck('id');

            foreach (Input::get('items') as $item)
            {
                if (empty($item['sku']) || empty($item['quantity'])) continue;

                $si = new OrderItem();
                $si->sales_id = $orderId;
                $si->sku = $item['sku'];
                $si->description = $item['description'];
                $si->quantity = $item['quantity'];
                $si->unit_price = $item['price'];
                $si->total = $item['price'] * $item['quantity'];
                $si->save();
            }


            DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 0, 1, 1, 1)', [$orderId, Cookie::get('user'), "Order created"]);

            return ["order_id" => $orderId];
        }
        catch (Exception $e)
        {
            return Response::json(array
            (
                "msg" => 'Error while saving the order. Please check your data. ' . $e->getMessage()
            ), 500);
        }
    }

    public function searchRecordNum($recordNum)
    {
        return Order::with('items')->where('record_num', strtoupper(trim($recordNum)))->orderBy('order_date', 'desc')->first();
    }

    public function bulkEtd()
    {
        try {
            $id = Input::get('id');
            if ((stripos($id, '2') === 0) && (strlen($id) == 9))
                $id = '0' . $id;

            $etd = strtotime(Input::get('etd'));
            if ($etd < strtotime("-1 month")) return '0';
            else $etd = date('Y-m-d', $etd);

            DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) (SELECT s.id, ?, ?, 1, 1, 1, 0 FROM eoc.sales s, eoc.direct_shipments_sales dss WHERE s.id = dss.sales_id AND dss.order_id = ? AND s.etd != ?)',
                [Cookie::get('user'), "ETD set to: " . $etd, $id, $etd]);
            DB::update('UPDATE eoc.sales s, eoc.direct_shipments_sales dss SET s.etd = ? WHERE s.id = dss.sales_id AND dss.order_id = ?', [$etd, $id]);
            return '1';
        }
        catch (Exception $e)
        {
            return Response::json(array
            (
                "msg" => $e->getMessage()
            ), 500);
        }
    }

    public function bulkDispatch()
    {
        try {
            $id = Input::get('id');
            if ((stripos($id, '2') === 0) && (strlen($id) == 9))
                $id = '0' . $id;

            DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) (SELECT s.id, ?, ?, 0, 1, 1, 1 FROM eoc.sales s, eoc.direct_shipments_sales dss WHERE s.id = dss.sales_id AND dss.order_id = ? AND s.status < 3 AND s.fulfilment = 3)',
                [Cookie::get('user'), "Status set to: Ready for Dispatch", $id]);
            DB::update('UPDATE eoc.sales s, eoc.direct_shipments_sales dss SET s.status = 3 WHERE s.id = dss.sales_id AND dss.order_id = ? AND s.status < 3 AND s.fulfilment = 3', [$id]);

            return '1';
        }
        catch (Exception $e)
        {
            return Response::json(array
            (
                "msg" => $e->getMessage()
            ), 500);
        }
    }

    public function updateFulfilment()
    {
        try {
            DB::update('UPDATE eoc.sales SET fulfilment = ? WHERE id = ?', [Input::get('code'), Input::get('id')]);
            DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 0, 1, 1, 1)', [Input::get('id'), Cookie::get('user'), "Fulfillment set to: " . IntegraUtils::getFulfillmentCodes()[Input::get('code')]]);
            return Input::get('code');
        }
        catch (Exception $e)
        {
            return Response::json(array
            (
                "msg" => $e->getMessage()
            ), 500);
        }
    }

    public function updateEtd()
    {
        try {
            $etd = strtotime(Input::get('etd'));
            if ($etd < strtotime("-1 month")) return '0';
            else $etd = date('Y-m-d', $etd);

            DB::update('UPDATE eoc.sales SET etd = ? WHERE id = ?', [$etd, Input::get('id')]);
            DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 1, 1, 1, 0)', [Input::get('id'), Cookie::get('user'), "ETD set to: " . $etd]);
            return '1';
        }
        catch (Exception $e)
        {
            return Response::json(array
            (
                "msg" => $e->getMessage()
            ), 500);
        }
    }

    public function updateStatus()
    {
        try {
            DB::update('UPDATE eoc.sales SET status = ? WHERE id = ?', [Input::get('code'), Input::get('id')]);
            DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 0, 1, 1, 1)', [Input::get('id'), Cookie::get('user'), "Status set to: " . IntegraUtils::getStatusCodes()[Input::get('code')]]);
            return Input::get('code');
        }
        catch (Exception $e)
        {
            return Response::json(array
            (
                "msg" => $e->getMessage()
            ), 500);
        }
    }

    public function orderInvoice($id)
    {
        return $this->invoice($id, false);
    }

    public function returnInvoice($id)
    {
        return $this->invoice($id, true);
    }

    public function invoice($id, $isReturn = false)
    {
        $user = Cookie::get('user');

        if (empty($user))
        {
            $u = Input::get('u');
            if (empty($u)) return 'Not logged in';

            $rows = DB::select('SELECT email FROM integra_prod.users WHERE MD5(email) = ?', [Input::get('u')]);
            if (empty($rows)) return 'Not logged in';
            $user = $rows[0];
        }

        $rows = DB::select('SELECT internal_id, order_date, record_num, buyer_name, street, city, state, zip, country, phone, store, total FROM eoc.sales WHERE id = ?', [$id]);
        if (empty($rows)) return '';
        $order = $rows[0];

        $rows = DB::select('SELECT street, city, state, zip, phone FROM eoc.pickup_sites WHERE shipping_only = 1 LIMIT 1');
        if (empty($rows)) return '';
        $order['our_street'] = $rows[0]['street'];
        $order['our_city'] = $rows[0]['city'];
        $order['our_state'] = $rows[0]['state'];
        $order['our_zip'] = $rows[0]['zip'];
        $order['our_phone'] = $rows[0]['phone'];

        $lines = DB::select('SELECT sku_noprefix, description, quantity, unit_price, total FROM eoc.sales_items WHERE sales_id = ?', [$id]);
        if (empty($lines)) return '';

        $rows = DB::select('SELECT base_currency_code, base_to_global_rate FROM magento.sales_flat_order WHERE increment_id = ?', [$order['internal_id']]);
        if (!empty($rows)) // convert
        {
            $rate = $rows[0]['base_to_global_rate'];
            $order['currency'] = $rows[0]['base_currency_code'];
        }
        else
        {
            $rate = 1;
            $order['currency'] = 'USD';
        }

        $itemTotal = 0;
        foreach ($lines as &$line)
        {
            $line['total'] /= $rate;
            $itemTotal += $line['total'];

            // break down components of kit (if it's a kit)
            if (strpos($line['sku_noprefix'], 'EK') === 0)
            {
                $components = DB::select(<<<EOQ
SELECT pc.sku, CONCAT(pc.brand, ' ', pc.name) AS description, kc.quantity
FROM products pk, products pc, kit_components kc
WHERE pk.sku = ?
AND pk.id = kc.product_id
AND pc.id = kc.component_product_id
EOQ
                    , [explode('$', $line['sku_noprefix'])[0]]);

                foreach ($components as $component)
                    $line['components'][] = ['sku' => $component['sku'], 'description' => $component['description'], 'quantity' => $component['quantity']];
            }
        }

        $order['total'] /= $rate;
        $order['shipping_handling'] = $order['total'] - $itemTotal;

        $rows = DB::select("SELECT name, magento_code FROM integra_prod.stores WHERE code = ?", [$order['store']]);
        if (!empty($rows))
        {
            $order['our_name'] = $rows[0]['name'];
            $order['store'] = $rows[0]['magento_code'];
        }

        if (empty($order['our_name'])) $order['our_name'] = 'Q&E Auto Parts';
        if (empty($order['store'])) $order['store'] = 'qeautoparts';

        DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 0, 1, 1, 0)',
            [$id, $user, ($isReturn ? 'Return' : 'Order') . ' invoice printed']);

        return View::make(($isReturn ? 'return_invoice' : 'order_invoice'), compact('order', 'lines'));
    }

    public function linkSupplierOrder()
    {
        $salesId = Input::get('salesId');
        $supplier = trim(Input::get('supplier'));
        $supplierOrderId = trim(Input::get('supplierOrderId'));

        if (!empty($salesId) && !empty($supplier) && !empty($supplierOrderId))
        {
            DB::insert('INSERT IGNORE INTO eoc.direct_shipments_sales (sales_id, order_id) VALUES (?, ?)', [$salesId, $supplierOrderId]);
            DB::insert('INSERT IGNORE INTO eoc.direct_shipments (sales_id, order_id, supplier, is_bulk) VALUES (?, ?, ?, 1)', [$salesId, $supplierOrderId, $supplier]);
            DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 1, 1, 1, 1)', [$salesId, Cookie::get('user'), "Linked to W" . $supplier . " order #" . $supplierOrderId]);
            return '1';
        }
    }

    public function downloadList()
    {
        set_time_limit(0);

        $res = $this->search();
        $rows = $res['orders'];

        $downloadsDir = public_path('downloads');

        if (!is_dir($downloadsDir))
            mkdir($downloadsDir);

        $baseFile = 'sales' . time() . '.csv';
        $file = $downloadsDir . DIRECTORY_SEPARATOR . $baseFile;
        $fp = fopen($file, 'w');

        fputcsv($fp, ["order_date", "record_num", "store", "internal_id", "agent", "buyer_name", "buyer_id", "fulfilment", "status", "class", "tracking", "last_remarks", "total"]);

        $fulfilment = [
            0 => 'Unspecified',
            1 => 'Direct',
            2 => 'Pickup',
            3 => 'EOC'
        ];

        $status = [
            0 => 'Unspecified',
            1 => 'Scheduled',
            2 => 'Item Ordered',
            3 => 'Ready for Dispatch',
            4 => 'Order Complete',
            90 => 'Cancelled',
            91 => 'Payment Pending',
            92 => 'Return Pending',
            93 => 'Return Complete',
            94 => 'Refund Pending',
            99 => 'Error',
            100 => 'Reshipment',
            101 => 'Exchange',
            102 => 'Non-Delivered',
            103 => 'Pending Refund 1',
            104 => 'Pending Refund 2',
            105 => 'Pending Refund 3'
        ];

        foreach ($rows as $row)
        {
            fputcsv($fp, [
                $row['order_date'],
                $row['record_num'],
                $row['store'],
                $row['internal_id'],
                $row['agent'],
                $row['buyer_name'],
                $row['buyer_id'],
                isset($fulfilment[$row['fulfilment']]) ? $fulfilment[$row['fulfilment']] : '',
                isset($status[$row['st0atus']]) ? $status[$row['status']]: '',
                $row['speed'],
                $row['tracking_num'],
                $row['last_remarks'],
                $row['total']]);
        }

        fclose($fp);

        return $baseFile;
    }

    public function updateTracking()
    {
        $salesId = Input::get('salesId');
        $newTracking = Input::get('trackingNum');
        $newCarrier = Input::get('carrier');

        if (empty($newTracking)) return;
        if (empty($newCarrier)) return;

        $rows = DB::select("SELECT tracking_num FROM eoc.sales WHERE id = ?", [$salesId]);
        if (empty($rows)) return;

        $oldTracking = $rows[0]['tracking_num'];

        DB::update("UPDATE eoc.sales SET tracking_num = ?, carrier = ? WHERE id = ?", [$newTracking, $newCarrier, $salesId]);
        DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 0, 1, 1, 1)', [$salesId, Cookie::get('user'), "Tracking set to: " . $newTracking . " - " . $newCarrier]);

        if (empty($oldTracking)) // only post tracking number if this is the first time
        {
            file_get_contents("http://integra.eocenterprise.com/tracking.php?sales_id=${salesId}");
        }

        return '1';
    }

    public function sendEmail()
    {
        $order = Input::get('order');
        $templateId = Input::get('templateId');

        $rows = DB::select("SELECT subject, body FROM integra_prod.templates WHERE id = ?", [$templateId]);
        if (empty($rows)) return;

        $subject = $rows[0]['subject'];
        $body = $rows[0]['body'];

        $rows = DB::select("SELECT name, email, phone FROM integra_prod.stores WHERE code = ?", [$order['store']]);
        if (empty($rows)) return;

        foreach ($order as $key => $val)
            if (!is_array($val)) $vars[strtoupper($key)] = $val;

        $storeName = $rows[0]['name'];
        $storeEmail = $rows[0]['email'];

        $vars['STORE_NAME'] = $storeName;
        $vars['STORE_EMAIL'] = $storeEmail;
        $vars['STORE_PHONE'] = $rows[0]['phone'];

        foreach ($vars as $key => $val)
        {
            $subject = str_replace("[{$key}]", $val, $subject);
            $body = str_replace("[{$key}]", htmlentities($val), $body);
        }

        $config = Config::get('integra');

        $transport = \Swift_SmtpTransport::newInstance($config['smtp']['host'], $config['smtp']['port'])
            ->setUsername($storeEmail)
            ->setPassword($config['smtp']['password']);

        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom([$storeEmail => $storeName])
            ->setTo($order['email'])
            ->setBody($body, 'text/html');

        $mailer = Swift_Mailer::newInstance($transport);
        $result = $mailer->send($message);

        DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 0, 1, 1, 1)', [$order['id'], Cookie::get('user'), "Sent email: {$subject}"]);

        return $result;
    }

    public function uploadBulkLink()
    {
        try
        {
            $file = Input::file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            if ($extension != 'csv')
                return Response::json('Only CSV files are supported.', 500);

            $directory = public_path() . "/uploads/bulk_link";
            if (!is_dir($directory)) mkdir($directory);

            $filename = sha1(time() . '_' . $file->getClientOriginalName()) . ".${extension}";
            $upload_success = Input::file('file')->move($directory, $filename);

            $inserted = 0;

            if ($upload_success)
            {
                Log::info("Uploaded success");
                $lines = file("{$directory}/$filename", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $lineCount = count($lines);

                Log::info("NUMBER OF LINE: ".$lineCount);

                for ($i = 0; $i < $lineCount; $i++)
                {
                    $cols = str_getcsv(trim($lines[$i]), ",");

                    // if 6 columns cannot be extracted, try semicolon
                    if (count($cols) < 6)
                    {
                        $cols = str_getcsv(trim($lines[$i]), ";");

                        // skip lines with insufficient columns
                        if (count($cols) < 6)
                            continue;
                    }

                    $recordNum = trim($cols[0]);
                    $supplier = intval(trim($cols[1]));
                    $supplierOrderId = trim($cols[2]);
                    $pounds = floatval(trim($cols[3]));
                    $ounces = floatval(trim($cols[4]));
                    $etd = trim($cols[5]);
                    $remarks = count($cols) >= 7 ? trim($cols[6]) : null;
                    if($remarks == null)
                    {
                        $remarks = "Linked to W" . $supplier . " order #" . $supplierOrderId;
                    }
                    $sku = count($cols) >= 8 ? trim($cols[7]) : null;

                    $quantity = count($cols) >= 9 ? trim($cols[8]) : null;

                    $weight = $pounds + ($ounces / 16);

                    try
                    {
                        $rows = DB::select("SELECT id FROM eoc.sales WHERE record_num = ?", [$recordNum]);
                        if (empty($rows)) continue;
                        $salesId = $rows[0]['id'];

                        if($quantity == "") $quantity = null;
                        if($sku == "") $sku = null;

                        DB::insert('INSERT IGNORE INTO eoc.direct_shipments_sales (sales_id, order_id) VALUES (?, ?)', [$salesId, $supplierOrderId]);
                        DB::insert('INSERT IGNORE INTO eoc.direct_shipments (sales_id, order_id, supplier, is_bulk, etd) VALUES (?, ?, ?, 1, ?)', [$salesId, $supplierOrderId, $supplier, $etd]);
                        DB::insert('INSERT INTO integra_prod.order_history (order_id, email, remarks, hide_sales, hide_data, hide_pricing, hide_shipping) VALUES (?, ?, ?, 1, 1, 1, 1)', [$salesId, Cookie::get('user'), $remarks]);

                        DB::update('UPDATE eoc.sales_items as i SET i.sku = COALESCE(?, i.sku), i.quantity = COALESCE(?, i.quantity ) WHERE i.sales_id = ?', [$sku, $quantity, $salesId]);

                        $updated = DB::update('UPDATE eoc.sales as s SET s.weight = ?, s.status = 2, s.fulfilment = 3, s.etd = ? WHERE s.id = ?', [$weight, $etd, $salesId]);

                        if ($updated > 0)
                            $inserted++;
                    }
                    catch (Exception $e)
                    {
                        Log::error("ERROR: ".$e->getMessage());
                    }
                }

                return array("newCount" => $inserted);
            }
        }
        catch (Exception $e)
        {
            return Response::json('Make sure that your file has the correct format. Duplicates will be skipped.', 500);
        }
    }

    public function skuWeeklyQueue()
    {
        DB::insert("INSERT INTO integra_prod.sku_weekly_queue (email, start_date, end_date, store, queue_date) VALUES(?, ?, ?, ?, NOW())",
        [ Cookie::get('user'), Input::get('start'), Input::get('end'), Input::get('store')] );

        return '1';
    }

    public function skuWeeklyList()
    {
        return DB::select("SELECT email, start_date, end_date, store, status, progress, output_file FROM integra_prod.sku_weekly_queue");
    }

    public function searchByEmail()
    {
        return DB::select("SELECT id, store, order_date, record_num, total, tracking_num, carrier, status FROM eoc.sales WHERE email = ?", [Input::get('email')]);
    }
}
