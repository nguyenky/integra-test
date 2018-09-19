<?php

class WarehouseMap extends Eloquent
{
    public $timestamps = false;
    public $table = 'warehouse_map';

    public function warehouse()
    {
        return $this->belongsTo('Warehouse');
    }
}
