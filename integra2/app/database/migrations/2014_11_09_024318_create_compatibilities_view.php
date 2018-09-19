<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCompatibilitiesView extends Migration
{
    public function up()
    {
        DB::statement("
            CREATE VIEW compatibilities AS
                SELECT cpe.sku as sku, em.make as make, em.model as model, em.year as year, en.message as notes
                FROM magento.catalog_product_entity cpe, magento.elite_1_mapping em, magento.elite_note en
                WHERE cpe.entity_id = em.entity_id
                AND em.note_id = en.id");
    }

    public function down()
    {
        DB::statement("DROP VIEW compatibilities");
    }
}
