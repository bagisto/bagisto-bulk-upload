<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBulkProductImportersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bulk_product_importers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('locale_code');

            $table->integer('attribute_family_id')->unsigned();

            $table->foreign('attribute_family_id')->references('id')->on('attribute_families')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_product_importers');
    }
};
