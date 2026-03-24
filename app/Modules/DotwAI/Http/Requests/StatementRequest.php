<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation request for the GET /api/dotwai/statement endpoint.
 *
 * Requires a resolved phone number (provided by dotwai.resolve middleware),
 * a start date, and an end date for the statement period.
 *
 * @see ACCT-02 Statement endpoint for company reconciliation
 */
class StatementRequest extends FormRequest
{
    /**
     * All DotwAI endpoints are pre-authorized by the dotwai.resolve middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for the statement request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone'     => ['required', 'string'],
            'date_from' => ['required', 'date', 'date_format:Y-m-d'],
            'date_to'   => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
