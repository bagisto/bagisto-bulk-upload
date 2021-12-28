<?php

namespace Webkul\Bulkupload\Repositories\Products;

use Storage;
use Webkul\Core\Eloquent\Repository;
use Illuminate\Container\Container as App;
use Webkul\Admin\Imports\DataGridImport;
use Illuminate\Support\Facades\Validator;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Bulkupload\Repositories\ImportProductRepository;
use Webkul\Product\Repositories\ProductFlatRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Bulkupload\Repositories\Products\HelperRepository;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Bulkupload\Repositories\ProductImageRepository;
use Webkul\Product\Repositories\ProductCustomerGroupPriceRepository;

class BookingProductRepository extends Repository
{
    /**
     * ImportProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\ImportProductRepository
     */
    protected $importProductRepository;

    /**
     * CategoryRepository object
     *
     * @var \Webkul\Category\Repositories\CategoryRepository
     */
    protected $categoryRepository;

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
     * AttributeFamilyRepository object
     *
     * @var \Webkul\Attribute\Repositories\AttributeFamilyRepository
     */
    protected $attributeFamilyRepository;

    /**
     * HelperRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\Products\HelperRepository
     */
    protected $helperRepository;

    /**
     * ProductImageRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\ProductImageRepository
     */
    protected $productImageRepository;

    /**
     * AttributeOptionRepository object
     *
     * @var \Webkul\Attribute\Repositories\AttributeOptionRepository
     */
    protected $attributeOptionRepository;

    /**
     * Create a new repository instance.
     *
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     * @param  \Webkul\Attribute\Repositories\AttributeOptionRepository  $attributeOptionRepository
     * @param  \Webkul\Category\Repositories\CategoryRepository  $categoryRepository
     * @param  \Webkul\Product\Repositories\ProductFlatRepository  $productFlatRepository
     * @param  \Webkul\Product\Repositories\ProductRepository  $productRepository
     * @param  \Webkul\Attribute\Repositories\AttributeFamilyRepository  $attributeFamilyRepository
     * @param  \Webkul\Bulkupload\Repositories\Products\HelperRepository  $helperRepository
     * @param  \Webkul\Bulkupload\Repositories\ProductImageRepository  $productImageRepository
     *
     * @return void
     */
    public function __construct(
        ImportProductRepository $importProductRepository,
        CategoryRepository $categoryRepository,
        ProductFlatRepository $productFlatRepository,
        ProductRepository $productRepository,
        AttributeFamilyRepository $attributeFamilyRepository,
        HelperRepository $helperRepository,
        ProductImageRepository $productImageRepository,
        AttributeOptionRepository $attributeOptionRepository
    )
    {
        $this->importProductRepository = $importProductRepository;

        $this->categoryRepository = $categoryRepository;

        $this->productFlatRepository = $productFlatRepository;

        $this->productRepository = $productRepository;

        $this->productImageRepository = $productImageRepository;

        $this->attributeOptionRepository = $attributeOptionRepository;

        $this->attributeFamilyRepository = $attributeFamilyRepository;

        $this->helperRepository = $helperRepository;
    }

