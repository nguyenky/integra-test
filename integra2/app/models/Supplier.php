<?php

class Supplier extends Eloquent
{
    public $timestamps = false;

    public function warehouses()
    {
        return $this->hasMany('Warehouse');
    }

    public function products()
    {
        return $this->hasMany('Product');
    }

    public function scopeName($q, $name)
    {
        return $q->whereName($name)->first();
    }

    public static function getSupplierByName($name) {
       return self::where('name', $name)->pluck('id');
    }
}
