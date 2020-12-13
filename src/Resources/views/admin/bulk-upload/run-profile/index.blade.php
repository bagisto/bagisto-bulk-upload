@extends('admin::layouts.content')

@section('page_title')
    {{ __('bulkupload::app.admin.bulk-upload.run-profile.index') }}
@stop

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
            <form action="{{ route('bulk-upload-admin.run-profile') }}" method="post">
                    @csrf
                    <div class="control-group">
                        <label for="export-product-type">{{ __('bulkupload::app.admin.bulk-upload.run-profile.select-file') }}</label>

                        <select class="control" id="data-flow-profile"  v-model="data_flow_profile" name="data_flow_profile">
                            <option>{{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}</option>

                                @if (isset($profiles))
                                    @foreach ($profiles as $profile)
                                        @foreach ($profile as $getProfileToExecute)
                                            <option value="{{ $getProfileToExecute->id }}">
                                                {{ $getProfileToExecute->name }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                @endif
                        </select>

                        <div class="page-action">
                            <button type="submit" :class="{ disabled: isDisabled }" :disabled="isDisabled" @click.prevent="runProfiler" class="btn btn-lg btn-primary mt-10">
                            {{ __('bulkupload::app.admin.bulk-upload.run-profile.run') }}
                            </button>
                        </div>
                    </div>
            </form>

            <div class="uploading-records" v-if="this.product.totalCSVRecords">
                <uploadingrecords :percentCount="percent" :uploadedProducts="product.countOfImportedProduct" :errorProduct="product.error" :totalRecords="product.totalCSVRecords" :countOfError="product.countOfError" :remainData="product.remainDataInCSV" ></uploadingrecords>
            </div>
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
                    data_flow_profile: '',
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
                }
            },

            computed: {
                isDisabled () {
                    if (this.data_flow_profile == '' || this.data_flow_profile == 'Please Select') {
                        return true;
                    } else {
                        return false;
                    }
                }
            },

            methods:{
                detectProfile: function() {
                    event.target.disabled = true;
                },

                runProfiler: function(e) {
                    event.target.disabled = true;

                    this.detectProfile();

                    const uri = "{{ route('bulk-upload-admin.read-csv') }}"
                    this.$http.post(uri, {
                        data_flow_profile_id: this.data_flow_profile
                    })
                    .then((result) => {
                        totalRecords = result.data;

                        if (typeof(totalRecords) == 'number') {
                            this.product.totalCSVRecords = this.product.remainDataInCSV = totalRecords;
                        }

                        if(totalRecords > this.product.countOfStartedProfiles) {
                            this.initiateProfiler(totalRecords);
                        } else {
                            window.flashMessages = [{
                                'type': 'alert-error',
                                'message': result.data.message
                            }];

                            this.$root.addFlashMessages()
                        }
                    })
                    .catch(function (error) {
                    });
                },

                initiateProfiler: function(totalRecords) {

                    const url = "{{ route('bulk-upload-admin.run-profile') }}"

                    this.$http.post(url, {
                        data_flow_profile_id: this.data_flow_profile,
                        numberOfCSVRecord: totalRecords,
                        countOfStartedProfiles: this.product.countOfStartedProfiles,
                        totalNumberOfCSVRecord: this.product.totalCSVRecords,
                        productUploaded: this.product.countOfImportedProduct,
                        errorCount: this.product.countOfError
                    })
                    .then((result) => {
                        this.data = result.data;

                        if (this.data.error) {
                            if (typeof(this.data.error) == "object") {
                                for (const [key, value] of Object.entries(this.data.error)) {
                                    this.product.error.push(value);
                                }
                            } else {
                                this.product.error.push(this.data.error);
                            }

                            this.product.countOfError++;
                        }

                        this.product.countOfImportedProduct = this.data.productsUploaded;
                        this.product.remainDataInCSV = this.data.remainDataInCSV;
                        this.product.countOfStartedProfiles = this.data.countOfStartedProfiles;

                        this.calculateProgress(result.data);
                    })
                    .catch(function(error) {
                    });
                },

                calculateProgress(result) {
                    finish = this.product.countOfImportedProduct;
                    progressPercent = parseInt((this.product.countOfImportedProduct/
                    this.product.totalCSVRecords)*100);

                    this.percent = progressPercent;

                    if (result.remainDataInCSV > 0) {
                        this.initiateProfiler(result.remainDataInCSV);
                    } else {
                        this.finishProfiler(this.percent);
                    }
                },

                errorCount: function(count) {
                    return console.count(this.product.error);
                },

                finishProfiler(percent) {
                }
            },
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