<?php

namespace Webkul\Bulkupload\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class UploadProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload:products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add Catalogs Mixtures';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        app('Webkul\Bulkupload\Http\Controllers\Admin\HelperController')->productUploadFromCommand($this);
    }
}
