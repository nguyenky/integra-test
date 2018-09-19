<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class AmazonRepricer extends Command
{
	protected $name = 'amazon:repricer';
    protected $description = 'Submits repricing feed to Amazon.';

	public function __construct()
	{
		parent::__construct();
	}

	public function fire()
	{
		$tempId = '_' . time();

		DB::update(<<<EOQ
UPDATE integra_prod.amazon_repricer_queue
SET feed_id = ?, proc_date = NOW()
WHERE feed_id = ''
EOQ
				, [$tempId]);

		$items = DB::select(<<<EOQ
SELECT al.sku, arq.price
FROM integra_prod.amazon_repricer_queue arq, eoc.amazon_listings al
WHERE arq.feed_id = ?
AND al.asin = arq.asin
EOQ
				, [$tempId]);

		if (empty($items)) return;

		$feedId = AmazonUtils::Reprice($items);
        if ($feedId)
		{
			DB::update(<<<EOQ
UPDATE integra_prod.amazon_repricer_queue
SET feed_id = ?, proc_date = NOW()
WHERE feed_id = ?
EOQ
					, [$feedId, $tempId]);
		}
	}
}
