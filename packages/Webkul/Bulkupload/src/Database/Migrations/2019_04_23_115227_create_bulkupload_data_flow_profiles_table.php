<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBulkuploadDataflowprofilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bulkupload_data_flow_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('attribute_family_id')->unsigned();
            $table->foreign('attribute_family_id', 'bulkupload_foreign_attribute_family_id')->references('id')->on('attribute_families')->onDelete('cascade');
            $table->boolean('run_status')->default(0);
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
        Schema::dropIfExists('bulkupload_data_flow_profiles');
    }
}
