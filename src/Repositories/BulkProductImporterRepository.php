<?php

namespace Webkul\Bulkupload\Repositories;

use Webkul\Core\Eloquent\Repository;

/**
 * BulkProductImporter Repository
 *
 */
class BulkProductImporterRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Webkul\Bulkupload\Contracts\BulkProductImporter';
    }
}
