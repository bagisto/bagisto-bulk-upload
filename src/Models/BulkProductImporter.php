<?php

namespace Webkul\Bulkupload\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Attribute\Models\AttributeFamilyProxy;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Bulkupload\Contracts\BulkProductImporter as BulkProductImporterContract;


class BulkProductImporter extends Model implements BulkProductImporterContract
{
    protected $guarded = [];

    /**
     * Get the product attribute family that owns the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function attribute_family(): BelongsTo
    {
        return $this->belongsTo(AttributeFamilyProxy::modelClass());
    }

    /**
     * Get the product files.
     */
    public function import_product(): HasMany
    {
        return $this->hasMany(ImportProductProxy::modelClass());
    }
}
