<?php

use \Carbon\Carbon;

class PaypalInvoiceTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('paypal_invoice_items')->delete();
        DB::table('paypal_invoices')->delete();

        PaypalInvoice::create(['invoice_num' => 'PP-8000', 'invoice_date' => Carbon::now(), 'email' => 'kbcware@yahoo.com', 'shipping_cost' => 0.00, 'total' => 99.00, 'order_id' => 2669551, 'status' => 'Unpaid', 'fulfillment' => 3, 'shipping_speed' => 'GROUND', 'remarks' => '', 'agent' => 'server@eocenterprise.com'])->items()->saveMany([
            new PaypalInvoiceItem(['sku' => '2004', 'description' => 'Engine Oil', 'quantity' => 1, 'unit_price' => 9.00, 'weight' => 2.050, 'supplier' => 1, 'supplier_cost' => 8.12]),
            new PaypalInvoiceItem(['sku' => '1708260243.14', 'description' => 'Turn Signal Assembly - Headlight', 'quantity' => 1, 'unit_price' => 90.00, 'weight' => 0.65, 'supplier' => 2, 'supplier_cost' => 86.15]),
        ]);
    }
}