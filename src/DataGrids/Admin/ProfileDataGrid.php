<?php

namespace Webkul\Bulkupload\DataGrids\Admin;

use Illuminate\Support\Facades\DB;
use Webkul\Ui\DataGrid\DataGrid;

class ProfileDataGrid extends DataGrid
{
    /**
     * Index 
     * 
     * @var string
     */
    protected $index = 'id';

    /**
     * Prepare the query builder
     *
     * @return void
     */
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

    /**
     * Add Column
     *
     * @return void
     */
    public function addColumns()
    {
        $this->addColumn([
            'index'      => 'profile_name',
            'label'      => __('bulkupload::app.admin.bulk-upload.data-flow-profile.name'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => __('admin::app.catalog.products.family'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true
        ]);

        $this->addColumn([
            'index'      => 'locale_code',
            'label'      => __('bulkupload::app.admin.bulk-upload.data-flow-profile.data-grid.locale_code'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'label'      => __('bulkupload::app.admin.bulk-upload.data-flow-profile.data-grid.created-at'),
            'type'       => 'datetime',
            'sortable'   => true,
            'searchable' => false,
            'filterable' => true
        ]);
    }

    /**
     * Prepare actions
     *
     * @return void
     */
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
            'type'          => __('admin::app.datagrid.delete'),
            'method'        => 'POST',
            'route'         => 'bulkupload.admin.profile.delete',
            'confirm_text'  => __('ui::app.datagrid.massaction.delete'),
            'icon'          => 'icon trash-icon',
            'title'         => ''
        ]);

        $this->enableAction = true;
    }

    /**
     * Prepare mass actions
     *
     * @return void
     */
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
