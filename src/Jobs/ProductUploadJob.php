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
        protected $countCSV,
    )
    {
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // flush session when the new product uploading
        session()->forget('notUploadedProduct');
        session()->forget('uploadedProduct');
        session()->forget('isFileUploadComplete');
        session()->forget('message');

        $simpleProductRepository = app('Webkul\Bulkupload\Repositories\Products\SimpleProductRepository');
        $errorArray = [];
        $records = [];
        $uploadedProduct = [];
        $isError = false;
        $count = 0;
        
        foreach($this->chunk as $data) {
            foreach($data as $key => $arr) {
                $count++;

                $uploadedProduct = $simpleProductRepository->createProduct($this->imageZipName, $this->dataFlowProfileRecord, $arr, $key);
                
                if (! empty($uploadedProduct)) {
                    $isError = true;
                    $errorArray['error'] = json_encode($uploadedProduct['error']);
                    $records[$key] = (object) array_merge($errorArray, $arr);
                    // store validation for products which is not uploads.
                    session()->push('notUploadedProduct', $errorArray);
                }
            }
        }
        // After Uploded Product store success message in session
        if ($this->countCSV == $count) {
            session()->put('message', "CSV Product Successfully Imported");
            session()->put('isFileUploadComplete', true);
        }

        if ($isError) {    

            Excel::store(new DataGridExport(collect($records)), 'error-csv-file/'.$this->dataFlowProfileRecord->profiler->id.'/'.Str::random(10).'.csv');
        }

    }
}
