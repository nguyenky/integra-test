<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWarehouseMapTable extends Migration
{
    public function up()
    {
        Schema::create('warehouse_map', function (Blueprint $table)
        {
            $table->increments('id');
            $table->unsignedInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->smallInteger('isle');
            $table->smallInteger('column');
            $table->smallInteger('x');
            $table->smallInteger('y');

            $table->unique(['warehouse_id', 'isle', 'column', 'x', 'y']);
        });
    }

    public function down()
    {
        Schema::drop('warehouse_map');
    }
}
