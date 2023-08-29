@extends('admin::layouts.content')

@section('page_title')
    {{ __('bulkupload::app.admin.bulk-upload.run-profile.index') }}
@stop

@push('css')
    <style>
        /* Loader Styles */
        .loader-container {
            /* display: none; */
            position: absolute;
            /* top: 0; */
            left: 25%;
            /* width: 100%; */
            /* height: 100%; */
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
            <form action="{{ route('bulk-upload-admin.run-profile') }}" method="post">
                    @csrf
                    <div class="control-group">
                        <label for="export-product-type">{{ __('bulkupload::app.admin.bulk-upload.run-profile.select-file') }}</label>

                        <select class="control" id="data-flow-profile"  v-model="data_flow_profile" name="data_flow_profile">
                            <option>{{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}</option>

                                @if (isset($profiles))
                                    @foreach ($profiles as $profile)
                                        <option value="{{ $profile->id }}">
                                            {{ $profile->name }}
                                        </option>
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

            <div class="page-action">
                <span
                    :class="{ disabled: isDisabled }"
                    :disabled="isDisabled"
                    class="btn btn-lg btn-primary mt-10"
                    @click.prevent="runConsoleCommand"
                >
                    {{ __('bulkupload::app.admin.bulk-upload.run-profile.run-command') }}
                </Span>

                <div id="loaderContainer" class="loader-container" v-if="running">
                    <div class="loader" ></div>
                </div>

                <p v-if="running">Time Taken: @{{ formattedTime }}</p>
            </div>

            <br>

            <accordian :title="'{{ __('bulkupload::app.admin.bulk-upload.run-profile.error') }}'" :active="true">
                <div slot="body">

                    <div v-for="(item, index) in errorCsvFile" :key="index" >

                        <table>
                            <tr>
                                <th>
                                    Profiler Name:-
                                </th>
                                <td>

                                    @{{ profilerNames[index] }}
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    CSV Link
                                </th>
                                <th>
                                    Date & Time
                                </th>
                                <th>
                                    Delete File
                                </th>
                            </tr>

                            <tr v-for="(record) in item">
                                <td>
                                    <a
                                        :href="record.link"
                                    >
                                        Download CSV
                                    </a>
                                </td>
                                <td>
                                    <span>
                                        @{{ record.time }}
                                    </span>
                                </td>
                                <td>
                                    <span @click="deleteCSV(index, record.fileName)">
                                        <button class="btn btn-primary">Delete</button>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </accordian>

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
                    errorCsvFile: [],
                    profilerNames: [],

                    startTime: 0,
                    timer: {
                        seconds: 0,
                        minutes: 0,
                        interval: null,
                    },
                    running: false,
                }
            },

            mounted() {
                // this.resetTimer();
                // this.stopTimer();
                this.loadStoredTimer();
            },

            computed: {
                isDisabled () {
                    this.getErrorCsvFile();

                    if (this.data_flow_profile == '' || this.data_flow_profile == 'Please Select') {
                        return true;
                    } else {
                        return false;
                    }
                },

                formattedTime() {
                    const minutes = Math.floor(this.timer.seconds / 60);
                    const seconds = this.timer.seconds % 60;
                    return `${minutes} minutes ${seconds} seconds`;
                },
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
                },

                runConsoleCommand: function() {

                    if (this.data_flow_profile != '' && this.data_flow_profile != 'Please Select') {

                        event.target.disabled = true;

                        this.detectProfile();

                        $("#loaderContainer").show();

                        this.startTimer();

                        localStorage.setItem('requestCompleted', 'waiting');

                        checkRequestStatusInterval = setInterval(this.checkRequestStatus, 5000);
                        console.log("testuing");

                        const uri = "{{ route('bulk-upload-admin.read-csv-command') }}"

                        this.$http.post(uri, {
                            data_flow_profile_id: this.data_flow_profile
                        })
                        .then((result) => {
                            console.log(result, "test");
                            localStorage.setItem('requestCompleted', 'complete'); // Store a flag in local storage
                        })
                        .catch(function (error) {
                            console.log(error);
                        });
                    }

                },

                checkRequestStatus: function () {
                    console.log(localStorage.getItem('requestCompleted'), "working");

                    if (localStorage.getItem('requestCompleted') == 'complete') {
                        window.flashMessages = [{
                            'type': 'alert-success',
                            'message': 'Products uploaded successfully.'
                        }];

                        this.$root.addFlashMessages();

                        clearInterval(this.timer.checkRequestStatusInterval);

                        this.getErrorCsvFile();

                        $("#loaderContainer").hide();

                        this.resetTimer();
                        this.stopTimer();

                        localStorage.setItem('requestCompleted', 'false');

                        window.location.reload();
                    }

                },

                getErrorCsvFile: function(e) {

                    const uri = "{{ route('download.csv') }}"

                    this.$http.get(uri)
                        .then((result) => {
                            this.errorCsvFile = result.data.resultArray;
                            this.profilerNames = result.data.profilerNames;
                        })
                        .catch(function (error) {
                            console.log(error);
                        });
                },

                deleteCSV: function(id, name) {
                    const uri = "{{ route('delete.csv.file') }}"

                    this.$http.get(uri, {params: {id: id, name:name}})
                        .then((result) => {

                            window.flashMessages = [{
                                'type': 'alert-success',
                                'message': result.data.message
                            }];

                            this.$root.addFlashMessages();

                            this.getErrorCsvFile();
                        })
                        .catch(function (error) {
                            console.log(error);
                        });
                },

                startTimer() {
                    if (!this.running) {
                        this.startTime = new Date().getTime() - (this.timer.seconds * 1000);
                        this.timer.interval = setInterval(this.updateTimer, 1000); // Update every second
                        this.running = true;
                        this.storeTimerState();
                    }
                },

                resetTimer() {
                    this.timer.seconds = 0;
                    this.startTime = new Date().getTime();
                    this.storeTimerState();
                },

                updateTimer() {
                    const currentTime = new Date().getTime();
                    const elapsedTime = Math.floor((currentTime - this.startTime) / 1000);
                    this.timer.seconds = elapsedTime;
                    this.storeTimerState();
                },

                stopTimer() {
                    clearInterval(this.timer.interval);
                    this.running = false;
                    this.storeTimerState();
                },

                storeTimerState() {
                    localStorage.setItem('timerState', JSON.stringify({
                        running: this.running,
                        startTime: this.startTime,
                        seconds: this.timer.seconds,
                    }));
                },

                loadStoredTimer() {
                    const storedState = localStorage.getItem('timerState');
                    if (storedState) {
                        const { running, startTime, seconds } = JSON.parse(storedState);
                        this.running = running;
                        this.startTime = startTime;
                        this.timer.seconds = seconds;
                        if (running) {
                        this.timer.interval = setInterval(this.updateTimer, 1000);
                        }
                    }
                },
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
