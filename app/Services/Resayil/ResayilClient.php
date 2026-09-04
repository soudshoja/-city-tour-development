<?php

namespace App\Services\Resayil;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin transport wrapper over Laravel's HTTP client — ported from the aircon
 * project's App\Services\Resayil\ResayilClient (auth + retry core only;
 * aircon's domain-specific messaging methods were deliberately NOT ported,
 * per the Module 5 brief). Error logging is the CALLER's job (see
 * ResayilProvisioningService), same split aircon uses: this client never
 * logs the request, only transports it — so the auth header can never leak
 * into a log line by accident.
 *
 * AUTH: both Resayil API surfaces (reseller: api.resayil.io/v1/resellers,
 * and account-level: api.resayil.io/v1) use the SAME scheme — a
 * `Token: <key>` HTTP header (not Bearer). This client is base-URL/token
 * agnostic: callers configure it for whichever surface they need.
 *
 * SECURITY: the token is read from server config/constructor args ONLY —
 * callers never touch the credential.
 *
 * Retries (5xx / connection failures) are handled by the HTTP client's
 * ->retry(); a 4xx is not retried (it would never succeed) and surfaces to
 * the caller as the raw Response for it to interpret.
 */
class ResayilClient
{
    public function __construct(
        protected ?string $baseUrl = null,
        protected ?string $token = null,
        protected ?int $timeout = null,
        protected ?int $retries = null,
    ) {
        // Defaults point at the RESELLER API — the confirmed, buildable
        // surface for Module 5. Account-scoped calls (e.g. team-member
        // creation) must pass an explicit $baseUrl/$token per company.
        $this->baseUrl ??= (string) config('resayil.reseller_base_url');
        $this->token ??= (string) config('resayil.reseller_token');
        $this->timeout ??= (int) config('resayil.timeout', 15);
        $this->retries ??= (int) config('resayil.retries', 3);
    }

    /**
     * @param  array<string,mixed>  $query
     * @param  array<string,string>  $headers
     */
    public function get(string $path, array $query = [], array $headers = []): Response
    {
        return $this->request($headers)->get($path, $query);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,string>  $headers
     */
    public function post(string $path, array $payload, array $headers = []): Response
    {
        return $this->request($headers)->post($path, $payload);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,string>  $headers
     */
    public function patch(string $path, array $payload = [], array $headers = []): Response
    {
        return $this->request($headers)->patch($path, $payload);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,string>  $headers
     */
    public function delete(string $path, array $payload = [], array $headers = []): Response
    {
        return $this->request($headers)->delete($path, $payload);
    }

    public function configured(): bool
    {
        return $this->baseUrl !== '' && $this->baseUrl !== null
            && $this->token !== '' && $this->token !== null;
    }

    /**
     * @param  array<string,string>  $headers
     */
    protected function request(array $headers = []): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) $this->baseUrl, '/'))
            ->withHeaders(['Token' => $this->token])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->retry($this->retries, 200, function ($exception, $request) {
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    return $exception->response->serverError();
                }

                return true;
            }, throw: false);

        return $headers === [] ? $request : $request->withHeaders($headers);
    }
}
