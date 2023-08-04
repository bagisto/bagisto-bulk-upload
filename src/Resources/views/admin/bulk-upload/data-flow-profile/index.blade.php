@extends('admin::layouts.content')

@section('page_title')
    {{ __('bulkupload::app.admin.bulk-upload.data-flow-profile.index') }}
@stop

@push('css')
    <style>
        .ms-15 {
            margin-left: 15px !important;
        }
    </style>
@endpush

@section('content')

    <div class="import-new-products mt-45 ms-15">
        <div class="heading mb-25">
            <h1>{{ __('bulkupload::app.admin.bulk-upload.data-flow-profile.add-profile') }}</h1>
        </div>

        <form
            method="POST"
            action="{{ route('bulkupload.bulk-upload.dataflow.add-profile') }}"
            @submit.prevent="onSubmit"
        >
            @csrf

            <?php $familyId = app('request')->input('family') ?>

            <div class="control-group" :class="[errors.has('name') ? 'has-error' : '' ]">
                <label for="profile_name" class="required">
                    {{ __('bulkupload::app.admin.bulk-upload.data-flow-profile.name') }}
                </label>

                <input type="text" v-validate="'required'" class="control" name="name" value="" data-vv-as="&quot;{{ __('bulkupload::app.admin.bulk-upload.data-flow-profile.name') }}&quot;"/>

                <span class="control-error" v-if="errors.has('name')">@{{ errors.first('name') }}</span>
            </div>

            <div class="control-group" :class="[errors.has('attribute_family_id') ? 'has-error' : '']">
                <label for="attribute_family_id" class="required">
                    {{ __('admin::app.catalog.products.family') }}
                </label>

                <select v-validate="'required'" class="control" id="attribute_family_id" name="attribute_family_id" {{ $familyId ? 'disabled' : '' }} data-vv-as="&quot;{{ __('admin::app.catalog.products.family') }}&quot;">
                    <option value="">
                        {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                    </option>

                    @foreach ($families as $family)
                        <option value="{{ $family->id }}" {{ ($familyId == $family->id || old('attribute_family_id') == $family->id) ? 'selected' : '' }}>{{ $family->name }}</option>
                    @endforeach
                </select>

                @if ($familyId)
                    <input type="hidden" name="attribute_family_id" value="{{ $familyId }}"/>
                @endif

                <span class="control-error" v-if="errors.has('attribute_family_id')">@{{ errors.first('attribute_family_id') }}</span>
            </div>

            <div class="control-group" :class="[errors.has('locale_code') ? 'has-error' : '']">
                <label for="locale_code" class="required">
                    {{ __('admin::app.settings.channels.default-locale') }}
                </label>

                <select v-validate="'required'" class="control" id="locale_code" name="locale_code" data-vv-as="&quot;{{ __('admin::app.settings.channels.default-locale') }}&quot;">
                    <option value="">
                        {{ __('bulkupload::app.admin.bulk-upload.run-profile.please-select') }}
                    </option>
                    @foreach (core()->getAllLocales() as $localeModel)
                        <option value="{{ $localeModel->code }}">
                            {{ $localeModel->name }}
                        </option>
                    @endforeach
                </select>

                <span class="control-error" v-if="errors.has('locale_code')">@{{ errors.first('locale_code') }}</span>
            </div>

            <div class="page-action" style="display:flex; justify-content: space-between;">
                <button type="submit" class="btn btn-lg btn-primary">
                    {{ __('bulkupload::app.admin.bulk-upload.upload-files.save')  }}
                </button>
            </div>
        </form>
    </div>

    <accordian :title="'{{ __('bulkupload::app.admin.bulk-upload.data-flow-profile.grid') }}'" :active="true" class="mt-45">
        <div slot="body">
            <div class="page-content">
                <datagrid-plus src="{{ route('admin.dataflow-profile.index') }}"></datagrid-plus>
            </div>
        </div>
    </accordian>
@stop

