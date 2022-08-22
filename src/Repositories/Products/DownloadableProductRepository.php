<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Storage;
use Illuminate\Container\Container as App;
use Webkul\Core\Eloquent\Repository;
use Illuminate\Support\Facades\Log;
use Webkul\Admin\Imports\DataGridImport;
use Illuminate\Support\Facades\Validator;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Bulkupload\Repositories\ImportProductRepository;
use Webkul\Product\Repositories\ProductFlatRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Bulkupload\Repositories\Products\HelperRepository;
use Webkul\Bulkupload\Repositories\ProductImageRepository;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Product\Repositories\ProductDownloadableLinkRepository;
use Webkul\Product\Repositories\ProductCustomerGroupPriceRepository;

class DownloadableProductRepository extends Repository
{
    /**
     * ImportProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\ImportProductRepository
     */
    protected $importProductRepository;

    /**
     * CategoryRepository object
     *
     * @var \Webkul\Category\Repositories\CategoryRepository
     */
    protected $categoryRepository;

    /**
     * ProductFlatRepository object
     *
     * @var \Webkul\Product\Repositories\ProductFlatRepository
     */
    protected $productFlatRepository;

    /**
     * ProductRepository object
     *
     * @var \Webkul\Product\Repositories\ProductRepository
     */
    protected $productRepository;

    /**
     * AttributeFamilyRepository object
     *
     * @var \Webkul\Attribute\Repositories\AttributeFamilyRepository
     */
    protected $attributeFamilyRepository;

    /**
     * HelperRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\Products\HelperRepository
     */
    protected $helperRepository;

    /**
     * ProductImageRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\ProductImageRepository
     */
    protected $productImageRepository;

    /**
     * ProductDownloadableLinkRepository object
     *
     * @var \Webkul\Product\Repositories\ProductDownloadableLinkRepository
     */
    protected $productDownloadableLinkRepository;

    /**
     * AttributeOptionRepository object
     *
     * @var \Webkul\Attribute\Repositories\AttributeOptionRepository
     */
    protected $attributeOptionRepository;

    /**
     * Create a new repository instance.
     *
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     * @param  \Webkul\Category\Repositories\CategoryRepository  $categoryRepository
     * @param  \Webkul\Product\Repositories\ProductFlatRepository  $productFlatRepository
     * @param  \Webkul\Product\Repositories\ProductRepository  $productRepository
     * @param  \Webkul\Attribute\Repositories\AttributeFamilyRepository  $attributeFamilyRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\HelperRepository  $helperRepository
     * @param  \Webkul\Bulkupload\Repositories\ProductImageRepository  $productImageRepository
     * @param  \Webkul\Product\Repositories\ProductDownloadableLinkRepository $productDownloadableLinkRepository
     * @param  \Webkul\Attribute\Repositories\AttributeOptionRepository  $attributeOptionRepository
     *
     * @return void
     */
    public function __construct(
        ImportProductRepository $importProductRepository,
        CategoryRepository $categoryRepository,
        ProductFlatRepository $productFlatRepository,
        ProductRepository $productRepository,
        AttributeFamilyRepository $attributeFamilyRepository,
        HelperRepository $helperRepository,
        ProductImageRepository $productImageRepository,
        ProductDownloadableLinkRepository $productDownloadableLinkRepository,
        AttributeOptionRepository $attributeOptionRepository
    )
    {
        $this->importProductRepository = $importProductRepository;

        $this->productDownloadableLinkRepository = $productDownloadableLinkRepository;

        $this->categoryRepository = $categoryRepository;

        $this->productFlatRepository = $productFlatRepository;

        $this->productRepository = $productRepository;

        $this->productImageRepository = $productImageRepository;

        $this->attributeFamilyRepository = $attributeFamilyRepository;

        $this->helperRepository = $helperRepository;

        $this->attributeOptionRepository = $attributeOptionRepository;
    }

