<?php

class EsiTarget extends Eloquent
{
    public $timestamps = false;
    protected $fillable = array('esi_category_id', 'esi_vehicle_id', 'last_scraped');

    public function category()
    {
        return $this->belongsTo('EsiCategory', 'esi_category_id');
    }

    public function vehicle()
    {
        return $this->belongsTo('EsiVehicle', 'esi_vehicle_id');
    }

    public function compatibilities()
    {
        return $this->hasMany('EsiCompatibility', 'esi_target_id');
    }
}
