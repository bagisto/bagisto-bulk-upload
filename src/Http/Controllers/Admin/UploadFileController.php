<?php

namespace Webkul\Bulkupload\Http\Controllers\Admin;

use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Bulkupload\Repositories\{ImportProductRepository, BulkProductImporterRepository};

class UploadFileController extends Controller
{
    /**
     * @var array
     */
    protected $product = [];

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Attribute\Repositories\AttributeFamilyRepository  $attributeFamilyRepository
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     * @param  \Webkul\Bulkupload\Repositories\BulkProductImporterRepository  $bulkProductImporterRepository
     *
     * @return void
     */
    public function __construct(
        protected AttributeFamilyRepository $attributeFamilyRepository,
        protected ImportProductRepository $importProductRepository,
        protected BulkProductImporterRepository $bulkProductImporterRepository
    )
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $families = $this->attributeFamilyRepository->all();

        return view('bulkupload::admin.bulk-upload.upload-files.index', compact('families'));
    }

    /**
     * Get profiles on basis of attribute family
     *
     * @return array
     */
    public function getAllBulkProductImporter()
    {
        $dataFlowProfiles = $this->bulkProductImporterRepository->findByField('attribute_family_id', request()->attribute_family_id);

        return ['dataFlowProfiles' => $dataFlowProfiles];
    }

    public function getProfiler()
    {
        $profiles = $this->importProductRepository->with('profiler')->get()
                    ->filter(fn($profile) => ! $profile->profiler->run_status)
                    ->pluck('profiler');

        return view($this->_config['view'], compact('profiles'));
    }
}
