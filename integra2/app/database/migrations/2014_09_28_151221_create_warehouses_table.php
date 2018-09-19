<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWarehousesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::create('warehouses', function (Blueprint $table)
        {
            $table->increments('id');
            $table->unsignedInteger('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->string('name');
            $table->string('code');
            $table->string('city');
            $table->string('state');
            $table->string('country')->default('US');
            $table->boolean('can_drop_ship')->default(true);
            $table->boolean('has_truck')->default(false);
            $table->boolean('is_active')->default(true);
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::drop('warehouses');
	}

}
