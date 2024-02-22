<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImportProductsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_products', function (Blueprint $table) {
            $table->increments('id');

            $table->boolean('is_downloadable')->default(0);
            $table->string('upload_link_files');
            $table->boolean('is_links_have_samples')->default(0);
            $table->string('upload_link_sample_files');
            $table->boolean('is_samples_available')->default(0);
            $table->string('upload_sample_files');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('image_path');
            $table->boolean('status')->default(1);

            $table->integer('attribute_family_id')->unsigned();
            $table->integer('bulk_product_importer_id')->unsigned();

            $table->foreign('attribute_family_id')->references('id')->on('attribute_families')->onDelete('cascade');
            $table->foreign('bulk_product_importer_id')->references('id')->on('bulk_product_importers')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_products');
    }
};
