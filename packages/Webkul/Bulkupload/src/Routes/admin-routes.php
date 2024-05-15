<?php

use Illuminate\Support\Facades\Route;
use Webkul\Bulkupload\Http\Controllers\Admin\{BulkUploadController, HelperController};

Route::group(['middleware' => ['web']], function () {

    Route::prefix(config('app.admin_url'))->group(function () {

        Route::group(['middleware' => ['admin']], function () {
            Route::prefix('bulkupload')->group(function () {

                Route::get('/upload-files', [BulkUploadController::class, 'index'])->defaults('_config', [
                    'view' => 'bulkupload::admin.bulk-upload.upload-files.index'
                ])->name('admin.bulk-upload.index');

                Route::get('/run-profile', [BulkUploadController::class, 'index'])->defaults('_config', [
                    'view' => 'bulkupload::admin.bulk-upload.run-profile.index'
                ])->name('admin.run-profile.index');

                Route::post('/read-csv', [HelperController::class, 'readCSVData'])->name('bulk-upload-admin.read-csv');

                Route::post('/getprofiles', [HelperController::class, 'getAllDataFlowProfiles'])
                ->name('bulk-upload-admin.get-all-profile');

                // Download Sample Files
                Route::post('/download',[HelperController::class, 'downloadFile'])->defaults('_config',[
                    'view' => 'bulkupload::admin.bulk-upload.upload-files.index'
                ])->name('download-sample-files');

                // import new products
                Route::post('/importnew', [HelperController::class, 'importNewProductsStore'])->defaults('_config',['view' => 'bulkupload::admin.bulk-upload.upload-files.index' ])->name('import-new-products-form-submit');

                Route::prefix('dataflowprofile')->group(function () {
                    Route::post('/runprofile', [HelperController::class, 'runProfile'])->defaults('_config', [
                        'view' => 'bulkupload::admin.bulk-upload.run-profile.progressbar'
                    ])->name('bulk-upload-admin.run-profile');

                    Route::get('/', [BulkUploadController::class, 'index'])->defaults('_config', [
                        'view' => 'bulkupload::admin.bulk-upload.data-flow-profile.index'
                    ])->name('admin.dataflow-profile.index');
        
                    Route::post('/addprofile', [BulkUploadController::class, 'store'])->defaults('_config', [
                        'view' => 'bulkupload::admin.bulk-upload.data-flow-profile.index'
                    ])->name('bulkupload.bulk-upload.dataflow.add-profile');
        
                    Route::post('/delete/{id}',[BulkUploadController::class, 'destroy'])->name('bulkupload.admin.profile.delete');
        
                    Route::get('/edit/{id}', [BulkUploadController::class, 'edit'])->defaults('_config', [
                        'view' => 'bulkupload::admin.bulk-upload.data-flow-profile.edit'
                    ])->name('bulkupload.admin.profile.edit');
        
                    Route::post('/update/{id}', [BulkUploadController::class, 'update'])->defaults('_config', [
                        'view' => 'bulkupload::admin.bulk-upload.data-flow-profile.index'
                    ])->name('admin.bulk-upload.dataflow.update-profile');

                    Route::post('/massdestroy', [BulkUploadController::class, 'massDestroy'])->defaults('_config', [
                        'redirect' => 'admin.dataflow-profile.index'
                    ])->name('bulkupload.admin.profile.massDelete');
                });
            });
        });
    });
});
