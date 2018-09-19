<?php

use Illuminate\Console\Command;

class ReportsSkuWeekly extends Command
{
	protected $name = 'reports:sku_weekly';
    protected $description = 'Generates weekly SKU sales report.';

	public function __construct()
	{
		parent::__construct();
	}

	public function fire()
    {
        DB::disableQueryLog();
        set_time_limit(0);

        $rows = DB::select(<<<EOQ
SELECT id, start_date, end_date, store
FROM integra_prod.sku_weekly_queue
WHERE status = 0
ORDER BY queue_date
LIMIT 1
EOQ
        );

        if (empty($rows)) return;

        $id = $rows[0]['id'];
        $store = $rows[0]['store'];
        $start = $rows[0]['start_date'];
        $end = $rows[0]['end_date'];

        $baseFile = "sku_weekly_{$store}_{$start}_{$end}_{$id}.csv";

        DB::update('UPDATE integra_prod.sku_weekly_queue SET status = 1, output_file = ?, process_date = NOW() WHERE id = ?', [$baseFile, $id]);

        $start = new DateTime($start);
        $end = new DateTime($end);
        $dates['sku'] = '';

        while ($start <= $end)
        {
            $dates[$start->format("o-W")] = $start->format('Y-m-d');
            $start->add(new DateInterval('P7D'));

            // up to 300 weeks max
            if (count($dates) > 301) break;
        }

        $skus = DB::select('SELECT sku FROM eoc.sold_skus WHERE store = ?', [$store]);

        $downloadsDir = public_path('downloads');

        if (!is_dir($downloadsDir))
            mkdir($downloadsDir);

        $file = $downloadsDir . DIRECTORY_SEPARATOR . $baseFile;
        $fp = fopen($file, 'w');

        fputcsv($fp, array_keys($dates));

        array_shift($dates);

        $doneCtr = 0;

        foreach ($skus as $s)
        {
            $sku = $s['sku'];
            $cols = [$sku];

            foreach ($dates as $header => $date)
            {
                $res = DB::select(<<<EOQ
SELECT IFNULL(SUM(quantity), 0) AS s
FROM eoc.sales s, eoc.sales_items si
WHERE s.id = si.sales_id
AND s.order_date >= ?
AND s.order_date < DATE_ADD(?, INTERVAL 7 DAY)
AND si.master_sku = ?
AND s.store = ?
EOQ
                    , [$date, $date, $sku, $store]);

                $cols[] = $res[0]['s'];
            }

            fputcsv($fp, $cols);

            $doneCtr++;
            DB::update('UPDATE integra_prod.sku_weekly_queue SET status = 1, progress = ? WHERE id = ?', [ceil(100 * $doneCtr / count($skus)), $id]);
        }

        fclose($fp);

        DB::update('UPDATE integra_prod.sku_weekly_queue SET status = 2, complete_date = NOW() WHERE id = ?', [$id]);
    }
}
