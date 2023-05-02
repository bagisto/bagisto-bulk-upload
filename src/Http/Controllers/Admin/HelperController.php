<?php

namespace Webkul\Bulkupload\Http\Controllers\Admin;

use Illuminate\Support\Facades\Storage;
use Webkul\Admin\Imports\DataGridImport;
use Webkul\Bulkupload\Helpers\ImportProduct;
use Webkul\Bulkupload\Requests\ImportProductRequest;
use Webkul\Bulkupload\Repositories\{ImportProductRepository, DataFlowProfileRepository};
use Webkul\Bulkupload\Repositories\Products\{SimpleProductRepository, ConfigurableProductRepository, VirtualProductRepository};
use Webkul\Bulkupload\Repositories\Products\{DownloadableProductRepository, GroupedProductRepository, BundledProductRepository, BookingProductRepository};

class HelperController extends Controller
{
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
    ) {}

    /**
     * Download sample files.
     *
     * @return \Symfony\Component\HttpFoundation\Response|void
     */
    public function downloadFile()
    {
        if (! request()->filled('download_sample')) {
            session()->flash('error', __('bulkupload::app.admin.bulk-upload.upload-files.select-sample-file'));

            return redirect()->back();
        }

        $items = array_values(array_map(function($item) {
            $item['children'] = [];
            return $item;
        }, config('product_types')));

        $types = core()->sortItems($items);

        foreach ($types as $productType) {
            if (request()->input('download_sample') == $productType['key'] . '-csv') {
                
                return response()->download(public_path('storage/downloads/sample-files/bulk'.$productType['key']  .'productupload.csv'));

            } else if (request()->input('download_sample') == $productType['key'] . '-xls') {

                return response()->download(public_path('storage/downloads/sample-files/bulk'.$productType['key']  .'productupload.xlsx'));
            } 
        }

        return redirect()->back();
    }

    /**
     * Get profiles on basis of attribute family
     *
     * @return array
     */
    public function getAllDataFlowProfiles()
    {
        $dataFlowProfiles = $this->dataFlowProfileRepository->findByField('attribute_family_id', request()->input('attribute_family_id'));

        return ['dataFlowProfiles' => $dataFlowProfiles];
    }

    /**
     * Read count of records in CSV/XLSX
     *
     * @return integer|void
     */
    public function readCSVData()
    {
        $countCSV = 0;

        $dataFlowProfileRecord = $this->importProductRepository->findOneByField('data_flow_profile_id', request()->input('data_flow_profile_id'));

        $this->dataFlowProfileRepository->update(['run_status' => '0'], request()->input('data_flow_profile_id'));

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
     * Store import products for profile execution
     *
     * @return \Illuminate\Http\Response
     */
    public function importNewProductsStore(ImportProductRequest $importProductRequest)
    {
        return $this->importProduct->store();
    }

    /**
     * Profile execution to upload products
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function runProfile()
    {
        $product = [];
        $imageZipName = null;

        $dataFlowProfileRecord = $this->importProductRepository->findOneByField('data_flow_profile_id', request()->input('data_flow_profile_id'));

        if ($dataFlowProfileRecord) {
            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];

            if (isset($dataFlowProfileRecord->image_path) && ($dataFlowProfileRecord->image_path != "") ) {
                $imageZipName = $this->storeImageZip($dataFlowProfileRecord);
            }

            if ((int) request()->input('numberOfCSVRecord') >= 0) {
                for ($i = (int) request()->input('countOfStartedProfiles'); $i < count($csvData); $i++) {
                    $product['loopCount'] = $i;

                    switch ($csvData[$i]['type']) {
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
