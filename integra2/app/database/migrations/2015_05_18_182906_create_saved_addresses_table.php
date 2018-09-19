<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSavedAddressesTable extends Migration
{
    public function up()
    {
        Schema::create('saved_addresses', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('alias', 100);
            $table->string('email', 100);
            $table->string('name', 100);
            $table->string('address', 100);
            $table->string('city', 50);
            $table->string('state', 100);
            $table->string('zip', 20);
            $table->string('country', 2);
            $table->string('phone', 50);
        });
    }

    public function down()
    {
        Schema::drop('saved_addresses');
    }
}
