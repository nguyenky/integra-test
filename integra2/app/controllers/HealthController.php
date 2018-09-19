<?php

use \Carbon\Carbon;

class HealthController extends \BaseController
{
    private static $_settings = null;

    public static function settings()
    {
        if (empty(self::$_settings))
        {
            $res = DB::select("SELECT name, val FROM integra_prod.health_settings");
            foreach ($res as $r) self::$_settings[$r['name']] = $r['val'];
        }
        
        return self::$_settings;
    }

    public function loadSettings()
    {
        return self::settings();
    }

    public function saveSettings()
    {
        foreach (Input::get('settings') as $key => $value)
        {
            $safeKey = preg_replace('/[^A-Za-z0-9_\\-]/', '', $key);
            DB::update("UPDATE integra_prod.health_settings SET val = ? WHERE name = ?", [$value, $safeKey]);
        }
        
        self::$_settings = null;

        return '1';
    }

    public function ebayMonitor()
    {
        $rows = DB::select(<<<EOQ
SELECT MIN(last_scraped) AS first, MAX(last_scraped) AS last
FROM eoc.ebay_monitor
WHERE disable = 0
AND deleted = 0
EOQ
            );

        $first = strtotime($rows[0]['first']);
        $last = strtotime($rows[0]['last']);

        if ($last < strtotime('-' . self::settings()['ebay_monitor_not_scraping_hours'] . ' hours'))
        {
            $res['remarks'] = 'Not scraping!';
            $res['status'] = 'danger';
        }
        else if ($first < strtotime('-' . self::settings()['ebay_monitor_danger_delayed_hours'] . ' hours'))
        {
            $res['remarks'] = 'Scraping too slow!';
            $res['status'] = 'danger';
        }
        else if ($first < strtotime('-' . self::settings()['ebay_monitor_warning_delayed_hours'] . ' hours'))
        {
            $res['remarks'] = 'Scraping too slow!';
            $res['status'] = 'warning';
        }
        else // ok
        {
            $res['remarks'] = 'OK.';
            $res['status'] = 'success';
        }

        $res['remarks'] .= " Oldest: " . date("Y-m-d H:i", $first) . ". Newest: " . date("Y-m-d H:i", $last);

        return $res;
    }

    public function amazonScraper()
    {
        $rows = DB::select(<<<EOQ
SELECT MIN(last_scraped) AS first, MAX(last_scraped) AS last
FROM eoc.amazon_listings
WHERE active = 1
EOQ
        );

        $first = strtotime($rows[0]['first']);
        $last = strtotime($rows[0]['last']);

        if ($last < strtotime('-' . self::settings()['amazon_scraper_not_scraping_hours'] . ' hours'))
        {
            $res['remarks'] = 'Not scraping!';
            $res['status'] = 'danger';
        }
        else if ($first < strtotime('-' . self::settings()['amazon_scraper_danger_delayed_hours'] . ' hours'))
        {
            $res['remarks'] = 'Scraping too slow!';
            $res['status'] = 'danger';
        }
        else if ($first < strtotime('-' . self::settings()['amazon_scraper_warning_delayed_hours'] . ' hours'))
        {
            $res['remarks'] = 'Scraping too slow!';
            $res['status'] = 'warning';
        }
        else // ok
        {
            $res['remarks'] = 'OK.';
            $res['status'] = 'success';
        }

        $res['remarks'] .= " Oldest: " . date("Y-m-d H:i", $first) . ". Newest: " . date("Y-m-d H:i", $last);

        return $res;
    }

    public function ebayInventory()
    {
        $last = strtotime(exec("grep ReviseInventoryStatus /var/www/webroot/ROOT/integra1/logs/ebay_inv.txt | tail -1 | cut -f1 -d']'"));
        $lastDownload = strtotime(exec("grep ActiveInventoryReport /var/www/webroot/ROOT/integra1/logs/ebay_inv.txt | tail -1 | cut -f1 -d']'"));
        $lastKits = strtotime(exec("stat -c \"%y\" /tmp/kit_revise | cut -f1 -d."));
        $last = min($last, $lastDownload, $lastKits);

        if ($last < strtotime('-' . self::settings()['ebay_inventory_no_revisions_hours'] . ' hours'))
        {
            $res['remarks'] = 'No revisions today!';
            $res['status'] = 'danger';
        }
        else // ok
        {
            $res['remarks'] = 'OK.';
            $res['status'] = 'success';
        }

        $res['remarks'] .= " Last revision: " . date("Y-m-d H:i", $last);

        return $res;
    }

