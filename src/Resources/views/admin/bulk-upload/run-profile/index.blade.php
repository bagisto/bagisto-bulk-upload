@extends('admin::layouts.content')

@section('page_title')
    {{ __('bulkupload::app.admin.bulk-upload.run-profile.index') }}
@stop

@push('css')
    <style>
        /* Loader Styles */
        .loader-container {
            position: absolute;
            left: 25%;
            bottom: 10%;
            background-color: rgba(0, 0, 0, 0); /* Transparent black background */
            z-index: -9999; /* Higher z-index to ensure it's on top of other elements */
            pointer-events: none; /* Allow user interactions with elements below */
        }

        .loader {
            border: 4px solid #f3f3f3; /* Light grey border */
            border-top: 4px solid #3498db; /* Blue border on top to create the spinning effect */
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 2s linear infinite; /* Spin animation */

        }

        .page-action {
            position: relative;
        }
    </style>
@endpush

@section('content')

    <!-- Run Profile -->
    <accordian :title="'{{ __('bulkupload::app.admin.bulk-upload.run-profile.index') }}'" :active="true">
        <div slot="body">
            <div class="app-profiler">
                <profiler></profiler>
            </div>
        </div>
    </accordian>

@stop

@push('scripts')
    <script type="text/x-template" id="profiler-template">
        <div class="run-profile">
            <run-profile-form
                :families="{{ json_encode($families) }}"
                :data_flow_profile_id="data_flow_profile_id"
            >
            </run-profile-form>

            <div class="uploading-records" v-if="this.product.totalCSVRecords">
                <uploadingrecords :percentCount="percent" :uploadedProducts="product.countOfImportedProduct" :errorProduct="product.error" :totalRecords="product.totalCSVRecords" :countOfError="product.countOfError" :remainData="product.remainDataInCSV" ></uploadingrecords>
            </div>
        </div>
    </script>

    <script type="text/x-template" id="run-profile-form-template">
        <div class="run-profile">

            <form @submit.prevent="runProfiler">
                <div class="control-group">
                    <label for="export-product-type">
                        {{ __('bulkupload::app.admin.bulk-upload.run-profile.select-file') }}
                    </label>

                    <select class="control" @change="getImporter()" id="data-flow-profile" v-model="data_flow_profile_id" name="data_flow_profile">
                        <option>
                            {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                        </option>

                        <option v-for="family in families" :key="family.id" :value="family.id">
                            @{{ family.name }}
                        </option>
                    </select>

                    {{-- <div class="page-action">
                        <button type="submit" :class="{ disabled: isDisabled }" :disabled="isDisabled" class="btn btn-lg btn-primary mt-10">
                            {{ __('bulkupload::app.admin.bulk-upload.run-profile.run') }}
                        </button>
                    </div> --}}
                </div>
            </form>
        </div>
    </script>

    <script type="text/x-template" id="uploadingRecords-template">
        <ul>
            <li>
                <i class="icon check-accent"></i>
                <span>{{ __('bulkupload::app.admin.bulk-upload.run-profile.profile-execution') }}</span>
            </li>

            <li v-if="this.countOfError > '0'">
                <i class="icon cross-accent"></i>
                <span>{{ __('bulkupload::app.admin.bulk-upload.run-profile.error-count') }}:  @{{this.countOfError}}</span>
            </li>

            <li v-if="this.countOfError > '0'">
                <i class="icon cross-accent"></i>
                <span >
                {{ __('bulkupload::app.admin.bulk-upload.run-profile.error-in-product') }} :
                    <label v-for= "error in this.errorProduct" style="display: inline-block; width: 100%; margin-left: 50px;">
                        <i class="icon icon-crossed"></i>
                        @{{ error }}
                    </label>
                </span>
            </li>

            <li>
                <i class="icon check-accent"></i>
                <span>{{ __('bulkupload::app.admin.bulk-upload.run-profile.warning') }}</span>
            </li>

            <li>
                <progress class="progression" v-if="this.remainData > '0'" :value="percentCount" max="100">
                </progress>
                <progress class="progression" v-else :value="100" max="100"></progress>

                <span style="vertical-align: 75%;" v-if="this.remainData > '0'"> @{{ this.percentCount}}%</span>
                <span style="vertical-align: 75%;" v-else> 100% </span>
            </li>

            <li>
                <i class="icon check-accent"></i>
                <span> @{{this.uploadedProducts}}/@{{this.totalRecords}} {{ __('bulkupload::app.admin.bulk-upload.run-profile.products-uploaded') }}</span>
            </li>

            <li v-if="this.remainData == '0'">
                <i class="icon finish-icon"></i>
                <span>{{ __('bulkupload::app.admin.bulk-upload.run-profile.finish') }} </span>
            </li>
        </ul>
    </script>

    <script>
        Vue.component('profiler', {
            template:'#profiler-template',

            data: function() {
                return {
                    data_flow_profile_id: '',
                    percent: 0,
                    product: {
                        countOfImportedProduct : 0,
                        countOfStartedProfiles : 0,
                        fetchedRecords : 10,
                        numberOfTimeInitiateProfilerCalled: 0,
                        totalCSVRecords:'',
                        dataArray:[],
                        error: [],
                        countOfError: 0,
                        remainDataInCSV: 1,
                    },
                    errorCsvFile: [],
                    profilerNames: '',

                    startTime: 0,
                    timer: {
                        seconds: 0,
                        minutes: 0,
                        interval: null,
                    },
                    running: false,
                }
            },
        })

        Vue.component('run-profile-form', {
            template:'#run-profile-form-template',

            props: ['families', 'data_flow_profile_id'],

            data: function() {
                return {
                }
            },

            computed: {
                isDisabled () {
                    if (this.data_flow_profile_id == '' || this.data_flow_profile_id == 'Please Select') {
                        return true;
                    } else {
                        return false;
                    }
                },
            },

            methods: {
                getImporter: function() {
                    if (this.data_flow_profile_id != '' && this.data_flow_profile_id != 'Please Select') {
                        var uri = "{{ route('admin.bulk-upload.upload-file.get-all-profile') }}"

                        this.$http.post(uri, {
                            attribute_family_id: this.data_flow_profile_id,
                        })
                        .then(response => {
                            console.log(response.data.dataFlowProfiles);
                        })
                        .catch(function(error) {
                        });

                    }
                },
            }
        })

        Vue.component('uploadingrecords', {
            template:'#uploadingRecords-template',
            props: ['percentCount', 'uploadedProducts','errorProduct','totalRecords', 'countOfError', 'remainData'],
            data: function() {
                return {
                    percentage: this.percentCount,
                }
            },
        })
    </script>

@endpush
