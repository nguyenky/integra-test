<?php

class EsiProduct extends Eloquent
{
    public $timestamps = false;

    public function compatibilities()
    {
        return $this->hasMany('EsiCompatibility', 'esi_product_id');
    }
}
