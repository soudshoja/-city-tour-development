<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Services\DotwAuditService;

/**
 * DotwCreatePreBooking — stub created by Plan 06-01.
 * Full implementation in Plan 06-02.
 */
class DotwCreatePreBooking
{
    public function __construct(
        private readonly DotwAuditService $auditService,
    ) {}

    public function __invoke(mixed $root, array $args): array
    {
        return [
            'success' => false,
            'error' => null,
            'meta' => [
                'trace_id' => app('dotw.trace_id'),
                'company_id' => null,
                'timestamp' => now()->toISOString(),
                'request_id' => app('dotw.trace_id'),
            ],
            'cached' => false,
            'data' => null,
        ];
    }
}
