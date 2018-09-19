<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class GetEbayListings extends Command
{
    protected $name = 'ebay:get_listings';
    protected $description = 'Retrieves current eBay listings and inventory status.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        //
    }

    protected function getArguments()
    {
        return [];
    }

    protected function getOptions()
    {
        return [];
    }

}
