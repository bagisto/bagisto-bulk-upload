<?php

namespace Webkul\Bulkupload\Http\Controllers\Admin;

use Webkul\Attribute\Repositories\AttributeFamilyRepository;
use Webkul\Bulkupload\Repositories\BulkProductImporterRepository;
use Webkul\Bulkupload\DataGrids\Admin\BulkProductImporterDataGrid;

class BulkProductImporterController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Attribute\Repositories\AttributeFamilyRepository  $attributeFamilyRepository
     * @param  \Webkul\Bulkupload\Repositories\BulkProductImporterRepository  $bulkProductImporterRepository
     *
     * @return void
     */
    public function __construct(
        protected AttributeFamilyRepository $attributeFamilyRepository,
        protected BulkProductImporterRepository $bulkProductImporterRepository
    )
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        if (request()->ajax()) {
            return app(BulkProductImporterDataGrid::class)->toJson();
        }

        $families = $this->attributeFamilyRepository->all();

        return view('bulkupload::admin.bulk-upload.bulk-product-importer.index', compact('families'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        request()->validate([
            'name'                => 'required|unique:bulk_product_importers',
            'attribute_family_id' => 'required',
            'locale_code'         => 'required'
        ]);

        $this->bulkProductImporterRepository->create(request()->all());

        session()->flash('success',trans('bulkupload::app.admin.bulk-upload.messages.profile-saved'));

        return redirect()->route('admin.bulk-upload.bulk-product-importer.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $families = $this->attributeFamilyRepository->all();

        $profiles = $this->bulkProductImporterRepository->findOrFail($id);

        return view('bulkupload::admin.bulk-upload.bulk-product-importer.edit', compact('families', 'profiles'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update($id)
    {
        $this->validate(request(), [
            'name'                => ['required', 'unique:bulk_product_importers,name,' . $id, new \Webkul\Core\Contracts\Validations\Code],
            'attribute_family_id' => 'required'
        ]);

        $this->bulkProductImporterRepository->update(request()->all(), $id);

        session()->flash('success', trans('bulkupload::app.admin.bulk-upload.messages.update-profile', ['name' => 'Product']));

        return redirect()->route('admin.bulk-upload.bulk-product-importer.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->bulkProductImporterRepository->findOrFail($id)->delete();

        session()->flash('success', trans('bulkupload::app.admin.bulk-upload.messages.profile-deleted'));

        return redirect()->route('admin.bulk-upload.bulk-product-importer.index');
    }

    /**
     * Mass Delete the dataflowprofiles
     *
     * @return \Illuminate\Http\Response
     */
    public function massDestroy()
    {
        $profileIds = explode(',', request()->input('indexes'));

        $this->bulkProductImporterRepository->whereIn('id', $profileIds)->delete();

        session()->flash('success', trans('bulkupload::app.admin.bulk-upload.messages.all-profile-deleted'));

        return redirect()->route('admin.bulk-upload.bulk-product-importer.index');
    }
}
