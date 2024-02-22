<?php

namespace Webkul\Bulkupload\Http\Controllers\Admin;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use League\OAuth1\Client\Server\Tumblr;
use Webkul\Admin\Imports\DataGridImport;
use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Bulkupload\Repositories\{ImportProductRepository, BulkProductImporterRepository};
use Webkul\Bulkupload\Jobs\ProductUploadJob;

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
        $uniqueAttributeFamilyIds = $this->importProductRepository->distinct()->pluck('attribute_family_id');

        $families = $this->attributeFamilyRepository->whereIn('id', $uniqueAttributeFamilyIds)->get();

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
        $productFileRecord = $this->importProductRepository->where([
            'bulk_product_importer_id' => request()->bulk_product_importer_id,
            'id' => request()->product_file_id,
        ])->first();

        if (! $productFileRecord) {
            return response()->json([
                'error'   => true,
                'message' => 'Selected File not found.'
            ]);
        }

        $csvData = (new DataGridImport)->toArray($productFileRecord->file_path)[0];

        // $productFileRecord->update(['status' => 0]);

        $countConfig = count(array_filter($csvData, function ($item) {
            return $item['type'] === 'configurable';
        }));
        
        $countCSV = ($countConfig > 0) ? $countConfig : count($csvData);
        
        if (! $countCSV) {
            // Handle the case when $countCSV is false (or any condition you need).
            return response()->json([
                "success" => false,
                "message" => "No CSV Data to Import"
            ]);
        }

        $imageZipName = null;

        if (isset($productFileRecord->image_path) && !empty($productFileRecord->image_path)) {
            $imageZipName = $this->storeImageZip($productFileRecord);
        }

        $chunks = array_chunk($csvData, 100);

        $batch = Bus::batch([])->dispatch();

        $batch->add(new ProductUploadJob($imageZipName, $productFileRecord, $chunks, $countCSV));
               
        // $productFileRecord->delete();
        
        return response()->json([
            "success" => true,
            "message" => "CSV Product Successfully Imported"
        ]);
    }

    /**
     * Store and extract images from a zip file, removing any single quotes or double quotes in image filenames.
     *
     * @param $dataFlowProfileRecord - The data flow profile record containing image information.
     * @return array - An array containing information about the extracted images.
     */
    public function storeImageZip($dataFlowProfileRecord)
    {
        // Create a ZipArchive instance to work with the zip file.
        $imageZip = new \ZipArchive();

        // Define the path where extracted images will be stored.
        $extractedPath = storage_path('app/public/imported-products/extracted-images/admin/'.$dataFlowProfileRecord->id.'/');

        // Check if the zip file can be opened successfully.
        if ($imageZip->open(storage_path('app/public/'.$dataFlowProfileRecord->image_path))) {
            $imageZipName = null; // Initialize the variable to store image zip information.

            // Loop through each file in the zip archive.
            for ($i = 0; $i < $imageZip->numFiles; $i++) {
                $filename = $imageZip->getNameIndex($i);

                // Extract information about the file, including its dirname.
                $imageZipName = pathinfo($filename);

                // Ensure the extracted path exists.
                if (!is_dir($extractedPath.$imageZipName['dirname'])) {
                    mkdir($extractedPath.$imageZipName['dirname'], 0777, true);
                }
            }

            // Extract all files from the zip archive to the specified path.
            $imageZip->extractTo($extractedPath);
            $imageZip->close();
        }

        // List all files in the extracted directory.
        $listOfImages = scandir($extractedPath.$imageZipName['dirname'].'/');

        // Iterate through the list of images to remove quotes from filenames.
        foreach ($listOfImages as $key => $imageName) {
            if (preg_match_all('/[\'"]/', $imageName)) {
                $fileName = preg_replace('/[\'"]/', '', $imageName);

                // Rename the file to remove quotes from its name.
                rename($extractedPath.$imageZipName['dirname'].'/'.$imageName, $extractedPath.$imageZipName['dirname'].'/'.$fileName);
            }
        }

        // Return information about the extracted images.
        return $imageZipName;
    }

    public function downloadCsv()
    {
        
        $folderPath = public_path('storage/error-csv-file');
        
        // Check if the folder exists
        if (!File::exists($folderPath)) {
            // If it doesn't exist, create it
            File::makeDirectory($folderPath, 0755, true, true);
        }

        $uploadedFilesError = File::allFiles($folderPath);

        $resultArray = collect($uploadedFilesError)
                ->map(function ($file) {
                    return [
                        $file->getRelativePath() => [
                            'link' => asset('storage/error-csv-file/' . $file->getRelativePathname()),
                            'time' => date('Y-m-d H:i:s', filectime($file)),
                            'fileName' => $file->getFilename(),
                        ],
                    ];
                })
                ->groupBy(function ($item) {
                    return key($item);
                })
                ->map(function ($group) {
                    return $group->map(function ($item) {
                        return $item[key($item)];
                    });
                })
                ->toArray();

            $ids = array_keys($resultArray);

            $profilerName = $this->bulkProductImporterRepository
                ->get()
                ->whereIn('id', $ids)
                ->pluck('name')
                ->all();

            return response()->json([
                'resultArray' => $resultArray,
                'profilerNames' => array_combine($ids, $profilerName),
            ]);
    }

    public function getProfiler()
    {
        return $this->bulkProductImporterRepository->find(request()->input('id'))->name;
    }

    public function deleteCSV()
    {

        $fileToDelete = 'error-csv-file/' . request('id') . '/' . request('name');

        if (Storage::delete($fileToDelete)) {
            return response()->json(['message' => 'File deleted successfully']);
        }

        return response()->json(['message' => 'File not found'], 404);
    }

    public function readErrorFile()
    {
        // Read the CSV file and generate HTML to display product data
        $csvFilePath = public_path('storage/error-csv-file/' . request()->bulk_product_importer_id . '/' . request()->product_file_id . '/error-file.csv');

        $csvData = [];

        if (($handle = fopen($csvFilePath, 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $csvData[] = $data;
            }
            fclose($handle);
        }

        return $csvData;
    }
    
    
    // Get Uploaded and not uploaded product detail
    public function getUploadedProductOrNotUploadedProduct()
    {   
        $data = [];
        $isFileUploadComplete = false;
        $status = request()->status;
        $message = false;
        
        if (session()->has('notUploadedProduct')) {
            $data['notUploadedProduct'] = session()->get('notUploadedProduct');
        }

        if (session()->has('uploadedProduct')) {
            $data['uploadedProduct'] = session()->get('uploadedProduct');
        }   
        

        if (session()->has('isFileUploadComplete')) {
            $isFileUploadComplete = session()->get('isFileUploadComplete');
            $status = false;
        }
        
        if (session()->has('message')) {
            $message = session()->get('message');
            $status = false;
        }
        
        if (empty($data)) {
            $status = false;
        }

        return response()->json(['message' => $data ,'status' => $status,'success'=>$message, 'isFileUploadComplete' => $isFileUploadComplete], 200);
    }
}
