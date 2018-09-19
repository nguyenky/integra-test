<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductCodesTable extends Migration
{
    public function up()
    {
        Schema::create('product_codes', function (Blueprint $table)
        {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->string('code', 50)->unique();
        });
    }

    public function down()
    {
        Schema::drop('product_codes');
    }
}
