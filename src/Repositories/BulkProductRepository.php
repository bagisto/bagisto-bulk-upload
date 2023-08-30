<?php

namespace Webkul\Bulkupload\Repositories;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Illuminate\Container\Container as App;
use Webkul\Core\Eloquent\Repository;
use Webkul\Product\Models\ProductAttributeValue;
use Webkul\Product\Repositories\{ProductRepository, ProductAttributeValueRepository, ProductInventoryRepository, ProductImageRepository};

class BulkProductRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @param  \Webkul\Product\Repositories\ProductRepository  $productRepository
     * @param  \Webkul\Product\Repositories\ProductAttributeValueRepository  $productAttributeValueRepository
     * @param  \Webkul\Product\Repositories\ProductInventoryRepository  $productInventoryRepository
     * @param  \Webkul\Product\Repositories\ProductImageRepository  $productImageRepository
     * @return void
     */
    public function __construct(
        protected ProductRepository $productRepository,
        protected ProductAttributeValueRepository $productAttributeValueRepository,
        protected ProductInventoryRepository $productInventoryRepository,
        protected ProductImageRepository $productImageRepository,
        App $app)
    {

        parent::__construct($app);
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Webkul\Product\Contracts\Product';
    }

    /**
     * update configurable product variants
     *
     * @param array $data
     * @param integer $id
     * @param string $attribute
     *
     * @return mixed
     */
    public function productRepositoryUpdateForVariants(array $data, $id, $attribute = "id")
    {
        Event::dispatch('catalog.product.update.before', $id);

        $product = $this->find($id);

        if ($product->parent_id && app('Webkul\Product\Type\Configurable')->checkVariantOptionAvailability($data, $product)) {
            $data['parent_id'] = null;
        }

        $product->update($data);

        foreach ($product->attribute_family->custom_attributes as $attribute) {
            if (! isset($data[$attribute->code]) || (in_array($attribute->type, ['date', 'datetime']) && ! $data[$attribute->code])) {
                continue;
            }

            if (in_array($attribute->type, ['multiselect', 'checkbox'])) {
                $data[$attribute->code] = implode(",", $data[$attribute->code]);
            }

            if (in_array($attribute->type, ['image', 'file'])) {
                $dir = 'product';
                $data[$attribute->code] = gettype($data[$attribute->code]) == 'object' ? request()->file($attribute->code)->store($dir) : null;
            }

            $attributeValue = $this->productAttributeValueRepository->updateOrCreate([
                'product_id'   => $product->id,
                'attribute_id' => $attribute->id,
                'channel'      => $attribute->value_per_channel ? $data['channel'] : null,
                'locale'       => $attribute->value_per_locale ? $data['locale'] : null
            ], [
                ProductAttributeValue::$attributeTypeFields[$attribute->type] => $data[$attribute->code]
            ]);

            if (in_array($attribute->type, ['image', 'file'])) {
                Storage::delete($attributeValue->text_value);
            }
        }

        if (request()->route()->getName() != 'admin.catalog.products.massupdate') {
            $product->categories()->sync($data['categories'] ?? []);
            $product->up_sells()->sync($data['up_sell'] ?? []);
            $product->cross_sells()->sync($data['cross_sell'] ?? []);
            $product->related_products()->sync($data['related_products'] ?? []);

            $previousVariantIds = $product->variants->pluck('id');
            if (isset($data['variants'])) {
                foreach ($data['variants'] as $variantId => $variantData) {
                    if (str_contains($variantId, 'variant_')) {
                        $permutation = collect($product->super_attributes)->mapWithKeys(function ($superAttribute) use ($variantData) {
                            return [$superAttribute->id => $variantData[$superAttribute->code]];
                        });

                        $this->productRepository->createVariant($product, $permutation, $variantData);
                    } elseif (is_numeric($index = $previousVariantIds->search($variantId))) {
                        $previousVariantIds->forget($index);

                        $variantData['channel'] = $data['channel'];
                        $variantData['locale'] = $data['locale'];

                        $this->productRepository->updateVariant($variantData, $variantId);
                    }
                }
            }

            $this->productInventoryRepository->saveInventories($data, $product);
            $this->productImageRepository->uploadImages($data, $product);
        }

        if (isset($data['channels'])) {
            $product['channels'] = $data['channels'];
        }

        Event::dispatch('catalog.product.update.after', $product);

        return $product;
    }
}