    /*
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Webkul\Product\Contracts\Product';
    }

    /**
     * create & update booking-type product
     *
     * @param array $requestData
     * @param array $imageZipName
     *
     * @return mixed
     */
    public function createProduct($requestData, $imageZipName)
    {
        try  {
            $dataFlowProfileRecord = $this->importProductRepository->findOneByField
            ('data_flow_profile_id', $requestData['data_flow_profile_id']);

            $csvData = (new DataGridImport)->toArray($dataFlowProfileRecord->file_path)[0];

            if ($requestData['totalNumberOfCSVRecord'] < 1000) {
                $processCSVRecords = $requestData['totalNumberOfCSVRecord']/($requestData['totalNumberOfCSVRecord']/10);
            } else {
                $processCSVRecords = $requestData['totalNumberOfCSVRecord']/($requestData['totalNumberOfCSVRecord']/100);
            }

            $uptoProcessCSVRecords = (int)$requestData['countOfStartedProfiles'] + 10;
            $processRecords = (int)$requestData['countOfStartedProfiles'] + (int)$requestData['numberOfCSVRecord'];

            $slot = [];

            if ($requestData['numberOfCSVRecord'] > $processCSVRecords) {
                for ($i = $requestData['countOfStartedProfiles']; $i < $uptoProcessCSVRecords; $i++) {
                    $invalidateProducts = $this->store($csvData[$i], $i, $dataFlowProfileRecord, $requestData, $imageZipName);

                    if (isset($invalidateProducts) && !empty($invalidateProducts)) {
                        return $invalidateProducts;
                    }
                }
            } else if ($requestData['numberOfCSVRecord'] <= 10) {
                for ($i = $requestData['countOfStartedProfiles']; $i < $processRecords; $i++) {
                    $invalidateProducts = $this->store($csvData[$i], $i, $dataFlowProfileRecord, $requestData, $imageZipName);

                    if (isset($invalidateProducts) && !empty($invalidateProducts)) {
                        return $invalidateProducts;
                    }
                }
            }

            if ($requestData['numberOfCSVRecord'] > 10) {
                $remainDataInCSV = (int)$requestData['numberOfCSVRecord'] - (int)$processCSVRecords;
            } else {
                $remainDataInCSV = 0;

                if($requestData['errorCount'] > 0) {
                    $uptoProcessCSVRecords = $requestData['totalNumberOfCSVRecord'] - $requestData['errorCount'];
                } else {
                    $uptoProcessCSVRecords = $processRecords;
                }
            }

            $requestData['countOfStartedProfiles'] = $i;

            $dataToBeReturn = [
                'remainDataInCSV' => $remainDataInCSV,
                'productsUploaded' => $uptoProcessCSVRecords,
                'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
            ];

            return $dataToBeReturn;
        } catch(\Exception $e) {
            \Log::error('booking create product log: '. $e->getMessage());

            $categoryError = explode('[' ,$e->getMessage());
            $categorySlugError = explode(']' ,$e->getMessage());
            $requestData['countOfStartedProfiles'] =  $i + 1;
            $productsUploaded = $i - $requestData['errorCount'];

            if ($requestData['numberOfCSVRecord'] != 0) {
                $remainDataInCSV = (int)$requestData['totalNumberOfCSVRecord'] - (int)$requestData['countOfStartedProfiles'];
            } else {
                $remainDataInCSV = 0;
            }

            if ($categoryError[0] == "No query results for model ") {
                $dataToBeReturn = array(
                    'remainDataInCSV' => $remainDataInCSV,
                    'productsUploaded' => $productsUploaded,
                    'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                    'error' => "Invalid Category Slug: " . $categorySlugError[1],
                );
                $categoryError[0] = null;
            } else if (isset($e->errorInfo)) {
                $dataToBeReturn = array(
                    'remainDataInCSV' => $remainDataInCSV,
                    'productsUploaded' => $productsUploaded,
                    'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                    'error' => $e->errorInfo[2],
                );
            } else {
                $dataToBeReturn = array(
                    'remainDataInCSV' => $remainDataInCSV,
                    'productsUploaded' => $productsUploaded,
                    'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                    'error' => $e->getMessage(),
                );
            }

            return $dataToBeReturn;
        }
    }

