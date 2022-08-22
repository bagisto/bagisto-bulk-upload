<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Storage;
use Illuminate\Container\Container as App;
use Webkul\Admin\Imports\DataGridImport;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Bulkupload\Repositories\ImportProductRepository;
use Webkul\Product\Repositories\ProductFlatRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Bulkupload\Repositories\Products\HelperRepository;
use Illuminate\Support\Facades\Validator;
use Webkul\Bulkupload\Repositories\ProductImageRepository;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Illuminate\Support\Facades\Log;
use Webkul\Product\Repositories\ProductCustomerGroupPriceRepository;

class GroupedProductRepository extends Repository
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
     * Create a new repository instance.
     *
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     * @param  \Webkul\Category\Repositories\CategoryRepository  $categoryRepository
     * @param  \Webkul\Product\Repositories\ProductFlatRepository  $productFlatRepository
     * @param  \Webkul\Product\Repositories\ProductRepository  $productRepository
     * @param  \Webkul\Attribute\Repositories\AttributeFamilyRepository  $attributeFamilyRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\HelperRepository  $helperRepository
     * @param  \Webkul\Bulkupload\Repositories\ProductImageRepository  $productImageRepository
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
        AttributeOptionRepository $attributeOptionRepository
    )
    {
        $this->importProductRepository = $importProductRepository;

        $this->categoryRepository = $categoryRepository;

        $this->productFlatRepository = $productFlatRepository;

        $this->attributeOptionRepository = $attributeOptionRepository;

        $this->productRepository = $productRepository;

        $this->productImageRepository = $productImageRepository;

        $this->attributeFamilyRepository = $attributeFamilyRepository;

        $this->helperRepository = $helperRepository;
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
     * create & update grouped-type product
     *
     * @param array $requestData
     * @param array $imageZipName
     * @param array $product
     *
     * @return mixed
     */
    public function createProduct($requestData, $imageZipName)
    {
        try {
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

            $inventory = [];

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
        } catch(\Exception $e) {
            Log::error('grouped create product log: '. $e->getMessage());

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

            $groupedProduct = $this->productRepository->create($data);
        } else {
            $groupedProduct = $productData;
        }

        unset($data);
        $data = [];
        $attributeCode = [];
        $attributeValue = [];

        //default attributes
        foreach ($groupedProduct->getTypeInstance()->getEditableAttributes()->toArray() as $key => $value) {
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

        //grouped product links
        if (isset($csvData['grouped_product_sku'])) {
            $groupedProductSku = explode(",", strtolower($csvData['grouped_product_sku']));
            $groupedQuantity = explode(",", $csvData['grouped_quantity']);
            $groupedSortOrder = explode(",", $csvData['grouped_sort_order']);

            for ($j = 0; $j < count($groupedProductSku); $j++) {
                $link = $j+1;

                $variants = true;

                $associatedProducts = $this->productRepository->findOneByField(['sku' => strtolower(trim($groupedProductSku[$j]))]);

                if (isset($associatedProducts) && !empty($associatedProducts)) {
                    if (isset($associatedProducts->parent_id)) {
                        $groupedLink['link_'.$link] = [
                            "associated_product_id" => $associatedProducts->id,
                            "qty" => $groupedQuantity[$j],
                            "sort_order" => $groupedSortOrder[$j],
                        ];
                    } else if ($associatedProducts->type == "simple") {
                        $groupedLink['link_'.$link] = [
                            "associated_product_id" => $associatedProducts->id,
                            "qty" => $groupedQuantity[$j],
                            "sort_order" => $groupedSortOrder[$j],
                        ];
                    }
                }
            }

            if(isset($groupedLink) && !empty($groupedLink)) {
                $data['links'] = $groupedLink;
            }
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

        $validationRules = $this->helperRepository->validateCSV($requestData['data_flow_profile_id'], $data, $dataFlowProfileRecord, $groupedProduct);

        $csvValidator = Validator::make($data, $validationRules);

        if ($csvValidator->fails()) {
            $errors = $csvValidator->errors()->getMessages();

            $this->helperRepository->deleteProductIfNotValidated($groupedProduct->id);

            foreach ($errors as $key => $error) {
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

        $this->productRepository->update($data, $groupedProduct->id);

        if (isset($imageZipName)) {
            $this->productImageRepository->bulkuploadImages($data, $groupedProduct, $imageZipName);
        } else if (isset($csvData['images'])) {
            $this->productImageRepository->bulkuploadImages($data, $groupedProduct, $imageZipName = null);
        }
    }
}