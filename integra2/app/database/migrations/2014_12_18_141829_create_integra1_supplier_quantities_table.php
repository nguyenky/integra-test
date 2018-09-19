<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIntegra1SupplierQuantitiesTable extends Migration
{
    public function up()
    {
        DB::statement("
            CREATE TABLE `supplier_quantities`
            (
                `mpn` varchar(50) NOT NULL,
                `qty` smallint(6) NOT NULL DEFAULT '0',
                `supplier` tinyint(4) NOT NULL DEFAULT '0',
                `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            ENGINE=FEDERATED
            DEFAULT CHARSET=utf8
            CONNECTION='mysql://eoc:eoc.password@127.0.0.1/eoc/supplier_qty'");
    }

    public function down()
    {
        DB::statement("DROP TABLE supplier_quantities");
    }
}
