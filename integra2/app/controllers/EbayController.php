<?php

class EbayController extends \BaseController
{


    public function reports() {
        $filter = Input::get('filter');
        $createdChartData = array();
        $editedChartData = array();
        $createdStats = array();
        $editedStats = array();

        $productivityModel = new Productivity();
        if($filter) {
            $viewMode = $filter['viewMode'];
            $startDate = DateTime::createFromFormat('Y-m-d', $filter['startDate']);
            $endDate = DateTime::createFromFormat('Y-m-d', $filter['endDate']);
            $createds = $productivityModel->getEbayCreated($viewMode, $startDate, $endDate);

            $createdChartData = $productivityModel->buildDataToDisplayOnChart($createds, $startDate, $endDate, $viewMode);

            $editeds = $productivityModel->getEbayEdited($viewMode, $startDate, $endDate);

            $editedChartData = $productivityModel->buildDataToDisplayOnChart($editeds, $startDate, $endDate, $viewMode);

            $createdStats = $productivityModel->getCreatedStats($startDate, $endDate);

            $editedStats = $productivityModel->getEditedStats($startDate, $endDate);

        } 
        
        return array('createdChartData' => $createdChartData, 'editedChartData' => $editedChartData,
                'createdStats' => $createdStats, 'editedStats' => $editedStats);

    }

    public function graph()
    {
        if (Input::has('days'))
            $days = Input::get('days');
        else $days = 2;

        $created = DB::select(<<<EOQ
SELECT DATE_FORMAT( created_on,  '%m/%d %H:00' ) AS d, created_by AS u, COUNT(DISTINCT item_id) AS c
FROM eoc.ebay_edit_log
WHERE created_on >= DATE_SUB( CURDATE( ) , INTERVAL ? DAY )
AND is_new = 1
GROUP BY 1, 2
ORDER BY created_on
EOQ
            , [$days]);

        $edited = DB::select(<<<EOQ
SELECT DATE_FORMAT( created_on,  '%m/%d %H:00' ) AS d, created_by AS u, COUNT(DISTINCT item_id) AS c
FROM eoc.ebay_edit_log
WHERE created_on >= DATE_SUB( CURDATE( ) , INTERVAL ? DAY )
AND is_new = 0
GROUP BY 1, 2
ORDER BY created_on
EOQ
            , [$days]);

        DB::statement("CALL integra_prod.compute_ebay_edit_productivity(CURDATE())");

        $statsEdited = DB::select(<<<EOQ
SELECT DATE(hr) AS date, REPLACE(email, '@eocenterprise.com', '') AS user, 
    FORMAT(AVG(output), 2) AS average, FORMAT(STD(output), 2) AS variance, 
    SUM(output) AS total, MIN(output) AS min, MAX(output) AS max, 
    ROUND(SUM(IF(output >= 18, 1, 0)) * 100 / COUNT(*)) AS hit_pct, 
    SUM(IF(output < 18, -1, 0)) + ROUND(SQRT(AVG(output) * SUM(output) / (STD(output)+1))) AS score
FROM productivity
WHERE task = 2
AND hr >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
GROUP BY 1, 2
ORDER BY 1 DESC, 8 DESC
EOQ
            , [$days]);

        DB::statement("CALL integra_prod.compute_ebay_new_productivity(CURDATE())");

        $statsCreated = DB::select(<<<EOQ
SELECT DATE(hr) AS date, REPLACE(email, '@eocenterprise.com', '') AS user, 
    FORMAT(AVG(output), 2) AS average, FORMAT(STD(output), 2) AS variance, 
    SUM(output) AS total, MIN(output) AS min, MAX(output) AS max, 
    ROUND(SUM(IF(output >= 8, 1, 0)) * 100 / COUNT(*)) AS hit_pct, 
    SUM(IF(output < 8, -1, 0)) + ROUND(SQRT(AVG(output) * SUM(output) / (STD(output)+1))) AS score
FROM productivity
WHERE task = 3
AND hr >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
GROUP BY 1, 2
ORDER BY 1 DESC, 8 DESC
EOQ
            , [$days]);

        return ['edited' => $edited, 'created' => $created, 'stats_edited' => $statsEdited, 'stats_created' => $statsCreated];
    }

    public function rawPreviewV2()
    {
        return EbayUtils::RenderTemplateV2(
            Input::get('title'),
            Input::get('desc'),
            Input::get('brand'),
            Input::get('condition'),
            Input::get('partNumbers'),
            Input::get('notes'),
            Input::get('ranges'));
    }

