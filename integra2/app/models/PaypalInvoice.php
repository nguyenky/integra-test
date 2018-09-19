<?php

class PaypalInvoice extends Eloquent
{
    public $timestamps = false;
    protected $fillable = ['invoice_num', 'paypal_invoice_id', 'invoice_date', 'email', 'first_name', 'last_name', 'line1', 'line2', 'city', 'state', 'zip', 'country', 'phone', 'shipping_cost', 'misc_item', 'misc_amount', 'total', 'order_id', 'auto_process'];

    public function items()
    {
        return $this->hasMany('PaypalInvoiceItem');
    }

    public function order()
    {
        return $this->belongsTo('Order', 'order_id', 'id');
    }
}