    /**
     * function to store product
     *
     * @param array $csvData
     * @param integer $i
     * @param \Webkul\Bulkupload\Contracts\ImportProduct $dataFlowProfileRecord
     * @param array $requestData
     * @param array $imageZipName
     *
     * @return mixed
     */
    public function store($csvData, $i, $dataFlowProfileRecord, $requestData, $imageZipName)
    {
        try {
            $createValidation = $this->helperRepository->createProductValidation($csvData, $i);

            if (isset($createValidation)) {
                return $createValidation;
            }

            request()->request->add(['i' => $i]);

            $productFlatData = $this->productFlatRepository->findWhere(['sku' => $csvData['sku'], 'url_key' => $csvData['url_key']])->first();

            $productData = $this->productRepository->findWhere(['sku' => $csvData['sku']])->first();

            $attributeFamilyData = $this->attributeFamilyRepository->findOneByfield(['name' => $csvData['attribute_family_name']]);

            if (! isset($productFlatData) && empty($productFlatData)) {
                $data['type'] = $csvData['type'];
                $data['attribute_family_id'] = $attributeFamilyData->id;
                $data['sku'] = $csvData['sku'];

                $bookingProductData = $this->productRepository->create($data);
            } else {
                $bookingProductData = $productData;
            }

            unset($data);
            $data = [];
            $attributeCode = [];
            $attributeValue = [];

            //default attributes
            foreach ($bookingProductData->getTypeInstance()->getEditableAttributes()->toArray() as $key => $value) {
                $searchIndex = $value['code'];
                if (array_key_exists($searchIndex, $csvData)) {
                    if (is_null($csvData[$searchIndex])) {
                        continue;
                    }

                    array_push($attributeCode, $searchIndex);

                    if ($searchIndex == "color" || $searchIndex == "size" || $searchIndex == "brand") {
                        $attributeOption = $this->attributeOptionRepository->findOneByField(['admin_name' => ucwords($csvData[$searchIndex])]);

                        array_push($attributeValue, $attributeOption['id']);
                    } else {
                        array_push($attributeValue, $csvData[$searchIndex]);
                    }

                    $data = array_combine($attributeCode, $attributeValue);
                }
            }

            $data['dataFlowProfileRecordId'] = $dataFlowProfileRecord->id;

            $categoryData = explode(',', $csvData['categories_slug']);


            if (is_null($csvData['categories_slug']) || empty($csvData['categories_slug'])) {
                $categoryID = $this->categoryRepository->findBySlugOrFail('root')->id;
            } else {
                foreach ($categoryData as $key => $value) {
                    $categoryID[$key] = $this->categoryRepository->findBySlugOrFail($categoryData[$key])->id;
                }
            }

            $data['categories'] = $categoryID;

            $data['channel'] = core()->getCurrentChannel()->code;

            $dataProfile = app('Webkul\Bulkupload\Repositories\DataFlowProfileRepository')->findOneByfield(['id' => $data['dataFlowProfileRecordId']]);
            $data['locale'] = $dataProfile->locale_code;

            //customerGroupPricing
            if (isset($csvData['customer_group_prices']) && ! empty($csvData['customer_group_prices'])) {
                $data['customer_group_prices'] = json_decode($csvData['customer_group_prices'], true);
                app(ProductCustomerGroupPriceRepository::class)->saveCustomerGroupPrices($data, $simpleproductData);
            }

            //booking product attributes

            if (strtolower($csvData['booking_type']) == "default") {
                $booking = $this->defaultBookingType($csvData);
            } else if (strtolower($csvData['booking_type']) == "appointment") {
                $booking = $this->appointmentBookingType($csvData);
            } else if (strtolower($csvData['booking_type']) == "event") {
                $booking = $this->eventBookingType($csvData);
            } else if (strtolower($csvData['booking_type']) == "rental") {
                $booking = $this->rentalBookingType($csvData);
            } else if (strtolower($csvData['booking_type']) == "table") {
                $booking = $this->tableBookingType($csvData);
            } else {
                \Log::error('booking type not found');
            }

            $data['booking'] = $booking;

            //images
            $individualProductimages = explode(',', $csvData['images']);

            if (isset($imageZipName)) {
                $images = Storage::disk('local')->files('public/imported-products/extracted-images/admin/'.$dataFlowProfileRecord->id.'/'.$imageZipName['dirname'].'/');

                foreach ($images as $imageArraykey => $imagePath) {
                    $imageName = explode('/', $imagePath);

                    if (in_array(last($imageName), preg_replace('/[\'"]/', '',$individualProductimages))) {
                    $data['images'][$imageArraykey] = $imagePath;
                }
                }
            } else if (isset($csvData['images'])) {
                foreach ($individualProductimages as $imageArraykey => $imageURL)
                {
                    if (filter_var(trim($imageURL), FILTER_VALIDATE_URL)) {
                        $imagePath = storage_path('app/public/imported-products/extracted-images/admin/'.$dataFlowProfileRecord->id);

                        if (!file_exists($imagePath)) {
                            mkdir($imagePath, 0777, true);
                        }

                        $imageFile = $imagePath.'/'.basename($imageURL);

                        file_put_contents($imageFile, file_get_contents(trim($imageURL)));

                        $data['images'][$imageArraykey] = $imageFile;
                    }
                }
            }

            //  to check validation
            $validationRules = $this->helperRepository->validateCSV($requestData['data_flow_profile_id'], $data, $dataFlowProfileRecord, $bookingProductData);

            $csvValidator = Validator::make($data, $validationRules);

            if ($csvValidator->fails()) {
                $errors = $csvValidator->errors()->getMessages();

                $this->helperRepository->deleteProductIfNotValidated($bookingProductData->id);

                foreach($errors as $key => $error) {
                    if ($error[0] == "The url key has already been taken.") {
                        $errorToBeReturn[] = "The url key " . $data['url_key'] . " has already been taken";
                    } else {
                        $errorToBeReturn[] = str_replace(".", "", $error[0]). " for sku " . $data['sku'];
                    }
                }

                $requestData['countOfStartedProfiles'] =  $i + 1;

                $productsUploaded = $i - $requestData['errorCount'];

                if ($requestData['numberOfCSVRecord'] != 0) {
                    $remainDataInCSV = (int)$requestData['totalNumberOfCSVRecord'] - (int)$requestData['countOfStartedProfiles'];
                } else {
                    $remainDataInCSV = 0;
                }

                $dataToBeReturn = array(
                    'remainDataInCSV' => $remainDataInCSV,
                    'productsUploaded' => $productsUploaded,
                    'countOfStartedProfiles' => $requestData['countOfStartedProfiles'],
                    'error' => $errorToBeReturn,
                );

                return $dataToBeReturn;
            }

            request()->request->add(['booking' => $booking]);

            $this->productRepository->update($data, $bookingProductData->id);

            if (isset($imageZipName)) {
                $this->productImageRepository->bulkuploadImages($data, $bookingProductData, $imageZipName);
            } else if (isset($csvData['images'])) {
                $this->productImageRepository->bulkuploadImages($data, $bookingProductData, $imageZipName = null);
            }
        } catch(\Exception $e) {
            \Log::error('booking product store function'. $e->getMessage());
        }
    }

