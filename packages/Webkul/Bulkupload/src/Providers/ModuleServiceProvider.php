<?php

namespace Webkul\Bulkupload\Providers;

use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        \Webkul\Bulkupload\Models\ImportProduct::class,
        \Webkul\Bulkupload\Models\DataFlowProfile::class,
    ];
}