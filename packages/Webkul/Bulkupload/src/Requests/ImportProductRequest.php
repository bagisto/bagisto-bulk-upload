<?php

namespace Webkul\Bulkupload\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'attribute_family'  => 'required',
            'file_path'         => 'required',
            'image_path'        => 'mimetypes:application/zip|max:10000',
            'data_flow_profile' => 'required',
        ];
    }
}
