<?php

namespace Webkul\Bulkupload\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Bulkupload\Contracts\ImportProduct as ImportProductContract;

class ImportProduct extends Model implements ImportProductContract
{
    protected $table = "import_products";

    protected $guarded = [];
}
