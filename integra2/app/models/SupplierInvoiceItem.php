<?php

class SupplierInvoiceItem extends Eloquent
{
    public $timestamps = false;
    protected $fillable = ['supplier_invoice_id', 'sku', 'quantity', 'quantity_shipped', 'unit_price'];

    public function order()
    {
        return $this->belongsTo('SupplierInvoice');
    }
}
