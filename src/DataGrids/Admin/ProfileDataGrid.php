<?php

namespace Webkul\Bulkupload\DataGrids\Admin;

use DB;
use Webkul\Ui\DataGrid\DataGrid;

/**
 * Profile Datagrid class
 *
 */
class ProfileDataGrid extends DataGrid
{
    /**
     * @var integer
     */
    protected $index = 'id';

    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('bulkupload_data_flow_profiles')
            ->leftJoin('attribute_families', 'bulkupload_data_flow_profiles.attribute_family_id', '=', 'attribute_families.id')
            ->select('bulkupload_data_flow_profiles.id',
            'bulkupload_data_flow_profiles.name as profile_name', 'attribute_families.name', 'bulkupload_data_flow_profiles.locale_code', 'bulkupload_data_flow_profiles.created_at');

        $this->addFilter('created_at', 'bulkupload_data_flow_profiles.created_at');
        $this->addFilter('profile_name', 'bulkupload_data_flow_profiles.name');
        $this->addFilter('name', 'attribute_families.name');

        $this->setQueryBuilder($queryBuilder);
    }

    public function addColumns()
    {
        $this->addColumn([
            'index'      => 'profile_name',
            'label'      => trans('bulkupload::app.admin.bulk-upload.data-flow-profile.name'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.catalog.products.familiy'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true
        ]);

        $this->addColumn([
            'index'      => 'locale_code',
            'label'      => trans('bulkupload::app.admin.bulk-upload.data-flow-profile.data-grid.locale_code'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'label'      => trans('bulkupload::app.admin.bulk-upload.data-flow-profile.data-grid.created-at'),
            'type'       => 'datetime',
            'sortable'   => true,
            'searchable' => false,
            'filterable' => true
        ]);
    }

    public function prepareActions()
    {
        $this->addAction([
            'type'   => 'Edit',
            'method' => 'GET',
            'route'  => 'bulkupload.admin.profile.edit',
            'icon'   => 'icon pencil-lg-icon',
            'title'  => ''

        ]);

        $this->addAction([
            'type'          => trans('admin::app.datagrid.delete'),
            'method'        => 'POST',
            'route'         => 'bulkupload.admin.profile.delete',
            'confirm_text'  => trans('ui::app.datagrid.massaction.delete'),
            'icon'          => 'icon trash-icon',
            'title'         => ''
        ]);

        $this->enableAction = true;
    }

    public function prepareMassActions()
    {
        $this->addMassAction([
            'type'   => 'delete',
            'label'  => 'Delete',
            'action' => route('bulkupload.admin.profile.massDelete'),
            'method' => 'POST',
            'title'  => ''
        ]);
    }
}
