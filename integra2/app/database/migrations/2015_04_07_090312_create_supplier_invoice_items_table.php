<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSupplierInvoiceItemsTable extends Migration
{
    public function up()
    {
        Schema::create('supplier_invoice_items', function (Blueprint $table)
        {
            $table->increments('id');
            $table->unsignedInteger('supplier_invoice_id');
            $table->foreign('supplier_invoice_id')->references('id')->on('supplier_invoices');
            $table->string('sku', 50);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 8, 2);

            $table->unique(['supplier_invoice_id', 'sku']);
        });
    }

    public function down()
    {
        Schema::drop('supplier_invoice_items');
    }
}
