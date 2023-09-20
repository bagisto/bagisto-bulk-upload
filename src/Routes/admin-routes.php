<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Webkul\Bulkupload\Http\Controllers\Admin\HelperController;
use Webkul\Bulkupload\Http\Controllers\Admin\BulkProductImporterController;
use Webkul\Bulkupload\Http\Controllers\Admin\UploadFileController;

Route::middleware(['web', 'admin'])
    ->prefix(config('app.admin_url'))
    ->group(function () {
        Route::prefix('bulkupload')->group(function () {
            Route::prefix('bulkproductimporter')->group(function () {

                // Index
                Route::get('/', [BulkProductImporterController::class, 'index'])
                    ->name('admin.bulk-upload.bulk-product-importer.index');

                // Store
                Route::post('/addprofile', [BulkProductImporterController::class, 'store'])
                    ->name('admin.bulk-upload.bulk-product-importer.add');

                // Edit
                Route::get('/edit/{id}', [BulkProductImporterController::class, 'edit'])
                    ->name('admin.bulk-upload.bulk-product-importer.edit');

                Route::post('/update/{id}', [BulkProductImporterController::class, 'update'])
                    ->name('admin.bulk-upload.bulk-product-importer.update');

                // Destroy
                Route::post('/delete/{id}', [BulkProductImporterController::class, 'destroy'])
                    ->name('admin.bulk-upload.bulk-product-importer.delete');

                // Mass Destroy
                Route::post('/massdestroy', [BulkProductImporterController::class, 'massDestroy'])
                    ->name('admin.bulk-upload.bulk-product-importer.massDelete');
            });

            Route::prefix('uploadfile')->group(function () {

                // Index
                Route::get('/', [UploadFileController::class, 'index'])
                    ->name('admin.bulk-upload.upload-file.index');

                // Download Sample Files
                Route::post('/download-sample-file',[UploadFileController::class, 'downloadSampleFile'])
                    ->name('admin.bulk-upload.upload-file.download-sample-files');

                Route::post('/import-products-file', [UploadFileController::class, 'storeProductsFile'])
                    ->name('admin.bulk-upload.upload-file.import-products-file');

                Route::post('/getprofiles', [BulkUploadController::class, 'getAllBulkProductImporter'])
                    ->name('admin.bulk-upload.upload-file.get-all-profile');

                // Route::get('/', [BulkProductImporterController::class, 'index'])->defaults('_config', [
                //     'view' => 'bulkupload::admin.bulk-upload.bulk-product-importer.index'
                // ])->name('admin.bulk-upload.upload-file.index');

                // Store
                // admin.bulk-upload.upload-file.index
                // Route::post('/addprofile', [BulkProductImporterController::class, 'store'])
                //     ->name('admin.bulk-upload.bulk-product-importer.add');

            });
        });
    });