    /**
     * prepare data for default booking-type
     *
     * @param array $data
     *
     * @return mixed
     */
    public function defaultBookingType($data)
    {
        try {
            if (isset($data['booking_slot_from']) && !empty($data['booking_slot_from'])) {
                $slot = $this->prepareDefaultBookingSlots($data);
            }

            if (strtolower($data['booking_type_day_wise']) == "many") {
                $bookingType  = "many";
                $duration = $data['duration'] ?? 45;
                $breakTime = $data['break_time'] ?? 15;
            } else {
                $bookingType  = "one";
            }

            $availableFrom = explode(',', $data['booking_available_from']);

            foreach ($availableFrom as $key => $availableDateTime) {
                $dateFormat = str_replace('/', '-', $availableFrom["0"]);
                $date = date('Y-m-d', strtotime($dateFormat));

                $time = date("H:i:s", strtotime($availableFrom["1"]));

                $from = $date.' '.$time;
            }

            $availableTo = explode(',' , $data['booking_available_to']);

            foreach ($availableTo as $key => $availableDateTime) {
                $dateFormat = str_replace('/', '-', $availableTo["0"]);
                $date = date('Y-m-d', strtotime($dateFormat));

                $time = date("H:i:s", strtotime($availableTo["1"]));

                $to = $date.' '.$time;
            }

            $booking = [
                "type" => "default",
                "location" => $data['booking_location'],
                "qty" => $data['booking_qty'] ?? 0,
                "available_from" => $from,
                "available_to" => $to,
                "booking_type" => $bookingType,
                "duration" => $duration ?? "",
                "break_time" => $breakTime ?? ""
            ];

            if (! empty($slot)) {
                $booking['slots'] = $slot;
            }

            return $booking;
        } catch (\Exception $e) {
            \Log::error('booking defaultBookingType log: '. $e->getMessage());
        }
    }

