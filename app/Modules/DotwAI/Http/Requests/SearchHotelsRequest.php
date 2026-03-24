<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation request for the search_hotels endpoint.
 *
 * Validates required search parameters (city, dates, occupancy) and
 * optional filter parameters (star rating, meal type, price range,
 * refundable, hotel name).
 *
 * The telephone field is required for the middleware (passed through to
 * PhoneResolverService) and for caching search results per phone number.
 *
 * @see SRCH-01 Search hotels by city/name
 * @see SRCH-05 Multi-room occupancy
 * @see SRCH-06 Filters
 */
class SearchHotelsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled by the dotwai.resolve middleware,
     * which resolves the phone number to a valid agent context.
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
            'city' => ['required', 'string', 'min:2'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'occupancy' => ['required', 'array', 'min:1'],
            'occupancy.*.adults' => ['required', 'integer', 'min:1', 'max:9'],
            'occupancy.*.children_ages' => ['nullable', 'array'],
            'occupancy.*.children_ages.*' => ['integer', 'min:0', 'max:17'],
            'nationality' => ['nullable', 'string'],
            'hotel' => ['nullable', 'string'],
            'star_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'meal_type' => ['nullable', 'string', 'in:All,RoomOnly,Breakfast,HalfBoard,FullBoard,AllInclusive'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'refundable' => ['nullable', 'boolean'],
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
            'city.required' => 'City name is required for hotel search.',
            'check_in.required' => 'Check-in date is required.',
            'check_in.after_or_equal' => 'Check-in date must be today or later.',
            'check_out.required' => 'Check-out date is required.',
            'check_out.after' => 'Check-out date must be after check-in date.',
            'occupancy.required' => 'At least one room with occupancy details is required.',
            'occupancy.*.adults.required' => 'Number of adults per room is required.',
            'occupancy.*.adults.min' => 'Each room must have at least 1 adult.',
            'occupancy.*.adults.max' => 'Maximum 9 adults per room.',
            'occupancy.*.children_ages.*.max' => 'Child age must be 17 or younger.',
            'telephone.required' => 'Phone number is required for agent identification.',
        ];
    }
}
