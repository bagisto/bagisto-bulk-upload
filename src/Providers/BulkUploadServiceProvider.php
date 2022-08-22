<?php

namespace Webkul\Bulkupload\Providers;

use Illuminate\Support\ServiceProvider;

class BulkUploadServiceProvider extends ServiceProvider
{
    public function boot()
    {
        include __DIR__ . '/../Http/admin-routes.php';

        $this->app->register(ModuleServiceProvider::class);
        $this->app->register(EventServiceProvider::class);

        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'bulkupload');

        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'bulkupload');

        $this->publishes([
            __DIR__ . '/../../publishable/assets' => public_path('themes/default/assets'),
        ], 'public');

        $this->publishes([
            __DIR__ . '/../Resources/views/admin/bulk-upload/layouts/nav-aside.blade.php' => resource_path('views/vendor/admin/layouts/nav-aside.blade.php'),
        ]);

        view()->composer(['bulkupload::admin.bulk-upload.upload-files.index'], function ($view) {
            $items = [];

            foreach (config('product_types') as $item) {
                $item['children'] = [];

                array_push($items, $item);
            }

            $types = core()->sortItems($items);

            $view->with('productTypes', $types);
        });
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfig();
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/system.php', 'core'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/admin-menu.php', 'menu.admin'
        );
    }
}
