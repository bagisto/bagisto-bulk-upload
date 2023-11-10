<?php

namespace Webkul\Bulkupload\DataGrids\Admin;

use DB;
use Webkul\Ui\DataGrid\DataGrid;

/**
 * Profile Datagrid class
 *
 */
class BulkProductImporterDataGrid extends DataGrid
{
    /**
     * @var integer
     */
    protected $index = 'id';

    /**
     * Sort order.
     *
     * @var string
     */
    protected $sortOrder = 'desc';

    /**
     * Items per page.
     *
     * @var int
     */
    protected $itemsPerPage = 10;

    /**
     * Prepare query builder.
     *
     * @return void
     */
    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('bulk_product_importers')
            ->leftJoin('attribute_families', 'bulk_product_importers.attribute_family_id', '=', 'attribute_families.id')
            ->select(
                'bulk_product_importers.id',
                'bulk_product_importers.name as profile_name',
                'attribute_families.name',
                'bulk_product_importers.locale_code',
                'bulk_product_importers.created_at'
            );

        $this->addFilter('created_at', 'bulk_product_importers.created_at');
        $this->addFilter('profile_name', 'bulk_product_importers.name');
        $this->addFilter('name', 'attribute_families.name');

        $this->setQueryBuilder($queryBuilder);
    }

    /**
     * Add columns.
     *
     * @return void
     */
    public function addColumns()
    {
        $this->addColumn([
            'index'      => 'profile_name',
            'label'      => trans('bulkupload::app.admin.bulk-upload.bulk-product-importer.name'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.catalog.products.family'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true
        ]);

        $this->addColumn([
            'index'      => 'locale_code',
            'label'      => trans('bulkupload::app.admin.bulk-upload.bulk-product-importer.data-grid.locale_code'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'label'      => trans('bulkupload::app.admin.bulk-upload.bulk-product-importer.data-grid.created-at'),
            'type'       => 'datetime',
            'sortable'   => true,
            'searchable' => false,
            'filterable' => true
        ]);
    }

    /**
     * Prepare actions.
     *
     * @return void
     */
    public function prepareActions()
    {
        $this->addAction([
            'title'  => trans('admin::app.datagrid.edit'),
            'method' => 'GET',
            'route'  => 'admin.bulk-upload.bulk-product-importer.edit',
            'icon'   => 'icon pencil-lg-icon',
        ]);

        $this->addAction([
            'title'        => trans('admin::app.datagrid.delete'),
            'method'       => 'POST',
            'route'        => 'admin.bulk-upload.bulk-product-importer.delete',
            'confirm_text' => trans('ui::app.datagrid.mass-action.delete', ['resource' => 'address']),
            'icon'         => 'icon trash-icon',
        ]);
    }

    /**
     * Prepare mass actions.
     *
     * @return void
     */
    public function prepareMassActions()
    {
        $this->addMassAction([
            'type'   => 'delete',
            'label'  => 'Delete',
            'action' => route('admin.bulk-upload.bulk-product-importer.massDelete'),
            'method' => 'POST',
            'title'  => ''
        ]);
    }
}
