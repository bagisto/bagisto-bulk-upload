@extends('admin::layouts.content')

@section('page_title')
    {{ __('bulkupload::app.admin.bulk-upload.upload-files.index') }}
@stop

@section('content')
    <div class="account-layout">

        <!-- download samples -->
        <accordian :title="'{{ __('bulkupload::app.admin.bulk-upload.upload-files.sample-file') }}'" :active="true">
            <div slot="body">
                <div class="import-product">
                    <form
                        action="{{ route('admin.bulk-upload.upload-file.download-sample-files') }}"
                        method="post"
                    >
                        <div class="account-table-content">
                            @csrf

                            <div class="control-group" :class="[errors.has('download_sample') ? 'has-error' : '' ]">
                                <label for="download_sample" class="required">
                                    {{ __('bulkupload::app.admin.bulk-upload.upload-files.download-sample') }}
                                </label>

                                <select class="control" id="download-sample" name="download_sample">
                                    <option value="">
                                        {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                                    </option>

                                    @foreach(config('product_types') as $key => $productType)
                                        <option value="{{ $key }}-product-upload.csv">
                                            {{ __('bulkupload::app.admin.bulk-upload.upload-files.csv-file', ['filetype' => ucwords($key) ]) }}
                                        </option>

                                        <option value="{{ $key }}-product-upload.xlsx">
                                            {{ __('bulkupload::app.admin.bulk-upload.upload-files.xls-file', ['filetype' => ucwords($key) ]) }}
                                        </option>
                                    @endforeach
                                </select>

                                <span class="control-error" v-if="errors.has('download_sample')">
                                    @{{ errors.first('download_sample') }}
                                </span>
                            </div>

                            <div class="mt-10">
                                <button type="submit" class="btn btn-lg btn-primary">
                                    {{ __('bulkupload::app.admin.bulk-upload.upload-files.download') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </accordian>

        <!-- Import New products -->
        <accordian :title="'{{ __('bulkupload::app.admin.bulk-upload.upload-files.import-products') }}'" :active="true">
            <div slot="body">
                <div class="import-new-products">
                    <form
                        method="POST"
                        action="{{ route('admin.bulk-upload.upload-file.import-products-file') }}"
                        enctype="multipart/form-data"
                        @submit.prevent="onSubmit"
                    >
                        @csrf

                        <?php $familyId = app('request')->input('family') ?>

                        <div class="page-content">
                            <div class="is_downloadable">
                                <downloadable-input></downloadable-input>
                            </div>

                            <div class="attribute-family">
                                <attribute-family></attribute-family>
                            </div>

                            <div class="control-group" :class="[errors.has('file_path') ? 'has-error' : '']">
                                <label for="file_path" class="required">
                                    {{ __('bulkupload::app.admin.bulk-upload.upload-files.file') }}
                                </label>

                                <input type="file" class="control" name="file_path" id="file">

                                <span class="control-error" v-if="errors.has('file_path')">
                                    @{{ errors.first('file_path') }}
                                </span>
                            </div>

                            <div class="control-group {{ $errors->first('image_path') ? 'has-error' :'' }}">
                                <label for="image_path">
                                    {{ __('bulkupload::app.admin.bulk-upload.upload-files.image') }}
                                </label>

                                <input type="file" class="control" name="image_path" id="image">

                                <span class="control-error">
                                    {{ $errors->first('image_path') }}
                                </span>
                            </div>
                        </div>

                        <div class="page-action">
                            <button type="submit" class="btn btn-lg btn-primary">
                                {{ __('bulkupload::app.admin.bulk-upload.upload-files.save')  }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </accordian>
    </div>
@stop

@push('scripts')
    <script type="text/x-template" id="downloadable-input-template">
        <div>
            <div class="control-group">
                <label for="is_downloadable">
                    {{ __('bulkupload::app.admin.bulk-upload.upload-files.is-downloadable') }}
                </label>

                <input type="checkbox" @click="showOptions()" id="is_downloadable" name="is_downloadable">
            </div>

            <div class="control-group" v-if="linkFiles">
                <label for="link_files" class="required">
                    {{ __('bulkupload::app.admin.bulk-upload.upload-files.upload-link-files') }}
                </label>

                <input type="file" class="control required" name="link_files" id="file">

                <span class="control-error">
                    {{ $errors->first('file_path') }}
                </span>
            </div>

            <div class="control-group" v-if="isLinkSample">
                <label for="is_link_sample">
                    {{ __('bulkupload::app.admin.bulk-upload.upload-files.sample-links') }}
                </label>

                <input type="checkbox" id="is_link_have_sample" @click="showlinkSamples()" name="is_link_have_sample" value="is_link_have_sample" >
            </div>

            <div class="control-group" v-if="linkSampleFiles">
                <label for="link_sample_files" class="required">
                    {{ __('bulkupload::app.admin.bulk-upload.upload-files.upload-link-sample-files') }}
                </label>

                <input type="file" class="control required"  name="link_sample_files" id="file">

                <span class="control-error">
                    {{ $errors->first('file_path') }}
                </span>
            </div>

            <div class="control-group" v-if="isSample">
                <label for="is_sample">
                    {{ __('bulkupload::app.admin.bulk-upload.upload-files.sample-available') }}
                </label>

                <input type="checkbox" id="is_sample" @click="showSamples()" name="is_sample">
            </div>

            <div class="control-group" v-if="sampleFile">
                <label for="sample_file" class="required">
                    {{ __('bulkupload::app.admin.bulk-upload.upload-files.upload-sample-files') }}
                </label>

                <input type="file" class="control required"  name="sample_file" id="file">

                <span class="control-error">
                    {{ $errors->first('file_path') }}
                </span>
            </div>
        </div>
    </script>

    <script type="text/x-template" id="attribute-family-template">
        <div>
            <div class="control-group {{ $errors->first('attribute_family_id') ? 'has-error' :'' }}">
                <label for="attribute_family_id" class="required">
                    {{ __('admin::app.catalog.products.family') }}
                </label>

                <select @change="onChange()" v-model="key" class="control" id="attribute_family_id" name="attribute_family_id" {{ $familyId ? 'disabled' : '' }}>
                    <option value="">
                        {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                    </option>

                    @foreach ($families as $family)
                        <option value="{{ $family->id }}" {{ ($familyId == $family->id || old('attribute_family_id') == $family->id) ? 'selected' : '' }}>
                            {{ $family->name }}
                        </option>
                    @endforeach
                </select>

                @if ($familyId)
                    <input type="hidden" name="attribute_family_id" value="{{ $familyId }}"/>
                @endif

                <span class="control-error">
                    {{ $errors->first('attribute_family_id') }}
                </span>
            </div>

            <div class="control-group {{ $errors->first('bulk_product_importer_id') ? 'has-error' :'' }}">
                <label for="bulk_product_importer_id" class="required">
                    {{ __('bulkupload::app.admin.bulk-upload.bulk-product-importer.index') }}
                </label>

                <select class="control" id="bulk_product_importer_id" name="bulk_product_importer_id">
                    <option value="">
                        {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                    </option>

                    <option v-for="dataflowprofile,index in dataFlowProfiles" :value="dataflowprofile.id">
                        @{{ dataflowprofile.name }}
                    </option>
                </select>

                <span class="control-error">
                    {{ $errors->first('bulk_product_importer_id') }}
                </span>

            </div>
        </div>
    </script>

    <script>
        Vue.component('downloadable-input', {
                template: '#downloadable-input-template',

                data: function() {
                    return {
                        key: "",
                        dataFlowProfiles: [],
                        isLinkSample: false,
                        isSample: false,
                        linkFiles: false,
                        linkSampleFiles: false,
                        sampleFile: false,
                    }
                },

                methods:{
                    showOptions: function() {
                        this.isLinkSample = ! this.isLinkSample;
                        this.isSample = ! this.isSample;
                        this.linkFiles = ! this.linkFiles;

                        this.linkSampleFiles = false;
                        this.sampleFile = false;
                    },

                    showlinkSamples: function() {
                        this.linkSampleFiles = ! this.linkSampleFiles;
                    },

                    showSamples: function() {
                        this.sampleFile = ! this.sampleFile;
                    }
                }
        });

        Vue.component('attribute-family', {
                template: '#attribute-family-template',

                data: function() {
                    return {
                        key: "",
                        dataFlowProfiles: [],
                    }
                },

                methods:{
                    onChange: function() {
                        var uri = "{{ route('admin.bulk-upload.upload-file.get-all-profile') }}"

                        this.$http.get(uri, {
                            params: {
                            'attribute_family_id': this.key,
                            }
                        })
                        .then(response => {
                            this.dataFlowProfiles = response.data.dataFlowProfiles;
                        })
                        .catch(function(error) {
                        });
                    }
                }
        });
    </script>
@endpush