    /**
     * prepare data for appointment booking-type
     *
     * @param array $data
     *
     * @return mixed
     */
    public function appointmentBookingType($data)
    {
        try {
            if (isset($data['booking_slot_from']) && !empty($data['booking_slot_from'])) {
                $slot = $this->prepareAppointmentBookingSlots($data);
            }

            if ($data['available_every_week'] == "yes") {
                $appointment = [
                    "type" => "appointment",
                    "location" => $data['booking_location'],
                    "qty" => $data['booking_qty'] ?? 0,
                    "available_every_week" => "1",
                    "duration" => $data['duration'] ?? 45,
                    "break_time" => $data['break_time'] ?? 15,
                    "same_slot_all_days" => $data['same_slot_all_days'] ? 1 : 0
                ];
            } else {
                $availableFrom = explode(',', $data['booking_available_from']);

                foreach ($availableFrom as $key => $availableDateTime) {
                    $dateFormat = str_replace('/', '-', $availableFrom["0"]);
                    $date = date('Y-m-d', strtotime($dateFormat));

                    $time = date("H:i:s", strtotime($availableFrom["1"]));

                    $from = $date.' '.$time;
                }

                $availableTo = explode(',' , $data['booking_available_to']);

                foreach ($availableTo as $key => $availableDateTime) {
                    $dateFormat = str_replace('/', '-', $availableTo["0"]);
                    $date = date('Y-m-d', strtotime($dateFormat));

                    $time = date("H:i:s", strtotime($availableTo["1"]));

                    $to = $date.' '.$time;
                }

                $appointment = [
                    "type" => "appointment",
                    "location" => $data['booking_location'],
                    "qty" => $data['booking_qty'] ?? 0,
                    "available_every_week" => "0",
                    "available_from" => $from,
                    "available_to" => $to,
                    "duration" => $data['duration'],
                    "break_time" => $data['break_time'],
                    "same_slot_all_days" => $data['same_slot_all_days'] ? 1 : 0
                ];
            }

            if (! empty($slot)) {
                $appointment['slots'] = $slot;
            }

            return $appointment;
        } catch (\Exception $e) {
            \Log::error('booking appointmentBookingType log: '. $e->getMessage());
        }
    }

    /**
     * prepare data for event booking-type
     *
     * @param array $data
     *
     * @return mixed
     */
    public function eventBookingType($data)
    {
        try {
            if (isset($data['booking_available_from']) && !empty($data['booking_available_to'])) {
                $ticket = $this->prepareEventBookingTickets($data);
            }

            $availableFrom = explode(',', $data['booking_available_from']);

            foreach ($availableFrom as $key => $availableDateTime) {
                $dateFormat = str_replace('/', '-', $availableFrom["0"]);
                $date = date('Y-m-d', strtotime($dateFormat));

                $time = date("H:i:s", strtotime($availableFrom["1"]));

                $from = $date.' '.$time;
            }

            $availableTo = explode(',' , $data['booking_available_to']);

            foreach ($availableTo as $key => $availableDateTime) {
                $dateFormat = str_replace('/', '-', $availableTo["0"]);
                $date = date('Y-m-d', strtotime($dateFormat));

                $time = date("H:i:s", strtotime($availableTo["1"]));

                $to = $date.' '.$time;
            }

            $booking = [
                "type" => "event",
                "location" => $data['booking_location'],
                "available_from" => $from,
                "available_to" => $to,
            ];

            if (! empty($ticket)) {
                $booking['tickets'] = $ticket;
            }

            return $booking;
        } catch (\Exception $e) {
            \Log::error('booking eventBookingType log: '. $e->getMessage());
        }
    }

