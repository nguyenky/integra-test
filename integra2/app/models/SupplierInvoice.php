<?php

class SupplierInvoice extends Eloquent
{
    public $timestamps = false;

    public function items()
    {
        return $this->hasMany('SupplierInvoiceItem');
    }

    public function supplier()
    {
        return $this->belongsTo('Supplier', 'supplier_id', 'id');
    }
}
