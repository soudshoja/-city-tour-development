<?php

namespace App\Console\Commands;

use App\Services\TaskStatusService;
use Illuminate\Console\Command;

/**
 * W6.S "Hold/confirmed follow-up lifecycle" item 2 (w6-brief.md, owner addition 2026-08-28) +
 * W6.V Kind 2 (AUTO_VOID). This command used to hard-code a Jazeera-only `confirmed` -> `void`
 * raw status flip that bypassed processTaskFinancial() entirely (ct-void-map.md §7 bug 8 /
 * importer-status-contract.md Table 1 row 'void'). That body is DELETED -- this command is now a
 * thin CLI wrapper over TWO TaskStatusService methods, exactly matching w6-brief.md's own split:
 *   - {@see TaskStatusService::expire()} -- never-issued/invoiced tasks -> status only (`expired`),
 *     no ledger.
 *   - {@see TaskStatusService::autoVoidExpiredInvoiced()} (W6.V) -- tasks that DO already carry an
 *     invoiceDetail -> `sub_type='AUTO_VOID'` through {@see TaskStatusService::void()} (a real
 *     ticket/client-leg reversal, commission un-earn, disposition -- never a raw status flip).
 * Both run for ALL suppliers (no Jazeera-only special-case survives).
 */
class ProcessExpiredConfirmedTasks extends Command
{
    protected $signature = 'tasks:process-expired-confirmed {--company-id= : Process only tasks for one company}';

    protected $description = 'Expire never-invoiced hold/confirmed tasks (TaskStatusService::expire()) and AUTO_VOID already-invoiced ones (TaskStatusService::autoVoidExpiredInvoiced()) past their deadline';

    public function handle(TaskStatusService $taskStatusService): int
    {
        $companyId = $this->option('company-id');
        $companyId = $companyId !== null ? (int) $companyId : null;

        $this->info('Running TaskStatusService::expire()' . ($companyId ? " for company {$companyId}" : ' for all companies'));

        $expiredCount = $taskStatusService->expire($companyId);

        $this->info("Expired {$expiredCount} task(s).");

        $this->info('Running TaskStatusService::autoVoidExpiredInvoiced()' . ($companyId ? " for company {$companyId}" : ' for all companies'));

        $autoVoidedCount = $taskStatusService->autoVoidExpiredInvoiced($companyId);

        $this->info("Auto-voided {$autoVoidedCount} already-invoiced task(s).");

        return 0;
    }
}
