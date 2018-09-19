<?php

class EsiCategory extends Eloquent
{
    public $timestamps = false;
    protected $fillable = array('id', 'title');

    public function targets()
    {
        return $this->hasMany('EsiTarget', 'esi_category_id');
    }
}
