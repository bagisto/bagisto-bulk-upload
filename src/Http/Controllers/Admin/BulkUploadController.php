<?php

namespace Webkul\Bulkupload\Http\Controllers\Admin;

use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Bulkupload\Repositories\{ImportProductRepository, DataFlowProfileRepository};
use Webkul\Bulkupload\Requests\AddProfileRequest;
use Webkul\Bulkupload\DataGrids\Admin\ProfileDataGrid;

class BulkUploadController extends Controller
{
     /**
     * Contains routes and views related configuration
     *
     * @var array
     */
    protected $_config;

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Attribute\Repositories\AttributeFamilyRepository  $attributeFamilyRepository
     * @param  \Webkul\Bulkupload\Repositories\DataFlowProfileRepository  $dataFlowProfileRepository
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     *
     * @return void
     */
    public function __construct(
        protected AttributeFamilyRepository $attributeFamilyRepository,
        protected DataFlowProfileRepository $dataFlowProfileRepository,
        protected ImportProductRepository $importProductRepository
    ) {
        $this->_config = request('_config');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        if (request()->ajax()) {
            return app(ProfileDataGrid::class)->toJson();
        }

        $profiles = [];
        $families = $this->attributeFamilyRepository->all();
        $allProfiles = $this->importProductRepository->all();

        if (! empty($allProfiles)) {
            $profilers = $allProfiles->pluck('data_flow_profile_id');
 
            foreach ($profilers as $key => $profiler) {
                $profiles[] = $this->dataFlowProfileRepository->findByfield(['id' => $profilers[$key], 'run_status' => '0']);
            }
        }
        
        return view($this->_config['view'], compact('families', 'profiles'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(AddProfileRequest $addProfileRequest)
    {
        $this->dataFlowProfileRepository->create($addProfileRequest->validated());

        session()->flash('success',trans('bulkupload::app.admin.bulk-upload.messages.profile-saved'));

        return redirect()->route('admin.dataflow-profile.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $families = $this->attributeFamilyRepository->all();
        $profiles = $this->dataFlowProfileRepository->findOrFail($id);

        return view($this->_config['view'], compact('families', 'profiles'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update($id)
    {
        $this->dataFlowProfileRepository->update(request()->except('_token'), $id);
        
        session()->flash('success', trans('admin::app.response.update-success', ['name' => 'Product']));

        return redirect()->route('admin.dataflow-profile.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $this->dataFlowProfileRepository->findOrFail($id)->delete();

        session()->flash('success',trans('bulkupload::app.admin.bulk-upload.messages.profile-deleted'));

        return response()->json(['message' => true], 200);
    }

    /**
     * Mass Delete the dataflowprofiles
     *
     * @return \Illuminate\Http\Response
     */
    public function massDestroy()
    {
        $profileIds = explode(',', request()->input('indexes'));

        foreach ($profileIds as $profileId) {
            $profile = $this->dataFlowProfileRepository->find($profileId);

            if (isset($profile)) {
                $this->dataFlowProfileRepository->delete($profileId);
            }
        }

        session()->flash('success', trans('admin::app.catalog.products.mass-delete-success'));

        return redirect()->route($this->_config['redirect']);
    }
}