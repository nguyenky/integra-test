<?php

class OrderAccess extends Eloquent
{
    public $timestamps = false;
    protected $table = 'order_access';

    public function users(){
    	return $this->hasMany('User','group_name','group_name');
    }
}
