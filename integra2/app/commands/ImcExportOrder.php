<?php

use Illuminate\Console\Command;

class ImcExportOrder extends Command
{
	protected $name = 'imc:order_export';
    protected $description = 'Process IMC order queue via export account.';

	public function __construct()
	{
		parent::__construct();
	}

	public function fire()
	{
		DB::disableQueryLog();

		DB::statement("TRUNCATE TABLE eoc.imc_order_queue");

		// temporary solution until the new rule-based mechanism is in place
		file_get_contents("http://integra.eocenterprise.com/jobs/auto_order_eoc.php");

		ImcUtils::OrderWebExport();
	}
}
