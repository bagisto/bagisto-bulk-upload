<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Log;
use Storage;
use Illuminate\Support\Facades\{Event, Validator};
use Webkul\Core\Eloquent\Repository as BaseRepository;
use Webkul\Attribute\Repositories\{AttributeFamilyRepository, AttributeOptionRepository};
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Inventory\Repositories\InventorySourceRepository;
use Webkul\Product\Repositories\{ProductCustomerGroupPriceRepository, ProductRepository};
use Webkul\Bulkupload\Repositories\{ImportProductRepository, ProductImageRepository};
use Webkul\Bulkupload\Repositories\Products\HelperRepository;

class SimpleProductRepository extends BaseRepository
{
    protected $errors = [];
    protected $dataNotInserted = [];

    /**
     * Create a new repository instance.
     *
     * @param  \Webkul\Attribute\Repositories\AttributeFamilyRepository  $attributeFamilyRepository
     * @param  \Webkul\Attribute\Repositories\AttributeOptionRepository  $attributeOptionRepository
     * @param  \Webkul\Category\Repositories\CategoryRepository  $categoryRepository
     * @param  \Webkul\Product\Repositories\ProductRepository  $productRepository
     * @param  \Webkul\Inventory\Repositories\InventorySourceRepository  $inventorySourceRepository
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
        protected ProductRepository $productRepository,
        protected InventorySourceRepository $inventorySourceRepository,
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
    public function createProduct($imageZipName, $dataFlowProfileRecord, $csvData, $key)
    {
        try {
            //Validation
            $createValidation = $this->helperRepository->createProductValidation($csvData, $key);

            if (isset($createValidation)) {
                return $createValidation;
            }

            // Check for Duplicate SKU
            $product = $this->productRepository->firstWhere('sku', $csvData['sku']);

            if ($product && $product->type != $csvData['type']) {
                return [
                    'error' => ["Duplicate entry for sku {$product->sku}"],
                ];
            }

            $attributeFamilyData = $this->attributeFamilyRepository->findOneByfield(['name' => $csvData['attribute_family_name']]);

            // Create Product
            if (! $product) {

                Event::dispatch('catalog.product.create.before');

                $product = $this->productRepository->create([
                    'sku' => $csvData['sku'],
                    'type' => $csvData['type'],
                    'attribute_family_id' => $attributeFamilyData->id,
                ]);

                Event::dispatch('catalog.product.create.after', $product);
            }

            // Process product attributes
            $data = $this->processProductAttributes($csvData, $product);

            // Process inventory
            $this->processProductInventory($csvData, $data);

            // Process categories
            $this->processProductCategories($csvData, $data, $dataFlowProfileRecord);

            // Process customer group pricing
            $this->processCustomerGroupPricing($csvData, $data, $product);

            // Process product images
            $this->processProductImages($csvData, $imageZipName, $dataFlowProfileRecord, $data);

            if ($product->type == 'downloadable' && isset($csvData['link_titles'])) {
                $data['downloadable_links'] = $this->addLinksAndSamples($csvData, $dataFlowProfileRecord, $product);
            }

            if ($product->type == 'downloadable' && isset($csvData['samples_title'])) {
                $data['downloadable_samples'] = $this->addSamples($csvData, $dataFlowProfileRecord, $product);
            }

            // Validate product data and handle errors
            $validationErrors = $this->validateProductData($data, $product);

            if ($validationErrors) {
                return $validationErrors;
            }

            Event::dispatch('catalog.product.update.before',  $product->id);

            $productFlat = $this->productRepository->update($data, $product->id);

            Event::dispatch('catalog.product.update.after', $productFlat);

            // Upload images if necessary
            // if (isset($imageZipName) || (!empty($csvData['images']))) {
            //     $imageZip = $imageZipName ?? null;
            //     $this->productImageRepository->bulkuploadImages($data, $productFlat, $imageZip, $dataFlowProfileRecord->id);
            // }

            if (isset($imageZipName)) {
                $this->productImageRepository->bulkuploadImages($data, $productFlat, $imageZipName, $dataFlowProfileRecord->id);
            } else if (isset($csvData['images'])) {
                $this->productImageRepository->bulkuploadImages($data, $productFlat, $imageZipName = null, $dataFlowProfileRecord->id);
            }
        } catch(\Exception $e) {
            Log::error('simple product store function '. $e->getMessage());
            Log::error('simple product store function '. $e);
        }
    }

    // Process product attributes and return data array
    private function processProductAttributes($csvData, $product)
    {
        $data = [];
        $attributeCode = [];
        $attributeValue = [];

        $attributes = $product->getTypeInstance()->getEditableAttributes()->toArray();

        foreach ($attributes as $attribute) {
            $searchIndex = strtolower($attribute['code']);

            $csvValue = $csvData[$searchIndex] ?? null;

            if (! is_null($csvValue)) {
                $attributeCode[] = $searchIndex;

                switch ($attribute['type']) {
                    case "select":
                        $attributeOption = $this->attributeOptionRepository->findOneByField(['admin_name' => $csvData[$searchIndex]]);
                        if ($attributeOption) {
                            $attributeValue[] = $attributeOption['id'];
                        }

                        break;

                    case "checkbox":
                        $attributeOption = $this->attributeOptionRepository->findOneByField(['attribute_id' => $attribute['id'], 'admin_name' => $csvData[$searchIndex]]);
                        if ($attributeOption) {
                            $attributeValue[] = [$attributeOption['id']];
                        }

                        break;

                    case in_array($searchIndex, ["color", "size", "brand"]):
                        $attributeOption = $this->attributeOptionRepository->findOneByField(['admin_name' => ucwords($csvData[$searchIndex])]);
                        if ($attributeOption) {
                            $attributeValue[] = $attributeOption['id'];
                        }

                        break;

                    default:
                        $attributeValue[] = $csvData[$searchIndex];
                        break;
                }

                $data = array_combine($attributeCode, $attributeValue);
            }
        }

        return $data;
    }

    // Process product inventory data and update $data array
    private function processProductInventory($csvData, &$data)
    {
        $inventoryCode = explode(', ', $csvData['inventory_sources']);

        $inventoryId = $this->inventorySourceRepository->whereIn('code', $inventoryCode)->pluck('id')->toArray();

        $inventoryData = explode(', ', $csvData['inventories']);

        if (count($inventoryId) != count($inventoryData)) {
            $inventoryData = array_fill(0, count($inventoryId), 0);
        }

        $data['inventories'] =  array_combine($inventoryId, $inventoryData);;
    }

    // Process product categories and update $data array
    private function processProductCategories($csvData, &$data, $dataFlowProfileRecord)
    {
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
    }

    // Process customer group pricing and update $data array
    private function processCustomerGroupPricing($csvData, &$data, $product)
    {
        if (isset($csvData['customer_group_prices']) && ! empty($csvData['customer_group_prices'])) {
            $data['customer_group_prices'] = json_decode($csvData['customer_group_prices'], true);
            app(ProductCustomerGroupPriceRepository::class)->saveCustomerGroupPrices($data, $product);
        }
    }

    // Process product images and update $data array
    private function processProductImages($csvData, $imageZipName, $dataFlowProfileRecord, $data)
    {
        $individualProductimages = explode(', ', $csvData['images']);

        if (isset($imageZipName)) {
            $imagePath = 'public/imported-products/extracted-images/admin/' . $dataFlowProfileRecord->id . '/' . $imageZipName['dirname'] . '/';

            $images = Storage::disk('local')->files($imagePath);

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

                    if (! file_exists($imagePath)) {
                        mkdir($imagePath, 0777, true);
                    }

                    $imageFile = $imagePath . '/' . basename($imageURL);

                    file_put_contents($imageFile, file_get_contents(trim($imageURL)));

                    $data['images'][$imageArraykey] = $imageFile;
                }
            }
        }
    }

    // Validate product data and handle errors
    private function validateProductData($data, $product)
    {
        $returnRules = $this->helperRepository->validateCSV($data, $product);
        $csvValidator = Validator::make($data, $returnRules);

        if ($csvValidator->fails()) {
            $errors = $csvValidator->errors()->getMessages();

            $this->helperRepository->deleteProductIfNotValidated($product->id);

            $errorToBeReturn = [];

            foreach ($errors as $error) {
                if ($error[0] == "The url key has already been taken.") {
                    $errorToBeReturn[] = "The url key " . $data['url_key'] . " has already been taken";
                } else {
                    $errorToBeReturn[] = str_replace(".", "", $error[0]) . " for sku " . $data['sku'];
                }
            }

            $dataToBeReturn = [
                // 'remainDataInCSV' => $remainDataInCSV,
                // 'productsUploaded' => $productsUploaded,
                // 'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                'error' => $errorToBeReturn,
            ];

            return $dataToBeReturn;
        }

        return null; // No validation errors
    }

    public function addLinksAndSamples($csvData, $dataFlowProfileRecord, $product)
    {
        $downloadableLinks = $this->extractDownloadableFiles($dataFlowProfileRecord);

        // Initialize arrays to store downloadable links
        $linkNameKey = [];
        $d_links = [];

        $linkDataKeys = [
            'link_titles',
            'link_prices',
            'link_types',
            'link_url',
            'link_file_names',
            'link_downloads',
            'link_sort_orders',
            'link_sample_types',
            'link_sample_url',
            'link_sample_file_names',
        ];

        $linkData = [];

        foreach ($linkDataKeys as $key) {
            $linkData[$key] = preg_split('/,\s*|,/', $csvData[$key]);
        }

        // Ensure that all link data arrays have the same length
        $dataLengths = array_map('count', $linkData);
        $uniqueLengths = array_unique($dataLengths);

        if (! (count($uniqueLengths) === 1)) {
            return null;
        }

        $dataLength = reset($uniqueLengths);

        // Loop through the downloadable links data
        for ($index = 0; $index < $dataLength; $index++) {
            $linkTitle = $linkData['link_titles'][$index];
            $linkType = trim(strtolower($linkData['link_types'][$index]));
            $linkSampleType = trim(strtolower($linkData['link_sample_types'][$index]));

            // Determine the file link or URL for the link
            $fileLink = $this->linkFileOrUrlUpload(
                $dataFlowProfileRecord,
                $linkType,
                ($linkType == "url") ? $linkData['link_url'][$index] : $linkData['link_file_names'][$index],
                $product->id,
                $downloadableLinks
            );

            // Determine the sample file or URL for the link
            $sampleFileLink = $this->fileOrUrlUpload(
                $dataFlowProfileRecord,
                $linkSampleType,
                ($linkSampleType == "url") ? $linkData['link_sample_url'][$index] : $linkData['link_sample_file_names'][$index],
                $product->id,
                $downloadableLinks,
                false
            );

            // Create the downloadable link array
            $link['link_' . $index] = [
                core()->getCurrentLocale()->code => [
                    "title" => $linkTitle,
                ],
                "price" => isset($linkData['link_prices'][$index]) ? $linkData['link_prices'][$index] : "",
                "type" => trim($linkData['link_types'][$index]),
                "sample_type" => trim($linkData['link_sample_types'][$index]),
                "downloads" => isset($linkData['link_downloads'][$index]) ? $linkData['link_downloads'][$index] : 0,
                "sort_order" => isset($linkData['link_sort_orders'][$index]) ? $linkData['link_sort_orders'][$index] : 0,
            ];

            if (trim($linkData['link_types'][$index]) == "url") {
                $link['link_' . $index]['url'] = trim($fileLink);
            } elseif (trim($linkData['link_types'][$index]) == "file" && isset($fileLink)) {
                $link['link_' . $index]['file'] = trim($fileLink);
                $link['link_' . $index]['file_name'] = trim($linkData['link_file_names'][$index]);
            }

            if (trim($linkData['link_sample_types'][$index]) == "url") {
                $link['link_' . $index]['sample_url'] = trim($linkData['link_sample_url'][$index]);
            } elseif (trim($linkData['link_sample_types'][$index]) == "file" && isset($sampleFileLink)) {
                $link['link_' . $index]['sample_file'] = trim($sampleFileLink);
                $link['link_' . $index]['sample_file_name'] = trim($linkData['link_sample_file_names'][$index]);
            }

            array_push($linkNameKey, 'link_' . $index);
            array_push($d_links, $link['link_' . $index]);
        }

        $combinedLinksArray = array_combine($linkNameKey, $d_links);

        return $combinedLinksArray;
    }

    public function addSamples($csvData, $dataFlowProfileRecord, $product)
    {
        $downloadableLinks = $this->extractDownloadableFiles($dataFlowProfileRecord);

        $d_samples = [];
        $sampleNameKey = [];

        $sampleDataKeys = [
            'samples_title',
            'sample_type',
            'sample_files',
            'sample_url',
            'sample_sort_order',
        ];

        $sampleData = [];

        foreach ($sampleDataKeys as $key) {
            $sampleData[$key] = preg_split('/,\s*|,/', $csvData[$key]);
        }

        // Ensure that all link data arrays have the same length
        $dataLengths = array_map('count', $sampleData);
        $uniqueLengths = array_unique($dataLengths);

        if (! (count($uniqueLengths) === 1)) {
            return null;
        }

        $dataLength = reset($uniqueLengths);
dd($sampleData);
        // Loop through the downloadable sample data
        for ($index = 0; $index < $dataLength; $index++) {
            $sampleFileType = trim(strtolower($sampleData[$index]));

            // Determine the file link or URL for the sample
            $sampleFileLink = $this->fileOrUrlUpload($dataFlowProfileRecord, $sampleFileType, ($sampleFileType == "url") ? $urlFiles[$index] : $sampleFiles[$index], $product->id, $downloadableLinks, true);

            // Create the downloadable sample array
            $sample['sample_' . $index] = [
                core()->getCurrentLocale()->code => [
                    "title" => $sampleTitle,
                ],
                "type" => trim($sampleType[$index]),
                "sort_order" => $sampleSortOrder[$index] ?? 0,
            ];

            if (trim($sampleType[$index]) == "url") {
                $sample['sample_' . $index]['url'] = trim($urlFiles[$index]);
            } elseif (trim($sampleType[$index]) == "file" && isset($sampleFileLink)) {
                $sample['sample_' . $index]['file'] = trim($sampleFileLink);
                $sample['sample_' . $index]['file_name'] = trim($sampleFiles[$index]);
            }

            array_push($sampleNameKey, 'sample_' . $index);
            array_push($d_samples, $sample['sample_' . $index]);
        }

        $combinedArray = array_combine($sampleNameKey, $d_samples);

        return $combinedArray;
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
                // Determine the file path
                $sourcePath = $flag ? "imported-products/extracted-images/admin/sample-files/" : "imported-products/extracted-images/admin/link-sample-files/";

                // 'upload_link_files', 'upload_sample_files', 'upload_link_sample_files'
                $sourcePath .= $dataFlowProfileRecord->id.'/'.$downloadableLinks['upload_'.($flag ? 'sample' : 'link_sample').'_filesZipName']['dirname'].'/'.trim(basename($file));

                $destination = "product/".$id.'/'.trim(basename($file));

                // Copy the file to the destination
                Storage::copy($sourcePath, $destination);

                return $destination;
            } else {
                // Handle URL upload
                $imagePath = storage_path('app/public/imported-products/extracted-images/admin/'.($flag ? 'sample-files' : 'link-sample-files').'/'.$dataFlowProfileRecord->id);

                if (!file_exists($imagePath)) {
                    mkdir($imagePath, 0777, true);
                }

                $imageFile = $imagePath.'/'.basename($file);

                file_put_contents($imageFile, file_get_contents(trim($file)));

                $sourcePath = "imported-products/extracted-images/admin/".($flag ? 'sample-files' : 'link-sample-files').'/'.$dataFlowProfileRecord->id.'/'.basename($file);

                $destination = "product/".$id.'/'.basename($file);

                Storage::copy($sourcePath, $destination);

                return $destination;
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
                // Determine the file path

                $sourcePath = "imported-products/extracted-images/admin/link-files/".$dataFlowProfileRecord->id.'/'.$downloadableLinks['upload_link_filesZipName']['dirname'].'/'.trim(basename($file));

                $destination = "product_downloadable_links/".$id.'/'.basename($file);

                // Copy the file to the destination
                Storage::copy($sourcePath, $destination);

                return $destination;
            } else {
                // Handle URL upload
                $filePath = storage_path('app/public/imported-products/extracted-images/admin/link-files/'.$dataFlowProfileRecord->id);

                if (!file_exists($filePath)) {
                    mkdir($filePath, 0777, true);
                }

                $imageFile = $filePath.'/'.basename($file);

                file_put_contents($imageFile, file_get_contents(trim($file)));

                $sourcePath = "imported-products/extracted-images/admin/link-files/".$dataFlowProfileRecord->id.'/'.basename($file);

                $destination = "product_downloadable_links/".$id.'/'.basename($file);

                Storage::copy($sourcePath, $destination);

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
        $result = [];

        $fileTypes = ['upload_link_files', 'upload_sample_files', 'upload_link_sample_files'];

        foreach ($fileTypes as $fileType) {

            if (isset($record->$fileType) && $record->$fileType !== "") {

                $zipArchive = new \ZipArchive();

                $extractedPath = storage_path("app/public/imported-products/extracted-images/admin/{$fileType}/{$record->id}/");

                if ($zipArchive->open(storage_path("app/public/{$record->$fileType}"))) {
                    for ($i = 0; $i < $zipArchive->numFiles; $i++) {
                        $filename = $zipArchive->getNameIndex($i);
                        $result["{$fileType}ZipName"] = pathinfo($filename);
                    }

                    $zipArchive->extractTo($extractedPath);
                    $zipArchive->close();
                }
            }
        }

        return $result;
    }
}
