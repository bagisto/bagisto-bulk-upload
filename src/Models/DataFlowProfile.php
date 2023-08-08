<?php

namespace Webkul\Bulkupload\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Bulkupload\Contracts\DataFlowProfile as DataFlowProfileContract;
use Webkul\Attribute\Models\AttributeFamily;


class DataFlowProfile extends Model implements DataFlowProfileContract
{
    protected $guarded = [];

    protected $table = "bulkupload_data_flow_profiles";

    public function attribute_family()
    {
        return $this->hasOne(AttributeFamily::class, 'id', 'attribute_family_id');
    }

    public function import_product()
    {
        return $this->hasOne(ImportProduct::class, 'id', 'data_flow_profile_id');
    }
}
