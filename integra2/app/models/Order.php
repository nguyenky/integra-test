<?php

class Order extends Eloquent
{
    public $timestamps = false;
    protected $table = 'sales';
    protected $primaryKey = 'id';

    public function items()
    {
        return $this->hasMany('OrderItem', 'sales_id', 'id');
    }
}
