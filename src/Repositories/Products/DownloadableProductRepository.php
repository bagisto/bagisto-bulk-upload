<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Log;
use Storage;
use Illuminate\Support\Facades\{Event, Validator};
use Webkul\Admin\Imports\DataGridImport;
use Webkul\Product\Repositories\ProductCustomerGroupPriceRepository;

class DownloadableProductRepository extends Repository
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
     * create & update downloadable-type product
     *
     * @return mixed
     */
    public function createProduct($imageZipName, $dataFlowProfileRecord, $csvData, $key)
    {


        $d_samples = [];

        if (isset($csvData['samples_title'])) {

            $sampleTitles = explode(', ', $csvData['samples_title']);
            $sampleType = explode(', ', $csvData['sample_type']);
            $sampleFiles = explode(', ', $csvData['sample_files']);
            $urlFiles = explode(', ', $csvData['sample_url']);
            $sampleSortOrder = ! empty($csvData['sample_sort_order']) ? explode(', ', $csvData['sample_sort_order']) : 0;

            foreach ($sampleTitles as $j => $sampleTitle) {
                $sampleTypeLowerCase = trim(strtolower($sampleType[$j]));
                $sampleFileName = ($sampleTypeLowerCase == 'url') ? $urlFiles[$j] : $sampleFiles[$j];

                if (isset($downloadableLinks['uploadSampleFilesZipName'])) {
                    if (isset($sampleType[$j - 1]) && trim(strtolower($sampleType[$j - 1])) == "url") {
                        $sampleFileName = $sampleFiles[$j - 1];
                    }

                    $files = $this->fileOrUrlUpload($dataFlowProfileRecord, $sampleTypeLowerCase, $sampleFileName, $product->id, $downloadableLinks, $sampleFile = true);

                    if (isset($files)) {
                        $sampleKey = 'sample_' . $j;
                        $d_samples[$sampleKey] = [
                            core()->getCurrentLocale()->code => [
                                "title" => $sampleTitle,
                            ],
                            "type" => $sampleTypeLowerCase,
                            "file" => trim($files),
                            "file_name" => $sampleFileName,
                            "sort_order" => $sampleSortOrder[$j] ?? 0,
                        ];
                    }
                }
            }
        }

        $data['downloadable_samples'] = $d_samples;

        //prepare downloadable sample data
        for ($j = 0; $j < count($sampleTitles); $j++) {
            if (trim(strtolower($sampleType[$j])) == "file") {
                if (isset($downloadableLinks['uploadSampleFilesZipName'])) {
                    if (trim(strtolower($sampleType[$j-1])) == "url") {
                        $sampleFileName = $sampleFiles[$j-1];
                    } else {
                        $sampleFileName = $sampleFiles[$j];
                    }

                    $files = $this->fileOrUrlUpload($dataFlowProfileRecord, $sampleType[$j], $sampleFileName, $product->id, $downloadableLinks, $sampleFile = true);

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
                $files = $this->fileOrUrlUpload($dataFlowProfileRecord, $sampleType[$j], $urlFiles[$j], $product->id, $downloadableLinks, $sampleFile = true);

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

        //for downloadable link explode
        if (isset($csvData['link_titles'])) {
            $linkTitles = explode(', ', $csvData['link_titles']);
            $linkTypes = explode(', ', $csvData['link_types']);

            $linkFileNames = explode(', ', $csvData['link_file_names']);

            $linkPrices = ! empty($csvData['link_prices']) ? explode(', ', $csvData['link_prices']) : "";

            $linkSampleTypes = ! empty($csvData['link_sample_types']) ? explode(', ', $csvData['link_sample_types']) : "file";

            $linkSampleFileNames = ! empty($csvData['link_sample_file_names']) ? explode(', ', $csvData['link_sample_file_names']) : "";

            $linkDownloads = ! empty($csvData['link_downloads']) ? explode(', ', $csvData['link_downloads']) : 0;

            $linkSortOrders = ! empty($csvData['link_sort_orders']) ? explode(', ', $csvData['link_sort_orders']) : 0;

            $linkSampleUrlNames = explode(', ', $csvData['link_sample_url']);
            $linkUrlNames = explode(', ', $csvData['link_url']);
        }

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

                        $sampleFileLink = $this->fileOrUrlUpload($dataFlowProfileRecord, $linkSampleTypes[$j], $linkSampleFile, $product->id, $downloadableLinks, $sampleLinkfile = false);
                    }
                } else if (trim(strtolower($linkSampleTypes[$j])) == "url") {
                    $sampleFileLink = $this->fileOrUrlUpload($dataFlowProfileRecord, $linkSampleTypes[$j], $linkSampleUrlNames[$j], $product->id, $downloadableLinks, $sampleLinkfile = false);
                }

                if (isset($downloadableLinks['uploadLinkFilesZipName'])) {
                    if (trim(strtolower($linkSampleTypes[$j-1])) == "url") {
                        $linkFileName = $linkFileNames[$j-1];
                    } else {
                        $linkFileName = $linkFileNames[$j];
                    }

                    $fileLink = $this->linkFileOrUrlUpload($dataFlowProfileRecord, $linkTypes[$j], $linkFileName, $product->id, $downloadableLinks);
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
                        $sampleFileLink = $this->fileOrUrlUpload($dataFlowProfileRecord, $linkSampleTypes[$j], $linkSampleFileNames[$j], $product->id, $downloadableLinks, $sampleLinkfile = false);
                    }
                } else if (trim(strtolower($linkSampleTypes[$j])) == "url") {
                    $sampleFileLink = $this->fileOrUrlUpload($dataFlowProfileRecord, $linkSampleTypes[$j], $linkSampleUrlNames[$j], $product->id, $downloadableLinks, $sampleLinkfile = false);
                }

                $fileLink = $this->linkFileOrUrlUpload($dataFlowProfileRecord, $linkTypes[$j], $linkUrlNames[$j], $product->id, $downloadableLinks);

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
