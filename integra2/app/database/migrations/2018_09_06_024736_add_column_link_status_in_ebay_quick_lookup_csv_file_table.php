<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnLinkStatusInEbayQuickLookupCsvFileTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('ebay_quick_lookup_csvfile',function(Blueprint $table){
			$table->integer('status')->default(0);
			$table->string('links')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('ebay_quick_lookup_csvfile', function(Blueprint $table)
		{
			$table->dropColumn(['status','links']);
		});
	}

}
