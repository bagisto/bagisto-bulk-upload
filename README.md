# Bagisto Bulk Upload

### 1. Introduction:

Bagisto Bulk Upload is useful for bulk/mass upload of products using CSV or XLS.    
By using this add-on, admin can upload mass/bulk products of types simple, configurable, virtual, grouped, bundle, downloadable, booking.

It packs in lots of demanding features that allows your business to scale in no time:

- Products can be imported to your Bagisto store by uploading CSV or XLS files.
- Data profile creation feature for admin.
- Each attribute has a different column.
- Image attachment with the product within CSV/XLS.
- If there is any error in the CSV / XLS file, then products will not be uploaded and hence customer/admin will come to know about the error.

### 2. Requirements:

- **Bagisto**: v1.1.2

### 3. Installation:

- Unzip the respective extension zip and then merge **packages** and **storage** folders into project root directory.
- Goto config/app.php file and add following line under 'providers'

```php
Webkul\Bulkupload\Providers\BulkUploadServiceProvider::class
```

- Go to your bagisto root directory and open **composer.json**, add following line under 'psr-4'

```php
"Webkul\\Bulkupload\\": "packages/Webkul/Bulkupload"
```

- Open **config/concord.php** and add following line under 'modules'

```php
\Webkul\Bulkupload\Providers\ModuleServiceProvider::class
```

- Run these commands below to complete the setup

```bash
composer dump-autoload
php artisan migrate
php artisan storage:link
php artisan route:cache
php artisan vendor:publish
-> Press 0 and then press enter to publish all assets and configurations.
```

> That's it, now just execute the project on your specified domain.
