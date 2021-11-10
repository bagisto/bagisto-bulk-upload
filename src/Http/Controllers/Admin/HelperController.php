<?php

namespace Webkul\Bulkupload\Http\Controllers\Admin;

use Webkul\Admin\Imports\DataGridImport;
use Maatwebsite\Excel\Validators\Failure;
use Webkul\Bulkupload\Helpers\ImportProduct;
use Webkul\Bulkupload\Repositories\ImportProductRepository;
use Webkul\Bulkupload\Repositories\DataFlowProfileRepository;
use Webkul\Bulkupload\Repositories\Products\SimpleProductRepository;
use Webkul\Bulkupload\Repositories\Products\ConfigurableProductRepository;
use Webkul\Bulkupload\Repositories\Products\VirtualProductRepository;
use Webkul\Bulkupload\Repositories\Products\DownloadableProductRepository;
use Webkul\Bulkupload\Repositories\Products\GroupedProductRepository;
use Webkul\Bulkupload\Repositories\Products\BundledProductRepository;
use Webkul\Bulkupload\Repositories\Products\BookingProductRepository;

class HelperController extends Controller
{

    /**
     * SimpleProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\Products\SimpleProductRepository
     *
     */
    protected $simpleProductRepository;

    /**
     * ConfigurableProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\Products\ConfigurableProductRepository
     *
     */
    protected $configurableProductRepository;

    /**
     * VirtualProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\Products\VirtualProductRepository;
     *
     */
    protected $virtualProductRepository;

    /**
     * DownloadableProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\Products\DownloadableProductRepository
     *
     */
    protected $downloadableProductRepository;

    /**
     * BundledProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\Products\BundledProductRepository
     *
     */
    protected $bundledProductRepository;

    /**
     * BookingProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\Products\BookingProductRepository
     *
     */
    protected $bookingProductRepository;

    /**
     * GroupedProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\Products\GroupedProductRepository
     *
     */
    protected $groupedProductRepository;

    /**
     * DataFlowProfileRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\DataFlowProfileRepository
     *
     */
    protected $dataFlowProfileRepository;

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
     * ImportProduct helper
     *
     * @var \Webkul\Bulkupload\Helpers\ImportProduct
     */
    protected $importProduct;

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
        ImportProductRepository $importProductRepository,
        DataFlowProfileRepository $dataFlowProfileRepository,
        SimpleProductRepository $simpleProductRepository,
        ConfigurableProductRepository $configurableProductRepository,
        VirtualProductRepository $virtualProductRepository,
        DownloadableProductRepository $downloadableProductRepository,
        BundledProductRepository $bundledProductRepository,
        BookingProductRepository $bookingProductRepository,
        GroupedProductRepository $groupedProductRepository,
        ImportProduct $importProduct
    )
    {
        $this->importProductRepository = $importProductRepository;

        $this->dataFlowProfileRepository = $dataFlowProfileRepository;

        $this->simpleProductRepository = $simpleProductRepository;

        $this->configurableProductRepository = $configurableProductRepository;

        $this->virtualProductRepository = $virtualProductRepository;

        $this->downloadableProductRepository = $downloadableProductRepository;

        $this->bundledProductRepository = $bundledProductRepository;

        $this->bookingProductRepository = $bookingProductRepository;

        $this->groupedProductRepository = $groupedProductRepository;

        $this->importProduct = $importProduct;
    }

    /**
     * Download sample files.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadFile()
    {
        $items = [];

        foreach (config('product_types') as $item) {
            $item['children'] = [];

            array_push($items, $item);
        }

        $types = core()->sortItems($items);

        foreach ($types as $key => $productType) {
            if (request()->download_sample == $key.'-csv') {
                return response()->download(public_path('storage/downloads/sample-files/bulk'.$key.'productupload.csv'));
            } else if (request()->download_sample == $key.'-xls') {
                return response()->download(public_path('storage/downloads/sample-files/bulk'.$key.'productupload.xlsx'));
            } else if (empty(request()->download_sample)) {
                return redirect()->back();
            }
        }
    }

    /**
     * Get profiles on basis of attribute family
     *
     * @return array
     */
    public function getAllDataFlowProfiles()
    {
        $attribute_family_id = request()->attribute_family_id;

        $dataFlowProfiles = $this->dataFlowProfileRepository->findByField('attribute_family_id', request()->attribute_family_id);

        return ['dataFlowProfiles' => $dataFlowProfiles];
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

        $this->dataFlowProfileRepository->update(['run_status' => '1'], request()->data_flow_profile_id);

        if ($dataFlowProfileRecord) {
            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];

            for ($i = 0; $i < count($csvData); $i++) {
                if ($csvData[$i]['type'] == 'configurable') {
                    $countCSV += 1;
                } else if ($csvData[0]['type'] != 'configurable') {
                    $countCSV = count($csvData);
                }
            }

            return $countCSV;
        }
    }

    /**
     * store import products for profile execution
     *
     * @return \Illuminate\Http\Response
     */
    public function importNewProductsStore()
    {
        $dataFlowProfileId = request()->data_flow_profile;

        if ($dataFlowProfileId) {
            $importedProducts = $this->importProduct->store();

            return $importedProducts;
        } else {
            session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.data-profile-not-selected'));

            return back();
        }
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
}
