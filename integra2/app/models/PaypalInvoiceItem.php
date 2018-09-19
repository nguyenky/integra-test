<?php

class PaypalInvoiceItem extends Eloquent
{
    public $timestamps = false;
    protected $fillable = ['name', 'description', 'quantity', 'unit_price'];

    public function invoice()
    {
        return $this->belongsTo('PaypalInvoice');
    }
}
