<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Storage;
use Illuminate\Support\Facades\{Event, Validator};
use Webkul\Product\Repositories\ProductCustomerGroupPriceRepository;

class VirtualProductRepository extends Repository
{
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
    public function createProduct($imageZipName, $dataFlowProfileRecord, $csvData, $key)
    {
        try {
            $createValidation = $this->helperRepository->createProductValidation($csvData, $key);

            if (isset($createValidation)) {
                return $createValidation;
            }

            $product = $this->productRepository->findWhere(['sku' => $csvData['sku']])->first();

            $attributeFamilyData = $this->attributeFamilyRepository->findOneByfield(['name' => $csvData['attribute_family_name']]);

            if (! $product) {
                Event::dispatch('catalog.product.create.before');

                $product = $this->productRepository->create([
                    'sku' => $csvData['sku'],
                    'type' => $csvData['type'],
                    'attribute_family_id' => $attributeFamilyData->id,
                ]);

                Event::dispatch('catalog.product.create.after', $product);
            } else {
                if ($product->type != 'virtual') {
                    $errorToBeReturn[] = "Duplicate entry for sku " . $product->sku;

                    $dataToBeReturn = [
                        'error' => $errorToBeReturn,
                    ];

                    return $dataToBeReturn;
                }
            }

            $data = [];
            $attributeCode = [];
            $attributeValue = [];

            $editableAttributes = $product->getTypeInstance()->getEditableAttributes()->toArray();

            //default attributes
            foreach ($editableAttributes as $key => $value) {
                $searchIndex = $value['code'];

                if (array_key_exists($searchIndex, $csvData) && ! is_null($csvData[$searchIndex])) {
                    $attributeCode[] = $searchIndex;

                    if (in_array($searchIndex, ["color", "size", "brand"])) {
                        $attributeOption = $this->attributeOptionRepository->findOneByField(['admin_name' => ucwords($csvData[$searchIndex])]);

                        if ($attributeOption) {
                            $attributeValue[] = $attributeOption['id'];
                        }
                    } else {
                        $attributeValue[] = $csvData[$searchIndex];
                    }

                    $data = array_combine($attributeCode, $attributeValue);
                }
            }

            $inventoryCode = explode(', ', $csvData['inventory_sources']);

            $inventoryIds = $this->inventorySourceRepository->whereIn('code', $inventoryCode)->pluck('id')->toArray();

            $inventoryData = explode(', ', $csvData['inventories']);

            if (count($inventoryIds) != count($inventoryData)) {
                $inventoryData = array_fill(0, count($inventoryIds), 0);
            }

            $data['inventories'] =  array_combine($inventoryIds, $inventoryData);

            if (is_null($csvData['categories_slug']) || empty($csvData['categories_slug'])) {
                $categoryID = $this->categoryRepository->findBySlugOrFail('root')->id;
            } else {
                $categoryData = explode(', ', $csvData['categories_slug']);

                $categoryID = array_map(function ($value) {
                    return $this->categoryRepository->findBySlugOrFail(strtolower($value))->id;
                }, $categoryData);
            }

            $data['locale'] = $dataFlowProfileRecord->profiler->locale_code;
            $data['channel'] = core()->getCurrentChannel()->code;
            $data['categories'] = $categoryID;

            //customerGroupPricing
            if (isset($csvData['customer_group_prices']) && ! empty($csvData['customer_group_prices'])) {
                $data['customer_group_prices'] = json_decode($csvData['customer_group_prices'], true);
                app(ProductCustomerGroupPriceRepository::class)->saveCustomerGroupPrices($data, $product);
            }

            //Product Images
            $individualProductimages = explode(', ', $csvData['images']);

            if (isset($imageZipName)) {
                $imageDirectory = 'public/imported-products/extracted-images/admin/'.$dataFlowProfileRecord->id.'/'.$imageZipName['dirname'].'/';

                $images = Storage::disk('local')->files($imageDirectory);

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

            //to check validation
            $validationRules = $this->helperRepository->validateCSV($data, $product);
            $csvValidator = Validator::make($data, $validationRules);

            if ($csvValidator->fails()) {
                $errors = $csvValidator->errors()->getMessages();

                $this->helperRepository->deleteProductIfNotValidated($product->id);

                foreach($errors as $key => $error) {
                    if ($error[0] == "The url key has already been taken.") {
                        $errorToBeReturn[] = "The url key " . $data['url_key'] . " has already been taken";
                    } else {
                        $errorToBeReturn[] = str_replace(".", "", $error[0]) . " for sku " . $data['sku'];
                    }
                }

                $dataToBeReturn = array(
                    // 'remainDataInCSV' => $remainDataInCSV,
                    // 'productsUploaded' => $productsUploaded,
                    // 'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                    'error' => $errorToBeReturn,
                );

                return $dataToBeReturn;
            }

            Event::dispatch('catalog.product.update.before',  $product->id);

            $configVirtualProduct = $this->productRepository->update($data, $product->id);

            Event::dispatch('catalog.product.update.after',$configVirtualProduct);

            if (isset($imageZipName) || (isset($csvData['images']) && ! empty($csvData['images']))) {
                $imageZip = isset($imageZipName) ? $imageZipName : null;
                $this->productImageRepository->bulkuploadImages($data, $product, $imageZip, $dataFlowProfileRecord->id);
            }

        } catch(\Exception $e) {
            \Log::error('virtual product store function'. $e->getMessage());
        }
    }
}
