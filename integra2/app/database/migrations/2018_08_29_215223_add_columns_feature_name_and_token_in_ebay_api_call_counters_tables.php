<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnsFeatureNameAndTokenInEbayApiCallCountersTables extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('ebay_api_call_counters',function(Blueprint $table){
			$table->string('feature_name')->nullable();
			$table->string('token')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('ebay_api_call_counters',function(){
			$table->dropColumn(['feature_name','token']);
		});
	}

}
