<?php

class ProductTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('products')->delete();

        Supplier::name('IMC')->products()->saveMany([
            new Product(['sku' => '1058206', 'name' => 'Auto Trans Fluid', 'brand' => 'Pentosin']),
            new Product(['sku' => '2004', 'name' => 'Engine Oil', 'brand' => 'Liqui Moly']),
            new Product(['sku' => 'BKR5EGP', 'name' => 'Spark Plug', 'brand' => 'NGK']),
            new Product(['sku' => '25859', 'name' => 'Disk Brake Rotor', 'brand' => 'Brembo', 'fulfillment' => Config::get('constants.DROPSHIP')]),
        ]);

        Supplier::name('SSF')->products()->saveMany([
            new Product(['sku' => '51168243575.9', 'name' => 'Sun Visor Clip - Beige', 'brand' => 'Genuine BMW']),
            new Product(['sku' => 'Q1460001.22', 'name' => 'Power Steering Fluid', 'brand' => 'Genuine Mercedes']),
            new Product(['sku' => '001989680313.851', 'name' => 'Automatic Transmission Fluid', 'brand' => 'Fuchs']),
            new Product(['sku' => '51218199923.9', 'name' => 'Outside Door Handle Assembly with Key', 'brand' => 'Genuine BMW']),
        ]);

        Product::create(['sku' => Config::get('constants.KIT_PREFIX') . '1', 'name' => 'ATF and Engine Oil', 'is_kit' => true])->components()->attach([
            Product::bySku('1058206')->id => ['quantity' => 1],
            Product::bySku('2004')->id => ['quantity' => 1],
        ]);

        Product::create(['sku' => Config::get('constants.KIT_PREFIX') . '2', 'name' => 'ATF and Power Steering Fluid', 'is_kit' => true])->components()->attach([
            Product::bySku('001989680313.851')->id => ['quantity' => 1],
            Product::bySku('Q1460001.22')->id => ['quantity' => 1],
        ]);

        Product::bySku('2004')->warehouses()->attach([Supplier::name('EOC')->warehouses()->pluck('id') => ['quantity' => 10]]);
    }
}