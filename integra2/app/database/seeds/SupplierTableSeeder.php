<?php

class SupplierTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('warehouses')->delete();
        DB::table('suppliers')->delete();

        Supplier::create(['name' => 'IMC'])->warehouses()->saveMany([
            new Warehouse(['name' => 'Canoga Park', 'code' => '1', 'city' => 'Canoga Park', 'state' => 'CA']),
            new Warehouse(['name' => 'Orange', 'code' => '2', 'city' => 'Orange', 'state' => 'CA']),
            new Warehouse(['name' => 'Union City', 'code' => '3', 'city' => 'Union City', 'state' => 'CA']),
            new Warehouse(['name' => 'Kirkland', 'code' => '5', 'city' => 'Kirkland', 'state' => 'WA']),
            new Warehouse(['name' => 'Portland', 'code' => '6', 'city' => 'Portland', 'state' => 'OR']),
            new Warehouse(['name' => 'Baltimore', 'code' => '7', 'city' => 'Baltimore', 'state' => 'MD']),
            new Warehouse(['name' => 'Pompano Beach', 'code' => '8', 'city' => 'Pompano Beach', 'state' => 'FL', 'has_truck' => true]),
            new Warehouse(['name' => 'Houston', 'code' => '9', 'city' => 'Houston', 'state' => 'TX']),
            new Warehouse(['name' => 'Dallas', 'code' => '11', 'city' => 'Dallas', 'state' => 'TX']),
            new Warehouse(['name' => 'Long Island', 'code' => '12', 'city' => 'Long Island', 'state' => 'NY']),
            new Warehouse(['name' => 'Miami', 'code' => '15', 'city' => 'Miami', 'state' => 'FL', 'can_drop_ship' => false, 'has_truck' => true]),
        ]);

        Supplier::create(['name' => 'SSF'])->warehouses()->saveMany([
            new Warehouse(['name' => 'South San Francisco', 'code' => 'SF', 'city' => 'San Francisco', 'state' => 'CA']),
            new Warehouse(['name' => 'Carson', 'code' => 'LB', 'city' => 'Carson', 'state' => 'CA']),
            new Warehouse(['name' => 'San Diego', 'code' => 'SD', 'city' => 'San Diego', 'state' => 'CA']),
            new Warehouse(['name' => 'Phoenix', 'code' => 'PH', 'city' => 'Phoenix', 'state' => 'AZ']),
            new Warehouse(['name' => 'Orange County', 'code' => 'OC', 'city' => 'Orange County', 'state' => 'OC']),
        ]);

        Supplier::create(['name' => 'ESI'])->warehouses()->saveMany([
            new Warehouse(['name' => 'Sunrise', 'code' => 'SU', 'city' => 'Sunrise', 'state' => 'FL']),
        ]);

        Supplier::create(['name' => 'EOC'])->warehouses()->saveMany([
            new Warehouse(['name' => 'Miami, FL', 'code' => 'MI', 'city' => 'Miami', 'state' => 'FL', 'can_drop_ship' => false]),
        ]);
    }
}