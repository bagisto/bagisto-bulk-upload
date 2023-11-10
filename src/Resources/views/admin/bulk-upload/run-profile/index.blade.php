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
            >
            </run-profile-form>
        </div>
    </script>

    <script type="text/x-template" id="run-profile-form-template">
        <div class="run-profile">

            <form>
                <div class="control-group">
                    <label for="attribute_family_id">
                        {{ __('admin::app.catalog.products.family') }}
                    </label>

                    <select class="control" @change="getImporter()" id="attribute_family_id" v-model="attribute_family_id" name="attribute_family_id">
                        <option>
                            {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                        </option>

                        <option v-for="family in families" :key="family.id" :value="family.id">
                            @{{ family.name }}
                        </option>
                    </select>
                </div>

                <div class="control-group">
                    <label for="export-product-type">
                        {{ __('bulkupload::app.admin.bulk-upload.bulk-product-importer.index') }}
                    </label>

                    <select class="control" @change="setProductFiles()" id="data-flow-profile" v-model="bulk_product_importer_id" name="bulk_product_importer_id">
                        <option>
                            {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                        </option>

                        <option v-for="importer in product_importer" :key="importer.id" :value="importer.id">
                            @{{ importer.name }}
                        </option>
                    </select>
                </div>

                <div class="control-group">
                    <label for="product_file">
                        {{ __('bulkupload::app.admin.bulk-upload.run-profile.select-file') }}
                    </label>

                    <select class="control" id="product_file" v-model="product_file_id" name="product_file">
                        <option>
                            {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                        </option>

                        <option v-for="file in product_file" :key="file.id" :value="file.id" v-if="file.status">
                            @{{ file.file_name }}
                            (@{{ formatDateTime(file.created_at) }})
                        </option>
                    </select>
                </div>

                <div class="page-action" v-if="this.product_file_id != '' && this.product_file_id != 'Please Select'">
                    <span type="submit" @click="runProfiler" :class="{ disabled: isDisabled }" :disabled="isDisabled" class="btn btn-lg btn-primary mt-10">
                        {{ __('bulkupload::app.admin.bulk-upload.run-profile.run') }}
                    </span>

                    <span type="submit" @click="deleteFile" class="btn btn-lg btn-primary mt-10">
                        {{ __('bulkupload::app.admin.bulk-upload.upload-file.delete') }}
                    </span>
                </div>
            </form>

            <br>

            <accordian :title="'{{ __('bulkupload::app.admin.bulk-upload.run-profile.error') }}'" :active="true">
                <div slot="body">

                    <div v-for="(item, index) in errorCsvFile" :key="index" >

                        <table>
                            <tr>
                                <th> Profiler Name:- @{{ profilerNames[index] }}</th>
                            </tr>

                            <tr>
                                <th> CSV Link </th>
                                <th> Date & Time </th>
                                <th> Delete File </th>
                            </tr>

                            <tr v-for="(record) in item">
                                <td>
                                    <a :href="record.link">Download CSV</a>
                                </td>
                                <td>
                                    <span>@{{ record.time }}</span>
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
        </div>
    </script>

    <script>
        Vue.component('profiler', {
            template:'#profiler-template',

            data: function() {
                return {
                }
            },
        })

        Vue.component('run-profile-form', {
            template:'#run-profile-form-template',

            props: ['families'],

            data: function() {
                return {
                    product_file: [],
                    product_file_id: '',
                    product_importer: [],
                    attribute_family_id: '',
                    bulk_product_importer_id: '',

                    errorCsvFile: [],
                    profilerNames: '',
                }
            },

            computed: {
                isDisabled() {
                    return this.product_file_id === '' || this.product_file_id === 'Please Select';
                },
            },

            created: function() {
                this.getErrorCsvFile();
            },

            methods: {
                async getImporter() {
                    if (this.attribute_family_id === '' || this.attribute_family_id === 'Please Select') {
                        return; // Exit early if attribute_family_id is empty or 'Please Select'
                    }

                    try {
                        const uri = "{{ route('admin.bulk-upload.upload-file.get-importar') }}";
                        const response = await this.$http.get(uri, {
                            params: {
                                'attribute_family_id': this.attribute_family_id,
                            }
                        });

                        this.product_importer = Object.values(response.data.dataFlowProfiles);
                    } catch (error) {
                        // Handle errors here if needed
                    }
                },

                setProductFiles() {
                    if (this.bulk_product_importer_id === '' || this.bulk_product_importer_id === 'Please Select') {
                        return; // Exit early if bulk_product_importer_id is empty or 'Please Select'
                    }

                    const selectedProfile = this.product_importer.find(obj => obj.id === this.bulk_product_importer_id);

                    if (selectedProfile) {
                        // If an item with the specified id is found, set this.product_file to its import_product property
                        this.product_file = selectedProfile.import_product;
                    }
                },

                async deleteFile() {
                    if (this.product_file_id === '' || this.product_file_id === 'Please Select') {
                        return; // Exit early if product_file_id is empty or 'Please Select'
                    }
                    id = this.product_file_id;
                    this.product_file_id = '';

                    try {
                        const uri = "{{ route('admin.bulk-upload.upload-file.delete') }}";
                        const response = await this.$http.post(uri, {
                            bulk_product_importer_id: this.bulk_product_importer_id,
                            product_file_id: id,
                        });

                        this.product_file = response.data.importer_product_file;
                    } catch (error) {
                        // Handle errors here if needed
                    }
                },

                formatDateTime: function(value) {
                    const dateTime = new Date(value);

                    return dateTime.toLocaleString(); // Adjust the format as needed
                },

                runProfiler: function(e) {
                    const id =this.product_file_id
                    this.product_file_id = '';

                    const uri = "{{ route('admin.bulk-upload.upload-file.run-profile.read-csv') }}";

                    this.$http.post(uri, {
                        product_file_id: id,
                        bulk_product_importer_id: this.bulk_product_importer_id
                    })
                    .then((result) => {
                        window.flashMessages = [{
                            'type': 'alert-success',
                            'message': result.data.message
                        }];

                        this.$root.addFlashMessages();
                    })
                    .catch(function (error) {
                    })
                    .finally(function () {
                        this.getErrorCsvFile;
                    });
                },

                getErrorCsvFile: function(e) {

                    const uri = "{{ route('admin.bulk-upload.upload-file.run-profile.download-csv') }}"

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
                    const uri = "{{ route('admin.bulk-upload.upload-file.run-profile.delete-csv-file') }}"

                    this.$http.post(uri, {id: id, name:name})
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
            }
        });
    </script>

@endpush
