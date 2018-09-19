<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaypalInvoicesTable extends Migration
{
    public function up()
    {
        Schema::create('paypal_invoices', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('invoice_num')->nullable()->unique();
            $table->string('paypal_invoice_id')->nullable()->unique();
            $table->date('invoice_date');
            $table->string('email', 260)->index();
            $table->string('first_name', 30);
            $table->string('last_name', 30);
            $table->string('line1', 100);
            $table->string('line2', 100)->nullable();
            $table->string('city', 50);
            $table->string('state', 100);
            $table->string('zip', 20);
            $table->string('country', 2);
            $table->string('phone', 50);
            $table->decimal('shipping_cost', 8, 2);
            $table->string('misc_item', 25)->nullable();                // label of the miscellaneous item (taxes, etc.)
            $table->decimal('misc_amount', 8, 2)->nullable();
            $table->decimal('total', 8, 2);
            $table->string('status', 20)->default('Unpaid');
            $table->string('merchant', 20);
            $table->unsignedInteger('order_id');
            $table->boolean('auto_process')->default(true);             // if true, set linked order to scheduled once payment is received
        });
    }

    public function down()
    {
        Schema::drop('paypal_invoices');
    }
}
