<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container as App;
use Webkul\Admin\Imports\DataGridImport;
use Illuminate\Support\Facades\Schema;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Product\Models\ProductAttributeValue;
use Webkul\Core\Eloquent\Repository;
use Webkul\Bulkupload\Repositories\ImportProductRepository;
use Webkul\Product\Repositories\ProductFlatRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Bulkupload\Repositories\Products\HelperRepository;
use Illuminate\Support\Facades\Validator;
use Webkul\Product\Repositories\ProductInventoryRepository;
use Webkul\Bulkupload\Repositories\ProductImageRepository;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Bulkupload\Repositories\BulkProductRepository;

class ConfigurableProductRepository extends Repository
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
     * ProductInventoryRepository object
     *
     * @var \Webkul\Product\Repositories\ProductInventoryRepository
     */
    protected $productInventoryRepository;

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
     * BulkProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\BulkProductRepository
     */
    protected $bulkProductRepository;

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
     * @param  \Webkul\Attribute\Repositories\AttributeRepository $attributeRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\HelperRepository  $helperRepository
     * @param  \Webkul\Bulkupload\Repositories\ProductImageRepository  $productImageRepository
     * @param  \Webkul\Bulkupload\Repositories\BulkProductRepository $bulkProductRepository
     * @param  \Webkul\Attribute\Repositories\AttributeOptionRepository  $attributeOptionRepository
     * @param  \Webkul\Product\Repositories\ProductInventoryRepository  $productInventoryRepository
     *
     * @return void
     */
    public function __construct(
        ImportProductRepository $importProductRepository,
        CategoryRepository $categoryRepository,
        ProductFlatRepository $productFlatRepository,
        ProductRepository $productRepository,
        AttributeFamilyRepository $attributeFamilyRepository,
        AttributeRepository $attributeRepository,
        HelperRepository $helperRepository,
        ProductImageRepository $productImageRepository,
        BulkProductRepository $bulkProductRepository,
        AttributeOptionRepository $attributeOptionRepository,
        ProductInventoryRepository $productInventoryRepository
    )
    {
        $this->importProductRepository = $importProductRepository;

        $this->categoryRepository = $categoryRepository;

        $this->productFlatRepository = $productFlatRepository;

        $this->productRepository = $productRepository;

        $this->productImageRepository = $productImageRepository;

        $this->attributeFamilyRepository = $attributeFamilyRepository;

        $this->attributeRepository = $attributeRepository;

        $this->productInventoryRepository = $productInventoryRepository;

        $this->helperRepository = $helperRepository;

        $this->bulkProductRepository = $bulkProductRepository;

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
     * create & update configurable-type product
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
            if ($requestData['totalNumberOfCSVRecord'] < 1000) {
                $processCSVRecords = $requestData['totalNumberOfCSVRecord']/($requestData['totalNumberOfCSVRecord']/10);
            } else {
                $processCSVRecords = $requestData['totalNumberOfCSVRecord']/($requestData['totalNumberOfCSVRecord']/100);
            }

            $dataFlowProfileRecord = $this->importProductRepository->findOneByField
            ('data_flow_profile_id', $requestData['data_flow_profile_id']);

            if ($dataFlowProfileRecord) {
                $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];

                foreach ($csvData as $key => $value) {
                    if ($requestData['numberOfCSVRecord'] >= 0) {
                        for ($i = $requestData['countOfStartedProfiles']; $i < count($csvData); $i++) {
                            $product['loopCount'] = $i;

                            if ($csvData[$i]['type'] == 'configurable') {
                                try {
                                    $createValidation = $this->helperRepository->createProductValidation($csvData[$i], $i);

                                    if ( isset($createValidation)) {
                                        return $createValidation;
                                    }

                                    unset($data);

                                    $productFlatData = $this->productFlatRepository->findOneWhere([
                                        'sku'       => $csvData[$i]['sku'],
                                        'url_key'   => $csvData[$i]['url_key']
                                    ]);

                                    $productData = $this->productRepository->findOneWhere([
                                        'sku'   => $csvData[$i]['sku']
                                        ]);

                                    $attributeFamilyData = $this->attributeFamilyRepository->findOneByfield('name', $csvData[$i]['attribute_family_name']);

                                    if (! isset($productFlatData) && empty($productData)) {
                                        $data['type'] = $csvData[$i]['type'];
                                        $data['attribute_family_id'] = $attributeFamilyData->id;
                                        $data['sku'] = $csvData[$i]['sku'];

                                        $product = $this->bulkProductRepository->create($data);
                                    } else {
                                        $product = $productData;
                                    }

                                    unset($data);
                                    $data = [];
                                    $attributeCode = [];
                                    $attributeValue = [];

                                    foreach ($product->getTypeInstance()->getEditableAttributes()->toArray() as $key => $value) {
                                        $attributeOptionArray = [];
                                        $searchIndex = strtolower($value['code']);

                                        if (array_key_exists($searchIndex, $csvData[$i])) {
                                            if ($searchIndex == 'tax_category_id') {
                                                continue;
                                            }

                                            array_push($attributeCode, $searchIndex);

                                            if ($value['type'] == "select") {
                                                $attributeOption = $this->attributeOptionRepository->findOneByField('admin_name', $csvData[$i][$searchIndex]);

                                                array_push($attributeValue, (isset($attributeOption['id']) ? $attributeOption['id'] : null));

                                            } else if ($value['type'] == "checkbox") {
                                                $attributeOption = $this->attributeOptionRepository->findOneWhere([
                                                    'attribute_id'  => $value['id'],
                                                    'admin_name'    => $csvData[$i][$searchIndex]
                                                ]);

                                                array_push($attributeOptionArray, (isset($attributeOption['id']) ? $attributeOption['id'] : null));

                                                array_push($attributeValue, $attributeOptionArray);

                                                unset($attributeOptionArray);
                                            } else {
                                                array_push($attributeValue, $csvData[$i][$searchIndex]);
                                            }

                                            $data = array_combine($attributeCode, $attributeValue);
                                        }
                                    }

                                    $data['dataFlowProfileRecordId'] = $dataFlowProfileRecord->id;
                                    $data['channel'] = core()->getCurrentChannel()->code;

                                    $dataProfile = app('Webkul\Bulkupload\Repositories\DataFlowProfileRepository')->findOneByfield(['id' => $data['dataFlowProfileRecordId']]);
                                    $data['locale'] = $dataProfile->locale_code;

                                    $data['tax_category_id'] = (isset($csvData[$i]['tax_category_id']) && $csvData[$i]['tax_category_id']) ? $csvData[$i]['tax_category_id'] : null;

                                    $categoryData = explode(',', $csvData[$i]['categories_slug']);

                                    if (is_null($csvData[$i]['categories_slug']) || empty($csvData[$i]['categories_slug'])) {
                                        $categoryID = $this->categoryRepository->findBySlugOrFail('root')->id;
                                    } else {
                                        foreach ($categoryData as $key => $value) {
                                            $categoryID[$key] = $this->categoryRepository->findBySlugOrFail($categoryData[$key])->id;
                                        }
                                    }

                                    $data['categories'] = $categoryID;

                                    $individualProductimages = explode(',', $csvData[$i]['images']);

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
                                                $imagePath = storage_path('app/public/  imported-products/extracted-images/admin/'.   $dataFlowProfileRecord->id);

                                                if (!file_exists($imagePath)) {
                                                    mkdir($imagePath, 0777, true);
                                                }

                                                $imageFile = $imagePath.'/'.basename($imageURL) ;

                                                file_put_contents($imageFile, file_get_contents (trim($imageURL)));

                                                $data['images'][$imageArraykey] = $imageFile;
                                            }
                                        }
                                    }

                                    $productAttributeStore = $this->bulkProductRepository->productRepositoryUpdateForVariants($data, $product->id);

                                    if (isset($imageZipName)) {
                                        $this->productImageRepository->bulkuploadImages($data, $product, $imageZipName);
                                    } else if (isset($csvData['images'])) {
                                        $this->productImageRepository->bulkuploadImages($data, $product, $imageZipName = null);
                                    }

                                    if (! isset($productFlatData) && empty($productFlatData)) {
                                        $productFlatData = DB::table('product_flat')->select('id')->orderBy('id', 'desc')->first();
                                    }

                                    $product['productFlatId'] = $productFlatData->id;

                                    $arr[] = $productFlatData->id;

                                    unset($categoryID);
                                } catch (\Exception $e) {
                                    $categoryError = explode('[' ,$e->getMessage());
                                    $categorySlugError = explode(']' ,$e->getMessage());

                                    $error = $e;

                                    $productUploadedWithError = $requestData['productUploaded'] + 1;
                                    $remainDataInCSV = $requestData['totalNumberOfCSVRecord'] - $productUploadedWithError;
                                    $requestData['countOfStartedProfiles'] = $i + 1;

                                    if ($categoryError[0] == "No query results for model ") {
                                        $dataToBeReturn = array(
                                            'remainDataInCSV' => $remainDataInCSV,
                                            'productsUploaded' => $requestData['productUploaded'],
                                            'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                                            'error' => "Invalid Category Slug: " . $categorySlugError[1],
                                        );
                                        $categoryError[0] = null;
                                    } else if (isset($e->errorInfo)) {
                                        $dataToBeReturn = array(
                                            'remainDataInCSV' => $remainDataInCSV,
                                            'productsUploaded' => $requestData['productUploaded'],
                                            'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                                            'error' => $e->errorInfo[2],
                                        );
                                    } else {
                                        $dataToBeReturn = array(
                                            'remainDataInCSV' => $remainDataInCSV,
                                            'productsUploaded' => $requestData['productUploaded'],
                                            'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                                            'error' => $e->getMessage(),
                                        );
                                    }
                                    return $dataToBeReturn;
                                }
                            } else if (isset($product['productFlatId'])) {
                                try {
                                    $current = $product['loopCount'];
                                    $num = 0;
                                    $inventory = [];

                                    $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];

                                    for ($i = $current; $i < count($csvData); $i++) {
                                        $product['loopCount'] = $i;

                                        if ($csvData[$i]['type'] != 'configurable') {
                                            $productFlatData = $this->productFlatRepository->findOneWhere([
                                                'sku'       => $csvData[$i]['sku'],
                                                'url_key'   => null
                                            ]);

                                            $productData = $this->productRepository->findOneWhere([
                                                'sku'   => $csvData[$i]['sku']
                                                ]);

                                            $attributeFamilyData = $this->attributeFamilyRepository->findOneWhere([
                                                'name' => $csvData[$i]['attribute_family_name']
                                            ]);

                                            if (! isset($productFlatData) && empty($productData)) {
                                                $data['parent_id']  = $product->id;
                                                $data['type']       = "simple";
                                                $data['attribute_family_id'] = $attributeFamilyData->id;
                                                $data['sku']        = $csvData[$i]['sku'];

                                                $configSimpleproduct = $this->productRepository->create($data);
                                            } else {
                                                $configSimpleproduct = $productData;
                                            }

                                            unset($data);

                                            $validateVariant = Validator::make($csvData[$i], [
                                                'sku'                       => ['required', 'unique:products,sku,' . $configSimpleproduct->id, new \Webkul\Core\Contracts\Validations\Slug],
                                                'name'                      => 'required',
                                                'super_attribute_price'     => 'required',
                                                'super_attribute_weight'    => 'required',
                                                'super_attribute_option'    => 'required',
                                                'super_attributes'          => 'required'
                                            ]);

                                            if ($validateVariant->fails()) {
                                                $errors = $validateVariant->errors()->getMessages();

                                                $this->helperRepository->deleteProductIfNotValidated($product->id);

                                                foreach($errors as $key => $error) {
                                                    $errorToBeReturn[] = str_replace(".", "", $error[0]). " for sku " .$csvData[$i]['sku'];
                                                }

                                                $productUploadedWithError = $requestData['productUploaded'] + 1;

                                                $requestData['countOfStartedProfiles'] = $i + 1;

                                                if ($requestData['numberOfCSVRecord'] != 0) {
                                                    $remainDataInCSV = $requestData['totalNumberOfCSVRecord'] - $productUploadedWithError;
                                                } else {
                                                    $remainDataInCSV = 0;
                                                }

                                                $dataToBeReturn = array(
                                                    'remainDataInCSV' => $remainDataInCSV,
                                                    'productsUploaded' => $requestData['productUploaded'],
                                                    'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                                                    'error' => $errorToBeReturn,
                                                );

                                                return $dataToBeReturn;
                                            }

                                            $inventory_data = core()->getCurrentChannel()->inventory_sources;

                                            foreach($inventory_data as $key => $datas) {
                                                $inventoryId = $datas->id;
                                            }

                                            $inventoryData[] = (string)$csvData[$i]['super_attribute_qty'];

                                            foreach ($inventoryData as $key => $d) {
                                                $inventory[$inventoryId] = $d;
                                            }

                                            $productInventory = $this->productInventoryRepository->findOneByField('product_id', $configSimpleproduct->id);

                                            if (! isset($productInventory) && empty($productInventory) || $productInventory->count() < 1) {
                                                $data['inventories'] =  $inventory;
                                            }

                                            $superAttributes = explode(',', $csvData[$i]['super_attributes']);
                                            $superAttributesOption = explode(',', $csvData[$i]['super_attribute_option']);

                                            $data['super_attributes'] = array_combine($superAttributes, $superAttributesOption);

                                            if (isset($data['super_attributes']) && $i == $current) {
                                                $super_attributes = [];

                                                foreach ($data['super_attributes'] as $attributeCode => $attributeOptions) {
                                                    $attribute = $this->attributeRepository->findOneByField('code', $attributeCode);

                                                    $super_attributes[$attribute->id] = $attributeOptions;

                                                    $users = $product->super_attributes()->where('id', $attribute->id)->exists();

                                                    if (! $users) {
                                                        $product->super_attributes()->attach($attribute->id);
                                                    }
                                                }
                                            }

                                            $data['dataFlowProfileRecordId'] = $dataFlowProfileRecord->id;
                                            $data['channel'] = core()->getCurrentChannel()->code;

                                            $dataProfile = app('Webkul\Bulkupload\Repositories\DataFlowProfileRepository')->findOneByfield(['id' => $data['dataFlowProfileRecordId']]);
                                            $data['locale'] = $dataProfile->locale_code;

                                            $data['price'] = (string)$csvData[$i]['super_attribute_price'];
                                            $data['special_price'] = (string)$csvData[$i]['special_price'];
                                            $data['special_price_from'] = (string)$csvData[$i]['special_price_from'];
                                            $data['special_price_to'] = (string)$csvData[$i]['special_price_to'];
                                            $data['new'] = (string)$csvData[$i]['new'];
                                            $data['featured'] = (string)$csvData[$i]['featured'];
                                            $data['visible_individually'] = (string)$csvData[$i]['visible_individually'];
                                            $data['tax_category_id'] = (string)$csvData[$i]['tax_category_id'];
                                            $data['cost'] = (string)$csvData[$i]['cost'];
                                            $data['width'] = (string)$csvData[$i]['width'];
                                            $data['height'] = (string)$csvData[$i]['height'];
                                            $data['depth'] = (string)$csvData[$i]['depth'];
                                            $data['status'] = (string)$csvData[$i]['status'];
                                            $data['attribute_family_id'] = $attributeFamilyData->id;
                                            $data['short_description'] = (string)$csvData[$i]['short_description'];
                                            $data['sku'] = (string)$csvData[$i]['sku'];
                                            $data['name'] = (string)$csvData[$i]['name'];
                                            $data['weight'] = (string)$csvData[$i]['super_attribute_weight'];
                                            $data['status'] = (string)$csvData[$i]['status'];

                                            if ( isset($data['super_attributes'])) {
                                                foreach ($data['super_attributes'] as $attributeCode => $attributeOptions) {
                                                    $attribute = $this->attributeRepository->findOneByField('code', $attributeCode);

                                                    if ( $attribute ) {
                                                        $attributeOptionColor = $this->attributeOptionRepository->findOneWhere([
                                                            'attribute_id'  => $attribute->id,
                                                            'admin_name'    => $attributeOptions,
                                                        ]);

                                                        $data[$attributeCode] = $attributeOptionColor->id;
                                                    }
                                                }
                                            }

                                            $individualProductimages = explode(',', $csvData[$i]['images']);

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
                                                        $imagePath = storage_path('app/public/  imported-products/extracted-images/admin/'.   $dataFlowProfileRecord->id);

                                                        if (!file_exists($imagePath)) {
                                                            mkdir($imagePath, 0777, true);
                                                        }

                                                        $imageFile = $imagePath.'/'.basename($imageURL) ;

                                                        file_put_contents($imageFile, file_get_contents (trim($imageURL)));

                                                        $data['images'][$imageArraykey] = $imageFile;
                                                    }
                                                }
                                            }

                                            $configSimpleProductAttributeStore = $this->bulkProductRepository->productRepositoryUpdateForVariants($data, $configSimpleproduct->id);

                                            if (isset($imageZipName)) {
                                                $this->productImageRepository->bulkuploadImages($data, $configSimpleproduct, $imageZipName);
                                            } else if (isset($csvData['images'])) {
                                                $this->productImageRepository->bulkuploadImages($data, $configSimpleproduct, $imageZipName = null);
                                            }

                                            $configSimpleProductAttributeStore['parent_id'] = $product['productFlatId'];

                                            $this->createFlat($configSimpleProductAttributeStore);

                                        } else {
                                            $savedProduct = $requestData['productUploaded'] + 1;
                                            $remainDataInCSV = $requestData['totalNumberOfCSVRecord'] - $savedProduct;
                                            $productsUploaded = $savedProduct;

                                            $requestData['countOfStartedProfiles'] = $product['loopCount'];

                                            $dataToBeReturn = array(
                                                'remainDataInCSV'           => $remainDataInCSV,
                                                'productsUploaded'          => $productsUploaded,
                                                'countOfStartedProfiles'    => $requestData['countOfStartedProfiles']
                                            );

                                            return $dataToBeReturn;
                                        }
                                    }

                                    if ($requestData['errorCount'] == 0) {
                                        $dataToBeReturn = [
                                            'remainDataInCSV' => 0,
                                            'productsUploaded' => $requestData['totalNumberOfCSVRecord'],
                                            'countOfStartedProfiles' => count($csvData),
                                        ];

                                        return $dataToBeReturn;
                                    } else {
                                        $dataToBeReturn = [
                                            'remainDataInCSV' => 0,
                                            'productsUploaded' => $requestData['totalNumberOfCSVRecord'] - $requestData['errorCount'],
                                            'countOfStartedProfiles' => count($csvData),
                                        ];

                                        return $dataToBeReturn;
                                    }

                                    $product['productFlatId'] = null;
                                } catch (\Exception $e) {
                                    $error = $e;
                                    $requestData['countOfStartedProfiles'] = $i + 1;
                                    $remainDataInCSV = $requestData['totalNumberOfCSVRecord'] - $requestData['productUploaded'];

                                    $dataToBeReturn = array(
                                        'remainDataInCSV' => $remainDataInCSV,
                                        'productsUploaded' => $requestData['productUploaded'],
                                        'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                                        'error' => $error->errorInfo[2] ?? $error->getMessage(),
                                    );

                                    return $dataToBeReturn;
                                }
                            }
                        }
                    }
                }
            }
        } catch(\Exception $e) {
            \Log::error('configurable create product log: '. $e->getMessage());
        }
    }

    /**
     * create product flat for variants
     *
     * @param \Webkul\Product\Contracts\Product $product
     *
     * @return mixed
     */
    public function createFlat($product, $parentProduct = null)
    {
        static $familyAttributes = [];

        static $superAttributes = [];

        if (! array_key_exists($product->attribute_family->id, $familyAttributes))
            $familyAttributes[$product->attribute_family->id] = $product->attribute_family->custom_attributes;

        if ($parentProduct && ! array_key_exists($parentProduct->id, $superAttributes))
            $superAttributes[$parentProduct->id] = $parentProduct->super_attributes()->pluck('code')->toArray();

        foreach (core()->getAllChannels() as $channel) {
            foreach ($channel->locales as $locale) {
                $productFlat = $this->productFlatRepository->findOneWhere([
                    'product_id' => $product->id,
                    'channel' => $channel->code,
                    'locale' => $locale->code
                ]);

                if (! $productFlat) {
                    $productFlat = $this->productFlatRepository->create([
                        'product_id' => $product->id,
                        'channel' => $channel->code,
                        'locale' => $locale->code
                    ]);
                }
                foreach ($familyAttributes[$product->attribute_family->id] as $attribute) {
                    if ($parentProduct && ! in_array($attribute->code, array_merge($superAttributes[$parentProduct->id], ['sku', 'name', 'price', 'weight', 'status'])))
                        continue;

                    if (in_array($attribute->code, ['tax_category_id']))
                        continue;

                    if (! Schema::hasColumn('product_flat', $attribute->code))
                        continue;

                    if ($attribute->value_per_channel) {
                        if ($attribute->value_per_locale) {
                            $productAttributeValue = $product->attribute_values()->where('channel', $channel->code)->where('locale', $locale->code)->where('attribute_id', $attribute->id)->first();
                        } else {
                            $productAttributeValue = $product->attribute_values()->where('channel', $channel->code)->where('attribute_id', $attribute->id)->first();
                        }
                    } else {
                        if ($attribute->value_per_locale) {
                            $productAttributeValue = $product->attribute_values()->where('locale', $locale->code)->where('attribute_id', $attribute->id)->first();
                        } else {
                            $productAttributeValue = $product->attribute_values()->where('attribute_id', $attribute->id)->first();
                        }
                    }

                    if ($product->type == 'configurable' && $attribute->code == 'price') {
                        try {
                            $productFlat->{$attribute->code} = app('Webkul\Bulkupload\Helpers\Price')->getVariantMinPrice($product);
                        } catch(\Exception $e) {}
                    } else {
                        try {
                            $productFlat->{$attribute->code} = $productAttributeValue[ProductAttributeValue::$attributeTypeFields[$attribute->type]];
                        } catch(\Exception $e) {}
                    }

                    if ($attribute->type == 'select') {
                        $attributeOption = $this->attributeOptionRepository->find($product->{$attribute->code});

                        if ($attributeOption) {
                            if ($attributeOptionTranslation = $attributeOption->translate($locale->code)) {
                                $productFlat->{$attribute->code . '_label'} = $attributeOptionTranslation->label;
                            } else {
                                $productFlat->{$attribute->code . '_label'} = $attributeOption->admin_name;
                            }
                        }
                    } elseif ($attribute->type == 'multiselect') {
                        $attributeOptionIds = explode(',', $product->{$attribute->code});

                        if (count($attributeOptionIds)) {
                            $attributeOptions = $this->attributeOptionRepository->findWhereIn('id', $attributeOptionIds);

                            $optionLabels = [];

                            foreach ($attributeOptions as $attributeOption) {
                                if ($attributeOptionTranslation = $attributeOption->translate($locale->code)) {
                                    $optionLabels[] = $attributeOptionTranslation->label;
                                } else {
                                    $optionLabels[] = $attributeOption->admin_name;
                                }
                            }

                            $productFlat->{$attribute->code . '_label'} = implode(', ', $optionLabels);
                        }
                    }
                }

                $productFlat->created_at = $product->created_at;

                $productFlat->updated_at = $product->updated_at;

                if ($parentProduct) {
                    $parentProductFlat = $this->productFlatRepository->findOneWhere([
                        'product_id' => $parentProduct->id,
                        'channel' => $channel->code,
                        'locale' => $locale->code
                    ]);
                }

                $productFlat->parent_id = $product->parent_id;

                $productFlat->save();

                $product->parent_id--;
            }
        }
    }
}