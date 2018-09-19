<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEsiProductsTable extends Migration
{
    public function up()
    {
        Schema::create('esi_products', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('sku')->unique();
            $table->string('name', 500)->index();
            $table->unsignedInteger('quantity');
            $table->decimal('list_price', 8, 2);
            $table->decimal('unit_price', 8, 2);
            $table->string('mpn')->nullable()->index();
            $table->timestamp('last_scraped');
        });
    }

    public function down()
    {
        Schema::drop('esi_products');
    }
}
