<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSupplierReturnsTable extends Migration
{
    public function up()
    {
        Schema::create('supplier_returns', function (Blueprint $table)
        {
            $table->increments('id');
            $table->unsignedInteger('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->string('return_num')->nullable()->unique();
            $table->date('return_date')->index();
            $table->string('status', 20);
            $table->string('credit_num')->index();
            $table->date('credit_date')->nullable();
            $table->decimal('total_credited', 8, 2)->nullable();
        });
    }

    public function down()
    {
        Schema::drop('supplier_returns');
    }
}
