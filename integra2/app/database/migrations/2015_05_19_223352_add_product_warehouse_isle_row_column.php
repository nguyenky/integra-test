<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProductWarehouseIsleRowColumn extends Migration
{
	public function up()
	{
        Schema::table('product_warehouse', function (Blueprint $table)
        {
            $table->smallInteger('isle')->index();
            $table->char('row')->index();
            $table->smallInteger('column')->index();

            $table->unique(['product_id', 'warehouse_id', 'isle', 'row', 'column']);
        });
	}

	public function down()
	{
        Schema::table('product_warehouse', function (Blueprint $table)
        {
            $table->dropColumn(['isle', 'row', 'column']);
        });
	}
}
