<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Webkul\Core\Eloquent\Repository as BaseRepository;
use Webkul\Attribute\Repositories\{AttributeFamilyRepository, AttributeOptionRepository};
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Inventory\Repositories\InventorySourceRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Bulkupload\Repositories\{ImportProductRepository, ProductImageRepository};
use Webkul\Bulkupload\Repositories\Products\HelperRepository;

abstract class Repository extends BaseRepository
{
    /**
     * Create a new repository instance.
     *
     * @param  \Webkul\Attribute\Repositories\AttributeFamilyRepository  $attributeFamilyRepository
     * @param  \Webkul\Attribute\Repositories\AttributeOptionRepository  $attributeOptionRepository
     * @param  \Webkul\Category\Repositories\CategoryRepository  $categoryRepository
     * @param  \Webkul\Product\Repositories\ProductRepository  $productRepository
     * @param  \Webkul\Inventory\Repositories\InventorySourceRepository  $inventorySourceRepository
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     * @param  \Webkul\Bulkupload\Repositories\ProductImageRepository  $productImageRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\HelperRepository  $helperRepository
     *
     * @return void
     */
    public function __construct(
        protected AttributeFamilyRepository $attributeFamilyRepository,
        protected AttributeOptionRepository $attributeOptionRepository,
        protected CategoryRepository $categoryRepository,
        protected ProductRepository $productRepository,
        protected InventorySourceRepository $inventorySourceRepository,
        protected ImportProductRepository $importProductRepository,
        protected ProductImageRepository $productImageRepository,
        protected HelperRepository $helperRepository,
    )
    {
    }
}
