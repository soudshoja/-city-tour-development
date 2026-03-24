<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for POST /api/dotwai/resend_voucher.
 *
 * Requires phone (to scope to agent/customer) and prebook_key
 * to identify the confirmed booking whose voucher to resend.
 *
 * @see HIST-03 resend_voucher endpoint
 */
class ResendVoucherRequest extends FormRequest
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
            'phone'       => 'required|string|regex:/^\+?[0-9]{10,15}$/',
            'prebook_key' => 'required|string',
        ];
    }
}
