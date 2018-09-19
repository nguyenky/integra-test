<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEbayLookupPenddingsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('ebay_lookup_pendings',function(Blueprint $table){
			$table->increments('id');
			$table->string('mpn')->nullable();
            $table->integer('status');
            $table->integer('csv_id');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('ebay_lookup_pendings');
	}

}
