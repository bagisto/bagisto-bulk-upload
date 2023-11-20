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
                Route::get('/get-profiles', [UploadFileController::class, 'getBulkProductImporter'])
                    ->name('admin.bulk-upload.upload-file.get-all-profile');

                // Route to import products from uploaded files
                Route::post('/import-products-file', [UploadFileController::class, 'storeProductsFile'])
                    ->name('admin.bulk-upload.upload-file.import-products-file');
            });

            Route::prefix('import-product-file')->group(function () {
                Route::get('/', [UploadFileController::class, 'getFamilyAttributesToUploadFile'])
                    ->name('admin.bulk-upload.import-file.run-profile.index');

                Route::get('/get-importer', [UploadFileController::class, 'getProductImporter'])
                    ->name('admin.bulk-upload.upload-file.get-importar');

                Route::post('/delete-file', [UploadFileController::class, 'deleteProductFile'])
                    ->name('admin.bulk-upload.upload-file.delete');

                Route::post('/read-csv', [UploadFileController::class, 'readCSVData'])
                    ->name('admin.bulk-upload.upload-file.run-profile.read-csv');

                Route::get('/download-csv', [UploadFileController::class, 'downloadCsv'])
                    ->name('admin.bulk-upload.upload-file.run-profile.download-csv');

                Route::post('/delete-csv', [UploadFileController::class, 'deleteCSV'])
                    ->name('admin.bulk-upload.upload-file.run-profile.delete-csv-file');

                Route::get('/get-uploaded-product', [UploadFileController::class, 'getUploadedProductOrNotUploadedProduct'])
                    ->name('admin.bulk-upload.upload-file.uploaded-product.or-not-uploaded-product');

                Route::get('/get-profiler', [UploadFileController::class, 'getProfiler'])
                    ->name('admin.bulk-upload.upload-file.run-profile.get-profiler-name');

                Route::get('/read-error-file', [UploadFileController::class, 'readErrorFile'])
                    ->name('admin.bulk-upload.upload-file.run-profile.read-error-file');
            });
        });
    });

