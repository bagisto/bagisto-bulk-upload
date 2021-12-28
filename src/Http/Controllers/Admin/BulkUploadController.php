<?php

namespace Webkul\Bulkupload\Http\Controllers\Admin;

use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Bulkupload\Repositories\ImportProductRepository;
use Webkul\Bulkupload\Repositories\DataFlowProfileRepository;

class BulkUploadController extends Controller
{
    /**
     * DataFlowProfileRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\DataFlowProfileRepository
     *
     */
     protected $dataFlowProfileRepository;

    /**
     * AttributeFamilyRepository object
     *
     * @var \Webkul\Attribute\Repositories\AttributeFamilyRepository
     *
     */
    protected $attributeFamilyRepository;

    /**
     * ImportProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\ImportProductRepository
     *
     */
    protected $importProductRepository;

    /**
     * @var array
     */
    protected $product = [];

     /**
     * Contains route related configuration
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
        AttributeFamilyRepository $attributeFamilyRepository,
        DataFlowProfileRepository $dataFlowProfileRepository,
        ImportProductRepository $importProductRepository
    )
    {
        $this->_config = request('_config');

        $this->attributeFamilyRepository = $attributeFamilyRepository;

        $this->importProductRepository = $importProductRepository;

        $this->dataFlowProfileRepository = $dataFlowProfileRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $profiles = null;
        $families = $this->attributeFamilyRepository->all();
        $allProfiles = $this->importProductRepository->get()->toArray();

        if (! empty($allProfiles)) {
            foreach ($allProfiles as $allProfile) {
                $profilers[] = $allProfile['data_flow_profile_id'];
            }

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
    public function store()
    {
        request()->validate([
            'name' => 'required|unique:bulkupload_data_flow_profiles',
            'attribute_family' => 'required',
            'locale_code' => 'required'
        ]);

        $dataFlowProfileAdmin['name'] = request()->name;
        $dataFlowProfileAdmin['attribute_family_id'] = request()->attribute_family;
        $dataFlowProfileAdmin['locale_code'] = request()->locale_code;


        $this->dataFlowProfileRepository->create($dataFlowProfileAdmin);

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
        $product = $this->dataFlowProfileRepository->update(request()->except('_token'), $id);
        $families = $this->attributeFamilyRepository->all();
        $profiles = $this->dataFlowProfileRepository->findOrFail($id);

        session()->flash('success', trans('admin::app.response.update-success', ['name' => 'Product']));

        return redirect()->route('admin.dataflow-profile.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $product = $this->dataFlowProfileRepository->findOrFail($id)->delete();

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