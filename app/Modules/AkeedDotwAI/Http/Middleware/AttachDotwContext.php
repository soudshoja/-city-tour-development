<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;

class AttachDotwContext
{
    public function handle(Request $request, Closure $next)
    {
        $companyId = config('akeed_dotwai.company_id');
        $customerPhone = $request->input('telephone') ?? $request->input('phone') ?? null;

        $normalizedPhone = $customerPhone ? $this->normalize($customerPhone) : null;

        $client = $normalizedPhone
            ? Client::where('phone', $normalizedPhone)->first()
            : null;

        $request->attributes->set('akeed_context', [
            'company_id' => $companyId,
            'customer_phone' => $normalizedPhone,
            'client' => $client,
        ]);

        return $next($request);
    }

    private function normalize(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phone = ltrim($phone, '0');
        if (strlen($phone) === 8) {
            $phone = config('akeed_dotwai.default_country_code', '965').$phone;
        }

        return '+'.$phone;
    }
}
