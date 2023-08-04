<?php

namespace Webkul\Bulkupload\Http\Controllers\Admin;

use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Bulkupload\Repositories\{ImportProductRepository, DataFlowProfileRepository};

class BulkUploadController extends Controller
{
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
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     * @param  \Webkul\Bulkupload\Repositories\DataFlowProfileRepository  $dataFlowProfileRepository
     *
     * @return void
     */
    public function __construct(
        protected AttributeFamilyRepository $attributeFamilyRepository,
        protected ImportProductRepository $importProductRepository,
        protected DataFlowProfileRepository $dataFlowProfileRepository
    )
    {
        $this->_config = request('_config');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $families = $this->attributeFamilyRepository->all();

        return view($this->_config['view'], compact('families'));
    }

    /**
     * Get profiles on basis of attribute family
     *
     * @return array
     */
    public function getAllDataFlowProfiles()
    {
        $dataFlowProfiles = $this->dataFlowProfileRepository->findByField('attribute_family_id', request()->attribute_family_id);

        return ['dataFlowProfiles' => $dataFlowProfiles];
    }

    public function getProfiler()
    {
        $profiles = null;
        $allProfiles = $this->importProductRepository->get()->toArray();

        if (! empty($allProfiles)) {
            foreach ($allProfiles as $allProfile) {
                $profilers[] = $allProfile['data_flow_profile_id'];
            }

            foreach ($profilers as $key => $profiler) {
                $profiles[] = $this->dataFlowProfileRepository->findByfield(['id' => $profilers[$key], 'run_status' => '0']);
            }
        }

        return view($this->_config['view'], compact('profiles'));

    }
}
