<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEsiCompatibilitiesTable extends Migration
{
    public function up()
    {
        Schema::create('esi_compatibilities', function (Blueprint $table)
        {
            $table->increments('id');

            $table->unsignedInteger('esi_product_id');
            $table->foreign('esi_product_id')->references('id')->on('esi_products');

            $table->unsignedInteger('esi_target_id');
            $table->foreign('esi_target_id')->references('id')->on('esi_targets');

            $table->unique(['esi_product_id', 'esi_target_id']);
        });
    }

    public function down()
    {
        Schema::drop('esi_compatibilities');
    }

}
