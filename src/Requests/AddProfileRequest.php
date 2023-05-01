<?php

namespace Webkul\Bulkupload\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddProfileRequest extends FormRequest
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
            'name'             => 'required|unique:bulkupload_data_flow_profiles',
            'attribute_family_id' => 'required',
            'locale_code'      => 'required'
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'attribute_family_id' => $this->input('attribute_family'),
        ]);

        $this->request->remove('attribute_family');
    }
}
