<?php

namespace Webkul\Bulkupload\Repositories;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Webkul\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Webkul\Product\Models\ProductAttributeValue;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Product\Repositories\ProductAttributeValueRepository;
use Webkul\Product\Repositories\ProductInventoryRepository;
use Webkul\Product\Repositories\ProductImageRepository;

class BulkProductRepository extends Repository
{
    /**
     * ProductRepository object
     *
     * @var \Webkul\Product\Repositories\ProductRepository
     */
    protected $productRepository;

    /**
     * ProductAttributeValueRepository object
     *
     * @var \Webkul\Product\Repositories\ProductAttributeValueRepository
     */
    protected $productAttributeValueRepository;

    /**
     * ProductInventoryRepository object
     *
     * @var \Webkul\Product\Repositories\ProductInventoryRepository
     */
    protected $productInventoryRepository;

    /**
     * ProductImageRepository object
     *
     * @var \Webkul\Product\Repositories\ProductImageRepository
     */
    protected $productImageRepository;

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
        ProductRepository $productRepository,
        ProductAttributeValueRepository $productAttributeValueRepository,
        ProductInventoryRepository $productInventoryRepository,
        ProductImageRepository $productImageRepository,
        App $app)
    {
        $this->productAttributeValueRepository = $productAttributeValueRepository;

        $this->productRepository = $productRepository;

        $this->productInventoryRepository = $productInventoryRepository;

        $this->productImageRepository = $productImageRepository;

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

        $configurable = app('Webkul\Product\Type\Configurable');

        if ($product->parent_id && $configurable->checkVariantOptionAvailabiliy($data, $product)) {
            $data['parent_id'] = NULL;
        }

        $product->update($data);

        $attributes = $product->attribute_family->custom_attributes;

        foreach ($attributes as $attribute) {
            if (! isset($data[$attribute->code]) || (in_array($attribute->type, ['date', 'datetime']) && ! $data[$attribute->code]))
                continue;

            if ($attribute->type == 'multiselect' || $attribute->type == 'checkbox') {
                $data[$attribute->code] = implode(",", $data[$attribute->code]);
            }

            if ($attribute->type == 'image' || $attribute->type == 'file') {
                $dir = 'product';
                if (gettype($data[$attribute->code]) == 'object') {
                    $data[$attribute->code] = request()->file($attribute->code)->store($dir);
                } else {
                    $data[$attribute->code] = NULL;
                }
            }

            $attributeValue = $this->productAttributeValueRepository->findOneWhere([
                    'product_id'    => $product->id,
                    'attribute_id'  => $attribute->id,
                    'channel'       => $attribute->value_per_channel ? $data['channel'] : null,
                    'locale'        => $attribute->value_per_locale ? $data['locale'] : null
                ]);

            if (! $attributeValue) {
                $this->productAttributeValueRepository->create([
                    'product_id'    => $product->id,
                    'attribute_id'  => $attribute->id,
                    'value'         => $data[$attribute->code],
                    'channel'       => $attribute->value_per_channel ? $data['channel'] : null,
                    'locale'        => $attribute->value_per_locale ? $data['locale'] : null
                ]);
            } else {
                $this->productAttributeValueRepository->update([
                    ProductAttributeValue::$attributeTypeFields[$attribute->type] => $data[$attribute->code]
                    ], $attributeValue->id
                );

                if ($attribute->type == 'image' || $attribute->type == 'file') {
                    Storage::delete($attributeValue->text_value);
                }
            }
        }

        if (request()->route()->getName() != 'admin.catalog.products.massupdate') {
            if  (isset($data['categories'])) {
                $product->categories()->sync($data['categories']);
            }

            if (isset($data['up_sell'])) {
                $product->up_sells()->sync($data['up_sell']);
            } else {
                $data['up_sell'] = [];
                $product->up_sells()->sync($data['up_sell']);
            }

            if (isset($data['cross_sell'])) {
                $product->cross_sells()->sync($data['cross_sell']);
            } else {
                $data['cross_sell'] = [];
                $product->cross_sells()->sync($data['cross_sell']);
            }

            if (isset($data['related_products'])) {
                $product->related_products()->sync($data['related_products']);
            } else {
                $data['related_products'] = [];
                $product->related_products()->sync($data['related_products']);
            }

            $previousVariantIds = $product->variants->pluck('id');
            if (isset($data['variants'])) {
                foreach ($data['variants'] as $variantId => $variantData) {
                    if (str_contains($variantId, 'variant_')) {
                        $permutation = [];
                        foreach ($product->super_attributes as $superAttribute) {
                            $permutation[$superAttribute->id] = $variantData[$superAttribute->code];
                        }

                        $this->productRepository>createVariant($product, $permutation, $variantData);
                    } else {
                        if (is_numeric($index = $previousVariantIds->search($variantId))) {
                            $previousVariantIds->forget($index);
                        }

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