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
    )
    {
    }

    /**
     * store import products for profile execution
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        // Validation
        request()->validate([
            'attribute_family_id'  => 'required',
            'file_path'            => 'required|mimetypes:application/zip|max:10000',
            'data_flow_profile'    => 'required',
        ]);

        // File and image handling
        $valid_image_extension = ['zip', 'rar'];
        $valid_extension = ['csv', 'xls', 'xlsx'];
        $fileStorePath = 'imported-products/admin/';

        $product = [
            'attribute_family_id'   => request('attribute_family_id'),
            'data_flow_profile_id'  => request('data_flow_profile'),
            'image_path'            => '',
            'file_path'             => '',
        ];

        if (request('is_downloadable')) {
            $product['is_downloadable'] = 1;

            foreach (['link_files', 'link_sample_files', 'sample_file'] as $fileType) {
                $file = request()->file($fileType);

                if (!empty($file) && in_array($file->getClientOriginalExtension(), $valid_image_extension)) {
                    $uploadedFile = $file->storeAs($fileStorePath . $fileType, uniqid() . '.' . $file->getClientOriginalExtension());
                    $product["upload_$fileType"] = $uploadedFile;
                } else {
                    session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.file-format-error'));
                    return redirect()->route('admin.bulk-upload.index');
                }

                if ($fileType === 'sample_file') {
                    $product['is_samples_available'] = 1;
                } elseif ($fileType === 'link_sample_files') {
                    $product['is_links_have_samples'] = 1;
                }
            }
        }

        if (in_array(request()->file('image_path')->getClientOriginalExtension(), $valid_image_extension) &&
            in_array(request()->file('file_path')->getClientOriginalExtension(), $valid_extension)) {
            $uploadedImage = request()->file('image_path')->storeAs($fileStorePath . 'images', uniqid() . '.' . request()->file('image_path')->getClientOriginalExtension());
            $product['image_path'] = $uploadedImage;

            $uploadedFile = request()->file('file_path')->storeAs($fileStorePath . 'files', uniqid() . '.' . request()->file('file_path')->getClientOriginalExtension());
            $product['file_path'] = $uploadedFile;
        } elseif (in_array(request()->file('file_path')->getClientOriginalExtension(), $valid_extension)) {
            $uploadedFile = request()->file('file_path')->storeAs($fileStorePath . 'files', uniqid() . '.' . request()->file('file_path')->getClientOriginalExtension());
            $product['file_path'] = $uploadedFile;
        } else {
            session()->flash('error', trans('bulkupload::app.admin.bulk-upload.messages.file-format-error'));
            return redirect()->route('admin.bulk-upload.index');
        }

        // Update or create data
        $data = $this->importProductRepository->findByField('data_flow_profile_id', request('data_flow_profile'));

        if ($data->isNotEmpty()) {
            $this->dataFlowProfileRepository->update(['run_status' => '0'], request('data_flow_profile'));
            $this->importProductRepository->update($product, $data->first()->id);
            session()->flash('success', trans('bulkupload::app.admin.bulk-upload.messages.update-profile'));
        } else {
            $this->importProductRepository->create($product);
            session()->flash('success', trans('bulkupload::app.admin.bulk-upload.messages.profile-saved'));
        }

        return redirect()->route('admin.bulk-upload.index');
    }
}
