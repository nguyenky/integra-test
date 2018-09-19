<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEsiTargetsTable extends Migration
{
    public function up()
    {
        Schema::create('esi_targets', function (Blueprint $table)
        {
            $table->increments('id');

            $table->unsignedInteger('esi_vehicle_id');
            $table->foreign('esi_vehicle_id')->references('id')->on('esi_vehicles');

            $table->unsignedInteger('esi_category_id');
            $table->foreign('esi_category_id')->references('id')->on('esi_categories');

            $table->timestamp('last_scraped')->nullable()->index();

            $table->unique(['esi_vehicle_id', 'esi_category_id']);
        });
    }

    public function down()
    {
        Schema::drop('esi_targets');
    }
}
