<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Illuminate\Container\Container as App;
use Webkul\Core\Eloquent\Repository;
use Illuminate\Support\Facades\Validator;
use Webkul\Product\Models\ProductAttributeValue;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Product\Repositories\ProductFlatRepository;
use Webkul\Bulkupload\Repositories\DataFlowProfileRepository;
use Webkul\Product\Repositories\ProductAttributeValueRepository;

class HelperRepository extends Repository
{
    /**
     * DataFlowProfileRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\DataFlowProfileRepository
     */
    protected $dataFlowProfileRepository;

    /**
     * ProductFlatRepository object
     *
     * @var \Webkul\Product\Repositories\ProductFlatRepository
     */
    protected $productFlatRepository;

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
     * Create a new repository instance.
     *
     * @param  \Webkul\Bulkupload\Repositories\DataFlowProfileRepository  $dataFlowProfileRepository
     * @param  \Webkul\Product\Repositories\ProductAttributeValueRepository  $productAttributeValueRepository
     * @param  \Webkul\Product\Repositories\ProductFlatRepository  $productFlatRepository
     * @param  \Webkul\Product\Repositories\ProductRepository  $productRepository
     * @return void
     */
    public function __construct(
        DataFlowProfileRepository $dataFlowProfileRepository,
        ProductAttributeValueRepository $productAttributeValueRepository,
        ProductFlatRepository $productFlatRepository,
        ProductRepository $productRepository
    )
    {
        $this->dataFlowProfileRepository = $dataFlowProfileRepository;

        $this->productAttributeValueRepository = $productAttributeValueRepository;

        $this->productFlatRepository = $productFlatRepository;

        $this->productRepository = $productRepository;
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
     * @param  \Webkul\Bulkupload\Contracts\ImportProduct  $dataFlowProfileRecord
     * @param  \Webkul\Product\Contracts\Product  $product
     * @return array
     */
    public function validateCSV($dataFlowProfileId, $records, $dataFlowProfileRecord, $product)
    {
        $messages = [];
        $rules = [];

        $profiler = $this->dataFlowProfileRepository->findOneByField('id', $dataFlowProfileId);

        if ($dataFlowProfileRecord) {
            foreach($records as $data) {
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

                    $validations = [];

                    if (! isset($this->rules[$attribute->code])) {
                        array_push($validations, $attribute->is_required ? 'required' : 'nullable');
                    } else {
                        $validations = $this->rules[$attribute->code];
                    }

                    if ($attribute->type == 'text' && $attribute->validation) {
                        array_push($validations,
                            $attribute->validation == 'decimal'
                            ? new \Webkul\Core\Contracts\Validations\Decimal
                            : $attribute->validation
                        );
                    }

                    if ($attribute->type == 'price') {
                        array_push($validations, new \Webkul\Core\Contracts\Validations\Decimal);
                    }

                    if ($attribute->is_unique) {
                        $this->id = $product;

                        array_push($validations, function ($field, $value, $fail) use ($attribute) {
                            $column = ProductAttributeValue::$attributeTypeFields[$attribute->type];

                            if (! $this->productAttributeValueRepository->isValueUnique($this->id, $attribute->id, $column, request($attribute->code))) {
                                $fail('The :attribute has already been taken.');
                            }
                        });
                    }

                    $this->rules[$attribute->code] = $validations;
                }

                $validationCheckForUpdateData = $this->productFlatRepository
                    ->findWhere(['sku' => $records['sku'], 'url_key' => $records['url_key']]);

                if (is_null($validationCheckForUpdateData) || empty($validationCheckForUpdateData)) {
                    $urlKeyUniqueness = "unique:product_flat,url_key";
                    array_push($this->rules["url_key"], $urlKeyUniqueness);
                }

                return $this->rules;
            }
        }
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

            if ( $validateProduct->fails() ) {
                $errors = $validateProduct->errors()->getMessages();

                foreach($errors as $key => $error) {
                    $recordCount = (int)$loopCount + (int)1;

                    $errorToBeReturn[] = str_replace(".", "", $error[0]) . " for record " . $recordCount;
                }

                request()->countOfStartedProfiles =  $loopCount + 1;

                $productsUploaded = $loopCount - request()->errorCount;

                if (request()->numberOfCSVRecord != 0) {
                    $remainDataInCSV = (int)request()->totalNumberOfCSVRecord - (int)request()->countOfStartedProfiles;
                } else {
                    $remainDataInCSV = 0;
                }

                $dataToBeReturn = array(
                    'remainDataInCSV'           => $remainDataInCSV,
                    'productsUploaded'          => $productsUploaded,
                    'countOfStartedProfiles'    => request()->countOfStartedProfiles,
                    'error'                     => $errorToBeReturn,
                );

                return $dataToBeReturn;
            }

            return null;
        } catch(\EXception $e) {
        }
    }
}
