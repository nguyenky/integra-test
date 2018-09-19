<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableTrait;
use Illuminate\Auth\Reminders\RemindableInterface;

class User extends Eloquent
{
	protected $table = 'users';
    protected $fillable = array('email','first_name','last_name','ip_restriction','group_name');

    public $timestamps = false;
    
    public function warehouses()
    {
        return $this->belongsToMany('Warehouse');
    }

    public function group_acls(){
    	return $this->hasMany('GroupAcl','group_name','group_name');
    }
}
