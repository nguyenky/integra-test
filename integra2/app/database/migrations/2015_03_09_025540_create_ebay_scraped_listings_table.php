<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEbayScrapedListingsTable extends Migration
{
    public function up()
    {
        Schema::create('ebay_scraped_listings', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('item_id', 30)->unique();
            $table->string('title');
            $table->integer('category_id');
            $table->string('category');
            $table->string('big_image');
            $table->string('small_image');
            $table->decimal('price', 8, 2);
            $table->decimal('shipping', 6, 2);
            $table->string('shipping_type', 30);
            $table->string('seller', 50);
            $table->integer('score');
            $table->decimal('rating', 5, 2);
            $table->boolean('top');
            $table->integer('hits');
            $table->integer('sold');
            $table->integer('available');
            $table->integer('compatible');
            $table->string('condition', 100);
            $table->string('sku', 100);
            $table->string('mpn', 100);
            $table->string('ipn', 100);
            $table->string('opn', 100);
            $table->string('placement', 100);
            $table->string('brand', 100);
            $table->string('surface_finish', 100);
            $table->string('warranty', 100);
            $table->string('others', 500);
        });
    }

    public function down()
    {
        Schema::drop('ebay_scraped_listings');
    }
}
