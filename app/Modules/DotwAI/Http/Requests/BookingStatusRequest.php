<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for GET /api/dotwai/booking_status.
 *
 * Requires phone (to scope by agent/customer) and at least one of
 * prebook_key or booking_code to identify the specific booking.
 *
 * @see HIST-01 booking_status endpoint
 */
class BookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;  // ResolveDotwAIContext middleware handles auth
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'phone'        => 'required|string|regex:/^\+?[0-9]{10,15}$/',
            'prebook_key'  => 'nullable|string',
            'booking_code' => 'nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required',
            'phone.regex'    => 'Phone number must be 10-15 digits',
        ];
    }
}
