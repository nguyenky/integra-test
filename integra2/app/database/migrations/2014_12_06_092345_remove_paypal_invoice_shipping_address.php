<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemovePaypalInvoiceShippingAddress extends Migration
{
    public function up()
    {
        Schema::table('paypal_invoices', function (Blueprint $table)
        {
            $table->dropColumn(['first_name', 'last_name', 'line1', 'line2', 'city', 'state', 'zip', 'country', 'phone', 'auto_process']);
            $table->string('shipping_speed', 100)->nullable();
            $table->tinyInteger('fulfillment')->default(0);
            $table->string('remarks', 200);
            $table->string('agent', 50)->nullable();
        });

        Schema::table('paypal_invoice_items', function (Blueprint $table)
        {
            $table->renameColumn('name', 'sku');
            $table->decimal('weight', 8, 2);
            $table->tinyInteger('supplier')->default(0);
            $table->decimal('supplier_cost', 8, 2);
        });
    }

    public function down()
    {
        Schema::table('paypal_invoice_items', function (Blueprint $table)
        {
            $table->dropColumn(['weight', 'supplier', 'supplier_cost']);
            $table->renameColumn('sku', 'name');
        });

        Schema::table('paypal_invoices', function (Blueprint $table)
        {
            $table->string('first_name', 30)->after('email');
            $table->string('last_name', 30)->after('first_name');
            $table->string('line1', 100)->after('last_name');
            $table->string('line2', 100)->nullable()->after('line1');
            $table->string('city', 50)->after('line2');
            $table->string('state', 100)->after('city');
            $table->string('zip', 20)->after('state');
            $table->string('country', 2)->after('zip');
            $table->string('phone', 50)->after('country');
            $table->boolean('auto_process')->default(true)->after('order_id');
            $table->dropColumn(['shipping_speed', 'fulfillment', 'remarks', 'agent']);
        });
    }
}