    public function generateVersions()
    {
        // expects POST of {mpn, versions: [{num, qty, price}]}
        $mpn = Input::get('mpn');
        $versions = Input::get('versions');

        $basic = IntegraUtils::queryMpn($mpn);

        $ret = [];

        foreach ($versions as $version)
        {
            $titles = IntegraUtils::generateTitles($mpn, $version['qty'], $version['num'], $basic['entity_id'], $basic['name'], $basic['brand']);

            $angle = 5;
            $increment = ceil(350 / count($titles));

            foreach ($titles as $title)
            {
                $v['title'] = $title;
                $v['qty'] = $version['qty'];
                $v['price'] = $version['price'];
                $v['mpn'] = $mpn;
                $v['brand'] = IntegraUtils::tokenTruncate($basic['brand'], 65);
                $v['ipn'] = IntegraUtils::tokenTruncate($basic['codes'], 65);
                $v['opn'] = IntegraUtils::tokenTruncate($basic['codes'], 65);

                $v['picture'] = "http://catalog.eocenterprise.com/img/" . str_replace('-', '', $mpn) . "/cl1-loqe-boqe-rt{$angle}-br1-tr1" . ($version['qty'] > 1 ? ('-qt' . $version['qty']) : '');
                $angle += $increment;

                $notes = 'This Listing Sells ' . $version['qty'] . ' Part' . ($version['qty'] > 1 ? 's' : '');
                if (!empty($basic['notes'])) $notes .= "\n" . $basic['notes'];

                $v['description'] = $basic['name'];
                $v['notes'] = $notes;

                $ret[] = $v;
            }
        }

        // returns array of {title, qty, price, picture, mpn, brand, description, notes}
        return $ret;
    }

    public function publish()
    {
        // expects POST of {title, qty, price, description, picture, mpn, brand, ipn?, opn?, surface?, placement?, notes}

        $title = Input::get('title');
        $qty = Input::get('qty');
        $price = Input::get('price');

        $description = Input::get('description');
        $notes = Input::get('notes');
        $picture = Input::get('picture');

        $attribs['mpn'] = Input::get('mpn');
        $attribs['brand'] = Input::get('brand');

        $conditionId = (stripos($attribs['brand'], 'reman') !== false) ? EbayUtils::$remanConditionId : EbayUtils::$newConditionId;

        if (Input::has('ipn')) $attribs['ipn'] = Input::get('ipn');
        if (Input::has('opn')) $attribs['opn'] = Input::get('opn');
        if (Input::has('surface')) $attribs['surface'] = Input::get('surface');
        if (Input::has('placement')) $attribs['placement'] = Input::get('placement');
        if (Input::has('warranty')) $attribs['warranty'] = Input::get('warranty');
        else $attribs['warranty'] = '1 year';

        // load compatibility and format
        $compats = DB::select("SELECT make, model, year, notes FROM integra_prod.compatibilities WHERE ebay_ok = 1 AND sku = ?", [$attribs['mpn']]);
        $compatibility = '<ItemCompatibilityList>';

        foreach ($compats as $compat)
        {
            $compatNotes = trim(str_replace(';;', ';', str_replace('; ;', '; ', str_replace('  ', ' ', preg_replace("/Qty:\\s*\\(\\d+\\)/i", '', $compat['notes'])))), " \t\n\r\0\x0B;");

            $compatibility .= '<Compatibility><NameValueList/><NameValueList><Name>Year</Name><Value>' . $compat['year'] . '</Value></NameValueList>';
            $compatibility .= '<NameValueList><Name>Make</Name><Value><![CDATA[' . $compat['make'] . ']]></Value></NameValueList>';
            $compatibility .= '<NameValueList><Name>Model</Name><Value><![CDATA[' . $compat['model'] . ']]></Value></NameValueList>';
            $compatibility .= '<CompatibilityNotes><![CDATA[' . $compatNotes . ']]></CompatibilityNotes></Compatibility>';
        }

        $compatibility .= '</ItemCompatibilityList>';

        // generate EDP from mpn-qty, store in sku
        if ($qty > 1) $baseSku = $attribs['mpn'] . "-{$qty}";
        else $baseSku = $attribs['mpn'];

        $dupeSku = App::make('ProductController')->dupeProduct($baseSku, stripos($attribs['mpn'], '.') ? 2 : 1);

        // pregenerate image, replace picture contents with alias
        file_get_contents("{$picture},al{$dupeSku}");
        $picture = 'http://catalog.eocenterprise.com/img/' . str_replace('-', '', $attribs['mpn']) . "/al{$dupeSku}";

        // returns {ack, item_id, error}
        return EbayUtils::ListItem($dupeSku, $title, $qty, $price, $description, $picture, $conditionId, $attribs, $compatibility, $notes);
    }

