<?php

return [
    [
        'key'        => 'catalog.bulkupload',
        'name'       => 'bulkupload::app.admin.bulk-upload.manage-bulk-upload',
        'route'      => 'admin.bulk-upload.bulk-product-importer.index',
        'sort'       => 5,
        'icon-class' => '',
    ], [
        'key'        => 'catalog.bulkupload.bulk-product-importer-profile',
        'name'       => 'bulkupload::app.admin.bulk-upload.bulk-product-importer.index',
        'route'      => 'admin.bulk-upload.bulk-product-importer.index',
        'sort'       => 1,
        'icon-class' => '',
    ], [
        'key'        => 'catalog.bulkupload.upload-files',
        'name'       => 'bulkupload::app.admin.bulk-upload.bulk-product-importer.index',
        'route'      => 'admin.bulk-upload.upload-file.index',
        'sort'       => 2,
        'icon-class' => '',
    ], [
        'key'        => 'catalog.bulkupload.run-profile',
        'name'       => 'bulkupload::app.admin.bulk-upload.run-profile.index',
        'route'      => 'admin.bulk-upload.import-file.run-profile.index',
        'sort'       => 3,
        'icon-class' => '',
    ]
];
