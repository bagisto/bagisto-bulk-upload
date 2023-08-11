<?php

use Illuminate\Support\Facades\Route;
use Webkul\Bulkupload\Http\Controllers\Admin\BulkUploadController;
use Webkul\Bulkupload\Http\Controllers\Admin\HelperController;
use Webkul\Bulkupload\Http\Controllers\Admin\DataFlowProfileController;

Route::group(['middleware' => ['web']], function () {

    Route::prefix(config('app.admin_url'))->group(function () {

        Route::group(['middleware' => ['admin']], function () {
            Route::prefix('bulkupload')->group(function () {

                // Bulk Upload Products
                Route::get('/upload-files', [BulkUploadController::class, 'index'])->defaults('_config', [
                    'view' => 'bulkupload::admin.bulk-upload.upload-files.index'
                ])->name('admin.bulk-upload.index');

                Route::get('/run-profile', [BulkUploadController::class, 'getProfiler'])->defaults('_config', [
                    'view' => 'bulkupload::admin.bulk-upload.run-profile.index'
                ])->name('admin.run-profile.index');

                Route::post('/getprofiles', [BulkUploadController::class, 'getAllDataFlowProfiles'])
                    ->name('bulk-upload-admin.get-all-profile');

                Route::post('/read-csv', [HelperController::class, 'readCSVData'])
                    ->name('bulk-upload-admin.read-csv');

                Route::get('/read-csv', function() {
                    Artisan::call('upload:products');
                })->name('bulk-upload-admin.read-csv-command');

                // Download Sample Files
                Route::post('/download',[HelperController::class, 'downloadFile'])->defaults('_config',[
                    'view' => 'bulkupload::admin.bulk-upload.upload-files.index'
                ])->name('download-sample-files');

                // import new products
                Route::post('/importnew', [HelperController::class, 'importNewProductsStore'])->defaults('_config',[
                    'view' => 'bulkupload::admin.bulk-upload.upload-files.index'
                ])->name('import-new-products-form-submit');

                Route::prefix('dataflowprofile')->group(function () {
                    Route::get('/', [DataFlowProfileController::class, 'index'])->defaults('_config', [
                        'view' => 'bulkupload::admin.bulk-upload.data-flow-profile.index'
                    ])->name('admin.dataflow-profile.index');

                    Route::post('/addprofile', [DataFlowProfileController::class, 'store'])->defaults('_config', [
                        'view' => 'bulkupload::admin.bulk-upload.data-flow-profile.index'
                    ])->name('bulkupload.bulk-upload.dataflow.add-profile');

                    // edit actions
                    Route::get('/edit/{id}', [DataFlowProfileController::class, 'edit'])->defaults('_config', [
                        'view' => 'bulkupload::admin.bulk-upload.data-flow-profile.edit'
                    ])->name('bulkupload.admin.profile.edit');

                    Route::post('/update/{id}', [DataFlowProfileController::class, 'update'])
                        ->name('admin.bulk-upload.dataflow.update-profile');

                    // destroy
                    Route::post('/delete/{id}',[DataFlowProfileController::class, 'destroy'])
                        ->name('bulkupload.admin.profile.delete');

                    //mass destroy
                    Route::post('/massdestroy', [DataFlowProfileController::class, 'massDestroy'])->defaults('_config', [
                        'redirect' => 'admin.dataflow-profile.index'
                    ])->name('bulkupload.admin.profile.massDelete');

                    Route::post('/runprofile', [HelperController::class, 'runProfile'])->defaults('_config', [
                        'view' => 'bulkupload::admin.bulk-upload.run-profile.progressbar'
                    ])->name('bulk-upload-admin.run-profile');
                });
            });
        });
    });
});
