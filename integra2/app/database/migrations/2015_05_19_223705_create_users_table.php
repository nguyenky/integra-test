<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('email', 100)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
        });
    }

    public function down()
    {
        Schema::drop('users');
    }
}
