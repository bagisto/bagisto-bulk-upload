<?php

namespace Webkul\Bulkupload\Http\Controllers\Admin;

use Webkul\Admin\Imports\DataGridImport;
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
     * Download sample files.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadSampleFile()
    {
        if (! empty(request()->download_sample)) {
            return response()->download(public_path("vendor/webkul/admin/assets/sample-files/".request()->download_sample));
        }

        session()->flash('error', 'Product type is not available, Please select valid product type');

        return redirect()->route('admin.bulk-upload.upload-file.index');
    }

    /**
     * Get profiles on basis of attribute family
     *
     * @return array
     */
    public function getBulkProductImporter()
    {
        $dataFlowProfiles = $this->bulkProductImporterRepository->findByField('attribute_family_id', request()->attribute_family_id);

        return ['dataFlowProfiles' => $dataFlowProfiles];
    }

    /**
     * store import products for profile execution
     *
     * @return \Illuminate\Http\Response
     */
    public function storeProductsFile()
    {
        $request = request();
        $importerId = $request->bulk_product_importer_id;

        $validExtensions = ['csv', 'xls', 'xlsx'];
        $validImageExtensions = ['zip', 'rar'];

        // Validate the request
        $request->validate([
            'file_path'                => 'required',
            'image_path'               => 'mimetypes:application/zip|max:10000',
            'attribute_family_id'      => 'required',
            'bulk_product_importer_id' => 'required',
        ]);

        $importer = $this->bulkProductImporterRepository->find($importerId);

        if (empty($importer)) {
            session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.data-profile-not-selected'));

            return back();
        }

        $product = [
            'attribute_family_id'      => $request->attribute_family_id,
            'bulk_product_importer_id' => $importerId,
            'is_downloadable'          => $request->is_downloadable ? 1 : 0,
            'is_links_have_samples'    => $request->is_link_have_sample ? 1 : 0,
            'is_samples_available'     => $request->is_sample ? 1 : 0,
            'image_path'               => '',
            'file_path'                => '',
            'file_name'                => $request->file('file_path')->getClientOriginalName()
        ];

        $fileStorePath = 'imported-products/admin';

        // Handle link files
        if ($request->hasFile('link_files')) {
            $linkFiles = $request->file('link_files');

            if (in_array($linkFiles->getClientOriginalExtension(), $validImageExtensions)) {
                $product['upload_link_files'] = $linkFiles->storeAs($fileStorePath . '/link-files', uniqid() . '.' . $linkFiles->getClientOriginalExtension());
            } else {
                session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

                return back();
            }
        }

        // Handle link sample files
        if ($request->is_link_have_sample && $request->hasFile('link_sample_files')) {
            $linkSampleFiles = $request->file('link_sample_files');

            if (in_array($linkSampleFiles->getClientOriginalExtension(), $validImageExtensions)) {
                $product['upload_link_sample_files'] = $linkSampleFiles->storeAs($fileStorePath . '/link-sample-files', uniqid() . '.' . $linkSampleFiles->getClientOriginalExtension());
            } else {
                session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

                return back();
            }
        }

        // Handle sample files
        if ($request->is_sample && $request->hasFile('sample_file')) {
            $sampleFile = $request->file('sample_file');

            if (in_array($sampleFile->getClientOriginalExtension(), $validImageExtensions)) {
                $product['upload_sample_files'] = $sampleFile->storeAs($fileStorePath . '/sample-file', uniqid() . '.' . $sampleFile->getClientOriginalExtension());
            } else {
                session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

                return back();
            }
        }

        // Handle image uploads
        if ($request->hasFile('image_path')) {
            $uploadedImage = request()->file('image_path');

            if (in_array($uploadedImage->getClientOriginalExtension(), $validImageExtensions)) {
                $product['image_path'] = $uploadedImage->storeAs($fileStorePath . '/images', uniqid() . '.' . $uploadedImage->getClientOriginalExtension());
            } else {
                session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

                return back();
            }
        }

        // Handle file uploads
        if ($request->hasFile('file_path')) {
            $uploadedFile = request()->file('file_path');

            if (in_array($uploadedFile->getClientOriginalExtension(), $validExtensions)) {
                $product['file_path'] = $uploadedFile->storeAs($fileStorePath . '/files', uniqid() . '.' . $uploadedFile->getClientOriginalExtension());
            } else {
                session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

                return back();
            }
        }

        $this->importProductRepository->create($product);

        session()->flash('success', trans('bulkupload::app.admin.bulk-upload.messages.profile-saved'));

        return back();
    }

    public function getFamilyAttributesToUploadFile()
    {
        $families = $this->attributeFamilyRepository->all();

        return view('bulkupload::admin.bulk-upload.run-profile.index', compact('families'));
    }

    /**
     * Get profiles on basis of attribute family
     *
     * @return array
     */
    public function getProductImporter()
    {
        $dataFlowProfiles = $this->bulkProductImporterRepository->findByField('attribute_family_id', request()->attribute_family_id);

        $productImporter = $dataFlowProfiles->filter(function ($dataFlowProfile) {
            return $dataFlowProfile->import_product->isNotEmpty();
        });

        return ['dataFlowProfiles' => $productImporter];
    }

    public function deleteProductFile()
    {
        try {
            $dataFlowProfile = $this->bulkProductImporterRepository->findOrFail(request()->bulk_product_importer_id);

            $dataFlowProfile->import_product()->where('id', request()->product_file_id)->delete();

            return ['importer_product_file' => $dataFlowProfile->import_product];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Handle the case where the bulk_product_importer_id does not exist
            return response()->json(['error' => 'Data Flow Profile not found'], 404);
        }
    }

    public function readCSVData()
    {
        $countCSV = 0;

        $dataFlowProfileRecord = $this->importProductRepository->where([
            'bulk_product_importer_id' => request()->bulk_product_importer_id,
            'id' => request()->product_file_id,
        ])->first();

        if ($dataFlowProfileRecord) {
+
            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];
dd($csvData);
            $countConfig = array_filter($csvData, function ($item) {
                return $item['type'] === 'configurable';
            });

            if (count($countConfig)) {
                $countCSV = count($countConfig);
            } else {
                $countCSV = count($csvData);
            }
        }

        return $countCSV;
    }

}
