<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for GET /api/dotwai/booking_history.
 *
 * Requires phone (to scope by agent/customer). Optional filters:
 * status, from_date/to_date date range, and pagination controls.
 *
 * @see HIST-02 booking_history endpoint with status/date filters and pagination
 */
class BookingHistoryRequest extends FormRequest
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
            'phone'     => 'required|string|regex:/^\+?[0-9]{10,15}$/',
            'status'    => 'nullable|string|in:confirmed,cancelled,failed,pending_payment',
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date|after_or_equal:from_date',
            'page'      => 'nullable|integer|min:1',
            'per_page'  => 'nullable|integer|min:1|max:50',
        ];
    }
}
