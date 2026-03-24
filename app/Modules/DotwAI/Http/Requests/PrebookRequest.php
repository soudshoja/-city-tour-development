<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for POST /api/dotwai/prebook_hotel.
 *
 * Supports two hotel resolution modes:
 * - option_number: Pick from cached search results (most common via WhatsApp flow)
 * - hotel_id: Direct DOTW hotel ID with explicit room_type_code + rate_basis_id
 *
 * The middleware (dotwai.resolve) has already validated the telephone is a
 * registered agent, but we keep the rule for form request consistency.
 */
class PrebookRequest extends FormRequest
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
            'telephone'                     => 'required|string',
            'option_number'                 => 'required_without:hotel_id|integer|min:1',
            'hotel_id'                      => 'required_without:option_number|string',
            'room_type_code'                => 'nullable|string',
            'rate_basis_id'                 => 'nullable|string',
            'check_in'                      => 'required|date|after_or_equal:today',
            'check_out'                     => 'required|date|after:check_in',
            'occupancy'                     => 'required|array|min:1',
            'occupancy.*.adults'            => 'required|integer|min:1|max:9',
            'occupancy.*.children_ages'     => 'nullable|array',
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
            'option_number.required_without' => 'Either option_number or hotel_id is required.',
            'hotel_id.required_without'      => 'Either hotel_id or option_number is required.',
            'check_in.after_or_equal'        => 'Check-in date must be today or in the future.',
            'check_out.after'                => 'Check-out must be after check-in.',
            'occupancy.min'                  => 'At least one room occupancy must be specified.',
            'occupancy.*.adults.min'         => 'Each room requires at least 1 adult.',
        ];
    }
}
