# Bagisto Bulk Upload

By using this add-on, the admin can mass/bulk upload products of all types: simple, configurable, virtual, grouped, bundle, downloadable, booking.

It packs in lots of demanding features that allows your business to scale in no time:

- Product can be upload by CSV / XLS files.
- Data profile creation feature for admin.
- Each attribute has a different column.
- Image attachment with the product within CSV/XLS.
- If there is any error in the CSV / XLS file, then products will not be uploaded and hence customer/admin will come to know about the error.

## Requirements:

- **Bagisto**: v1.2.0

## Installation with composer:
- Run the following commands
```
composer require bagisto/bulk-upload
```
- Goto config/concord.php file and add following line under 'modules'
```php
\Webkul\Bulkupload\Providers\ModuleServiceProvider::class
```

- Run these commands below to complete the setup
```
composer dump-autoload
```

```
php artisan migrate
php artisan storage:link
php artisan route:cache
php artisan vendor:publish
```
-> Press 0 and then press enter to publish all assets and configurations.

## Installation without composer:

- Unzip the respective extension zip and then merge "packages" and "storage" folders into project root directory.
- Goto config/app.php file and add following line under 'providers'

```
Webkul\Bulkupload\Providers\BulkUploadServiceProvider::class
```

- Goto composer.json file and add following line under 'psr-4'

```
"Webkul\\Bulkupload\\": "packages/Webkul/Bulkupload/src"
```

- Goto config/concord.php file and add following line under 'modules'

```php
\Webkul\Bulkupload\Providers\ModuleServiceProvider::class
```

- Run these commands below to complete the setup

```
composer dump-autoload
```

```
php artisan migrate
```

```
php artisan storage:link
```

```
php artisan route:cache
```

```
php artisan vendor:publish

-> Press 0 and then press enter to publish all assets and configurations.
```

> That's it, now just execute the project on your specified domain.
