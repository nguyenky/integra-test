<?php

class ProductWarehouse extends Eloquent
{
    public $timestamps = false;
    public $table = 'product_warehouse';

    public function product()
    {
        return $this->belongsTo('Product');
    }

    public function warehouse()
    {
        return $this->belongsTo('Warehouse');
    }

    public static function byBin($warehouseId, $isle, $row, $column)
    {
        return ProductWarehouse::where('warehouse_id', $warehouseId)
            ->where('isle', $isle)
            ->where('row', $row)
            ->where('column', $column)->first();
    }
}
