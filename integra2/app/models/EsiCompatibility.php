<?php

class EsiCompatibility extends Eloquent
{
    public $timestamps = false;
    protected $fillable = array('esi_product_id', 'esi_target_id');

    public function product()
    {
        return $this->belongsTo('EsiProduct', 'esi_product_id');
    }

    public function target()
    {
        return $this->belongsTo('EsiTarget', 'esi_target_id');
    }
}
