<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Illuminate\Support\Facades\Validator;
use Webkul\Core\Eloquent\Repository;
use Webkul\Product\Models\ProductAttributeValue;
use Webkul\Product\Repositories\{ProductRepository, ProductFlatRepository, ProductAttributeValueRepository};

class HelperRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @param  \Webkul\Product\Repositories\ProductRepository  $productRepository
     * @param  \Webkul\Product\Repositories\ProductFlatRepository  $productFlatRepository
     * @param  \Webkul\Product\Repositories\ProductAttributeValueRepository  $productAttributeValueRepository
     * @return void
     */
    public function __construct(
        protected ProductRepository $productRepository,
        protected ProductFlatRepository $productFlatRepository,
        protected ProductAttributeValueRepository $productAttributeValueRepository,
    )
    {
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    function model()
    {
        return 'Webkul\Product\Contracts\Product';
    }

    /**
     * validation Rules for creating product
     *
     * @param integer $dataFlowProfileId
     * @param array $records
     * @param  \Webkul\Product\Contracts\Product  $product
     * @return array
     */
    public function validateCSV($records, $product)
    {
        // Initialize rules with type validation rules
        $this->rules = array_merge($product->getTypeInstance()->getTypeValidationRules(), [
            'sku'                => ['required', 'unique:products,sku,' . $product->id, new \Webkul\Core\Contracts\Validations\Slug],
            'special_price_from' => 'nullable|date',
            'special_price_to'   => 'nullable|date|after_or_equal:special_price_from',
            'special_price'      => ['nullable', new \Webkul\Core\Contracts\Validations\Decimal, 'lt:price'],
        ]);

        foreach ($product->getEditableAttributes() as $attribute) {
            if ($attribute->code == 'sku' || $attribute->type == 'boolean') {
                continue;
            }

            // Initialize validations with required or nullable based on attribute settings
            $validations = [$attribute->is_required ? 'required' : 'nullable'];

            if ($attribute->type == 'text' && $attribute->validation) {
                // Add custom validation rules if applicable
                $validations[] = $attribute->validation == 'decimal' ? new \Webkul\Core\Contracts\Validations\Decimal : $attribute->validation;
            }

            if ($attribute->type == 'price') {
                // Add decimal validation for price attributes
                $validations[] = new \Webkul\Core\Contracts\Validations\Decimal;
            }

            if ($attribute->is_unique) {
                // Add unique validation for unique attributes
                $validations[] = function ($field, $value, $fail) use ($attribute, $product) {
                    $column = ProductAttributeValue::$attributeTypeFields[$attribute->type];
                    if (! $this->productAttributeValueRepository->isValueUnique($product, $attribute->id, $column, request($attribute->code))) {
                        $fail('The :attribute has already been taken.');
                    }
                };
            }

            // Assign validations to the rules array
            $this->rules[$attribute->code] = $validations;
        }

        // Check for URL key uniqueness if not found in update data
        $validationCheckForUpdateData = $this->productFlatRepository
            ->findWhere(['sku' => $records['sku'], 'url_key' => $records['url_key']]);

        if (empty($validationCheckForUpdateData)) {
            $this->rules["url_key"][] = "unique:product_flat,url_key";
        }

        return $this->rules;
    }

    /**
     * delete Product if validation fails
     *
     * @param  integer  $id
     * @return void
     */
    public function deleteProductIfNotValidated($id)
    {
        $this->productRepository->findOrFail($id)->delete();
    }

    /**
     * Validation check for product creation
     *
     * @param  array $record
     * @param  integer $loopCount
     * @return void
     */
    public function createProductValidation($record, $loopCount)
    {
        try {
            $validateProduct = Validator::make($record, [
                'type'                  => 'required',
                'sku'                   => 'required',
                'attribute_family_name' => 'required'
            ]);

            if ($validateProduct->fails()) {
                $errors = $validateProduct->errors()->all();
                $recordCount = (int)$loopCount + 1;

                $errorToBeReturn = array_map(function ($error) use ($recordCount) {
                    return str_replace(".", "", $error) . " for record " . $recordCount;
                }, $errors);

                return ['error' => $errorToBeReturn];
            }

            return null;
        } catch(\EXception $e) {
        }
    }
}