    public function queueKitHunter()
    {
        return IntegraUtils::tryFunc(function()
        {
            $elements = [];

            if (Input::get('job_type') == 1) {
                foreach (Input::get('components') as $component) {
                    $fields = explode('~', str_replace('[', '~', str_replace(']', '', $component)));
                    $element = trim($fields[0]);

                    if (count($fields) == 2)
                        $element .= '~' . trim($fields[1]);

                    $elements[] = $element;
                }
            }
            else if (Input::get('job_type') == 2) {
                foreach (Input::get('components') as $component) {
                    $fields = explode('~', str_replace(' ', '', $component));
                    $element = trim($fields[0]);

                    if (count($fields) == 2)
                        $element .= '~' . trim($fields[1]);

                    $elements[] = $element;
                }
            }

            $addons = [];
            foreach (Input::get('addons') as $addon)
            {
                $addons[] = $addon['supplier'] . '~' . $addon['qty'] . '~' . $addon['mpn'];
            }

            DB::insert("INSERT INTO integra_prod.kit_hunter_queue (queue_date, elements, addons, base_title, profit_pct, shipping, make, job_type, versions) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    implode('|', $elements),
                    implode('|', $addons),
                    Input::get('base_title'),
                    Input::get('profit_pct'),
                    Input::get('shipping'),
                    Input::get('make'),
                    Input::get('job_type'),
                    Input::get('versions')
                ]);
            $queueId = DB::getPdo()->lastInsertId();

            if (Input::get('job_type') == 1) {
                DB::insert("INSERT INTO integra_prod.kit_hunter_proc (queue_id, vehicle_id, status) (SELECT ?, ed.id, 0 FROM magento.elite_1_definition ed WHERE ed.make_id > 0 AND ed.model_id > 0 AND ed.year_id > 0 AND make = ? ORDER BY ed.match_rank DESC)",
                    [$queueId, Input::get('make')]);
            }

            return ["msg" => 'The kit hunting job was successfully queued.'];
        });
    }

    public function viewKitHunter($id, $page = 1)
    {
        $rows = DB::select("SELECT COUNT(*) as c FROM integra_prod.products pk WHERE kit_hunter_id = ? AND is_kit = 1", [$id]);
        $kitCount = $rows[0]['c'];

        $pageSize = 20;
        $numPages = max(ceil($kitCount / $pageSize), 1);

        if ($page <= 0) $page = 1;
        else if ($page > $numPages) $page = $numPages;

        $offset = $pageSize * ($page - 1);

        $kits = DB::select("SELECT pk.supplier_id, pk.sku, pk.id, pk.name, pk.publish_status, pk.ebay_id, pk.ebay_price FROM integra_prod.products pk WHERE kit_hunter_id = ? AND is_kit = 1 ORDER BY pk.id LIMIT ?, ?", [$id, $offset, $pageSize]);

        $allKits = [];

        foreach ($kits as $k)
        {
            $components = DB::select(<<<EOQ
SELECT pc.sku, pc.name, pc.brand, kc.quantity
FROM integra_prod.products pc, integra_prod.kit_components kc
WHERE kc.component_product_id = pc.id
AND kc.product_id = ?
EOQ
                , [$k['id']]);

            $allKits[$k['supplier_id']][] = ['sku' => $k['sku'], 'ebay_price' => $k['ebay_price'], 'ebay_id' => $k['ebay_id'], 'url' => (empty($k['ebay_id']) ? '' : 'http://www.ebay.com/itm/' . $k['ebay_id']), 'publish_status' => $k['publish_status'], 'name' => $k['name'], 'components' => $components];
        }

        $allSuppliers = array_keys($allKits);
        $ret = [];

        foreach ($allSuppliers as $supplier)
        {
            $ret[] = ['id' => $supplier, 'kits' => $allKits[$supplier]];
        }

        return ['suppliers' => $ret, 'pages' => $numPages];
    }

    public function deleteKit($sku)
    {
        DB::delete(<<<EOQ
DELETE FROM integra_prod.kit_components
WHERE product_id IN (
    SELECT id FROM integra_prod.products
    WHERE sku = ?
    AND is_kit = 1
    AND ebay_id IS NULL
    AND kit_hunter_id IS NOT NULL
)
EOQ
        , [$sku]);

        DB::delete(<<<EOQ
DELETE FROM integra_prod.products
WHERE sku = ?
AND is_kit = 1
AND ebay_id IS NULL
AND kit_hunter_id IS NOT NULL
EOQ
        , [$sku]);

        return '';
    }

    public function publishKit($sku)
    {
        DB::update(<<<EOQ
UPDATE integra_prod.products
SET publish_status = 1
WHERE sku = ?
AND is_kit = 1
AND ebay_id IS NULL
AND kit_hunter_id IS NOT NULL
AND publish_status = 0
EOQ
            , [$sku]);

        return '';
    }

    public function searchPartType($keyword)
    {
        $types = DB::select(<<<EOQ
SELECT IF (position > '', CONCAT(name, ' [', position, ']'), name) AS display
FROM integra_prod.part_types
WHERE name LIKE CONCAT('%', ?, '%')
ORDER BY LOCATE(?, name), name
LIMIT 20
EOQ
            , [$keyword, $keyword]);

        $ret = [];

        foreach ($types as $type)
            $ret[] = $type['display'];

        return $ret;
    }

    public function listMakes()
    {
        $rows = DB::select("SELECT title FROM magento.elite_level_1_make ORDER BY title");
        $ret = [];

        foreach ($rows as $row)
            $ret[] = $row['title'];

        return $ret;
    }

    public function listKitHunter()
    {
        $jobs = DB::select(<<<EOQ
SELECT id, queue_date, base_title, make, elements, job_type,
IF(job_type=4,100,(SELECT IFNULL(CEIL(SUM(IF(status = 2, 1, 0)) * 100 / COUNT(*)), 0) FROM integra_prod.kit_hunter_proc khp WHERE khp.queue_id = khq.id)) AS progress,
(SELECT COUNT(*) FROM integra_prod.products p WHERE p.is_kit = 1 AND p.kit_hunter_id = khq.id) AS kits_found,
(SELECT COUNT(*) FROM integra_prod.products p WHERE p.is_kit = 1 AND p.kit_hunter_id = khq.id AND p.ebay_id > '') AS kits_listed
FROM integra_prod.kit_hunter_queue khq
EOQ
        );

        foreach ($jobs as &$job)
        {
            $components = [];
            $elements = explode('|', $job['elements']);

            foreach ($elements as $element)
            {
                $fields = explode('~', $element);
                $component = $fields[0];

                if (count($fields) == 2)
                {
                    if ($job['job_type'] == 1)
                        $component .= ' [' . $fields[1] . ']';
                    else $component .= 'x ' . $fields[1];
                }

                $components[] = $component;
            }

            sort($components);

            $job['components'] = implode(', ', $components);
            unset($job['elements']);
        }

        return IntegraUtils::paginate($jobs);
    }

    public function listMonitor($page = 1)
    {
        $where = '';

        if (Input::has('search'))
        {
            $search = trim(str_replace('%', '', str_replace("'", '', Input::get('search'))));
            if (!empty($search)) $where = " AND (keywords LIKE '{$search}%' OR item_id LIKE '{$search}%') ";
        }

        $rows = DB::select("SELECT COUNT(DISTINCT keywords) as c FROM eoc.ebay_monitor WHERE disable = 0 {$where}");
        $kwCount = $rows[0]['c'];

        $pageSize = 50;
        $numPages = max(ceil($kwCount / $pageSize), 1);

        if ($page <= 0) $page = 1;
        else if ($page > $numPages) $page = $numPages;

        $offset = $pageSize * ($page - 1);

        $kwList = ["'~'"]; // dummy entry to avoid empty IN query
        $kws = DB::select(<<<EOQ
SELECT keywords, MAX(below_min), MAX(started_selling), MAX(changed), MAX(deleted), MAX(last_scraped)
FROM eoc.ebay_monitor
WHERE disable = 0 {$where}
GROUP BY keywords
ORDER BY MAX(below_min) DESC, MAX(started_selling) DESC, MAX(changed) DESC, MAX(deleted) DESC, MAX(last_scraped) DESC
LIMIT ?, ?
EOQ
            , [$offset, $pageSize]);
        foreach ($kws as $k) $kwList[] = "'" . trim($k['keywords']) . "'";

        $kws = implode(', ', $kwList);

        $rows = DB::select(<<<EOQ
SELECT keywords, item_id, cur_title, prev_title, cur_price, prev_price, cur_sold, prev_sold,
last_scraped, TIMESTAMPDIFF(HOUR, prev_scrape, last_scraped) AS hours, changed, started_selling, deleted, below_min
FROM eoc.ebay_monitor
WHERE disable = 0 AND keywords IN ({$kws})
ORDER BY below_min DESC, started_selling DESC, changed DESC, deleted DESC, last_scraped DESC
EOQ
        );

        $keywords = [];

        foreach ($rows as $row)
        {
            $item = [];
            $item['id'] = $row['item_id'];
            $item['url'] = 'http://www.ebay.com/itm/' . $row['item_id'];
            $item['cur_title'] = $row['cur_title'];
            $item['prev_title'] = $row['prev_title'];
            $item['cur_price'] = $row['cur_price'];
            $item['prev_price'] = $row['prev_price'];
            $item['cur_sold'] = $row['cur_sold'];
            $item['prev_sold'] = $row['prev_sold'];
            $item['last_scraped'] = $row['last_scraped'];
            $item['days'] = round($row['hours'] / 24, 1);
            $item['changed'] = $row['changed'];
            $item['deleted'] = $row['deleted'];
            $item['started_selling'] = $row['started_selling'];
            $item['below_min'] = $row['below_min'];
            $item['sold_change'] = $item['cur_sold'] - $item['prev_sold'];

            $kw = trim($row['keywords']);

            if (!array_key_exists($kw, $keywords))
                $keywords[$kw] = ['keywords' => $kw, 'items' => [], 'changed' => false, 'deleted' => false, 'started_selling' => false, 'below_min' => false];

            $keywords[$kw]['items'][] = $item;
            $keywords[$kw]['changed'] |= $item['changed'];
            $keywords[$kw]['deleted'] |= $item['deleted'];
            $keywords[$kw]['started_selling'] |= $item['started_selling'];
            $keywords[$kw]['below_min'] |= $item['below_min'];
        }

        return ['keywords' => array_values($keywords), 'pages' => $numPages];
    }

    public function ackMonitor($id)
    {
        DB::update(<<<EOQ
UPDATE eoc.ebay_monitor
SET prev_title = cur_title,
prev_price = cur_price,
prev_sold = cur_sold,
prev_scrape = last_scraped,
below_min = 0
WHERE item_id = ?
EOQ
        , [$id]);
    }

    public function unmonitor($id)
    {
        DB::update(<<<EOQ
UPDATE eoc.ebay_monitor
SET disable = 0
WHERE item_id = ?
EOQ
            , [$id]);
    }

    public function previewV2($id)
    {
        $res = file_get_contents("http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=XML&appid="
            . EbayUtils::$appId . "&siteid=0&version=847&ItemID={$id}&IncludeSelector=Compatibility,Description,ItemSpecifics");
        EbayApiCallCounter::create(['ebay_service_name'=>'GetSingleItem']);
        $xml = simplexml_load_string($res);

        if ($xml->Ack != 'Success' && $xml->Ack != 'Warning')
            return 'Invalid item ID';

        $title = trim((string)$xml->Item->Title);
        $condition = (string)$xml->Item->ConditionDisplayName;
        $brand = '';
        $mpns = [];
        $notes = [];

        $html = (string) $xml->Item->Description;

        $startDesc = 'padding: 5px 5px 5px 0px;">';
        $idx = stripos($html, $startDesc);
        if ($idx > 0)
        {
            $html = substr($html, $idx + strlen($startDesc));
            $endDesc = '<img';
            $idx = stripos($html, $endDesc);
            if ($idx === false)
            {
                $endDesc = '___________________________';
                $idx = stripos($html, $endDesc);
            }
            if ($idx === false) return 'Unrecognized listing template (or already upgraded)';
            $html = substr($html, 0, $idx);

            $endDesc = 'Item Condition:';
            $idx = stripos($html, $endDesc);
            $html2 = substr($html, 0, $idx);

            preg_match_all("/(?<str>[^<>]+)</i", $html2, $matches, PREG_SET_ORDER);
            foreach ($matches as $m)
            {
                if (!isset($m['str'])) continue;
                $tm = trim($m['str']);
                if (!empty($tm)
                    && $tm != $title
                    && $tm != 'N/A'
                    && stripos($tm, 'not available') === false)
                    $notes[html_entity_decode($tm)] = 1;
            }

            preg_match("/Part Description:(?<str>[^<]+)/i", $html, $matches);
            if (isset($matches['str'])) $desc = trim($matches['str']);

            preg_match("/Part Notes?:(?<str>[^<]+)/i", $html, $matches);
            if (isset($matches['str']))
            {
                $tm = trim($matches['str']);
                if (!empty($tm)
                    && $tm != $title
                    && $tm != 'N/A'
                    && stripos($tm, 'not available') === false)
                    $notes[html_entity_decode($tm)] = 1;
            }

            preg_match("/Part Numbers?:(?<str>[^<]+)/i", $html, $matches);
            if (isset($matches['str']))
            {
                $vals = explode(';', str_replace('/', ';', str_replace(',', ';', html_entity_decode($matches['str']))));

                foreach ($vals as $v)
                    $mpns[strtoupper(trim($v))] = 1;
            }
        }
        else return 'Unrecognized listing template (or already upgraded)';

        if (empty($notes))
            $notes['N/A'] = 1;

        if (empty($desc))
            $desc = $title;

        if (!empty($xml->Item->ItemSpecifics) && !empty($xml->Item->ItemSpecifics->NameValueList))
        {
            foreach ($xml->Item->ItemSpecifics->NameValueList as $pair)
            {
                if ($pair->Name == 'Manufacturer Part Number'
                    || $pair->Name == 'Interchange Part Number'
                    ||$pair->Name == 'Other Part Number')
                {
                    $vals = explode(';', str_replace('/', ';', str_replace(',', ';', html_entity_decode((string)$pair->Value))));

                    foreach ($vals as $v)
                        $mpns[strtoupper(trim($v))] = 1;
                }
                else if ($pair->Name == 'Part Brand' || $pair->Name == 'Brand')
                    $brand = (string)$pair->Value;
            }
        }

        $compats = [];

        if (isset($xml->Item->ItemCompatibilityList))
        {
            foreach ($xml->Item->ItemCompatibilityList->children() as $comp) {
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

        ksort($compats);
        $ranges = [];

        foreach ($compats as $makeModel => $compat)
        {
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

        $notes = array_keys($notes);
        $desc = explode("\n", $desc);

        return View::make('ebay_v2', compact('mpns', 'title', 'brand', 'condition', 'desc', 'notes', 'ranges'));
    }

    public function upgradeTemplate($id)
    {
        $res = $this->previewV2($id);
        if (is_string($res))
        {
            return ['ack' => 'Error', 'error' => $res];
        }
        else return EbayUtils::ReviseTemplate($id, $res->render());
    }

    public function suspend($id)
    {
        return EbayUtils::Suspend($id, Input::get('reason'));
    }

    public function resume($id)
    {
        DB::update(<<<EOQ
UPDATE eoc.ebay_listings
SET suspended = 0, resumed_on = NOW()
WHERE item_id = ?
EOQ
            , [$id]);

        return '1';
    }

    public function suspendList()
    {
        return DB::select(<<<EOQ
SELECT item_id, suspended, suspended_on, REPLACE(suspended_by, '@eocenterprise.com', '') AS suspended_by, suspend_reason, resumed_on
FROM eoc.ebay_listings
WHERE suspended_on IS NOT NULL
ORDER BY suspended_on
EOQ
            );
    }

    public function downloadCostWeight()
    {
        set_time_limit(0);

        $rows = DB::select(<<<EOQ
SELECT item_id, sku, REPLACE(REPLACE(REPLACE(components, ',', ' / '), '$', ' / '), '~', '-') AS components, total_weight, total_cost, price AS selling_price, min_price
FROM eoc.ebay_listings
WHERE active = 1
EOQ
        );
        
        $file = storage_path('ebay_cost_weight.csv');
        
        file_put_contents($file, "item_id, sku, components, total_weight, total_cost, selling_price, min_price\n");

        foreach ($rows as $row)
        {
            file_put_contents($file,
                $row['item_id'] . ',' .
                $row['sku'] . ',' .
                $row['components'] . ',' .
                $row['total_weight'] . ',' .
                $row['total_cost'] . ',' .
                $row['selling_price'] . ',' .
                $row['min_price'] . "\n", FILE_APPEND);
        }

        return Response::download($file, 'ebay_cost_weight.csv', ['Content-Type' => 'text/csv']);
    }

    public function downloadMonitor()
    {
        set_time_limit(0);

        $rows = DB::select(<<<EOQ
SELECT
	em.id,
	em.keywords,
	em.changed,
	em.below_min,
	em.started_selling,
	em.deleted,
	(
		SELECT eg.seller
		FROM eoc.ebay_grid eg
		WHERE eg.this_item = em.item_id
		AND eg.seller > ''
		LIMIT 1
	) AS seller,
	em.item_id AS competition_item_id,
	em.cur_sold AS competition_sold_qty,
	em.cur_price AS competition_price,
	em.vs_ours AS our_item_id,
	el.sold AS our_sold_qty,
	el.price AS our_price,
	el.min_price,
	IF(em.strategy = 1, 0.00, -0.01) AS rate,
	el.sku AS our_sku,
	em.last_scraped,
	(
		SELECT eel.created_by
		FROM eoc.ebay_edit_log eel
		WHERE eel.item_id = em.vs_ours
		AND eel.edited_field = 'Price'
		ORDER BY eel.created_by DESC
		LIMIT 1
	) AS eoc_agent,
	"" AS new_our_item_id,
	"" AS new_min_price,
	"" AS new_rate,
	"" AS disable
FROM eoc.ebay_listings el, eoc.ebay_monitor em
WHERE el.item_id = em.vs_ours
AND em.disable = 0
AND em.strategy IN (1, 2)
UNION ALL
SELECT
	em.id,
	em.keywords,
	em.changed,
	em.below_min,
	em.started_selling,
	em.deleted,
	(
		SELECT eg.seller
		FROM eoc.ebay_grid eg
		WHERE eg.this_item = em.item_id
		AND eg.seller > ''
		LIMIT 1
	) AS seller,
	em.item_id AS competition_item_id,
	em.cur_sold AS competition_sold_qty,
	em.cur_price AS competition_price,
	el.item_id AS our_item_id,
	el.sold AS our_sold_qty,
	el.price AS our_price,
	el.min_price,
	emm.variance AS rate,
	el.sku AS our_sku,
	em.last_scraped,
	(
		SELECT eel.created_by
		FROM eoc.ebay_edit_log eel
		WHERE eel.item_id = el.item_id
		AND eel.edited_field = 'Price'
		ORDER BY eel.created_by DESC
		LIMIT 1
	) AS eoc_agent,
	"" AS new_our_item_id,
	"" AS new_min_price,
	"" AS new_rate,
	"" AS disable
FROM eoc.ebay_listings el, eoc.ebay_monitor em, integra_prod.ebay_monitor_matrix emm
WHERE el.item_id = emm.our_item_id
AND em.disable = 0
AND em.strategy = 99
AND emm.competitor_item_id = em.item_id
EOQ
        );

        $file = storage_path('monitor_' . time() . '.csv');

        file_put_contents($file, "id,keywords,changed,below_min,started_selling,deleted,seller,competition_item_id,competition_sold_qty,competition_price,our_item_id,our_sold_qty,our_price,min_price,rate,our_sku,last_scraped,eoc_agent,new_our_item_id,new_min_price,new_rate,disable\n");

        foreach ($rows as $row)
        {
            file_put_contents($file,
                $row['id'] . ',' .
                $row['keywords'] . ',' .
                $row['changed'] . ',' .
                $row['below_min'] . ',' .
                $row['started_selling'] . ',' .
                $row['deleted'] . ',' .
                $row['seller'] . ',' .
                $row['competition_item_id'] . ',' .
                $row['competition_sold_qty'] . ',' .
                $row['competition_price'] . ',' .
                $row['our_item_id'] . ',' .
                $row['our_sold_qty'] . ',' .
                $row['our_price'] . ',' .
                $row['min_price'] . ',' .
                $row['rate'] . ',' .
                $row['our_sku'] . ',' .
                $row['last_scraped'] . ',' .
                $row['eoc_agent'] . ',' .
                $row['new_our_item_id'] . ',' .
                $row['new_min_price'] . ',' .
                $row['new_rate'] . ',' .
                $row['disable'] . "\n", FILE_APPEND);
        }

        return Response::download($file, 'ebay_monitor.csv', ['Content-Type' => 'text/csv']);
    }


    public function migrateNewVsOur() {
        try {
            $file = Input::file('file');

            $extension = strtolower($file->getClientOriginalExtension());
            if ($extension != 'csv')
                return Response::json('Only CSV files are supported.', 500);

            $directory = public_path() . "/uploads/monitor";
            if (!is_dir($directory)) { 
                mkdir($directory ); 
            }

            $filename = sha1(time())."_ebay_monitor_migration.csv";

            $upload_success = Input::file('file')->move($directory, $filename);

            if($upload_success) {
                Artisan::call('ebay_monitor:migrate', ['file_name' => $filename]);
            }
            return array(
                'success' => $upload_success, 
                'message' => ($upload_success ? "File uploaded successfully. A Job has called for migrating." : "Upload file failed.")
            );

        } catch(Exception $ex) {
            Log::error($ex->getMessage());
        }
    }



    public function uploadMonitor()
    {
        try
        {
            $file = Input::file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            if ($extension != 'csv')
                return Response::json('Only CSV files are supported.', 500);

            $directory = public_path() . "/uploads/monitor";
            if (!is_dir($directory)) mkdir($directory);

            $filename = sha1(time() . '_' . $file->getClientOriginalName()) . ".${extension}";
            $upload_success = Input::file('file')->move($directory, $filename);

            $updated = 0;

            if ($upload_success)
            {
                $lines = file("{$directory}/$filename", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                if (count($lines) < 2)
                    return Response::json('Make sure that your file follows the template and includes the header.', 500);

                $lineCount = count($lines);

                for ($i = 1; $i < $lineCount; $i++)
                {
                    $cols = str_getcsv(trim($lines[$i]), ",");

                    // if 2 columns cannot be extracted, try semicolon
                    if (count($cols) < 22)
                    {
                        $cols = str_getcsv(trim($lines[$i]), ";");

                        // skip lines with insufficient columns
                        if (count($cols) < 22) {
                            error_log("Line $i only has " . count($cols) . ' columns');
                            continue;
                        }
                    }

                    $id = intval($cols[0]);

                    if (empty($id))
                        continue;

                    $oldItemId = trim($cols[7]);
                    $oldOurItemId = trim($cols[10]);

                    $processed = false;

                    try
                    {
                        $newOurItemId = trim($cols[18]);
                        $newRate = trim($cols[20]);

                        if ($newOurItemId != '' || $newRate != '')
                        {
                            $processed = true;
                            $rows = DB::select("SELECT strategy FROM eoc.ebay_monitor WHERE id = ?", [$id]);
                            if (empty($rows)) continue;
                            $strategy = $rows[0]['strategy'];

                            // upgrade to matrix format
                            if ($strategy != 99)
                            {
                                DB::update("DELETE FROM integra_prod.ebay_monitor_matrix WHERE competitor_item_id = ? AND our_item_id = ?", [$oldItemId, $oldOurItemId]);
                                DB::update("
INSERT IGNORE INTO integra_prod.ebay_monitor_matrix
(competitor_item_id, our_item_id, variance, can_increase_price)
(SELECT item_id, vs_ours, IF(strategy = 1, 0.00, -0.01), 0
FROM eoc.ebay_monitor
WHERE id = ?)", [$id]);
                                DB::update("UPDATE eoc.ebay_monitor SET vs_ours = NULL, strategy = 99 WHERE id = ?", [$id]);
                            }
                        }

                        if ($newOurItemId != '')
                            DB::update("UPDATE integra_prod.ebay_monitor_matrix SET our_item_id = ? WHERE competitor_item_id = ? AND our_item_id = ?", [$newOurItemId, $oldItemId, $oldOurItemId]);

                        if ($newRate != '')
                            DB::update("UPDATE integra_prod.ebay_monitor_matrix SET variance = ? WHERE competitor_item_id = ? AND our_item_id = ?", [$newRate, $oldItemId, $oldOurItemId]);
                    }
                    catch (Exception $e)
                    {
                        error_log($e->getMessage());
                    }

                    if ($newOurItemId == '')
                        $newOurItemId = $oldOurItemId;

                    try
                    {
                        $newMinPrice = trim($cols[19]);
                        if (!empty($newMinPrice))
                        {
                            $processed = true;
                            DB::update("
UPDATE eoc.ebay_listings el
SET el.min_price = ?
WHERE el.item_id = ?", [$newMinPrice, $newOurItemId]);
                        }
                    }
                    catch (Exception $e)
                    {
                        error_log($e->getMessage());
                    }

                    try
                    {
                        $disable = trim($cols[21]);
                        if ($disable == '1')
                        {
                            $processed = true;
                            DB::update("UPDATE eoc.ebay_monitor SET disable = 1 WHERE id = ?", [$id]);
                        }
                    }
                    catch (Exception $e)
                    {
                        error_log($e->getMessage());
                    }

                    if ($processed)
                        $updated++;
                }

                return array("count" => $updated);
            }
        }
        catch (Exception $e)
        {
            return Response::json('Make sure that your file follows the template.', 500);
        }
    }
}
