<?php

namespace Webkul\Bulkupload\Providers;

use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        \Webkul\Bulkupload\Models\BulkProductImporter::class,
        \Webkul\Bulkupload\Models\ImportProduct::class,
    ];
}
