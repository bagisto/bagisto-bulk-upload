<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Log;
use Storage;
use Illuminate\Support\Facades\{Event, Validator};
use Webkul\Product\Repositories\ProductCustomerGroupPriceRepository;

use function PHPSTORM_META\type;

class SimpleProductRepository extends Repository
{
    protected $errors = [];
    protected $dataNotInserted = [];

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

            if ($product->type == 'downloadable') {
                $this->addLinksAndSamples($csvData, $data, $dataFlowProfileRecord, $product);
            }

            dd($data);

            // Validate product data and handle errors
            $validationErrors = $this->validateProductData($data, $product);

            if ($validationErrors) {
                return $validationErrors;
            }

            Event::dispatch('catalog.product.update.before',  $product->id);

            $productFlat = $this->productRepository->update($data, $product->id);

            Event::dispatch('catalog.product.update.after', $productFlat);

            // Upload images if necessary
            if (isset($imageZipName) || (!empty($csvData['images']))) {
                $imageZip = $imageZipName ?? null;
                $this->productImageRepository->bulkuploadImages($data, $product, $imageZip, $dataFlowProfileRecord->id);
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

    public function addLinksAndSamples($csvData, $data, $dataFlowProfileRecord, $product)
    {
        $downloadableLinks = $this->extractDownloadableFiles($dataFlowProfileRecord);

        // Define a helper function to parse CSV data or provide a default value
        function parseCsvData($csvData, $key, $default = null) {
            return isset($csvData[$key]) ? explode(',', $csvData[$key]) : $default;
        }

        // Initialize arrays to store downloadable links
        $linkNameKey = [];
        $d_links = [];

        // Check if 'link_titles' key exists in $csvData
        if (isset($csvData['link_titles'])) {
            $linkTypes = parseCsvData($csvData, 'link_types');
            $linkTitles = parseCsvData($csvData, 'link_titles');
            $linkPrices = parseCsvData($csvData, 'link_prices', []);
            $linkUrlNames = parseCsvData($csvData, 'link_url');
            $linkFileNames = parseCsvData($csvData, 'link_file_names');
            $linkDownloads = parseCsvData($csvData, 'link_downloads', 0);
            $linkSortOrders = parseCsvData($csvData, 'link_sort_orders', 0);
            $linkSampleTypes = parseCsvData($csvData, 'link_sample_types', ['file']);
            $linkSampleUrlNames = parseCsvData($csvData, 'link_sample_url');
            $linkSampleFileNames = parseCsvData($csvData, 'link_sample_file_names', []);
        }

        // Loop through the downloadable links data
        foreach ($linkTitles as $index => $linkTitle) {

            $linkType = trim(strtolower($linkTypes[$index]));
            $linkSampleType = trim(strtolower($linkSampleTypes[$index]));

            // Determine the sample file or URL for the link
            $sampleFileLink = null;

            if ($linkSampleType == "file") {
                if (isset($downloadableLinks['upload_link_sample_filesZipName'])) {
                    $sampleFileLink = $this->fileOrUrlUpload(
                        $dataFlowProfileRecord,
                        $linkSampleType,
                        ($linkSampleType == "url") ? $linkSampleFileNames[$index - 1] : $linkSampleFileNames[$index],
                        $product->id,
                        $downloadableLinks,
                        false
                    );
                }
            } elseif ($linkSampleType == "url") {
                $sampleFileLink = $this->fileOrUrlUpload(
                    $dataFlowProfileRecord,
                    $linkSampleType,
                    $linkSampleUrlNames[$index],
                    $product->id,
                    $downloadableLinks,
                    false
                );
            }

            // Determine the file link or URL for the link
            $fileLink = $this->linkFileOrUrlUpload(
                $dataFlowProfileRecord,
                $linkTypes[$index],
                ($linkSampleType == "url") ? $linkUrlNames[$index] : $linkFileNames[$index],
                $product->id,
                $downloadableLinks
            );

            // Create the downloadable link array
            $link['link_'.$index] = [
                core()->getCurrentLocale()->code => [
                    "title" => $linkTitle,
                ],
                "price" => $linkPrices[$index],
                "type" => trim($linkTypes[$index]),
                "file" => trim($fileLink),
                "file_name" => ($linkType == "file") ? trim($linkFileNames[$index]) : "",
                "sample_type" => trim($linkSampleTypes[$index]),
                "downloads" => $linkDownloads[$index] ?? 0,
                "sort_order" => $linkSortOrders[$index] ?? 0,
            ];

            if (trim($linkSampleTypes[$index]) == "url") {
                $link['link_'.$index]['sample_url'] = trim($linkSampleUrlNames[$index]);
            } elseif (trim($linkSampleTypes[$index]) == "file" && isset($sampleFileLink)) {
                $link['link_'.$index]['sample_file'] = trim($sampleFileLink);
                $link['link_'.$index]['sample_file_name'] = trim($linkSampleFileNames[$index]);
            }

            array_push($linkNameKey, 'link_'.$index);
            array_push($d_links, $link['link_'.$index]);
        }

        $combinedLinksArray = array_combine($linkNameKey, $d_links);

        $data['downloadable_links'] = $combinedLinksArray;

        dd($data);
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
                $sourcePath .= $dataFlowProfileRecord->id.'/'.$downloadableLinks['upload'.($flag ? 'Sample' : '').'FilesZipName']['dirname'].'/'.trim(basename($file));

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
                $imagePath = storage_path('app/public/imported-products/extracted-images/admin/link-files/'.$dataFlowProfileRecord->id);

                if (!file_exists($imagePath)) {
                    mkdir($imagePath, 0777, true);
                }

                $imageFile = $imagePath.'/'.basename($file);

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
