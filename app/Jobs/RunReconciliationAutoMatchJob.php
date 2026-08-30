<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ReconciliationRun;
use App\Services\Accounting\ReconciliationAutoMatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): "a Run-now button (permission accounting.reconcile, queued job,
 * withoutOverlapping)." Dispatched by `ReconciliationController::runNow()`; wraps
 * {@see ReconciliationAutoMatchService::run()} with `$trigger = manual` (vs. the console command's
 * `nightly`) so the run-status panel's "last nightly run" and an operator's own manual runs are
 * always distinguishable.
 */
class RunReconciliationAutoMatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $companyId,
        private readonly ?int $triggeredBy,
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('reconciliation-auto-run:'.$this->companyId))->releaseAfter(5)->expireAfter(600)];
    }

    public function handle(ReconciliationAutoMatchService $service): void
    {
        $service->run($this->companyId, ReconciliationRun::TRIGGER_MANUAL, $this->triggeredBy);
    }
}
