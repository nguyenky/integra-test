<?php

class EbayQuickLookupCsvfile extends Eloquent
{

    protected $table = 'ebay_quick_lookup_csvfile';
    protected $fillable = array('name','status','links');
    public $timestamps = false;

    public function EbayQuickLookupPendings()
    {
        return $this->hasMany('EbayQuickLookupPending', 'csv_id');
    }
}