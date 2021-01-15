@extends('admin::layouts.content')

@section('page_title')
    {{ __('bulkupload::app.admin.bulk-upload.upload-files.index') }}
@stop

@section('content')
    <div class="account-layout">

        <!-- download samples -->
        <accordian :title="'{{ __('bulkupload::app.admin.bulk-upload.upload-files.download-sample') }}'" :active="true">
            <div slot="body">
                <div class="import-product">
                    <form action="{{ route('download-sample-files') }}" method="post">
                        <div class="account-table-content">
                            @csrf
                            <div class="control-group">
                                <select class="control" id="download-sample" name="download_sample">
                                    <option value="">
                                        {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                                    </option>

                                    @foreach($productTypes as $key => $productType)
                                        <option value="{{ $key }}-csv">
                                            {{ __('bulkupload::app.admin.bulk-upload.upload-files.csv-file', ['filetype' => ucwords($key) ]) }}
                                        </option>

                                        <option value="{{ $key }}-xls">
                                            {{ __('bulkupload::app.admin.bulk-upload.upload-files.xls-file', ['filetype' => ucwords($key) ]) }}
                                        </option>
                                    @endforeach
                                </select>

                                <div class="mt-10">
                                    <button type="submit" class="btn btn-lg btn-primary">
                                        {{ __('bulkupload::app.admin.bulk-upload.upload-files.download') }}
                                    </button>
                                </div>
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
                    <form method="POST" action="{{ route('import-new-products-form-submit') }}" enctype="multipart/form-data">
                        @csrf
                        <?php $familyId = app('request')->input('family') ?>

                        <div class="page-content">
                            <div class="is_downloadable">
                                <downloadable-input>
                                </downloadable-input>
                            </div>

                            <div class="attribute-family">
                                <attributefamily></attributefamily>
                            </div>

                            <div class="control-group {{ $errors->first('file_path') ? 'has-error' :'' }}">
                                <label class="required">{{ __('bulkupload::app.admin.bulk-upload.upload-files.file') }} </label>

                                <input type="file" class="control" name="file_path" id="file">

                                <span class="control-error">{{ $errors->first('file_path') }}</span>
                            </div>

                            <div class="control-group {{ $errors->first('image_path') ? 'has-error' :'' }}">
                                <label>{{ __('bulkupload::app.admin.bulk-upload.upload-files.image') }} </label>

                                <input type="file" class="control" name="image_path" id="image">

                                <span class="control-error">{{ $errors->first('image_path') }}</span>
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

            <div class="control-group" v-if="isLinkSample">
                <label for="is_link_sample">{{ __('bulkupload::app.admin.bulk-upload.upload-files.sample-links') }}</label>

                <input type="checkbox" id="is_link_have_sample" @click="showlinkSamples()" name="is_link_have_sample" value="is_link_have_sample" >
            </div>

            <div class="control-group" v-if="isSample">
                <label for="is_sample">{{ __('bulkupload::app.admin.bulk-upload.upload-files.sample-available') }}</label>

                <input type="checkbox" id="is_sample" @click="showSamples()" name="is_sample">
            </div>

            <div class="control-group" v-if="linkFiles">
                <label class="required">{{ __('bulkupload::app.admin.bulk-upload.upload-files.upload-link-files') }}</label>

                <input type="file" class="control required" name="link_files" id="file">

                <span class="control-error">{{ $errors->first('file_path') }}</span>
            </div>

            <div class="control-group" v-if="linkSampleFiles">
                <label class="required">{{ __('bulkupload::app.admin.bulk-upload.upload-files.upload-link-sample-files') }}</label>

                <input type="file" class="control required"  name="link_sample_files" id="file">

                <span class="control-error">{{ $errors->first('file_path') }}</span>
            </div>

            <div class="control-group" v-if="sampleFile">
                <label class="required">{{ __('bulkupload::app.admin.bulk-upload.upload-files.upload-sample-files') }}</label>

                <input type="file" class="control required"  name="sample_file" id="file">

                <span class="control-error">{{ $errors->first('file_path') }}</span>
            </div>

        </div>
    </script>

    <script type="text/x-template" id="attribute-family-template">
        <div>
            <div class="control-group {{ $errors->first('attribute_family') ? 'has-error' :'' }}">
                <label for="attribute_family" class="required">{{ __('admin::app.catalog.products.familiy') }}</label>

                <select @change="onChange()" v-model="key" class="control" id="attribute_family" name="attribute_family" {{ $familyId ? 'disabled' : '' }}>
                    <option value="">
                        {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                    </option>

                    @foreach ($families as $family)
                        <option value="{{ $family->id }}" {{ ($familyId == $family->id || old('attribute_family') == $family->id) ? 'selected' : '' }}>{{ $family->name }}</option>
                    @endforeach
                </select>

                @if ($familyId)
                    <input type="hidden" name="attribute_family" value="{{ $familyId }}"/>
                @endif

                <span class="control-error">{{ $errors->first('attribute_family') }}</span>
            </div>

            <div class="control-group {{ $errors->first('data_flow_profile') ? 'has-error' :'' }}">
                <label for="data-flow-profile" class="required">{{ __('bulkupload::app.admin.bulk-upload.data-flow-profile.index') }}</label>

                <select class="control" id="data-flow-profile" name="data_flow_profile">
                    <option value="">
                        {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                    </option>
                    <option v-for="dataflowprofile,index in dataFlowProfiles" :value="dataflowprofile.id">@{{ dataflowprofile.name }}</option>
                </select>

            <span class="control-error">{{ $errors->first('data_flow_profile') }}</span>

            </div>
        </div>
    </script>

    <script>
        Vue.component('attributefamily', {
                template: '#attribute-family-template',
                data: function() {
                    return {
                        key: "",
                        // seller: "",
                        dataFlowProfiles: [],
                    }
                },

                mounted: function() {
                },

                methods:{
                    onChange: function() {
                        this_this = this;

                        var uri = "{{ route('bulk-upload-admin.get-all-profile') }}"

                        this_this.$http.post(uri, {
                            attribute_family_id: this_this.key,
                            // seller_id: this_this.seller,
                        })
                        .then(response => {
                            this_this.dataFlowProfiles = response.data.dataFlowProfiles;
                        })

                        .catch(function(error) {
                        });
                    }
                }
        })

        Vue.component('downloadable-input', {
                template: '#downloadable-input-template',
                data: function() {
                    return {
                        key: "",
                        // seller: "",
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
                        this.isLinkSample = !this.isLinkSample;
                        this.isSample = !this.isSample;
                        this.linkFiles = !this.linkFiles;

                        this.linkSampleFiles = false;
                        this.sampleFile = false;
                    },

                    showlinkSamples: function() {
                        this.linkSampleFiles = !this.linkSampleFiles;
                    },

                    showSamples: function() {
                        this.sampleFile = !this.sampleFile;
                    }
                }
        })
    </script>
@endpush