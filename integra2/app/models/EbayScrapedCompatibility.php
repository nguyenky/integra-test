<?php

class EbayScrapedCompatibility extends Eloquent
{
    public $timestamps = false;
    protected $table = 'ebay_scraped_compatibilities';

    public function listing()
    {
        return $this->belongsTo('EbayScrapedListing', 'item_id', 'item_id');
    }
}
