<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class EbayMonitorMigration extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'ebay_monitor:migrate';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Migrate old vs_ours';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		Log::info("================ Start job EbayMonitorMigration ===============");
		try {

			$filename = $this->argument('file_name');

			$directory = public_path() . "/uploads/monitor";
			
			Log::info("Processing file: ".$filename);

			$lines = file("{$directory}/$filename", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			Log::info("Num lines: ".count($lines));
			for($i = 1; $i < count($lines); $i++) {
				Log::info($lines[$i]);

				$cols = str_getcsv(trim($lines[$i]), ",");
				if (count($cols) < 2) {
					$cols = str_getcsv(trim($lines[$i]), ";");
					if (count($cols) < 2) {
						continue;
					} 
				}
				$this->processData($cols);
			}
			unlink("{$directory}/$filename");
		} catch(Exception $ex) {
			Log::error("============= Exception in EbayMonitorMigration job =============");
			Log::error($ex->getMessage());
		}
	}

	protected function processData($cols) {
		/*$sql = "
			UPDATE eoc.ebay_monitor 
			SET vs_ours = ".$cols[0].
			" WHERE vs_ours = ".$cols[1]
		;

		DB::statement($sql);
		*/

		DB::table('eoc.ebay_monitor')->where('vs_ours', $cols[0])->update(array('vs_ours' => $cols[1]));
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('file_name', InputArgument::REQUIRED, 'An file_name argument.'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
		);
	}

}
