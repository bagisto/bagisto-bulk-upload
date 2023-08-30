<?php

namespace Webkul\Bulkupload\Http\Controllers\Admin;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Webkul\Admin\Imports\DataGridImport;
use Webkul\Bulkupload\Helpers\ImportProduct;
use Webkul\Bulkupload\Jobs\ProductUploadJob;
use Webkul\Bulkupload\Repositories\Products\SimpleProductRepository;
use Webkul\Bulkupload\Repositories\Products\BookingProductRepository;
use Webkul\Bulkupload\Repositories\Products\BundledProductRepository;
use Webkul\Bulkupload\Repositories\Products\GroupedProductRepository;
use Webkul\Bulkupload\Repositories\Products\VirtualProductRepository;
use Webkul\Bulkupload\Repositories\Products\ConfigurableProductRepository;
use Webkul\Bulkupload\Repositories\Products\DownloadableProductRepository;
use Webkul\Bulkupload\Repositories\{ImportProductRepository, DataFlowProfileRepository};

class HelperController extends Controller
{
    /**
     * @var array
     */
    protected $product = [];

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     * @param  \Webkul\Bulkupload\Repositories\DataFlowProfileRepository  $dataFlowProfileRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\SimpleProductRepository  $simpleProductRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\ConfigurableProductRepository  $configurableProductRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\VirtualProductRepository  $virtualProductRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\DownloadableProductRepository  $downloadableProductRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\BundledProductRepository  $bundledProductRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\BookingProductRepository  $bookingProductRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\GroupedProductRepository  $groupedProductRepository
     * @param  \Webkul\Bulkupload\Helpers\ImportProduct  $importProduct
     *
     * @return void
     */
    public function __construct(
        protected ImportProductRepository $importProductRepository,
        protected DataFlowProfileRepository $dataFlowProfileRepository,
        protected SimpleProductRepository $simpleProductRepository,
        protected ConfigurableProductRepository $configurableProductRepository,
        protected VirtualProductRepository $virtualProductRepository,
        protected DownloadableProductRepository $downloadableProductRepository,
        protected BundledProductRepository $bundledProductRepository,
        protected BookingProductRepository $bookingProductRepository,
        protected GroupedProductRepository $groupedProductRepository,
        protected ImportProduct $importProduct
    )
    {
    }

    /**
     * Download sample files.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadFile()
    {
        foreach (config('product_types') as $key => $productType) {
            $fileExtension = request()->download_sample == $key . '-csv' ? 'csv' : (request()->download_sample == $key . '-xls' ? 'xlsx' : null);

            if ($fileExtension) {
                $filePath = public_path('storage/downloads/sample-files/bulk' . $key . 'productupload.' . $fileExtension);

                if (file_exists($filePath)) {
                    return response()->download($filePath);
                }
            }
        }

        session()->flash('error', 'Product type is not available');
        return redirect()->route('admin.bulk-upload.index');
    }

    /**
     * store import products for profile execution
     *
     * @return \Illuminate\Http\Response
     */
    public function importNewProductsStore()
    {
        request()->validate([
            'file_path' => 'required',
            'attribute_family_id' => 'required',
            'data_flow_profile' => 'required',
            'image_path' => 'mimetypes:application/zip|max:10000',
        ]);

        if ($this->dataFlowProfileRepository->find(request()->data_flow_profile)) {
            return $this->importProduct->store();
        }

        session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.data-profile-not-selected'));

        return back();
    }

    /**
     * Read count of records in CSV/XLSX
     *
     * @return \Illuminate\Http\Response
     */
    public function readCSVData()
    {
        $dataFlowProfileRecord = $this->importProductRepository->findOneByField('data_flow_profile_id', request()->data_flow_profile_id);

        if ($dataFlowProfileRecord) {
            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];

            $countCSV = count(array_filter($csvData, fn($item) => $item['type'] === 'configurable')) ?: count($csvData);
        } else {
            $countCSV = 0;
        }

        $this->dataFlowProfileRepository->update(['run_status' => '1'], request()->data_flow_profile_id);

