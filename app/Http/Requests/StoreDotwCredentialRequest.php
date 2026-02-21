<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for storing (creating or updating) DOTW credentials for a company.
 *
 * Used for both create and update (upsert) operations — the controller calls
 * CompanyDotwCredential::updateOrCreate(), so this single request class covers
 * both paths. All required fields are validated with field-specific error messages
 * so that API consumers receive actionable feedback on validation failures.
 *
 * Satisfies ERROR-05 requirement: missing required fields return messages that
 * name the specific missing field (e.g. "Please provide passenger dotw_username").
 */
class StoreDotwCredentialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Returns true unconditionally — authentication and authorization for the
     * DOTW admin endpoints are handled at route middleware level in Phase 8
     * (B2B packaging). This is consistent with other API routes in the project
     * that currently have no auth middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'dotw_username'     => ['required', 'string', 'max:100'],
            'dotw_password'     => ['required', 'string', 'max:200'],
            'dotw_company_code' => ['required', 'string', 'max:50'],
            'markup_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * Follows ERROR-05 convention: "Please provide passenger {field_name}" wording
     * ensures API consumers receive messages that name the specific missing field.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dotw_username.required'     => 'Please provide passenger dotw_username',
            'dotw_password.required'     => 'Please provide passenger dotw_password',
            'dotw_company_code.required' => 'Please provide passenger dotw_company_code',
            'markup_percent.numeric'     => 'markup_percent must be a number between 0 and 100',
            'markup_percent.min'         => 'markup_percent must be at least 0',
            'markup_percent.max'         => 'markup_percent must not exceed 100',
        ];
    }
}
