<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for the payment_link endpoint.
 *
 * Validates the phone number and prebook key needed to generate
 * a MyFatoorah payment link for a pending DOTW booking.
 *
 * @see B2B-02 B2B agent payment link generation
 * @see B2C-01 B2C customer payment link generation
 */
class PaymentLinkRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'telephone'   => 'required|string',
            'prebook_key' => 'required|string',
        ];
    }
}
