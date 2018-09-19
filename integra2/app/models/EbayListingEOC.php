<?php

class EbayListingEOC extends Eloquent
{
	protected $connection= 'mysql_eoc';

    protected $table = 'ebay_listings';
    
    public $timestamps = false;
}
