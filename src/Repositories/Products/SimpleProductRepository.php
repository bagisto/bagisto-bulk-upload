<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Storage;
use Illuminate\Container\Container as App;
use Webkul\Admin\Imports\DataGridImport;
use Illuminate\Support\Facades\Validator;
use Webkul\Core\Eloquent\Repository;
use Illuminate\Support\Facades\Event;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Bulkupload\Repositories\ImportProductRepository;
use Webkul\Product\Repositories\ProductFlatRepository;
use Webkul\Inventory\Repositories\InventorySourceRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Bulkupload\Repositories\Products\HelperRepository;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Bulkupload\Repositories\ProductImageRepository;
use Webkul\Product\Repositories\ProductCustomerGroupPriceRepository;

class SimpleProductRepository extends Repository
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
     * AttributeOptionRepository object
     *
     * @var \Webkul\Attribute\Repositories\AttributeOptionRepository
     */
    protected $attributeOptionRepository;

    /**
     * InventorySourceRepository object
     *
     * @var \Webkul\Inventory\Repositories\InventorySourceRepository
     */
    protected $inventorySourceRepository;

    /**
     * Create a new repository instance.
     *
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     * @param  \Webkul\Attribute\Repositories\AttributeOptionRepository  $attributeOptionRepository
     * @param  \Webkul\Category\Repositories\CategoryRepository  $categoryRepository
     * @param  \Webkul\Product\Repositories\ProductFlatRepository  $productFlatRepository
     * @param  \Webkul\Product\Repositories\ProductRepository  $productRepository
     * @param  \Webkul\Attribute\Repositories\AttributeFamilyRepository  $attributeFamilyRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\HelperRepository  $helperRepository
     * @param  \Webkul\Bulkupload\Repositories\ProductImageRepository  $productImageRepository
     * @param  \Webkul\Inventory\Repositories\InventorySourceRepository  $inventorySourceRepository
     *
     * @return void
     */
    public function __construct(
        ImportProductRepository $importProductRepository,
        AttributeOptionRepository $attributeOptionRepository,
        CategoryRepository $categoryRepository,
        ProductFlatRepository $productFlatRepository,
        ProductRepository $productRepository,
        AttributeFamilyRepository $attributeFamilyRepository,
        HelperRepository $helperRepository,
        ProductImageRepository $productImageRepository,
        InventorySourceRepository $inventorySourceRepository
    )
    {
        $this->importProductRepository = $importProductRepository;

        $this->categoryRepository = $categoryRepository;

        $this->attributeOptionRepository = $attributeOptionRepository;

        $this->productFlatRepository = $productFlatRepository;

        $this->productRepository = $productRepository;

        $this->productImageRepository = $productImageRepository;

        $this->attributeFamilyRepository = $attributeFamilyRepository;

        $this->helperRepository = $helperRepository;

        $this->inventorySourceRepository = $inventorySourceRepository;
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
     * create & update simple-type product
     *
     * @param array $requestData
     * @param array $imageZipName
     * @param array $product
     *
     * @return mixed
     */
    public function createProduct($requestData, $imageZipName, $product)
    {
        try {
            $inventory = [];

            $dataFlowProfileRecord = $this->importProductRepository->findOneByField
            ('data_flow_profile_id', $requestData['data_flow_profile_id']);

            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];

            if ($requestData['totalNumberOfCSVRecord'] < 1000) {
                $processCSVRecords = $requestData['totalNumberOfCSVRecord']/($requestData['totalNumberOfCSVRecord']/10);
            } else {
                $processCSVRecords = $requestData['totalNumberOfCSVRecord']/($requestData['totalNumberOfCSVRecord']/100);
            }

            $uptoProcessCSVRecords = (int)$requestData['countOfStartedProfiles'] + 10;

            $processRecords = (int)$requestData['countOfStartedProfiles'] + (int)$requestData['numberOfCSVRecord'];


            if ($requestData['numberOfCSVRecord'] > $processCSVRecords) {
                for ($i = $requestData['countOfStartedProfiles']; $i < $uptoProcessCSVRecords; $i++) {
                    $invalidateProducts = $this->store($csvData[$i], $i, $dataFlowProfileRecord, $requestData, $imageZipName);

                    if (isset($invalidateProducts) && !empty($invalidateProducts)) {
                        return $invalidateProducts;
                    }
                }
            } else if ($requestData['numberOfCSVRecord'] <= 10) {
                for ($i = $requestData['countOfStartedProfiles']; $i < $processRecords; $i++) {
                    $invalidateProducts = $this->store($csvData[$i], $i, $dataFlowProfileRecord, $requestData, $imageZipName);

                    if (isset($invalidateProducts) && !empty($invalidateProducts)) {
                        return $invalidateProducts;
                    }
                }
            }

            if ($requestData['numberOfCSVRecord'] > 10) {
                $remainDataInCSV = (int)$requestData['numberOfCSVRecord'] - (int)$processCSVRecords;
            } else {
                $remainDataInCSV = 0;

                if($requestData['errorCount'] > 0) {
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
        } catch (\Exception $e) {
            Log::error('simple create product log: '. $e->getMessage());

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
     *
     * @return mixed
     */
    public function store($csvData, $i, $dataFlowProfileRecord, $requestData, $imageZipName)
    {
        try {
            $createValidation = $this->helperRepository->createProductValidation($csvData, $i);

            if (isset($createValidation)) {
                return $createValidation;
            }

            $productFlatData = $this->productFlatRepository->findWhere(['sku' => $csvData['sku'], 'url_key' => $csvData['url_key']])->first();

            $productData = $this->productRepository->findWhere(['sku' => $csvData['sku']])->first();

            $attributeFamilyData = $this->attributeFamilyRepository->findOneByfield(['name' => $csvData['attribute_family_name']]);

            if (! isset($productFlatData) && empty($productFlatData)) {
                $data['type'] = $csvData['type'];
                $data['attribute_family_id'] = $attributeFamilyData->id;
                $data['sku'] = $csvData['sku'];

                Event::dispatch('catalog.product.create.before');
                $simpleproductData = $this->productRepository->create($data);
                Event::dispatch('catalog.product.create.after', $simpleproductData);
            } else {
                $simpleproductData = $productData;
            }

            unset($data);
            $data = [];
            $attributeCode = [];
            $attributeValue = [];

            //default attributes
            foreach ($simpleproductData->getTypeInstance()->getEditableAttributes()->toArray() as $key => $value) {
                $attributeOptionArray = array();
                $searchIndex = strtolower($value['code']);

                if (array_key_exists($searchIndex, $csvData)) {

                    if (is_null($csvData[$searchIndex])) {
                        continue;
                    }

                    array_push($attributeCode, $searchIndex);

                    if ($value['type'] == "select") {
                        $attributeOption = $this->attributeOptionRepository->findOneByField(['admin_name' => $csvData[$searchIndex]]);

                        array_push($attributeValue, $attributeOption['id']);
                    } else if ($value['type'] == "checkbox") {
                        $attributeOption = $this->attributeOptionRepository->findOneByField(['attribute_id' => $value['id'], 'admin_name' => $csvData[$searchIndex]]);

                        array_push($attributeOptionArray, $attributeOption['id']);

                        array_push($attributeValue, $attributeOptionArray);
                        unset($attributeOptionArray);
                    } else {
                        array_push($attributeValue, $csvData[$searchIndex]);
                    }

                    $data = array_combine($attributeCode, $attributeValue);
                }
            }

            $data['dataFlowProfileRecordId'] = $dataFlowProfileRecord->id;

            $inventorySource = $csvData['inventory_sources'];
            $inventoryCode = explode(',', $inventorySource);

            foreach ($inventoryCode as $key => $value) {
                $inventoryId = $this->inventorySourceRepository->findOneByfield(['code' => trim($value)])->pluck('id')->toArray();
            }

            $inventoryData[] = (string)$csvData['inventories'];

            foreach ($inventoryData as $key => $d) {
                $inventoryQuantity = explode(',', trim($d));

                if (count($inventoryId) != count($inventoryQuantity)) {
                    array_push($inventoryQuantity, "0");
                }

                $inventory = array_combine($inventoryId, $inventoryQuantity);
            }

            $data['inventories'] =  $inventory;

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
                        $imagePath = storage_path('app/public/imported-products/extracted-images/admin/'.$dataFlowProfileRecord->id);

                        if (!file_exists($imagePath)) {
                            mkdir($imagePath, 0777, true);
                        }

                        $imageFile = $imagePath.'/'.basename($imageURL);

                        file_put_contents($imageFile, file_get_contents(trim($imageURL)));

                        $data['images'][$imageArraykey] = $imageFile;
                    }
                }
            }

            $returnRules = $this->helperRepository->validateCSV($requestData['data_flow_profile_id'], $data, $dataFlowProfileRecord, $simpleproductData);

            $csvValidator = Validator::make($data, $returnRules);

            if ($csvValidator->fails()) {
                $errors = $csvValidator->errors()->getMessages();

                $this->helperRepository->deleteProductIfNotValidated($simpleproductData->id);

                foreach($errors as $key => $error) {
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

            Event::dispatch('catalog.product.update.before',  $simpleproductData->id);
            $configSimpleProductAttributeStore = $this->productRepository->update($data, $simpleproductData->id);
            Event::dispatch('catalog.product.update.after',$configSimpleProductAttributeStore);

            if (isset($imageZipName)) {
                $this->productImageRepository->bulkuploadImages($data, $simpleproductData, $imageZipName);
            } else if (isset($csvData['images'])) {
                $this->productImageRepository->bulkuploadImages($data, $simpleproductData, $imageZipName = null);
            }
        } catch(\Exception $e) {
            \Log::error('simple product store function'. $e->getMessage());
        }
    }
}