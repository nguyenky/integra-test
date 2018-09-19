<?php

class EbayListing extends Eloquent
{
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo('Product', 'sku', 'sku');
    }
}
