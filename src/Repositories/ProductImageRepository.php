<?php

namespace Webkul\Bulkupload\Repositories;

use Illuminate\Support\Facades\Storage;
use Webkul\Core\Eloquent\Repository;

/**
 * Product Image Repository
 *
 */
class ProductImageRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Webkul\Product\Contracts\ProductImage';
    }

    /**
     * @param array $data
     * @param mixed $product
     * @return mixed
     */
    public function uploadImages($data, $product)
    {
        $previousImageIds = $product->images()->pluck('id');

        if (isset($data['images'])) {
            foreach ($data['images'] as $imageId => $image) {
                $file = 'images.' . $imageId;
                $dir = 'product/' . $product->id;

                if (str_contains($imageId, 'image_') && request()->hasFile($file)) {
                    $this->create([
                        'path' => request()->file($file)->store($dir),
                        'product_id' => $product->id
                    ]);
                } elseif (is_numeric($index = $previousImageIds->search($imageId)) && request()->hasFile($file)) {
                    if ($imageModel = $this->find($imageId)) {
                        Storage::delete($imageModel->path);
                    }

                    $this->update([
                        'path' => request()->file($file)->store($dir)
                    ], $imageId);
                }
            }
        }

        $previousImageIds->each(function ($imageId) {
            if ($imageModel = $this->find($imageId)) {
                Storage::delete($imageModel->path);
                $this->delete($imageId);
            }
        });
    }

    /**
     * @param array $data
     * @param mixed $product
     * @param array $imageZipName
     *
     * @return mixed
     */
    public function bulkuploadImages($data, $product, $imageZipName)
    {
        if (isset($data['images'])) {
            foreach ($data['images'] as $value) {
                $files = "imported-products/extracted-images/admin/{$data['dataFlowProfileRecordId']}/" . (is_null($imageZipName) ? '' : "{$imageZipName['dirname']}/") . basename($value);
                $destination = "product/{$product->id}/" . basename($value);

                Storage::copy($files, $destination);

                $this->create([
                    'path' => "product/{$product->id}/" . basename($value),
                    'product_id' => $product->id
                ]);
            }
        }
    }
}
