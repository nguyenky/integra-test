<?php

class OrderItem extends Eloquent
{
    public $timestamps = false;
    protected $table = 'sales_items';

    public function order()
    {
        return $this->belongsTo('Order', 'sales_id', 'id');
    }
}
