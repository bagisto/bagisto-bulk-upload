<?php

namespace Webkul\Bulkupload\Http\Controllers\Admin;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
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
            if (request()->download_sample == $key.'-csv') {
                return response()->download(public_path('storage/downloads/sample-files/bulk'.$key.'productupload.csv'));
            } else if (request()->download_sample == $key.'-xls') {
                return response()->download(public_path('storage/downloads/sample-files/bulk'.$key.'productupload.xlsx'));
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
            'file_path'           => 'required',
            'attribute_family_id' => 'required',
            'data_flow_profile'   => 'required',
            'image_path'          => 'mimetypes:application/zip|max:10000',
        ]);

        if (! empty($this->dataFlowProfileRepository->find(request()->data_flow_profile))) {
            $importedProducts = $this->importProduct->store();

            return $importedProducts;
        } else {
            session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.data-profile-not-selected'));

            return back();
        }
    }

    /**
     * Read count of records in CSV/XLSX
     *
     * @return \Illuminate\Http\Response
     */
    public function readCSVData()
    {
        $countCSV = 0;

        $dataFlowProfileRecord = $this->importProductRepository->findOneByField('data_flow_profile_id', request()->data_flow_profile_id);

        // $this->dataFlowProfileRepository->update(['run_status' => '1'], request()->data_flow_profile_id);

        if ($dataFlowProfileRecord) {
            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];

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

    /**
     * profile execution to upload products
     *
     * @return \Illuminate\Http\Response
     */
    public function runProfile()
    {
        $data_flow_profile_id = request()->data_flow_profile_id;
        $numberOfCSVRecord = request()->numberOfCSVRecord;
        $countOfStartedProfiles = request()->countOfStartedProfiles;
        $product = [];
        $imageZipName = null;

        $dataFlowProfileRecord = $this->importProductRepository->findOneByField
        ('data_flow_profile_id', $data_flow_profile_id);

        if ($dataFlowProfileRecord) {
            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];

            if (isset($dataFlowProfileRecord->image_path) && ($dataFlowProfileRecord->image_path != "") ) {
                $imageZipName = $this->storeImageZip($dataFlowProfileRecord);
            }

            if ($numberOfCSVRecord >= 0) {
                for ($i = $countOfStartedProfiles; $i < count($csvData); $i++) {
                    $product['loopCount'] = $i;

                    switch($csvData[$i]['type']) {
                        case "simple":
                            $simpleProduct = $this->simpleProductRepository->createProduct(request()->all(), $imageZipName, $product);

                            return response()->json($simpleProduct);

                        case "virtual":
                            $virtualProduct = $this->virtualProductRepository->createProduct(request()->all(), $imageZipName);

                            return response()->json($virtualProduct);
                        case "downloadable":
                            $downloadableProduct =  $this->downloadableProductRepository->createProduct(request()->all(), $imageZipName);

                            return response()->json($downloadableProduct);
                        case "grouped":
                            $groupedProduct = $this->groupedProductRepository->createProduct(request()->all(), $imageZipName);

                            return response()->json($groupedProduct);
                        case "booking":
                            $bookingProduct = $this->bookingProductRepository->createProduct(request()->all(), $imageZipName);

                            return response()->json($bookingProduct);
                        case "bundle":
                            $bundledProduct = $this->bundledProductRepository->createProduct(request()->all(), $imageZipName);

                            return response()->json($bundledProduct);
                        case "configurable" OR "variant":
                            $configurableProduct = $this->configurableProductRepository->createProduct(request()->all(), $imageZipName, $product);

                            return response()->json($configurableProduct);
                    }
                }
            } else {
                return response()->json([
                    "success" => true,
                    "message" => "CSV Product Successfully Imported"
                ]);
            }
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
                    ->filter(fn($profile) => ! $profile->profiler->run_status)
                    ->pluck('profiler');

        $command->warn('Info: List of available Profiler to run...');

        foreach ($profiles as $profile) {
            $command->comment('Profiler Id: '. $profile->id . ', Profiler Name: '. $profile->name);
        }

        // $dataFlowProfileId = $command->ask('Enter profiler id');
        $dataFlowProfileId = request()->input('data_flow_profile_id');

        $countCSV = 0;

        $dataFlowProfileRecord = $this->importProductRepository->findOneByField('data_flow_profile_id', $dataFlowProfileId);

        // $this->dataFlowProfileRepository->update(['run_status' => '1'], $dataFlowProfileId);

        if ($dataFlowProfileRecord) {
            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];

            $countConfig = array_filter($csvData, function ($item) {
                                return $item['type'] === 'configurable';
                            });

            if (count($countConfig)) {
                $countCSV = count($countConfig);
            } else {
                $countCSV = count($csvData);
            }
        }

        if ($countCSV) {

            $imageZipName = null;

            if (isset($dataFlowProfileRecord->image_path) && ($dataFlowProfileRecord->image_path != "") ) {
                $imageZipName = $this->storeImageZip($dataFlowProfileRecord);
            }

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
        } else {
            return response()->json([
                "success" => true,
                "message" => "CSV Product Successfully Imported"
            ]);
        }
    }

    public function downloadCsv()
    {
        $uploadedFilesError = File::allFiles(public_path('storage/error-csv-file'));

        $uploadedFilesError = array_map(function ($file) {
            return [
                    $file->getRelativePath() => $file->getRealPath(),
                    'time' => date('Y-m-d H:i:s', filectime($file))
                ];
        }, $uploadedFilesError);

        $resultArray = collect($uploadedFilesError)
                    ->groupBy(function ($item) {
                        return key($item);
                    })
                    ->map(function ($group) {
                        return collect($group)->map(function ($item) {
                            return [
                                'link' => $item[key($item)],
                                'time' => $item['time'],
                            ];
                        });
                    });

                // Convert the result to a plain array
                $resultArray = $resultArray->toArray();

        return response()->json($resultArray);

    }
}
