<?php

class DatabaseSeeder extends Seeder {

	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		Eloquent::unguard();

        $this->call('SupplierTableSeeder');
        $this->call('ProductTableSeeder');
        $this->call('PaypalInvoiceTableSeeder');
	}

}
