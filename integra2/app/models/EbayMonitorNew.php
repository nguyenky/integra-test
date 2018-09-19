<?php

class EbayMonitorNew extends Eloquent
{
	protected $connection= 'mysql_eoc';

    protected $table = 'ebay_monitor';

    public $timestamps = false;
}
