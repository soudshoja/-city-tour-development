<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for POST /api/dotwai/agent-b2c and /api/dotwai/agent-b2b.
 *
 * The facade accepts {action, params, telephone}.
 * The telephone field is consumed by the dotwai.resolve middleware.
 * Params is an open array — each action validates its own required params
 * inside AgentController before delegating to the service.
 *
 * @see AGEN-01 Single agent endpoint routes all actions
 */
class AgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'telephone' => 'required|string',
            'action'    => 'required|string|in:search,details,book,pay,cancel,status,history,voucher',
            'params'    => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'action.in' => 'Invalid action. Allowed: search, details, book, pay, cancel, status, history, voucher.',
        ];
    }
}
