<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for POST /api/dotwai/confirm_booking.
 *
 * Validates the prebook_key and passenger details required to confirm
 * a hotel reservation with DOTW.
 *
 * Salutation defaults to 'Mr' if not provided (handled in BookingService).
 */
class ConfirmBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'telephone'                   => 'required|string',
            'prebook_key'                 => 'required|string',
            'passengers'                  => 'required|array|min:1',
            'passengers.*.first_name'     => 'required|string|min:2|max:25',
            'passengers.*.last_name'      => 'required|string|min:2|max:25',
            'passengers.*.salutation'     => 'nullable|string',
            'email'                       => 'nullable|email',
            'special_requests'            => 'nullable|string|max:500',
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prebook_key.required'               => 'A prebook_key is required to confirm a booking.',
            'passengers.required'                => 'At least one passenger is required.',
            'passengers.min'                     => 'At least one passenger is required.',
            'passengers.*.first_name.required'   => 'Each passenger must have a first_name.',
            'passengers.*.first_name.min'        => 'Passenger first_name must be at least 2 characters.',
            'passengers.*.last_name.required'    => 'Each passenger must have a last_name.',
            'passengers.*.last_name.min'         => 'Passenger last_name must be at least 2 characters.',
            'email.email'                        => 'A valid email address is required for voucher delivery.',
        ];
    }
}
