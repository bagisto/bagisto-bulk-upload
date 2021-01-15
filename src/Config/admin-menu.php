<?php

return [
    [
        'key'        => 'bulkupload',
        'name'       => 'bulkupload::app.admin.bulk-upload.index',
        'route'      => 'admin.bulk-upload.index',
        'sort'       => 9,
        'icon-class' => 'bulk-upload-icon',
    ], [
        'key'        => 'bulkupload.manage-bulk-upload',
        'name'       => 'bulkupload::app.admin.bulk-upload.manage-bulk-upload',
        'route'      => 'admin.dataflow-profile.index',
        'sort'       => 1,
        'icon-class' => '',
    ], [
        'key'        => 'bulkupload.manage-bulk-upload.data-flow-profile',
        'name'       => 'bulkupload::app.admin.bulk-upload.data-flow-profile.index',
        'route'      => 'admin.dataflow-profile.index',
        'sort'       => 1,
        'icon-class' => '',
    ], [
        'key'        => 'bulkupload.manage-bulk-upload.upload-files',
        'name'       => 'bulkupload::app.admin.bulk-upload.upload-files.index',
        'route'      => 'admin.bulk-upload.index',
        'sort'       => 2,
        'icon-class' => '',
    ], [
        'key'        => 'bulkupload.manage-bulk-upload.run-profile',
        'name'       => 'bulkupload::app.admin.bulk-upload.run-profile.index',
        'route'      => 'admin.run-profile.index',
        'sort'       => 3,
        'icon-class' => '',
    ]
];