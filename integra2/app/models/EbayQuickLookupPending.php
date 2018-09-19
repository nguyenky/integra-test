<?php

class EbayQuickLookupPending extends Eloquent
{

    protected $table = 'ebay_lookup_pendings';
    protected $fillable = array('mpn', 'status','csv_id');
    public $timestamps = false;
}