    /**
     * prepare data for rental booking-type
     *
     * @param array $data
     *
     * @return mixed
     */
    public function rentalBookingType($data)
    {
        try {
            if ($data['available_every_week'] == "no" || empty($data['available_every_week'])) {
                $availableEveryWeek = "0";

                $availableFrom = explode(',', $data['booking_available_from']);

                foreach ($availableFrom as $key => $availableDateTime) {
                    $dateFormat = str_replace('/', '-', $availableFrom["0"]);
                    $date = date('Y-m-d', strtotime($dateFormat));

                    $time = date("H:i:s", strtotime($availableFrom["1"]));

                    $from = $date.' '.$time;
                }

                $availableTo = explode(',' , $data['booking_available_to']);

                foreach ($availableTo as $key => $availableDateTime) {
                    $dateFormat = str_replace('/', '-', $availableTo["0"]);
                    $date = date('Y-m-d', strtotime($dateFormat));

                    $time = date("H:i:s", strtotime($availableTo["1"]));

                    $to = $date.' '.$time;
                }

                $booking['booking_available_from'] = $from;
                $booking['booking_available_to'] = $to;
            } else {
                $availableEveryWeek = "1";
            }

            if ($data['renting_type'] == "daily") {
                $booking = [
                    "type" => "rental",
                    "location" => $data['booking_location'],
                    "qty" => $data['booking_qty'],
                    "available_every_week" => $availableEveryWeek,
                    "renting_type" => "daily",
                    "daily_price" => $data['daily_price'] ?? 0,
                ];
            } else if ($data['renting_type'] == "hourly") {
                $slot = $this->prepareRentalBookingSlots($data);

                $booking = [
                    "type" => "rental",
                    "location" => $data['booking_location'],
                    "qty" => $data['booking_qty'],
                    "available_every_week" => $availableEveryWeek,
                    "renting_type" => "hourly",
                    "hourly_price" => $data['hourly_price'] ?? 0,
                    "same_slot_all_days" => $data['same_slot_all_days']
                ];

                if (! empty($slot)) {
                    $booking['slots'] = $slot;
                }
            } else if ($data['renting_type'] == "daily_hourly") {
                $slot = $this->prepareRentalBookingSlots($data);

                if (trim(strtolower($data['same_slot_all_days'])) == "no" || empty($data['same_slot_all_days'])) {
                    $data['same_slot_all_days'] = null;
                }

                $booking = [
                    "type" => "rental",
                    "location" => $data['booking_location'],
                    "qty" => $data['booking_qty'],
                    "available_every_week" => $availableEveryWeek,
                    "renting_type" => "daily_hourly",
                    "daily_price" => $data['daily_price'] ?? 0,
                    "hourly_price" => $data['hourly_price'] ?? 0,
                    "same_slot_all_days" => $data['same_slot_all_days'] ? 1 : 0
                ];

                if (! empty($slot)) {
                    $booking['slots'] = $slot;
                }
            }

            return $booking;
        } catch (\Exception $e) {
            \Log::error('booking rentalBookingType log: '. $e->getMessage());
        }
    }

