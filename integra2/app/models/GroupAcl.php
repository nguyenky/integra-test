<?php

class GroupAcl extends Eloquent
{
    public $timestamps = false;
    protected $table = 'group_acl';
    protected $fillable = array('group_name','page_url');

    public function page(){

    	return $this->hasOne('Page','url','page_url');
    	
    }
}
	
