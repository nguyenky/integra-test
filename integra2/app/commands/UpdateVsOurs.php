<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateVsOurs extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'ebay:update_vs_ours';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Update new vs_ours field';

	public function update_vs_ours($old_vs_ours,$new_vs_ours){
        DB::update(<<<EOQ
UPDATE eoc.ebay_monitor
SET vs_ours = ?
WHERE vs_ours = ?
EOQ
            , [$old_vs_ours,$new_vs_ours]);
    }

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
		$file = $this->argument('file');
		$i=0;
        while(! feof($file))
          {
          $lines[$i] = (fgetcsv($file));
          $i++;
          }

        fclose($file);
        foreach($lines as $k => $line)
        {
            if($k>0)
            {
                $old_vs_ours = $line[0];
                $new_vs_ours = $line[1];
                $this->update_vs_ours($old_vs_ours,$new_vs_ours);
            }
        }
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('file', InputArgument::REQUIRED, 'Changed File'),
		);
	}

}
