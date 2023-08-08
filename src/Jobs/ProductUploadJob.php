<?php

namespace Webkul\Bulkupload\Jobs;

use Illuminate\Bus\{Batchable, Queueable};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class ProductUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /**
     * Create a new job instance.
     * @param  array $chunk
     */
    public function __construct(
        protected $data,
        protected $imageZipName,
        protected $dataFlowProfileRecord,
        protected $chunk,
    )
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $simpleProductRepository = app('Webkul\Bulkupload\Repositories\Products\SimpleProductRepository');

        foreach($this->chunk as $data) {
            foreach($data as $arr) {
dd($arr);
                switch($csvData[$i]['type']) {
                    case "simple":
                        $simpleProduct = $this->simpleProductRepository->createProduct(request()->all(), $imageZipName, $dataFlowProfileRecord, $csvData);

                    case "virtual":
                        $virtualProduct = $this->virtualProductRepository->createProduct(request()->all(), $imageZipName);

                    case "downloadable":
                        $downloadableProduct =  $this->downloadableProductRepository->createProduct(request()->all(), $imageZipName);

                    case "grouped":
                        $groupedProduct = $this->groupedProductRepository->createProduct(request()->all(), $imageZipName);

                    case "booking":
                        $bookingProduct = $this->bookingProductRepository->createProduct(request()->all(), $imageZipName);

                    case "bundle":
                        $bundledProduct = $this->bundledProductRepository->createProduct(request()->all(), $imageZipName);

                    case "configurable" OR "variant":
                        $configurableProduct = $this->configurableProductRepository->createProduct(request()->all(), $imageZipName, $product);

                }

                $simpleProductRepository->createProductFromCommand($this->data, $this->imageZipName, $this->dataFlowProfileRecord, $arr);
            }
        }
    }
}
