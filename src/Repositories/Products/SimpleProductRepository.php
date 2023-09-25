<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\{Event, Validator};
use Webkul\Core\Eloquent\Repository;
use Webkul\Attribute\Repositories\{AttributeFamilyRepository, AttributeOptionRepository};
use Webkul\Product\Repositories\{ProductRepository, ProductFlatRepository};
use Webkul\Bulkupload\Repositories\Products\HelperRepository;

// use Webkul\Category\Repositories\CategoryRepository;
// use Webkul\Inventory\Repositories\InventorySourceRepository;
// use Webkul\Product\Repositories\ProductCustomerGroupPriceRepository;
// use Webkul\Bulkupload\Repositories\{ImportProductRepository, ProductImageRepository};

class SimpleProductRepository extends Repository
{
    protected $errors = [];
    protected $dataNotInserted = [];

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
        protected ProductRepository $productRepository,
        protected ProductFlatRepository $productFlatRepository,
        // protected CategoryRepository $categoryRepository,
        // protected InventorySourceRepository $inventorySourceRepository,
        // protected ImportProductRepository $importProductRepository,
        // protected ProductImageRepository $productImageRepository,
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
                session()->push('csvDataWithError', ['error' => $createValidation['error'], 'dataNotInserted' => $csvData]);

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
                $simpleproductData = $this->productRepository->UpdateOrCreate($data);
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

            $returnRules = $this->helperRepository->validateCSV($data, $dataFlowProfileRecord, $simpleproductData);

            $csvValidator = Validator::make($data, $returnRules);

            if ($csvValidator->fails()) {
                session(['value1' => $csvValidator->errors()->getMessages(), 'value2' => $csvData]);

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

    /**
     * create & update simple-type product
     *
     * @param array $requestData
     * @param array $imageZipName
     * @param array $product
     *
     * @return mixed
     */
    public function createProduct($imageZipName, $dataFlowProfileRecord, $csvData, $key)
    {
        try {
            $createValidation = $this->helperRepository->createProductValidation($csvData, $key);

            if (isset($createValidation)) {
                return $createValidation;
            }

            $product = $this->productRepository->findWhere(['sku' => $csvData['sku']])->first();

            $attributeFamilyData = $this->attributeFamilyRepository->findOneByfield(['name' => $csvData['attribute_family_name']]);

            if (empty($product)) {

                $data['sku'] = $csvData['sku'];
                $data['type'] = $csvData['type'];
                $data['attribute_family_id'] = $attributeFamilyData->id;

                Event::dispatch('catalog.product.create.before');

                $product = $this->productRepository->create($data);

                Event::dispatch('catalog.product.create.after', $product);
            }

            unset($data);
            $data = [];
            $attributeCode = [];
            $attributeValue = [];

            //default attributes
            foreach ($product->getTypeInstance()->getEditableAttributes()->toArray() as $value) {
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