        return $countCSV;
    }

    /**
     * profile execution to upload products
     *
     * @return \Illuminate\Http\Response
     */
    public function runProfile()
    {
        $dataFlowProfileId = request()->data_flow_profile_id;
        $csvData = (new DataGridImport)->toArray($this->importProductRepository->findOneByField('data_flow_profile_id', $dataFlowProfileId)->file_path)[0];

        if ($dataFlowProfileRecord = $this->importProductRepository->findOneByField('data_flow_profile_id', $dataFlowProfileId)) {
            $imageZipName = $dataFlowProfileRecord->image_path ? $this->storeImageZip($dataFlowProfileRecord) : null;

            for ($i = request()->countOfStartedProfiles; $i < count($csvData); $i++) {
                $product['loopCount'] = $i;

                switch ($csvData[$i]['type']) {
                    case "simple":
                        return response()->json($this->simpleProductRepository->createProduct(request()->all(), $imageZipName, $product));
                    case "virtual":
                        return response()->json($this->virtualProductRepository->createProduct(request()->all(), $imageZipName));
                    case "downloadable":
                        return response()->json($this->downloadableProductRepository->createProduct(request()->all(), $imageZipName));
                    case "grouped":
                        return response()->json($this->groupedProductRepository->createProduct(request()->all(), $imageZipName));
                    case "booking":
                        return response()->json($this->bookingProductRepository->createProduct(request()->all(), $imageZipName));
                    case "bundle":
                        return response()->json($this->bundledProductRepository->createProduct(request()->all(), $imageZipName));
                    case "configurable":
                    case "variant":
                        return response()->json($this->configurableProductRepository->createProduct(request()->all(), $imageZipName, $product));
                }
<<<<<<< HEAD
            } else {
                return response()->json([
                    "success" => true,
                    "message" => "CSV Product Successfully Imported",
                ]);
=======
>>>>>>> b92a89fd8dcf8e0bb4efcfeef99bf37fef2472ff
            }

            return response()->json(["success" => true, "message" => "CSV Product Successfully Imported"]);
        }
    }

    public function storeImageZip($dataFlowProfileRecord)
    {
        $imageZip = new \ZipArchive();
        $extractedPath = storage_path('app/public/imported-products/extracted-images/admin/'.$dataFlowProfileRecord->id.'/');

        if ($imageZip->open(storage_path('app/public/'.$dataFlowProfileRecord->image_path))) {
            for ($i = 0; $i < $imageZip->numFiles; $i++) {
                $filename = $imageZip->getNameIndex($i);
                $imageZipName = pathinfo($filename);
            }

            $imageZip->extractTo($extractedPath);
            $imageZip->close();
        }

        $listOfImages = scandir($extractedPath.$imageZipName['dirname'].'/');

        foreach ($listOfImages as $key => $imageName) {
            if (preg_match_all('/[\'"]/', $imageName)) {
                $fileName = preg_replace('/[\'"]/', '',$imageName);

                rename($extractedPath.$imageZipName['dirname'].'/'.$imageName, $extractedPath.$imageZipName['dirname'].'/'.$fileName);
            }
        }

        return $imageZipName;
    }

    public function productUploadFromCommand($command = null)
    {
        $profiles = $this->importProductRepository->with('profiler')->get()
            ->filter(fn($profile) => !$profile->profiler->run_status)
            ->pluck('profiler');

        $command->warn('Info: List of available Profiler to run...');

        $profiles->each(function ($profile) use ($command) {
            $command->comment('Profiler Id: ' . $profile->id . ', Profiler Name: ' . $profile->name);
        });

        $dataFlowProfileId = request()->input('data_flow_profile_id');
        $countCSV = 0;
        $dataFlowProfileRecord = $this->importProductRepository->findOneByField('data_flow_profile_id', $dataFlowProfileId);

        $this->dataFlowProfileRepository->update(['run_status' => '1'], $dataFlowProfileId);

        if ($dataFlowProfileRecord) {
            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];
            $countCSV = count(array_filter($csvData, fn($item) => $item['type'] === 'configurable')) ?: count($csvData);
        }

        if ($countCSV) {
            $imageZipName = $dataFlowProfileRecord && $dataFlowProfileRecord->image_path ? $this->storeImageZip($dataFlowProfileRecord) : null;
            $chunks = array_chunk($csvData, 100);
            $batch = Bus::batch([])->dispatch();

            request()->merge([
                "totalNumberOfCSVRecord" => $countCSV,
                "remainingProducts" => $countCSV,
                "productUploaded" => 0,
                "errorCount" => 0,
                "countOfStartedProfiles" => 0,
                "numberOfCSVRecord" => $countCSV,
            ]);

            $batch->add(new ProductUploadJob($imageZipName, $dataFlowProfileRecord, $chunks));

            return response()->json(["success" => true, "message" => "CSV Product Successfully Imported"]);
        } else {
            return response()->json(["success" => true, "message" => "CSV Product Successfully Imported"]);
        }
    }

    public function downloadCsv()
    {
        $uploadedFilesError = File::allFiles(public_path('storage/error-csv-file'));

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
                ->groupBy(function ($item, $key) {
                    return key($item);
                })
                ->map(function ($group) {
                    return $group->map(function ($item) {
                        return $item[key($item)];
                    });
                })
                ->toArray();

            $ids = array_keys($resultArray);

            $profilerName = $this->dataFlowProfileRepository
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
        return $this->dataFlowProfileRepository->find(request()->input('id'))->name;
    }

    public function deleteCSV()
    {
        $fileToDelete = 'error-csv-file/' . request('id') . '/' . request('name');

        if (Storage::delete($fileToDelete)) {
            return response()->json(['message' => 'File deleted successfully']);
        }

        return response()->json(['message' => 'File not found'], 404);
    }
}
