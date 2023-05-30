<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImportProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('import_products', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('attribute_family_id')->unsigned();
            $table->foreign('attribute_family_id', 'import_admin_foreign_attribute_family_id')->references('id')->on('attribute_families')->onDelete('cascade');

            $table->integer('data_flow_profile_id')->unsigned();
            $table->foreign('data_flow_profile_id', 'import_admin_foreign_data_flow_profile_id')->references('id')->on('bulkupload_data_flow_profiles')->onDelete('cascade');

            $table->boolean('is_downloadable')->default(0);
            $table->string('upload_link_files');

            $table->boolean('is_links_have_samples')->default(0);
            $table->string('upload_link_sample_files');

            $table->boolean('is_samples_available')->default(0);
            $table->string('upload_sample_files');

            $table->string('file_path');
            $table->string('image_path');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('import_products');
    }
}
