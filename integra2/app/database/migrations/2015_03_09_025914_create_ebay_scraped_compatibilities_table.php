<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEbayScrapedCompatibilitiesTable extends Migration
{
    public function up()
    {
        Schema::create('ebay_scraped_compatibilities', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('item_id', 30);
            $table->foreign('item_id')->references('item_id')->on('ebay_scraped_listings');
            $table->string('make', 100);
            $table->string('model', 100);
            $table->string('year', 10);
            $table->string('trim', 100);
            $table->string('engine', 100);
            $table->string('notes', 500);
        });
    }

    public function down()
    {
        Schema::drop('ebay_scraped_compatibilities');
    }
}
