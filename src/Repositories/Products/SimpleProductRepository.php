<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Storage;
use Illuminate\Support\Facades\Log;
use Webkul\Core\Eloquent\Repository;
use Webkul\Admin\Imports\DataGridImport;
use Illuminate\Support\Facades\{Event, Validator};
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Inventory\Repositories\InventorySourceRepository;
use Webkul\Bulkupload\Repositories\Products\HelperRepository;
use Webkul\Product\Repositories\ProductCustomerGroupPriceRepository;
use Webkul\Product\Repositories\{ProductRepository, ProductFlatRepository};
use Webkul\Bulkupload\Repositories\{ImportProductRepository, ProductImageRepository};
use Webkul\Attribute\Repositories\{AttributeFamilyRepository, AttributeOptionRepository};

class SimpleProductRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @param  \Webkul\Attribute\Repositories\AttributeFamilyRepository  $attributeFamilyRepository
     * @param  \Webkul\Attribute\Repositories\AttributeOptionRepository  $attributeOptionRepository
     * @param  \Webkul\Category\Repositories\CategoryRepository  $categoryRepository
     * @param  \Webkul\Inventory\Repositories\InventorySourceRepository  $inventorySourceRepository
     * @param  \Webkul\Product\Repositories\ProductRepository  $productRepository
     * @param  \Webkul\Product\Repositories\ProductFlatRepository  $productFlatRepository
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     * @param  \Webkul\Bulkupload\Repositories\ProductImageRepository  $productImageRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\HelperRepository  $helperRepository
     *
     * @return void
     */
    public function __construct(
        protected AttributeFamilyRepository $attributeFamilyRepository,
        protected AttributeOptionRepository $attributeOptionRepository,
        protected CategoryRepository $categoryRepository,
        protected InventorySourceRepository $inventorySourceRepository,
        protected ProductRepository $productRepository,
        protected ProductFlatRepository $productFlatRepository,
        protected ImportProductRepository $importProductRepository,
        protected ProductImageRepository $productImageRepository,
        protected HelperRepository $helperRepository,
    )
    {
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
            $dataFlowProfileRecord = $this->importProductRepository->findOneByField('data_flow_profile_id', $requestData['data_flow_profile_id']);
            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];
            $processCSVRecords = ($requestData['totalNumberOfCSVRecord'] < 1000) ? $requestData['totalNumberOfCSVRecord'] / ($requestData['totalNumberOfCSVRecord'] / 10) : $requestData['totalNumberOfCSVRecord'] / ($requestData['totalNumberOfCSVRecord'] / 100);
            $uptoProcessCSVRecords = (int)$requestData['countOfStartedProfiles'] + 10;
            $processRecords = (int)$requestData['countOfStartedProfiles'] + (int)$requestData['numberOfCSVRecord'];

            for ($i = $requestData['countOfStartedProfiles']; $i < min($uptoProcessCSVRecords, $processRecords); $i++) {
                $invalidateProducts = $this->store($csvData[$i], $i, $dataFlowProfileRecord, $requestData, $imageZipName);
                if (isset($invalidateProducts) && !empty($invalidateProducts)) {
                    return $invalidateProducts;
                }
            }

            $remainDataInCSV = ($requestData['numberOfCSVRecord'] > 10) ? (int)$requestData['numberOfCSVRecord'] - (int)$processCSVRecords : 0;
            if ($requestData['errorCount'] > 0) {
                $uptoProcessCSVRecords = $requestData['totalNumberOfCSVRecord'] - $requestData['errorCount'];
            } else {
                $uptoProcessCSVRecords = $processRecords;
            }

            $requestData['countOfStartedProfiles'] = $i;

            $dataToBeReturn = [
                'remainDataInCSV' => $remainDataInCSV,
                'productsUploaded' => $uptoProcessCSVRecords,
                'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
            ];

            return $dataToBeReturn;
        } catch (\Exception $e) {
            Log::error('simple create product log: ' . $e->getMessage());
            $categoryError = explode('[', $e->getMessage());
            $categorySlugError = explode(']', $e->getMessage());
            $requestData['countOfStartedProfiles'] = $i + 1;
            $productsUploaded = $i - $requestData['errorCount'];
            $remainDataInCSV = ($requestData['numberOfCSVRecord'] != 0) ? (int)$requestData['totalNumberOfCSVRecord'] - (int)$requestData['countOfStartedProfiles'] : 0;

            if ($categoryError[0] == "No query results for model ") {
                $dataToBeReturn = ['remainDataInCSV' => $remainDataInCSV, 'productsUploaded' => $productsUploaded, 'countOfStartedProfiles' => $requestData['countOfStartedProfiles'], 'error' => "Invalid Category Slug: " . $categorySlugError[1]];
                $categoryError[0] = null;
            } else if (isset($e->errorInfo)) {
                $dataToBeReturn = ['remainDataInCSV' => $remainDataInCSV, 'productsUploaded' => $productsUploaded, 'countOfStartedProfiles' => $requestData['countOfStartedProfiles'], 'error' => $e->errorInfo[2]];
            } else {
                $dataToBeReturn = ['remainDataInCSV' => $remainDataInCSV, 'productsUploaded' => $productsUploaded, 'countOfStartedProfiles' => $requestData['countOfStartedProfiles'], 'error' => $e->getMessage()];
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
            $validation = $this->helperRepository->createProductValidation($csvData, $i);
            if ($validation) {
                return $validation;
            }

            $productFlatData = $this->productFlatRepository->findWhere(['sku' => $csvData['sku'], 'url_key' => $csvData['url_key']])->first();
            $productData = $this->productRepository->findWhere(['sku' => $csvData['sku']])->first();
            $attributeFamilyData = $this->attributeFamilyRepository->findOneByfield(['name' => $csvData['attribute_family_name']]);

            $isNewProduct = !isset($productFlatData) && empty($productFlatData);
            $simpleproductData = $isNewProduct ? $this->createProductStore($csvData, $attributeFamilyData) : $productData;

            $data = $this->prepareProductAttributes($simpleproductData, $csvData);
            $data['dataFlowProfileRecordId'] = $dataFlowProfileRecord->id;
            $data['inventories'] = $this->prepareInventoryData($csvData);
            $data['categories'] = $this->prepareCategoryData($csvData);

            $this->processCustomerGroupPrices($csvData, $data, $simpleproductData);
            $this->processProductImages($csvData, $data, $imageZipName, $dataFlowProfileRecord);

            $returnRules = $this->helperRepository->validateCSV($requestData['data_flow_profile_id'], $data, $dataFlowProfileRecord, $simpleproductData);
            $csvValidator = Validator::make($data, $returnRules);

            if ($csvValidator->fails()) {
                return $this->handleValidationErrors($csvValidator, $data);
            }

            $this->updateProduct($simpleproductData, $data);

        } catch(\Exception $e) {
            \Log::error('simple product store function'. $e->getMessage());
        }
    }

    private function createProductStore($csvData, $attributeFamilyData) {
        $data['type'] = $csvData['type'];
        $data['attribute_family_id'] = $attributeFamilyData->id;
        $data['sku'] = $csvData['sku'];

        Event::dispatch('catalog.product.create.before');
        $simpleproductData = $this->productRepository->create($data);
        Event::dispatch('catalog.product.create.after', $simpleproductData);

        return $simpleproductData;
    }

    private function prepareProductAttributes($simpleproductData, $csvData) {
        $data = [];
        $attributeCode = [];
        $attributeValue = [];

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
                } else {
                    array_push($attributeValue, $csvData[$searchIndex]);
                }

                $data = array_combine($attributeCode, $attributeValue);
            }
        }

        return $data;
    }

    private function prepareInventoryData($csvData) {
        $inventorySource = $csvData['inventory_sources'];
        $inventoryCode = explode(',', $inventorySource);

        $inventoryId = [];
        foreach ($inventoryCode as $key => $value) {
            $inventoryId[] = $this->inventorySourceRepository->findOneByfield(['code' => trim($value)])->pluck('id')->toArray();
        }

        $inventoryData = [(string) $csvData['inventories']];
        $inventory = [];

        foreach ($inventoryData as $key => $d) {
            $inventoryQuantity = explode(',', trim($d));

            if (count($inventoryId) != count($inventoryQuantity)) {
                array_push($inventoryQuantity, "0");
            }

            $inventory = array_combine($inventoryId, $inventoryQuantity);
        }

        return $inventory;
    }

    private function prepareCategoryData($csvData) {
        $categoryData = explode(',', $csvData['categories_slug']);

        if (is_null($csvData['categories_slug']) || empty($csvData['categories_slug'])) {
            $categoryID = $this->categoryRepository->findBySlugOrFail('root')->id;
        } else {
            $categoryID = [];

            foreach ($categoryData as $value) {
                $category = $this->categoryRepository->findBySlug($value);

                if ($category) {
                    $categoryID[] = $category->id;
                }
            }
        }

        return $categoryID;
    }

    private function processCustomerGroupPrices($csvData, &$data, $simpleproductData) {
        if (isset($csvData['customer_group_prices']) && ! empty($csvData['customer_group_prices'])) {
            $data['customer_group_prices'] = json_decode($csvData['customer_group_prices'], true);

            // Assuming you have a method in your ProductCustomerGroupPriceRepository class to save prices.
            app(ProductCustomerGroupPriceRepository::class)->saveCustomerGroupPrices($data, $simpleproductData);
        }
    }

    private function processProductImages($csvData, &$data, $imageZipName, $dataFlowProfileRecord) {
        if (isset($imageZipName)) {
            $images = Storage::disk('local')->files('public/imported-products/extracted-images/admin/'.$dataFlowProfileRecord->id.'/'.$imageZipName['dirname'].'/');

            foreach ($images as $imageArraykey => $imagePath) {
                $imageName = explode('/', $imagePath);

                if (in_array(last($imageName), preg_replace('/[\'"]/', '', $individualProductimages))) {
                    $data['images'][$imageArraykey] = $imagePath;
                }
            }
        } else if (isset($csvData['images'])) {
            $individualProductimages = explode(',', $csvData['images']);
            foreach ($individualProductimages as $imageArraykey => $imageURL) {
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
    }

    private function handleValidationErrors($csvValidator, $data) {
        $errors = $csvValidator->errors()->getMessages();
        $errorToBeReturn = [];

        foreach ($errors as $key => $error) {
            if ($error[0] == "The url key has already been taken.") {
                $errorToBeReturn[] = "The url key " . $data['url_key'] . " has already been taken";
            } else {
                $errorToBeReturn[] = str_replace(".", "", $error[0]) . " for sku " . $data['sku'];
            }
        }

        $requestData['countOfStartedProfiles'] =  $i + 1;
        $productsUploaded = $i - $requestData['errorCount'];

        $remainDataInCSV = ($requestData['numberOfCSVRecord'] != 0) ? (int)$requestData['totalNumberOfCSVRecord'] - (int)$requestData['countOfStartedProfiles'] : 0;

        $dataToBeReturn = [
            'remainDataInCSV' => $remainDataInCSV,
            'productsUploaded' => $productsUploaded,
            'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
            'error' => $errorToBeReturn,
        ];

        return $dataToBeReturn;
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
    public function createProductFromCommand($imageZipName, $dataFlowProfileRecord, $csvData, $key)
    {
        try {
            $invalidateProducts = $this->storeFromCommand(request()->all(), $imageZipName, $dataFlowProfileRecord, $csvData, $key);

            if (isset($invalidateProducts) && !empty($invalidateProducts)) {

                return $invalidateProducts;
            }

        } catch (\Exception $e) {
            Log::error('simple create product log: '. $e->getMessage());
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
    public function storeFromCommand($requestData, $imageZipName, $dataFlowProfileRecord, $csvData, $i)
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
            foreach ($simpleproductData->getTypeInstance()->getEditableAttributes()->toArray() as $value) {
                $attributeOptionArray = array();
                $searchIndex = strtolower($value['code']);

                if (array_key_exists($searchIndex, $csvData) && ! is_null($csvData[$searchIndex])) {

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

            foreach ($inventoryCode as $value) {
                $inventoryId = $this->inventorySourceRepository->findOneByfield(['code' => trim($value)])->pluck('id')->toArray();
            }

            $inventoryData[] = (string)$csvData['inventories'];

            foreach ($inventoryData as $d) {
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
                foreach ($categoryData as $k => $value) {
                    $categoryID[$k] = $this->categoryRepository->findBySlugOrFail($categoryData[$k])->id;
                }
            }

            $data['categories'] = $categoryID;
            $data['channel'] = core()->getCurrentChannel()->code;

            $dataProfile = $dataFlowProfileRecord->profiler;

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

            $returnRules = $this->helperRepository->validateCSV($data, $dataFlowProfileRecord, $simpleproductData);

            $csvValidator = Validator::make($data, $returnRules);

            if ($csvValidator->fails()) {
                $errors = $csvValidator->errors()->getMessages();

                $this->helperRepository->deleteProductIfNotValidated($simpleproductData->id);

                foreach($errors as $error) {
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
