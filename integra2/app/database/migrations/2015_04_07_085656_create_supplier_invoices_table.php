<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSupplierInvoicesTable extends Migration
{
    public function up()
    {
        Schema::create('supplier_invoices', function (Blueprint $table)
        {
            $table->increments('id');
            $table->unsignedInteger('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->string('invoice_num')->unique();
            $table->string('order_num')->index();
            $table->string('po_num')->index();
            $table->date('order_date')->index();
            $table->decimal('total', 8, 2);
        });
    }

    public function down()
    {
        Schema::drop('supplier_invoices');
    }
}
