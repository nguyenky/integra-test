<?php

class SupplierQuantity extends Eloquent
{
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo('Product', 'mpn', 'sku');
    }
}
