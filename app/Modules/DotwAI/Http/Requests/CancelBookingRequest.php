<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for POST /api/dotwai/cancel_booking.
 *
 * Validates the two-step cancellation request:
 * - Step 1 (confirm=no): Preview penalty without executing cancellation
 * - Step 2 (confirm=yes): Execute cancellation with penalty acknowledgment
 *
 * penalty_amount is only required when confirm=yes, as the agent must
 * explicitly acknowledge the penalty amount before DOTW executes the cancel.
 */
class CancelBookingRequest extends FormRequest
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
            'phone'          => ['required', 'string'],
            'prebook_key'    => ['required', 'string'],
            'confirm'        => ['required', 'in:no,yes'],
            'penalty_amount' => ['required_if:confirm,yes', 'numeric', 'min:0'],
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
            'phone.required'          => 'Phone number is required.',
            'prebook_key.required'    => 'Booking reference (prebook_key) is required.',
            'confirm.required'        => 'Confirm field is required (no or yes).',
            'confirm.in'              => 'Confirm must be "no" (preview) or "yes" (execute).',
            'penalty_amount.required_if' => 'Penalty amount is required when confirming cancellation.',
            'penalty_amount.numeric'  => 'Penalty amount must be a numeric value.',
            'penalty_amount.min'      => 'Penalty amount cannot be negative.',
        ];
    }
}
