<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkInvoiceUploadRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select an Excel file to upload.',
            'file.mimes' => 'The file must be an Excel file (.xlsx, .xls) or CSV (.csv).',
            'file.max' => 'The file must not exceed 10MB.',
        ];
    }
}
