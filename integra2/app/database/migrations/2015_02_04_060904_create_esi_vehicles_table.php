<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEsiVehiclesTable extends Migration
{
    public function up()
    {
        Schema::create('esi_vehicles', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('year', 4)->index();
            $table->string('make', 50)->index();
            $table->string('model', 255)->index();
            $table->unsignedInteger('model_id');
        });
    }

    public function down()
    {
        Schema::drop('esi_vehicles');
    }
}
