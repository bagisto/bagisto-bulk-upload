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
            Route::prefix('bulk-product-importer')->group(function () {

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

            Route::prefix('upload-file')->group(function () {

                // Route to display the index page for uploading files
                Route::get('/', [UploadFileController::class, 'index'])
                    ->name('admin.bulk-upload.upload-file.index');

                // Route to handle downloading sample files
                Route::post('/download-sample-file',[UploadFileController::class, 'downloadSampleFile'])
                    ->name('admin.bulk-upload.upload-file.download-sample-files');

                // Route to fetch bulk product importer profiles
                Route::post('/getprofiles', [UploadFileController::class, 'getBulkProductImporter'])
                    ->name('admin.bulk-upload.upload-file.get-all-profile');

                // Route to import products from uploaded files
                Route::post('/import-products-file', [UploadFileController::class, 'storeProductsFile'])
                    ->name('admin.bulk-upload.upload-file.import-products-file');
            });

            Route::prefix('import-product-file')->group(function () {
                Route::get('/', [UploadFileController::class, 'getImporaterToUploadFile'])
                    ->name('admin.bulk-upload.import-file.run-profile.index');

                Route::post('/run-profile', [HelperController::class, 'runProfile'])->defaults('_config', [
                    'view' => 'bulkupload::admin.bulk-upload.run-profile.progressbar'
                ])->name('admin.bulk-upload.upload-file.run-profile.import-file');

                Route::post('/read-csv', [HelperController::class, 'readCSVData'])
                    ->name('admin.bulk-upload.upload-file.run-profile.read-csv');

                Route::post('/read-csv-command', function() {
                    Artisan::call('upload:products');
                })->name('admin.bulk-upload.upload-file.run-profile.read-csv-command');

                Route::get('/download-csv', [HelperController::class, 'downloadCsv'])
                    ->name('admin.bulk-upload.upload-file.run-profile.download-csv');

                Route::get('/get-profiler', [HelperController::class, 'getProfiler'])
                    ->name('admin.bulk-upload.upload-file.run-profile.get-profiler-name');

                Route::get('/delete-csv', [HelperController::class, 'deleteCSV'])
                    ->name('admin.bulk-upload.upload-file.run-profile.delete-csv-file');
            });
        });
    });

