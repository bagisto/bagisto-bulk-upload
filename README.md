# Bagisto Bulk Upload

[![Latest Stable Version](http://poser.pugx.org/bagisto/bagisto-bulk-upload/v)](https://packagist.org/packages/bagisto/bagisto-bulk-upload) [![Total Downloads](http://poser.pugx.org/bagisto/bagisto-bulk-upload/downloads)](https://packagist.org/packages/bagisto/bagisto-bulk-upload) [![Latest Unstable Version](http://poser.pugx.org/bagisto/bagisto-bulk-upload/v/unstable)](https://packagist.org/packages/bagisto/bagisto-bulk-upload) [![License](http://poser.pugx.org/bagisto/bagisto-bulk-upload/license)](https://packagist.org/packages/bagisto/bagisto-bulk-upload)

By using this add-on, the admin can mass/bulk upload products of all types: simple, configurable, virtual, grouped, bundle, downloadable, booking.

It packs in lots of demanding features that allows your business to scale in no time:

- Product can be upload by CSV / XLS files.
- Data profile creation feature for admin.
- Each attribute has a different column.
- Image attachment with the product within CSV/XLS.
- If there is any error in the CSV / XLS file, then products will not be uploaded and hence customer/admin will come to know about the error.

## Requirements:

- **Bagisto**: v1.3.3

## Installation with composer:
- Run the following command
```
composer require bagisto/bagisto-bulk-upload
```

- Goto vendor/bagisto/bagisto-bulkupload and copy the storage folder and merge it into the root of your project.

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
php artisan config:cache
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
