<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSupplierReturnItemsTable extends Migration
{
    public function up()
    {
        Schema::create('supplier_return_items', function (Blueprint $table)
        {
            $table->increments('id');
            $table->unsignedInteger('supplier_return_id');
            $table->foreign('supplier_return_id')->references('id')->on('supplier_returns');
            $table->string('invoice_num')->index();
            $table->string('sku', 50)->index();
            $table->string('reason', 50);
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_credited')->nullable();
            $table->decimal('unit_price_credited', 8, 2)->nullable();

            $table->unique(['supplier_return_id', 'sku', 'invoice_num']);
        });
    }

    public function down()
    {
        Schema::drop('supplier_return_items');
    }
}
