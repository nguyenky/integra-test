<?php

class Warehouse extends Eloquent
{
    public $timestamps = false;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    public function addStock()
    {

        return $this->belongsTo('Supplier');
    }

    public function products()
    {
        return $this->hasMany('Product');
    }

    public function users()
    {
        return $this->belongsToMany('User');
    }

    public static function bySku($sku)
    {
        return Product::where('sku', $sku)->first();
    }
}
