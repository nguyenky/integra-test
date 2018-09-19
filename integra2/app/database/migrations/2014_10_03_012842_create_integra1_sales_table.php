<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIntegra1SalesTable extends Migration
{
    public function up()
    {
        DB::statement("
            CREATE TABLE `sales` (
              `id` int(10) unsigned NOT NULL,
              `store` varchar(20) NOT NULL,
              `internal_id` varchar(50) NOT NULL,
              `record_num` varchar(50) DEFAULT NULL,
              `order_date` datetime NOT NULL,
              `total` decimal(8,2) NOT NULL,
              `buyer_id` varchar(50) DEFAULT NULL,
              `email` varchar(50) DEFAULT NULL,
              `buyer_name` varchar(100) DEFAULT NULL,
              `street` varchar(100) DEFAULT NULL,
              `city` varchar(50) DEFAULT NULL,
              `state` varchar(20) DEFAULT NULL,
              `country` varchar(50) NOT NULL DEFAULT 'US',
              `zip` varchar(20) DEFAULT NULL,
              `phone` varchar(50) DEFAULT NULL,
              `speed` varchar(100) DEFAULT NULL,
              `tracking_num` varchar(50) NOT NULL DEFAULT '',
              `carrier` varchar(20) NOT NULL DEFAULT '',
              `remarks` varchar(200) NOT NULL DEFAULT '',
              `fulfilled` tinyint(1) NOT NULL DEFAULT '0',
              `agent` varchar(50) DEFAULT NULL,
              `auto_order` tinyint(4) NOT NULL DEFAULT '0',
              `pickup_id` int(11) DEFAULT NULL,
              `fulfilment` tinyint(4) NOT NULL DEFAULT '0',
              `site_id` int(11) NOT NULL DEFAULT '0',
              `status` tinyint(4) NOT NULL DEFAULT '0',
              `fake_tracking` tinyint(1) NOT NULL DEFAULT '0',
              `supplier_cost` decimal(8,2) NOT NULL DEFAULT '0.00',
              `weight` decimal(10,5) NOT NULL DEFAULT '0.00000',
              `supplier` tinyint(4) NOT NULL DEFAULT '0',
              `shipping_cost` decimal(5,2) NOT NULL DEFAULT '0.00',
              `intl_street` varchar(100) DEFAULT NULL,
              `intl_city` varchar(50) DEFAULT NULL,
              `intl_state` varchar(20) DEFAULT NULL,
              `intl_country` varchar(50) DEFAULT 'US',
              `intl_zip` varchar(20) DEFAULT NULL,
              `related_sales_id` int(11) DEFAULT NULL,
              `related_record_num` varchar(50) DEFAULT NULL,
              `sold_price` decimal(8,2) DEFAULT NULL,
              `listing_fee` decimal(8,2) NOT NULL DEFAULT '0.00',
              `supplier_tax` decimal(8,2) NOT NULL DEFAULT '0.00',
              `profit` decimal(8,2) NOT NULL DEFAULT '0.00',
              `loss_reason` varchar(255) DEFAULT NULL,
              `loss_solution` varchar(255) DEFAULT NULL)
            ENGINE=FEDERATED
            DEFAULT CHARSET=utf8
            CONNECTION='mysql://eoc:eoc.password@127.0.0.1/eoc/sales'");
    }

    public function down()
    {
        DB::statement("DROP TABLE sales");
    }
}