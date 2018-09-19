<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWarehouseUserTable extends Migration
{
    public function up()
    {
        Schema::create('warehouse_user', function (Blueprint $table)
        {
            $table->increments('id');
            $table->unsignedInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');

            $table->unique(['warehouse_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::drop('warehouse_user');
    }
}