    /*
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Webkul\Product\Contracts\Product';
    }

    /**
     * create & update downloadable-type product
     *
     * @param array $requestData
     * @param array $imageZipName
     *
     * @return mixed
     */
    public function createProduct($requestData, $imageZipName)
    {
        $uploadLinkFilesZipName = null;
        $uploadSampleFilesZipName = null;
        $uploadLinkSampleFilesZipName = null;

        try  {
            $dataFlowProfileRecord = $this->importProductRepository->findOneByField
            ('data_flow_profile_id', $requestData['data_flow_profile_id']);

            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];

            $downloadableLinks = $this->extractDownloadableFiles($dataFlowProfileRecord);

            if ($requestData['totalNumberOfCSVRecord'] < 1000) {
                $processCSVRecords = $requestData['totalNumberOfCSVRecord']/($requestData['totalNumberOfCSVRecord']/10);
            } else {
                $processCSVRecords = $requestData['totalNumberOfCSVRecord']/($requestData['totalNumberOfCSVRecord']/100);
            }

            $uptoProcessCSVRecords = (int)$requestData['countOfStartedProfiles'] + 10;
            $processRecords = (int)$requestData['countOfStartedProfiles'] + (int)$requestData['numberOfCSVRecord'];

            if ($requestData['numberOfCSVRecord'] > $processCSVRecords) {
                for ($i = $requestData['countOfStartedProfiles']; $i < $uptoProcessCSVRecords; $i++) {
                    $invalidateProducts = $this->store($csvData[$i], $i, $dataFlowProfileRecord, $requestData, $imageZipName, $downloadableLinks);

                    if (isset($invalidateProducts) && !empty($invalidateProducts)) {
                        return $invalidateProducts;
                    }
                }
            } else if ($requestData['numberOfCSVRecord'] <= 10) {
                for ($i = $requestData['countOfStartedProfiles']; $i < $processRecords; $i++) {
                    $invalidateProducts = $this->store($csvData[$i], $i, $dataFlowProfileRecord, $requestData, $imageZipName, $downloadableLinks);

                    if (isset($invalidateProducts) && !empty($invalidateProducts)) {
                        return $invalidateProducts;
                    }
                }
            }

            if ($requestData['numberOfCSVRecord'] > 10) {
                $remainDataInCSV = (int)$requestData['numberOfCSVRecord'] - (int)$processCSVRecords;
            } else {
                $remainDataInCSV = 0;

                if ($requestData['errorCount'] > 0) {
                    $uptoProcessCSVRecords = $requestData['totalNumberOfCSVRecord'] - $requestData['errorCount'];
                } else {
                    $uptoProcessCSVRecords = $processRecords;
                }
            }

            $requestData['countOfStartedProfiles'] = $i;

            $dataToBeReturn = [
                'remainDataInCSV' => $remainDataInCSV,
                'productsUploaded' => $uptoProcessCSVRecords,
                'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
            ];

            return $dataToBeReturn;

        } catch(\Exception $e) {
            Log::error('downloadable create product log: '. $e->getMessage());

            $categoryError = explode('[' ,$e->getMessage());
            $categorySlugError = explode(']' ,$e->getMessage());
            $requestData['countOfStartedProfiles'] =  $i + 1;
            $productsUploaded = $i - $requestData['errorCount'];

            if ($requestData['numberOfCSVRecord'] != 0) {
                $remainDataInCSV = (int)$requestData['totalNumberOfCSVRecord'] - (int)$requestData['countOfStartedProfiles'];
            } else {
                $remainDataInCSV = 0;
            }

            if ($categoryError[0] == "No query results for model ") {
                $dataToBeReturn = array(
                    'remainDataInCSV' => $remainDataInCSV,
                    'productsUploaded' => $productsUploaded,
                    'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                    'error' => "Invalid Category Slug: " . $categorySlugError[1],
                );
                $categoryError[0] = null;
            } else if (isset($e->errorInfo)) {
                $dataToBeReturn = array(
                    'remainDataInCSV' => $remainDataInCSV,
                    'productsUploaded' => $productsUploaded,
                    'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                    'error' => $e->errorInfo[2],
                );
            } else {
                $dataToBeReturn = array(
                    'remainDataInCSV' => $remainDataInCSV,
                    'productsUploaded' => $productsUploaded,
                    'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                    'error' => $e->getMessage(),
                );
            }

            return $dataToBeReturn;
        }
    }

    /**
     * function to store product
     *
     * @param array $csvData
     * @param integer $i
     * @param \Webkul\Bulkupload\Contracts\ImportProduct $dataFlowProfileRecord
     * @param array $requestData
     * @param array $imageZipName
     * @param array $downloadableLinks
     *
     * @return mixed
     */
    public function store($csvData, $i, $dataFlowProfileRecord, $requestData, $imageZipName, $downloadableLinks)
    {
        $createValidation = $this->helperRepository->createProductValidation($csvData, $i);

        if (isset($createValidation)) {
            return $createValidation;
        }

        $d_samples = [];
        $sampleNameKey = [];
        $linkNameKey = [];
        $d_links = [];

        if (isset($csvData['samples_title'])) {
            $csvData['sample_sort_order'] = "";
            $sampleTitles = explode(',', $csvData['samples_title']) ;
            $sampleType = explode(',', $csvData['sample_type']) ;
            $sampleFiles = explode(',', $csvData['sample_files']) ;
            $urlFiles = explode(',', $csvData['sample_url']) ;
            $sampleSortOrder = !empty($csvData['sample_sort_order']) ? explode(',', $csvData['sample_sort_order']) : 0;
        }

        //for downloadable link explode
        if (isset($csvData['link_titles'])) {
            $linkTitles = explode(',', $csvData['link_titles']);
            $linkTypes = explode(',', $csvData['link_types']);

            $linkFileNames = explode(',', $csvData['link_file_names']);

            $linkPrices = !empty($csvData['link_prices']) ? explode(',', $csvData['link_prices']) : "";

            $linkSampleTypes = !empty($csvData['link_sample_types']) ? explode(',', $csvData['link_sample_types']) : "file";

            $linkSampleFileNames = !empty($csvData['link_sample_file_names']) ? explode(',', $csvData['link_sample_file_names']) : "";

            $linkDownloads = !empty($csvData['link_downloads']) ? explode(',', $csvData['link_downloads']) : 0;

            $linkSortOrders = !empty($csvData['link_sort_orders']) ? explode(',', $csvData['link_sort_orders']) : 0;

            $linkSampleUrlNames = explode(',', $csvData['link_sample_url']);
            $linkUrlNames = explode(',', $csvData['link_url']);
        }

        $productFlatData = $this->productFlatRepository->findWhere(['sku' => $csvData['sku'], 'url_key' => $csvData['url_key']])->first();

        $productData = $this->productRepository->findWhere(['sku' => $csvData['sku']])->first();

        $attributeFamilyData = $this->attributeFamilyRepository->findOneByfield(['name' => $csvData['attribute_family_name']]);

        if (! isset($productFlatData) && empty($productFlatData)) {
            $data['type'] = $csvData['type'];
            $data['attribute_family_id'] = $attributeFamilyData->id;
            $data['sku'] = $csvData['sku'];

            $downloadableProduct = $this->productRepository->create($data);
        } else {
            $downloadableProduct = $productData;
        }

        unset($data);
        $data = [];
        $attributeCode = [];
        $attributeValue = [];

        //default attributes
        foreach ($downloadableProduct->getTypeInstance()->getEditableAttributes()->toArray() as $key => $value) {
            $searchIndex = $value['code'];
            if (array_key_exists($searchIndex, $csvData)) {
                if (is_null($csvData[$searchIndex])) {
                    continue;
                }

                array_push($attributeCode, $searchIndex);

                if ($searchIndex == "color" || $searchIndex == "size" || $searchIndex == "brand") {
                    $attributeOption = $this->attributeOptionRepository->findOneByField(['admin_name' => ucwords($csvData[$searchIndex])]);

                    array_push($attributeValue, $attributeOption['id']);
                } else {
                    array_push($attributeValue, $csvData[$searchIndex]);
                }

                $data = array_combine($attributeCode, $attributeValue);
            }
        }

        $data['dataFlowProfileRecordId'] = $dataFlowProfileRecord->id;

        $categoryData = explode(',', $csvData['categories_slug']);

        if (is_null($csvData['categories_slug']) || empty($csvData['categories_slug'])) {
            $categoryID = $this->categoryRepository->findBySlugOrFail('root')->id;
        } else {
            foreach ($categoryData as $key => $value) {
                $categoryID[$key] = $this->categoryRepository->findBySlugOrFail($categoryData[$key])->id;
            }
        }

        $data['categories'] = $categoryID;
        $data['channel'] = core()->getCurrentChannel()->code;

        $dataProfile = app('Webkul\Bulkupload\Repositories\DataFlowProfileRepository')->findOneByfield(['id' => $data['dataFlowProfileRecordId']]);
        $data['locale'] = $dataProfile->locale_code;

        //customerGroupPricing
        if (isset($csvData['customer_group_prices']) && ! empty($csvData['customer_group_prices'])) {
            $data['customer_group_prices'] = json_decode($csvData['customer_group_prices'], true);
            app(ProductCustomerGroupPriceRepository::class)->saveCustomerGroupPrices($data, $simpleproductData);
        }


        //prepare downloadable sample data
        for ($j = 0; $j < count($sampleTitles); $j++) {
            if (trim(strtolower($sampleType[$j])) == "file") {
                if (isset($downloadableLinks['uploadSampleFilesZipName'])) {
                    if (trim(strtolower($sampleType[$j-1])) == "url") {
                        $sampleFileName = $sampleFiles[$j-1];
                    } else {
                        $sampleFileName = $sampleFiles[$j];
                    }

                    $files = $this->fileOrUrlUpload($dataFlowProfileRecord, $sampleType[$j], $sampleFileName, $downloadableProduct->id, $downloadableLinks, $sampleFile = true);

                    if (isset($files)) {
                        $sample['sample_'.$j] = [
                            core()->getCurrentLocale()->code => [
                                "title" => $sampleTitles[$j],
                            ],
                            "type" => trim($sampleType[$j]),
                            "file" => trim($files),
                            "file_name" => $sampleFileName,
                            "sort_order" => $sampleSortOrder[$j] ?? 0,
                        ];

                        array_push($sampleNameKey, 'sample_'.$j);
                        array_push($d_samples, $sample['sample_'.$j]);
                    }
                }
            } else if (trim(strtolower($sampleType[$j])) == "url") {
                $files = $this->fileOrUrlUpload($dataFlowProfileRecord, $sampleType[$j], $urlFiles[$j], $downloadableProduct->id, $downloadableLinks, $sampleFile = true);

                if (isset($files)) {
                    $sample['sample_'.$j] = [
                        core()->getCurrentLocale()->code => [
                            "title" => $sampleTitles[$j],
                        ],
                        "type" => trim($sampleType[$j]),
                        "url" => trim($urlFiles[$j]),
                        "sort_order" => $sampleSortOrder[$j] ?? 0,
                    ];

                    array_push($sampleNameKey, 'sample_'.$j);
                    array_push($d_samples, $sample['sample_'.$j]);
                }
            }
        }

        $combinedArray = array_combine($sampleNameKey, $d_samples);

        $data['downloadable_samples'] = $combinedArray;

        //for downloadable links
        for ($j = 0; $j < count($linkTitles); $j++) {
            if (trim(strtolower($linkTypes[$j])) == "file") {
                if (trim(strtolower($linkSampleTypes[$j])) == "file") {
                    if (isset($downloadableLinks['uploadLinkSampleFilesZipName'])) {
                        if (trim(strtolower($linkSampleTypes[$j-1])) == "url") {
                            $linkSampleFile = $linkSampleFileNames[$j-1];
                        } else {
                            $linkSampleFile = $linkSampleFileNames[$j];
                        }

                        $sampleFileLink = $this->fileOrUrlUpload($dataFlowProfileRecord, $linkSampleTypes[$j], $linkSampleFile, $downloadableProduct->id, $downloadableLinks, $sampleLinkfile = false);
                    }
                } else if (trim(strtolower($linkSampleTypes[$j])) == "url") {
                    $sampleFileLink = $this->fileOrUrlUpload($dataFlowProfileRecord, $linkSampleTypes[$j], $linkSampleUrlNames[$j], $downloadableProduct->id, $downloadableLinks, $sampleLinkfile = false);
                }

                if (isset($downloadableLinks['uploadLinkFilesZipName'])) {
                    if (trim(strtolower($linkSampleTypes[$j-1])) == "url") {
                        $linkFileName = $linkFileNames[$j-1];
                    } else {
                        $linkFileName = $linkFileNames[$j];
                    }

                    $fileLink = $this->linkFileOrUrlUpload($dataFlowProfileRecord, $linkTypes[$j], $linkFileName, $downloadableProduct->id, $downloadableLinks);
                }

                    if (isset($fileLink)) {
                    $link['link_'.$j] = [
                        core()->getCurrentLocale()->code => [
                            "title" => $linkTitles[$j],
                        ],
                        "price" => $linkPrices[$j],
                        "type" => trim($linkTypes[$j]),
                        "file" => trim($fileLink),
                        "file_name" => $linkFileName,
                        "sample_type" => trim($linkSampleTypes[$j]),
                        "downloads" => $linkDownloads[$j] ?? 0,
                        "sort_order" => $linkSortOrders[$j] ?? 0,
                    ];

                    if (trim($linkSampleTypes[$j]) == "url") {
                        $link['link_'.$j]['sample_url'] = trim($linkSampleUrlNames[$j]);
                    } else if (trim($linkSampleTypes[$j]) == "file") {
                        $link['link_'.$j]['sample_file'] = trim($sampleFileLink);

                        $link['link_'.$j]['sample_file_name'] = trim($linkSampleFile);
                    }

                    array_push($linkNameKey, 'link_'.$j);
                    array_push($d_links, $link['link_'.$j]);
                }
            } else if (trim(strtolower($linkTypes[$j])) == "url") {
                if (trim(strtolower($linkSampleTypes[$j])) == "file") {
                    if (isset($downloadableLinks['uploadLinkSampleFilesZipName'])) {
                        $sampleFileLink = $this->fileOrUrlUpload($dataFlowProfileRecord, $linkSampleTypes[$j], $linkSampleFileNames[$j], $downloadableProduct->id, $downloadableLinks, $sampleLinkfile = false);
                    }
                } else if (trim(strtolower($linkSampleTypes[$j])) == "url") {
                    $sampleFileLink = $this->fileOrUrlUpload($dataFlowProfileRecord, $linkSampleTypes[$j], $linkSampleUrlNames[$j], $downloadableProduct->id, $downloadableLinks, $sampleLinkfile = false);
                }

                $fileLink = $this->linkFileOrUrlUpload($dataFlowProfileRecord, $linkTypes[$j], $linkUrlNames[$j], $downloadableProduct->id, $downloadableLinks);

                if (isset($fileLink)) {
                    $link['link_'.$j] = [
                        core()->getCurrentLocale()->code => [
                            "title" => $linkTitles[$j],
                        ],
                        "price" => $linkPrices[$j],
                        "type" => trim($linkTypes[$j]),
                        "url" => trim($linkUrlNames[$j]) ?? "",
                        "sample_type" => trim($linkSampleTypes[$j]),
                        "downloads" => $linkDownloads[$j] ?? 0,
                        "sort_order" => $linkSortOrders[$j] ?? 0,
                    ];

                    if (trim($linkSampleTypes[$j]) == "url") {
                        $link['link_'.$j]['sample_url'] = trim($linkSampleUrlNames[$j]);
                    } else if (trim($linkSampleTypes[$j]) == "file") {
                        $link['link_'.$j]['sample_file'] = trim($sampleFileLink);
                        $link['link_'.$j]['sample_file_name'] = trim($linkSampleFileNames[$j]);
                    }

                    array_push($linkNameKey, 'link_'.$j);
                    array_push($d_links, $link['link_'.$j]);
                }
            }
        }

        $combinedLinksArray = array_combine($linkNameKey, $d_links);

        $data['downloadable_links'] = $combinedLinksArray;

        //Product Images
        $individualProductimages = explode(',', $csvData['images']);

        if (isset($imageZipName)) {
            $images = Storage::disk('local')->files('public/imported-products/extracted-images/admin/'.$dataFlowProfileRecord->id.'/'.$imageZipName['dirname'].'/');

            foreach ($images as $imageArraykey => $imagePath) {
                $imageName = explode('/', $imagePath);

                if (in_array(last($imageName), preg_replace('/[\'"]/', '',$individualProductimages))) {
                    $data['images'][$imageArraykey] = $imagePath;
                }
            }
        } else if (isset($csvData['images'])) {
            foreach ($individualProductimages as $imageArraykey => $imageURL)
            {
                if (filter_var(trim($imageURL), FILTER_VALIDATE_URL)) {
                    $imagePath = storage_path('app/public/imported-products/extracted-images/   admin/'.$dataFlowProfileRecord->id);

                    if (!file_exists($imagePath)) {
                        mkdir($imagePath, 0777, true);
                    }

                    $imageFile = $imagePath.'/'.basename($imageURL);

                    file_put_contents($imageFile, file_get_contents(trim($imageURL)));

                    $data['images'][$imageArraykey] = $imageFile;
                }
            }
        }

        $validationRules = $this->helperRepository->validateCSV($requestData['data_flow_profile_id'], $data, $dataFlowProfileRecord, $downloadableProduct);

        $csvValidator = Validator::make($data, $validationRules);

        if ($csvValidator->fails()) {
            $errors = $csvValidator->errors()->getMessages();

            $this->helperRepository->deleteProductIfNotValidated($downloadableProduct->id);

            foreach($errors as $key => $error){
                if ($error[0] == "The url key has already been taken.") {
                    $errorToBeReturn[] = "The url key " . $data['url_key'] . " has already been taken";
                } else {
                    $errorToBeReturn[] = str_replace(".", "", $error[0]). " for sku " . $data['sku'];
                }
            }

            $requestData['countOfStartedProfiles'] =  $i + 1;

            $productsUploaded = $i - $requestData['errorCount'];

            if ($requestData['numberOfCSVRecord'] != 0) {
                $remainDataInCSV = (int)$requestData['totalNumberOfCSVRecord'] - (int)$requestData['countOfStartedProfiles'];
            } else {
                $remainDataInCSV = 0;
            }

            $dataToBeReturn = array(
                'remainDataInCSV' => $remainDataInCSV,
                'productsUploaded' => $productsUploaded,
                'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                'error' => $errorToBeReturn,
            );

            return $dataToBeReturn;
        }

        $this->productRepository->update($data, $downloadableProduct->id);

        if (isset($imageZipName)) {
            $this->productImageRepository->bulkuploadImages($data, $downloadableProduct, $imageZipName);
        } else if (isset($csvData['images'])) {
            $this->productImageRepository->bulkuploadImages($data, $downloadableProduct, $imageZipName = null);
        }
    }

    /**
     * upload sample file link or url
     *
     * @param \Webkul\Bulkupload\Contracts\ImportProduct $dataFlowProfileRecord
     * @param string $type
     * @param string|array $file
     * @param integer $id
     * @param array $downloadableLinks
     * @param string $flag
     *
     * @return mixed
     */
    public function fileOrUrlUpload($dataFlowProfileRecord, $type, $file, $id, $downloadableLinks, $flag)
    {
        try {
            if (trim($type) == "file") {
                if ($flag) {
                    $files = "imported-products/extracted-images/admin/sample-files/".$dataFlowProfileRecord->id.'/'. $downloadableLinks['uploadSampleFilesZipName']['dirname'].'/'.trim(basename($file));

                    $destination = "product/".$id.'/'.trim(basename($file));

                    Storage::copy($files, $destination);

                    return $destination;
                } else {
                    $files = "imported-products/extracted-images/admin/link-sample-files/".$dataFlowProfileRecord->id.'/'. $downloadableLinks['uploadLinkSampleFilesZipName']['dirname'].'/'.trim(basename($file));

                    $destination = "product/".$id.'/'.trim(basename($file));

                    Storage::copy($files, $destination);

                    return $destination;
                }
            } else {
                if ($flag) {
                    $imagePath = storage_path('app/public/imported-products/extracted-images/admin/sample-files/'.$dataFlowProfileRecord->id);

                    if (!file_exists($imagePath)) {
                        mkdir($imagePath, 0777, true);
                    }

                    $imageFile = $imagePath.'/'.basename($file);

                    file_put_contents($imageFile, file_get_contents(trim($file)));

                    $files = "imported-products/extracted-images/admin/sample-files/".$dataFlowProfileRecord->id.'/'.basename($file);

                    $destination = "product/".$id.'/'.basename($file);
                    Storage::copy($files, $destination);

                    return $destination;
                } else {
                    $imagePath = storage_path('app/public/imported-products/extracted-images/admin/link-sample-files/'.$dataFlowProfileRecord->id);

                    if (!file_exists($imagePath)) {
                        mkdir($imagePath, 0777, true);
                    }

                    $imageFile = $imagePath.'/'.basename($file);

                    file_put_contents($imageFile, file_get_contents(trim($file)));

                    $files = "imported-products/extracted-images/admin/link-sample-files/".$dataFlowProfileRecord->id.'/'.basename($file);

                    $destination = "product/".$id.'/'.basename($file);
                    Storage::copy($files, $destination);

                    return $destination;
                }
            }
        } catch(\Exception $e) {
            Log::error('downloadable fileOrUrlUpload log: '. $e->getMessage());
        }
    }

    /**
     * upload link file or url
     *
     * @param \Webkul\Bulkupload\Contracts\ImportProduct $dataFlowProfileRecord
     * @param string $type
     * @param string|array $file
     * @param integer $id
     * @param array $downloadableLinks
     *
     * @return mixed
     */
    public function linkFileOrUrlUpload($dataFlowProfileRecord, $type, $file, $id, $downloadableLinks)
    {
        try {
            if (trim($type) == "file") {
                $files = "imported-products/extracted-images/admin/link-files/".$dataFlowProfileRecord->id.'/'. $downloadableLinks['uploadLinkFilesZipName']['dirname'].'/'.trim(basename($file));

                $destination = "product_downloadable_links/".$id.'/'.basename($file);

                Storage::copy($files, $destination);

                return $destination;
            } else {
                $imagePath = storage_path('app/public/imported-products/extracted-images/admin/link-files/'.$dataFlowProfileRecord->id);

                if (!file_exists($imagePath)) {
                    mkdir($imagePath, 0777, true);
                }

                $imageFile = $imagePath.'/'.basename($file);

                file_put_contents($imageFile, file_get_contents(trim($file)));

                $files = "imported-products/extracted-images/admin/link-files/".$dataFlowProfileRecord->id.'/'.basename($file);

                $destination = "product_downloadable_links/".$id.'/'.basename($file);

                Storage::copy($files, $destination);

                return $destination;
            }
        } catch(\Exception $e) {
            Log::error('downloadable linkFileOrUrlUpload log: '. $e->getMessage());
        }
    }

    /**
     * unzip zip files and store in storage folder
     *
     * @param \Webkul\Bulkupload\Contracts\ImportProduct $record
     *
     * @return mixed
     */
    public function extractDownloadableFiles($record)
    {
        if (isset($record->upload_link_files) && ($record->upload_link_files != "") ) {
            $uploadLinkFilesZip = new \ZipArchive();

            $extractedPath = storage_path('app/public/imported-products/extracted-images/admin/link-files/'.$record->id.'/');

            if ($uploadLinkFilesZip->open(storage_path('app/public/'.$record->upload_link_files))) {
                for ($i = 0; $i < $uploadLinkFilesZip->numFiles; $i++) {
                    $filename = $uploadLinkFilesZip->getNameIndex($i);
                    $uploadLinkFilesZipName = pathinfo($filename);
                }

                $uploadLinkFilesZip->extractTo($extractedPath);
                $uploadLinkFilesZip->close();
            }
        } else {
            $uploadLinkFilesZipName = null;
        }

        if (isset($record->upload_sample_files) && ($record->upload_sample_files != "") ) {
            $uploadSampleFilesZip = new \ZipArchive();

            $extractedPath = storage_path('app/public/imported-products/extracted-images/admin/sample-files/'.$record->id.'/');

            if ($uploadSampleFilesZip->open(storage_path('app/public/'.$record->upload_sample_files))) {
                for ($i = 0; $i < $uploadSampleFilesZip->numFiles; $i++) {
                    $filename = $uploadSampleFilesZip->getNameIndex($i);
                    $uploadSampleFilesZipName = pathinfo($filename);
                }

                $uploadSampleFilesZip->extractTo($extractedPath);
                $uploadSampleFilesZip->close();
            }
        } else {
            $uploadSampleFilesZipName = null;
        }

        if (isset($record->upload_link_sample_files) && ($record->upload_link_sample_files != "") ) {
            $uploadLinkSampleFilesZip = new \ZipArchive();

            $extractedPath = storage_path('app/public/imported-products/extracted-images/admin/link-sample-files/'.$record->id.'/');

            if ($uploadLinkSampleFilesZip->open(storage_path('app/public/'.$record->upload_link_sample_files))) {
                for ($i = 0; $i < $uploadLinkSampleFilesZip->numFiles; $i++) {
                    $filename = $uploadLinkSampleFilesZip->getNameIndex($i);
                    $uploadLinkSampleFilesZipName = pathinfo($filename);
                }

                $uploadLinkSampleFilesZip->extractTo($extractedPath);
                $uploadLinkSampleFilesZip->close();
            }
        } else {
            $uploadLinkSampleFilesZipName = null;
        }

        return [
            'uploadLinkSampleFilesZipName' => $uploadLinkSampleFilesZipName, 'uploadSampleFilesZipName' => $uploadSampleFilesZipName,
            'uploadLinkFilesZipName' => $uploadLinkFilesZipName
        ];
    }
}