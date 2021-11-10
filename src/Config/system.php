<?php

return [
    [
        'key'       => 'bulkupload',
        'name'      => 'bulkupload::app.admin.system.bulkupload',
        'sort'      => 5
    ], [
        'key'       => 'bulkupload.settings',
        'name'      => 'bulkupload::app.admin.system.settings',
        'sort'      => 1,
    ], [
        'key'       => 'bulkupload.settings.general',
        'name'      => 'bulkupload::app.admin.system.general',
        'sort'      => 1,
        'fields'    => [
            [
                'name'          => 'status',
                'title'         => 'bulkupload::app.admin.system.status',
                'type'          => 'boolean',
                'channel_based' => true,
                'locale_based'  => false
            ]
        ]
    ]
];