<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEsiCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('esi_categories', function (Blueprint $table)
        {
            $table->unsignedInteger('id')->primary();
            $table->string('title', 50)->index();
        });
    }

    public function down()
    {
        Schema::drop('esi_categories');
    }
}