    /**
     * prepare data for table booking-type
     *
     * @param array $data
     *
     * @return mixed
     */
    public function tableBookingType($data)
    {
        try {
            if ($data['available_every_week'] == "no" || empty($data['available_every_week'])) {
                $availableEveryWeek = "0";

                $availableFrom = explode(',', $data['booking_available_from']);

                foreach ($availableFrom as $key => $availableDateTime) {
                    $dateFormat = str_replace('/', '-', $availableFrom["0"]);
                    $date = date('Y-m-d', strtotime($dateFormat));

                    $time = date("H:i:s", strtotime($availableFrom["1"]));

                    $from = $date.' '.$time;
                }

                $availableTo = explode(',' , $data['booking_available_to']);

                foreach ($availableTo as $key => $availableDateTime) {
                    $dateFormat = str_replace('/', '-', $availableTo["0"]);
                    $date = date('Y-m-d', strtotime($dateFormat));

                    $time = date("H:i:s", strtotime($availableTo["1"]));

                    $to = $date.' '.$time;
                }

                $booking['booking_available_from'] = $from;
                $booking['booking_available_to'] = $to;
            } else {
                $availableEveryWeek = "1";
            }

            if ($data['price_type'] == "table") {
                $booking['guest_limit'] = $data['guest_limit'] ?? 0;
            }

            if (trim(strtolower($data['same_slot_all_days'])) == "no" || empty($data['same_slot_all_days'])) {
                $data['same_slot_all_days'] = null;
            }

            $slot = $this->prepareRentalBookingSlots($data);

            $booking = [
                "type" => "table",
                "location" => $data['booking_location'],
                "qty" => $data['booking_qty'],
                "available_every_week" => $availableEveryWeek,
                "price_type" => $data['price_type'],
                "duration" => $data['duration'],
                "break_time" => $data['break_time'],
                "prevent_scheduling_before" => $data['prevent_scheduling_before'],
                "same_slot_all_days" => $data['same_slot_all_days'] ? 1 : 0
            ];

            if (! empty($slot)) {
                $booking['slots'] = $slot;
            }

            return $booking;
        } catch (\Exception $e) {
            \Log::error('booking tableBookingType log: '. $e->getMessage());
        }
    }

    /**
     * prepare slots for default booking type
     *
     * @param array $data
     *
     * @return array
     */
    public function prepareDefaultBookingSlots($record)
    {
        try {
            $weekNames = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');

            $bookingSlotFromDay = explode(',', $record['booking_slot_from_day']);
            $bookingSlotFrom = explode(',', $record['booking_slot_from']) ?? "12:00:00";
            $bookingSlotToDay = explode(',', $record['booking_slot_to_day']);
            $bookingSlotTo = explode(',', $record['booking_slot_to']) ?? "13:00:00";

            if (strtolower($record['booking_type_day_wise']) == "one") {
                for ($j = 0; $j < count($bookingSlotFrom); $j++) {
                    $fromDay = array_search(strtolower($bookingSlotFromDay[$j]), $weekNames);
                    $toDay = array_search(strtolower($bookingSlotToDay[$j]), $weekNames);

                    $from = date("H:i:s", strtotime($bookingSlotFrom[$j]));
                    $to = date("H:i:s", strtotime($bookingSlotTo[$j]));

                    $slotter[$j] = [
                        "from_day" => $fromDay,
                        "from" => $from,
                        "to_day" => $toDay,
                        "to" => $to,
                    ];
                }
            } else {
                $defaultBookingStatus = explode(',', $record['default_booking_status']);

                for ($j = 0; $j < count($weekNames); $j++) {
                    $from = date("H:i:s", strtotime($bookingSlotFrom[$j]));
                    $to = date("H:i:s", strtotime($bookingSlotTo[$j]));

                    $slotter[$j] = [
                        "from" => $from,
                        "to" => $to,
                        "status" => $defaultBookingStatus[$j] ?? 0
                    ];
                }
            }

            return $slotter;
        } catch(\Exception $e) {
            \Log::error('booking prepareDefaultBookingSlots log: '. $e->getMessage());
        }
    }

    /**
     * prepare slots for appointment booking type
     *
     * @param array $data
     *
     * @return array
     */
    public function prepareAppointmentBookingSlots($record)
    {
        try {
            $bookingSlotFrom = explode(',', $record['booking_slot_from']);
            $bookingSlotTo = explode(',', $record['booking_slot_to']);

            for($j = 0; $j < count($bookingSlotFrom); $j++) {
                $from = date("H:i:s", strtotime($bookingSlotFrom[$j]));
                $to = date("H:i:s", strtotime($bookingSlotTo[$j]));

                if ($record['same_slot_all_days'] == "no" || empty($record['same_slot_all_days'])) {
                    $weekNames = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');

                    $dayWiseSlots = explode(',', $record['day_wise_slots']);

                    $weekDayNumber = array_search(trim(strtolower($dayWiseSlots[$j])), $weekNames);
                    $slotter[$weekDayNumber] = [
                        $slotArray[$j] = [
                            "from" => $from,
                            "to" => $to,
                        ]
                    ];
                } else {
                    $slotter[$j] = [
                        "from" => $from,
                        "to" => $to,
                    ];
                }
            }

            return $slotter;
        } catch (\Exception $e) {
            \Log::error('booking prepareAppointmentBookingSlots log: '. $e->getMessage());
        }
    }

