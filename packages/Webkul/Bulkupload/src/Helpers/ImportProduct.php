<?php

namespace Webkul\Bulkupload\Helpers;

use Webkul\Bulkupload\Repositories\{ImportProductRepository, DataFlowProfileRepository};

class ImportProduct
{
     /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Bulkupload\Repositories\ImportProductRepository  $importProductRepository
     * @param  \Webkul\Bulkupload\Repositories\DataFlowProfileRepository  $dataFlowProfileRepository
     *
     * @return void
     */
    public function __construct(
        protected ImportProductRepository $importProductRepository,
        protected DataFlowProfileRepository $dataFlowProfileRepository
    ) {}

    /**
     * store import products for profile execution
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        $valid_extension = ['csv', 'xls', 'xlsx'];
        $valid_image_extension = ['zip', 'rar'];

        $imageDir = 'imported-products/admin/images';
        $fileDir = 'imported-products/admin/files';
        $linkFilesDir = 'imported-products/admin/link-files';
        $linkSampleFilesDir = 'imported-products/admin/link-sample-files';
        $sampleFileDir = 'imported-products/admin/sample-file';
     
        if (request()->has('is_downloadable')) {
            $product['is_downloadable'] = 1;

            if (
                request()->hasFile('link_files') 
                && ! in_array(request()->file('link_files')->getClientOriginalExtension(), $valid_image_extension)
            ) {
                $uploadedLinkFiles = request()->file('link_files')->storeAs($linkFilesDir, uniqid() . '.' . request()->file('link_files')->getClientOriginalExtension());

                $product['upload_link_files'] = $uploadedLinkFiles;
            } else {
                session()->flash('error', __('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

                return redirect()->route('admin.bulk-upload.index');
            }

            if (request()->input('is_link_have_sample')) {
                $product['is_links_have_samples'] = 1;

                if (in_array(request()->file('link_sample_files')->getClientOriginalExtension(), $valid_image_extension)) {
                    $uploadedLinkSampleFiles = request()->file('link_sample_files')->storeAs($linkSampleFilesDir, uniqid().'.'.request()->file('link_sample_files')->getClientOriginalExtension());

                    $product['upload_link_sample_files'] = $uploadedLinkSampleFiles;
                } else {
                    session()->flash('error', __('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

                    return redirect()->route('admin.bulk-upload.index');
                }
            }

            if (request()->input('is_sample')) {
                $product['is_samples_available'] = 1;

                if (in_array(request()->file('sample_file')->getClientOriginalExtension(), $valid_image_extension)) {
                    $uploadedSampleFiles = request()->file('sample_file')->storeAs($sampleFileDir, uniqid().'.'.request()->file('sample_file')->getClientOriginalExtension());

                    $product['upload_sample_files'] = $uploadedSampleFiles;
                } else {
                    session()->flash('error', __('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

                    return redirect()->route('admin.bulk-upload.index');
                }
            }
        }

        $product['data_flow_profile_id'] = request()->input('data_flow_profile');
        $product['attribute_family_id'] = request()->input('attribute_family');

        if (
            request()->hasFile('image_path') 
            && in_array(request()->file('image_path')->getClientOriginalExtension(), $valid_image_extension)
            && in_array(request()->file('file_path')->getClientOriginalExtension(), $valid_extension)
        ) {
            $uploadedImage = request()->file('image_path')->storeAs($imageDir, uniqid().'.'.request()->file('image_path')->getClientOriginalExtension());
            
            $product['image_path'] = $uploadedImage;

            $uploadedFile = request()->file('file_path')->storeAs($fileDir, uniqid() . '.' . request()->file('file_path')->getClientOriginalExtension());

            $product['file_path'] = $uploadedFile;
        } elseif (
            ! request()->hasFile('image_path')
            && in_array(request()->file('file_path')->getClientOriginalExtension(), $valid_extension)
        ) {
            $product['image_path'] = '';

            $uploadedFile = request()->file('file_path')->storeAs($fileDir, uniqid() . '.' . request()->file('file_path')->getClientOriginalExtension());

            $product['file_path'] = $uploadedFile;
        } else {
            session()->flash('error', __('bulkupload::app.admin.bulk-upload.messages.file-format-error'));

            return redirect()->route('admin.bulk-upload.index');
        }

        $importedProduct = $this->importProductRepository->findOneByField('data_flow_profile_id', request()->input('data_flow_profile'));

        if ($importedProduct) {
            $this->dataFlowProfileRepository->update(['run_status' => '0'], request()->input('data_flow_profile'));

            $this->importProductRepository->update($product, $importedProduct->id);

            session()->flash('success', __('bulkupload::app.admin.bulk-upload.messages.update-profile'));

            return redirect()->route('admin.bulk-upload.index');
        }

        $this->importProductRepository->create($product);

        session()->flash('success', __('bulkupload::app.admin.bulk-upload.messages.profile-saved'));

        return redirect()->route('admin.bulk-upload.index');
    }
}