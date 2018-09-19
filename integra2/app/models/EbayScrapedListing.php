<?php

class EbayScrapedListing extends Eloquent
{
    public $timestamps = false;
    protected $table = 'ebay_scraped_listings';

    public function compatibilities()
    {
        return $this->hasMany('EbayScrapedCompatibility', 'item_id', 'item_id');
    }
}
