<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIntegra1SalesItemsTable extends Migration
{
    public function up()
    {
        DB::statement("
            CREATE TABLE `sales_items` (
            `id` int(10) unsigned NOT NULL,
              `sales_id` int(10) unsigned NOT NULL,
              `ebay_item_id` varchar(30) NOT NULL DEFAULT '',
              `amazon_asin` varchar(30) NOT NULL DEFAULT '',
              `sku` varchar(50) NOT NULL DEFAULT '',
              `description` varchar(200) NOT NULL DEFAULT '',
              `quantity` int(10) unsigned NOT NULL,
              `unit_price` decimal(8,2) NOT NULL,
              `total` decimal(8,2) NOT NULL,
              `shipment_order_id` varchar(20) DEFAULT NULL
            )
            ENGINE=FEDERATED
            DEFAULT CHARSET=utf8
            CONNECTION='mysql://eoc:eoc.password@127.0.0.1/eoc/sales_items'");
    }

    public function down()
    {
        DB::statement("DROP TABLE sales_items");
    }
}