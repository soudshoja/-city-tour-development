<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation request for the get_hotel_details endpoint.
 *
 * Validates required parameters for retrieving room details from a
 * specific DOTW hotel: hotel_id, dates, and occupancy.
 *
 * The telephone field is required for the middleware.
 *
 * @see SRCH-02 Get hotel details (browse mode)
 */
class GetHotelDetailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled by the dotwai.resolve middleware.
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
            'hotel_id' => ['required', 'string'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'occupancy' => ['required', 'array', 'min:1'],
            'occupancy.*.adults' => ['required', 'integer', 'min:1', 'max:9'],
            'occupancy.*.children_ages' => ['nullable', 'array'],
            'occupancy.*.children_ages.*' => ['integer', 'min:0', 'max:17'],
            'telephone' => ['required', 'string'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hotel_id.required' => 'Hotel ID is required to get room details.',
            'check_in.required' => 'Check-in date is required.',
            'check_out.required' => 'Check-out date is required.',
            'check_out.after' => 'Check-out date must be after check-in date.',
            'occupancy.required' => 'At least one room with occupancy details is required.',
            'telephone.required' => 'Phone number is required for agent identification.',
        ];
    }
}
