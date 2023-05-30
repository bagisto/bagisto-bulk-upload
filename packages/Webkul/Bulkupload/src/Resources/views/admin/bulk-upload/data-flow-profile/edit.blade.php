@extends('admin::layouts.content')

@section('page_title')
    {{ __('bulkupload::app.admin.bulk-upload.bulk-upload-dataflow-profile') }}
@endsection

@push('css')
    <style>
        .pt-10 {
            padding-left: 10px; 
        }
    </style>
@endpush

@section('content')
    <div class="account-content">
        <div class="account-layout">
            <!-- Import New products -->
            <div class="import-new-products mt-45 pt-10">
                <div class="heading">
                    <h1>{{ __('bulkupload::app.admin.bulk-upload.data-flow-profile.edit-profile') }}</h1>
                </div>
                
                <form method="POST" action="{{ route('admin.bulk-upload.dataflow.update-profile',$profiles->id) }}" @submit.prevent="onSubmit">
                    @csrf
                    <?php $familyId = app('request')->input('family') ?>

                    <div class="control-group" :class="[errors.has('name') ? 'has-error' : '']">
                        <label for="profile_name" class="required">{{ __('bulkupload::app.admin.bulk-upload.data-flow-profile.name') }}</label>
                        <input type="text" class="control"  v-validate="'required'" name="name" id="name" value="{{ $profiles->name ?? old('name')}}"/>
                        <span class="control-error" v-if="errors.has('name')" v-text="errors.first('name')"></span>
                    </div>

                    <div class="control-group" :class="[errors.has('attribute_family_id') ? 'has-error' : '']">
                        <label for="attribute_family_id" class="required">{{ __('admin::app.catalog.products.family') }}</label>

                        <select class="control"  v-validate="'required'" id="attribute_family_id" name="attribute_family_id" {{ $familyId ? 'disabled' : '' }}>
                            @foreach ($families as $family)
                                <option value="{{ $family->id }}" {{ ($familyId == $family->id || old('attribute_family_id') == $family->id) ? 'selected' : '' }}>{{ $family->name }}</option>
                            @endforeach
                        </select>

                        @if ($familyId)
                            <input type="hidden" name="attribute_family_id" value="{{ $familyId }}"/>
                        @endif
                        <span class="control-error" v-if="errors.has('attribute_family_id')" v-text="errors.first('attribute_family_id')"></span>
                    </div>

                    <div class="control-group" :class="[errors.has('locale_code') ? 'has-error' : '']">
                        <label for="locale_code" class="required">{{ __('admin::app.settings.channels.default-locale') }}</label>
        
                        <select v-validate="'required'" class="control" id="locale_code" name="locale_code" data-vv-as="&quot;{{ __('admin::app.settings.channels.default-locale') }}&quot;">
                            @foreach (core()->getAllLocales() as $localeModel)
                                <option value="{{ $localeModel->code }}" {{ $profiles->locale_code == $localeModel->code ? 'selected' : '' }}>
                                    {{ $localeModel->name }}
                                </option>
                            @endforeach
                        </select>
                        <span class="control-error" v-if="errors.has('locale_code')" v-text="errors.first('locale_code')"></span>
                    </div>

                    <div class="page-action" style="display:flex; justify-content: space-between;">
                        <button type="submit" class="btn btn-lg btn-primary">
                            {{ __('bulkupload::app.admin.bulk-upload.data-flow-profile.update-profile')  }}
                        </button>
                    </div>
                    <br>
                </form>
            </div>

            <div class="page-content">
                <datagrid-plus src="{{ route('admin.dataflow-profile.index') }}"></datagrid-plus>
            </div>
        </div>
    </div>
@endsection
