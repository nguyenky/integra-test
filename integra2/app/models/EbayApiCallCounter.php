<?php

class EbayApiCallCounter extends Eloquent
{
    protected $table = 'ebay_api_call_counters';
    protected $fillable = array('ebay_service_name','feature_name','token');
    public $timestamps = false;
}