    /**
     * prepare tickets for event booking type
     *
     * @param array $data
     *
     * @return array
     */
    public function prepareEventBookingTickets($record)
    {
        try {
            $ticketName = explode(',', $record['event_ticket_name']);
            $ticketDescription = explode(',', $record['event_ticket_description']);
            $ticketPrice = explode(',', $record['event_ticket_price']);
            $ticketQty = explode(',', $record['event_ticket_qty']);

            for($j = 0; $j < count($ticketName); $j++) {
                $slotter['ticket_'.$j] = [
                    core()->getCurrentLocale()->code => [
                        "name" => $ticketName[$j],
                        "description" => $ticketDescription[$j]
                    ],
                    "price" => $ticketPrice[$j],
                    "qty" => $ticketQty[$j],
                ];
            }

            return $slotter;
        } catch (\Exception $e) {
            \Log::error('booking prepareEventBookingTickets log: '. $e->getMessage());
        }
    }

    /**
     * prepare slots for rental booking type
     *
     * @param array $data
     *
     * @return array
     */
    public function prepareRentalBookingSlots($record)
    {
        try {
            $bookingSlotFrom = explode(',', $record['booking_slot_from']);
            $bookingSlotTo = explode(',', $record['booking_slot_to']);
            $weekDayNumber = [];
            $slotter = [];

            for($j = 0; $j < count($bookingSlotFrom); $j++) {
                $from = date("H:i:s", strtotime($bookingSlotFrom[$j]));
                $to = date("H:i:s", strtotime($bookingSlotTo[$j]));

                if ($record['same_slot_all_days'] == "no" || empty($record['same_slot_all_days'])) {
                    $weekNames = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');

                    $dayWiseSlots = explode(',', $record['day_wise_slots']);

                    $weekDayNumber = array_search(trim(strtolower($dayWiseSlots[$j])), $weekNames);
                    $slotter[$weekDayNumber] = [
                        $slotArray[$j] = [
                            "from" => $from,
                            "to" => $to,
                        ]
                    ];
                } else {
                    $slotter[$j] = [
                        "from" => $from,
                        "to" => $to,
                    ];
                }
            }

            return $slotter;
        } catch (\Exception $e) {
            \Log::error('booking prepareRentalBookingSlots log: '. $e->getMessage());
        }
    }

    /**
     * prepare slots for table booking type
     *
     * @param array $data
     *
     * @return array
     */
    public function prepareTableBookingSlots($record)
    {
        try {
            $bookingSlotFrom = explode(',', $record['booking_slot_from']);
            $bookingSlotTo = explode(',', $record['booking_slot_to']);
            $weekDayNumber = [];
            $slotter = [];

            for($j = 0; $j < count($bookingSlotFrom); $j++) {
                $from = date("H:i:s", strtotime($bookingSlotFrom[$j]));
                $to = date("H:i:s", strtotime($bookingSlotTo[$j]));

                if ($record['same_slot_all_days'] == "no" || empty($record['same_slot_all_days'])) {
                    $weekNames = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');

                    $dayWiseSlots = explode(',', $record['day_wise_slots']);

                    $weekDayNumber = array_search(trim(strtolower($dayWiseSlots[$j])), $weekNames);
                    $slotter[$weekDayNumber] = [
                        $slotArray[$j] = [
                            "from" => $from,
                            "to" => $to,
                        ]
                    ];
                } else {
                    $slotter[$j] = [
                        "from" => $from,
                        "to" => $to,
                    ];
                }
            }

            return $slotter;
        } catch (\Exception $e) {
            \Log::error('booking prepareTableBookingSlots log: '. $e->getMessage());
        }
    }
}