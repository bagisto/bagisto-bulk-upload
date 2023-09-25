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

        $errorArray = [];
        $records = [];
        $uploadedProduct = [];
        $isError = false;

        foreach($this->chunk as $data) {
            foreach($data as $key => $arr) {

                switch($arr['type']) {
                    case "simple":
                        $uploadedProduct = $simpleProductRepository->createProduct($this->imageZipName, $this->dataFlowProfileRecord, $arr, $key);

                        return response()->json($simpleProduct);

                    case "virtual":
                        $virtualProduct = $this->virtualProductRepository->createProduct(request()->all(), $imageZipName);

                        return response()->json($virtualProduct);
                    case "downloadable":
                        $downloadableProduct =  $this->downloadableProductRepository->createProduct(request()->all(), $imageZipName);

                        return response()->json($downloadableProduct);
                    case "grouped":
                        $groupedProduct = $this->groupedProductRepository->createProduct(request()->all(), $imageZipName);

                        return response()->json($groupedProduct);
                    case "booking":
                        $bookingProduct = $this->bookingProductRepository->createProduct(request()->all(), $imageZipName);

                        return response()->json($bookingProduct);
                    case "bundle":
                        $bundledProduct = $this->bundledProductRepository->createProduct(request()->all(), $imageZipName);

                        return response()->json($bundledProduct);
                    case "configurable" OR "variant":
                        $configurableProduct = $this->configurableProductRepository->createProduct(request()->all(), $imageZipName, $product);

                        return response()->json($configurableProduct);
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
