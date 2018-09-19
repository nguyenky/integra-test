<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class AmazonListProducts extends Command
{
	protected $name = 'amazon:list_products';
    protected $description = 'Submits a product feed to Amazon to list products from the queue.';

	public function __construct()
	{
		parent::__construct();
	}

	public function fire()
	{
        $items = DB::select('SELECT id, asin, sku, price, quantity, `condition`, currency FROM integra_prod.amazon_listing_queue WHERE status = 0 ORDER BY queue_date LIMIT 100');
        if (!empty($items)) AmazonUtils::ListByASIN($items);
	}
}
