<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ImcExportScrape extends Command
{
	protected $name = 'imc:scrape_export';
    protected $description = 'Scrapes IMC export products.';

	public function __construct()
	{
		parent::__construct();
	}

	public function fire()
	{
		DB::disableQueryLog();

		$mpns = DB::select(<<<EOQ
SELECT q.mpn
FROM eoc.imc_qty q LEFT JOIN integra_prod.imc_export_items i ON q.mpn = i.mpn_unspaced
ORDER BY i.last_scraped
LIMIT ?
EOQ
		, [$this->argument('max_items')]);

		ImcUtils::ScrapeExportPrice($mpns, $this->argument('username'), $this->argument('password'));
	}

	protected function getArguments()
	{
		return array
		(
			array('max_items', InputArgument::REQUIRED, 'Max number of items to scrape'),
			array('username', InputArgument::REQUIRED, 'IMC username'),
			array('password', InputArgument::REQUIRED, 'IMC password'),
		);
	}
}
