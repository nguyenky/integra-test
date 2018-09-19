<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class EsiUpdateTargets extends Command
{
    protected $name = 'esi:update_targets';
    protected $description = 'Updates ESI categories, vehicles, and scraper targets.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $this->info("Updating ESI categories...");
        EsiUtils::UpdateCategories();

        $this->info("Updating ESI vehicles...");
        EsiUtils::UpdateVehicles();

        $this->info("Updating ESI targets...");
        DB::statement('INSERT IGNORE INTO esi_targets (esi_vehicle_id, esi_category_id) (SELECT v.id, c.id FROM esi_vehicles v, esi_categories c)');
    }
}
