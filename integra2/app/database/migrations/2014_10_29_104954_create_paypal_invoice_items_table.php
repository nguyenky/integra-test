<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaypalInvoiceItemsTable extends Migration
{
    public function up()
    {
        Schema::create('paypal_invoice_items', function (Blueprint $table)
        {
            $table->increments('id');
            $table->unsignedInteger('paypal_invoice_id');
            $table->foreign('paypal_invoice_id')->references('id')->on('paypal_invoices');
            $table->string('name', 60);
            $table->string('description', 1000);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 8, 2);
        });
    }

    public function down()
    {
        Schema::drop('paypal_invoice_items');
    }
}
