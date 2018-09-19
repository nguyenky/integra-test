<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEbayListingsTable extends Migration
{
	public function up()
	{
        Schema::create('ebay_listings', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('item_id', 30)->unique();
            $table->string('sku');
            $table->decimal('price', 8, 2);
            $table->integer('quantity');
            $table->boolean('active')->default(true);
            $table->boolean('always_list')->default(false);
        });
	}

	public function down()
	{
        Schema::drop('ebay_listings');
	}
}
