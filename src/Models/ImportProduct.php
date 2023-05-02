<?php

namespace Webkul\Bulkupload\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Bulkupload\Contracts\ImportProduct as ImportProductContract;

class ImportProduct extends Model implements ImportProductContract
{
    /**
     * Define guarded property
     *
     * @var array
     */
    protected $guarded = [];
}