    public function imcBulkOrderMorning()
    {
        $dayOfWeek = date('w');

        if ($dayOfWeek == 0)
        {
            $res['remarks'] = 'No Sunday orders.';
            $res['status'] = 'success';
        }
        else
        {
            $skedTime = self::settings()['imc_bulk_order_morning_time'];
            $searchFrom = self::settings()['imc_bulk_order_morning_time_from'];
            $searchTo = self::settings()['imc_bulk_order_morning_time_to'];
            $skedTimeToday = strtotime(date('Y-m-d') . ' ' . $skedTime) + (self::settings()['imc_bulk_order_allow_mins'] * 60);
            $now = time();

            if ($now <= $skedTimeToday)
            {
                $res['remarks'] = "Scheduled today at {$skedTime}";
                $res['status'] = 'success';
            }
            else
            {
                $rows = DB::select(<<<EOQ
SELECT order_id, order_date
FROM eoc.direct_shipments
WHERE supplier = 1
AND is_bulk = 1
AND order_date >= CONCAT(CURDATE(), ' ', '{$searchFrom}:00')
AND order_date < CONCAT(CURDATE(), ' ', '{$searchTo}:00')
ORDER BY order_date DESC
LIMIT 1
EOQ
                );

                if (empty($rows))
                {
                    $res['remarks'] = 'Order placement failed!';
                    $res['status'] = 'danger';
                }
                else
                {
                    $orderId = $rows[0]['order_id'];
                    $orderDate = date('Y-m-d H:i', strtotime($rows[0]['order_date']));

                    $res['remarks'] = "Order #{$orderId} placed at {$orderDate}";
                    $res['status'] = 'success';
                }
            }
        }

        return $res;
    }

    public function imcBulkOrderNoon()
    {
        $dayOfWeek = date('w');

        if ($dayOfWeek == 0 || $dayOfWeek == 6)
        {
            $res['remarks'] = 'No weekend orders.';
            $res['status'] = 'success';
        }
        else
        {
            $skedTime = self::settings()['imc_bulk_order_noon_time'];
            $searchFrom = self::settings()['imc_bulk_order_noon_time_from'];
            $searchTo = self::settings()['imc_bulk_order_noon_time_to'];
            $skedTimeToday = strtotime(date('Y-m-d') . ' ' . $skedTime) + (self::settings()['imc_bulk_order_allow_mins'] * 60);
            $now = time();

            if ($now <= $skedTimeToday)
            {
                $res['remarks'] = "Scheduled today at {$skedTime}";
                $res['status'] = 'success';
            }
            else
            {
                $rows = DB::select(<<<EOQ
SELECT order_id, order_date
FROM eoc.direct_shipments
WHERE supplier = 1
AND is_bulk = 1
AND order_date >= CONCAT(CURDATE(), ' ', '{$searchFrom}:00')
AND order_date < CONCAT(CURDATE(), ' ', '{$searchTo}:00')
ORDER BY order_date DESC
LIMIT 1
EOQ
                );

                if (empty($rows))
                {
                    $res['remarks'] = 'Order placement failed!';
                    $res['status'] = 'danger';
                }
                else
                {
                    $orderId = $rows[0]['order_id'];
                    $orderDate = date('Y-m-d H:i', strtotime($rows[0]['order_date']));

                    $res['remarks'] = "Order #{$orderId} placed at {$orderDate}";
                    $res['status'] = 'success';
                }
            }
        }

        return $res;
    }

    public function ssfBulkOrder()
    {
        $dayOfWeek = date('w');

        if ($dayOfWeek == 0 || $dayOfWeek == 6)
        {
            $res['remarks'] = 'No weekend orders.';
            $res['status'] = 'success';
        }
        else
        {
            $skedTime = self::settings()['ssf_bulk_order_time'];
            $skedTimeToday = strtotime(date('Y-m-d') . ' ' . $skedTime) + (self::settings()['ssf_bulk_order_allow_mins'] * 60);
            $now = time();

            if ($now <= $skedTimeToday)
            {
                $res['remarks'] = "Scheduled today at {$skedTime}";
                $res['status'] = 'success';
            }
            else
            {
                $rows = DB::select(<<<EOQ
SELECT order_id, order_date
FROM eoc.direct_shipments
WHERE supplier = 2
AND is_bulk = 1
AND order_date >= CURDATE()
ORDER BY order_date DESC
LIMIT 1
EOQ
                );

                if (empty($rows))
                {
                    $res['remarks'] = 'Order placement failed!';
                    $res['status'] = 'danger';
                }
                else
                {
                    $orderId = $rows[0]['order_id'];
                    $orderDate = date('Y-m-d H:i', strtotime($rows[0]['order_date']));

                    $res['remarks'] = "Order #{$orderId} placed at {$orderDate}";
                    $res['status'] = 'success';
                }
            }
        }

        return $res;
    }

    public function ebay_api_call_counters(){
        $count = EbayApiCallCounter::where('timestamp','>=',\Carbon\Carbon::now()->subHours(24))->count();
        $res['remarks'] = $count.' calling in 24 hours';
        $res['status'] = 'success';
        return $res;

    }
    public function countersDetail(){
        $data = EbayApiCallCounter::where('timestamp','>=',\Carbon\Carbon::now()->subHours(24))->selectRaw('count(*) as total,feature_name,token')->groupBy('feature_name')->groupBy('token')->get();

        $res['status'] = 'success';
        $res['data'] = $data;
        return $res;
    }
}
