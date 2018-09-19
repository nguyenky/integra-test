<?php

class EsiVehicle extends Eloquent
{
    public $timestamps = false;
    protected $fillable = array('year', 'make', 'model', 'model_id');

    public function targets()
    {
        return $this->hasMany('EsiTarget', 'esi_vehicle_id');
    }
}
