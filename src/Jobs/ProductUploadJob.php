<?php

namespace Webkul\Bulkupload\Jobs;

use Excel;
use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Bus\{Batchable, Queueable};
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Webkul\Admin\Exports\DataGridExport;

class ProductUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /**
     * Create a new job instance.
     * @param  array $chunk
     */
    public function __construct(
        protected $imageZipName,
        protected $dataFlowProfileRecord,
        protected $chunk,
    )
    {
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $simpleProductRepository = app('Webkul\Bulkupload\Repositories\Products\SimpleProductRepository');
        $downloadableProductRepository = app('Webkul\Bulkupload\Repositories\Products\DownloadableProductRepository');

        $errorArray = [];
        $records = [];
        $uploadedProduct = [];
        $isError = false;

        foreach($this->chunk as $data) {
            foreach($data as $key => $arr) {

                switch($arr['type']) {
                    case "simple":
                        $uploadedProduct = $simpleProductRepository->createProduct($this->imageZipName, $this->dataFlowProfileRecord, $arr, $key);

                        break;
                    case "virtual":
                        $uploadedProduct = $simpleProductRepository->createProduct($this->imageZipName, $this->dataFlowProfileRecord, $arr, $key);

                        break;
                    case "downloadable":
                        $uploadedProduct = $downloadableProductRepository->createProduct($this->imageZipName, $this->dataFlowProfileRecord, $arr, $key);

                        break;
                    case "grouped":
                        $uploadedProduct = $groupedProductRepository->createProduct(request()->all(), $imageZipName);

                        break;
                    case "booking":
                        $uploadedProduct = $bookingProductRepository->createProduct(request()->all(), $imageZipName);

                        break;
                    case "bundle":
                        $uploadedProduct = $bundledProductRepository->createProduct(request()->all(), $imageZipName);

                        break;
                    case "configurable" OR "variant":
                        $uploadedProduct = $configurableProductRepository->createProduct(request()->all(), $imageZipName, $product);

                        break;
                }

                if (! empty($uploadedProduct)) {
                    $isError = true;
                    $errorArray['error'] = json_encode($uploadedProduct['error']);
                    $records[$key] = (object) array_merge($errorArray, $arr);
                }
            }
        }

        if ($isError) {
            Excel::store(new DataGridExport(collect($records)), 'error-csv-file/'.$this->dataFlowProfileRecord->profiler->id.'/'.Str::random(10).'.csv');
        }

    }
}
