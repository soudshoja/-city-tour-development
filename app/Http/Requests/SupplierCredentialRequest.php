<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierCredentialRequest extends FormRequest
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
            'supplier_id' => 'required|exists:suppliers,id',
            'company_id' => 'required|exists:companies,id',
            'type' => 'required|in:basic,oauth',
            'username' => 'required_if:type,basic',
            'password' => 'required_if:type,basic',
            'client_id' => 'required_if:type,oauth',
            'client_secret' => 'required_if:type,oauth',
            'access_token' => 'nullable',
            'refresh_token' => 'nullable',
            'expires_at' => 'nullable|date_time',
        ];
    }
}
