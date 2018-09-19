<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('sku')->unique();
            $table->string('name', 500)->index();
            $table->string('brand')->nullable()->index();
            $table->unsignedInteger('fulfillment')->nullable(); // 1 - always dropship; 2 - always in-house shipping
            $table->unsignedInteger('supplier_id')->nullable(); // preferred supplier
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->boolean('is_kit')->default(false);          // if kit, get components from kit_components table
            $table->boolean('ground_only')->default(false);     // ground shipping only (hazardous materials)
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
        Schema::drop('products');
    }

}
