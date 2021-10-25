<?php

namespace Webkul\Bulkupload\Helpers;

use Webkul\Bulkupload\Repositories\ImportProductRepository;
use Webkul\Bulkupload\Repositories\DataFlowProfileRepository;

class ImportProduct
{
    /**
     * ImportProductRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\ImportProductRepository
     *
     */
    protected $importProductRepository;

    /**
     * DataFlowProfileRepository object
     *
     * @var \Webkul\Bulkupload\Repositories\DataFlowProfileRepository
     *
     */
    protected $dataFlowProfileRepository;

     /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     * @param  \Webkul\Bulkupload\Repositories\DataFlowProfileRepository  $dataFlowProfileRepository
     *
     * @return void
     */
    public function __construct(
        ImportProductRepository $importProductRepository,
        DataFlowProfileRepository $dataFlowProfileRepository
    )
    {
        $this->importProductRepository = $importProductRepository;

        $this->dataFlowProfileRepository = $dataFlowProfileRepository;
    }

    /**
     * store import products for profile execution
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        request()->validate ([
            'attribute_family'  => 'required',
            'file_path'         => 'required',
            'image_path'        => 'mimetypes:application/zip|max:10000',
            'data_flow_profile' => 'required',
        ]);

        $valid_extension = ['csv', 'xls', 'xlsx'];
        $valid_image_extension = ['zip', 'rar'];

        $imageDir = 'imported-products/admin/images';
        $fileDir = 'imported-products/admin/files';
        $linkFilesDir = 'imported-products/admin/link-files';
        $linkSampleFilesDir = 'imported-products/admin/link-sample-files';
        $sampleFileDir = 'imported-products/admin/sample-file';

        $attribute_family_id = request()->attribute_family;
        $data_flow_profile_id = request()->data_flow_profile;

        $image = request()->file('image_path');
        $file = request()->file('file_path');
        $linkFiles = request()->file('link_files');
        $linkSampleFiles = request()->file('link_sample_files');
        $sampleFile = request()->file('sample_file');

        if (! isset($image)) {
            $image = '';
        }

        if (request()->is_downloadable) {
            $product['is_downloadable'] = 1;

            if (! empty($linkFiles) && in_array($linkFiles->getClientOriginalExtension(), $valid_image_extension)) {
                $uploadedLinkFiles = $linkFiles->storeAs($linkFilesDir, uniqid().'.'.$linkFiles->getClientOriginalExtension());

                $product['upload_link_files'] = $uploadedLinkFiles;
            } else {
                session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

                return redirect()->route('admin.bulk-upload.index');
            }

            if (request()->is_link_have_sample) {
                $product['is_links_have_samples'] = 1;

                if (in_array($linkSampleFiles->getClientOriginalExtension(), $valid_image_extension)) {
                    $uploadedLinkSampleFiles = $linkSampleFiles->storeAs($linkSampleFilesDir, uniqid().'.'.$linkSampleFiles->getClientOriginalExtension());

                    $product['upload_link_sample_files'] = $uploadedLinkSampleFiles;
                } else {
                    session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

                    return redirect()->route('admin.bulk-upload.index');
                }
            }

            if (request()->is_sample) {
                $product['is_samples_available'] = 1;

                if (in_array($sampleFile->getClientOriginalExtension(), $valid_image_extension)) {
                    $uploadedSampleFiles = $sampleFile->storeAs($sampleFileDir, uniqid().'.'.$sampleFile->getClientOriginalExtension());

                    $product['upload_sample_files'] = $uploadedSampleFiles;
                } else {
                    session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

                    return redirect()->route('admin.bulk-upload.index');
                }
            }
        }

        $product['data_flow_profile_id'] = $data_flow_profile_id;
        $product['attribute_family_id'] = $attribute_family_id;

        if ((! empty($image) && in_array($image->getClientOriginalExtension(), $valid_image_extension)) && (in_array($file->getClientOriginalExtension(), $valid_extension))) {
            $uploadedImage = $image->storeAs($imageDir, uniqid().'.'.$image->getClientOriginalExtension());
            
            $product['image_path'] = $uploadedImage;

            $uploadedFile = $file->storeAs($fileDir, uniqid().'.'.$file->getClientOriginalExtension());

            $product['file_path'] = $uploadedFile;
        } else if ( empty($image) && (in_array($file->getClientOriginalExtension(), $valid_extension))) {
            $product['image_path'] = '';

            $uploadedFile = $file->storeAs($fileDir, uniqid().'.'.$file->getClientOriginalExtension());

            $product['file_path'] = $uploadedFile;
        } else {
            session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

            return redirect()->route('admin.bulk-upload.index');
        }

        $data = $this->importProductRepository->findOneByField('data_flow_profile_id', $data_flow_profile_id);

        if ($data) {
            $this->dataFlowProfileRepository->update(['run_status' => '0'], $data_flow_profile_id);

            $this->importProductRepository->Update($product, $data->id);

            session()->flash('success',trans('bulkupload::app.admin.bulk-upload.messages.update-profile'));

            return redirect()->route('admin.bulk-upload.index');
        } else {
            $importNewProductsStore = $this->importProductRepository->create($product);

            session()->flash('success',trans('bulkupload::app.admin.bulk-upload.messages.profile-saved'));

            return redirect()->route('admin.bulk-upload.index');
        }
    }
}