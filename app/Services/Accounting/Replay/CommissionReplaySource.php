<?php

declare(strict_types=1);

namespace App\Services\Accounting\Replay;

use App\Models\InvoiceDetail;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * CT-A3 wave 2 (W2-1) — the `commission` class: one `JV`/`AGENT_COMMISSION` document per
 * `invoice_details` row carrying a positive `commission`, under
 * `InvoiceController::createProfitEntries()`'s own key `invoice-detail:{id}:agent-commission`.
 *
 * The purposes are wave 1's E4 pair — `COMMISSION_EXPENSE` (5130) / `COMMISSION_PAYABLE` (2210),
 * never the payroll pair `SALARY_EXPENSE` (5160) / `SALARY_PAYABLE` (2201) that CT-F38 found
 * KWD 15,207.752 of sales commission sitting on. A commission with no positive amount is
 * `no_commission`, a skip, exactly as the live feeder returns early on `$commission <= 0`.
 */
final class CommissionReplaySource implements ReplaySource
{
    use ChecksExistingDocument;

    public function __construct(private readonly PostingService $posting) {}

    public function name(): string
    {
        return 'commission';
    }

    public function idempotencyKeyFor(Model $row): string
    {
        return 'invoice-detail:'.$row->getKey().':agent-commission';
    }

    public function describe(Model $row): string
    {
        return 'invoice_detail #'.$row->getKey().' (commission)';
    }

    public function rows(int $companyId, ?Carbon $from, ?Carbon $to, ?int $limit): iterable
    {
        $query = InvoiceDetail::withoutGlobalScopes()
            ->whereNull('invoice_details.deleted_at')
            ->join('invoices', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->whereNull('invoices.deleted_at')
            ->join('agents', 'agents.id', '=', 'invoices.agent_id')
            ->join('branches', 'branches.id', '=', 'agents.branch_id')
            ->where('branches.company_id', $companyId)
            ->where('invoice_details.commission', '>', 0)
            ->when($from, fn ($q) => $q->whereDate('invoices.invoice_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('invoices.invoice_date', '<=', $to))
            ->orderBy('invoices.invoice_date')
            ->orderBy('invoice_details.id')
            ->select('invoice_details.*')
            ->with(['invoice.agent', 'task']);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->cursor();
    }

    public function replay(Model $row): ReplayOutcome
    {
        /** @var InvoiceDetail $row */
        $invoice = $row->invoice;
        $agent = $invoice?->agent;

        if ($invoice === null || $agent === null) {
            return ReplayOutcome::skipped($row->id, 'no_invoice_or_agent');
        }

        $companyId = (int) ($agent->branch?->company_id ?? 0);

        if ($companyId <= 0) {
            return ReplayOutcome::refused($row->id, 'unresolved_company');
        }

        $commission = round((float) ($row->commission ?? 0), 3);

        if ($commission <= 0) {
            return ReplayOutcome::skipped($row->id, 'no_commission');
        }

        $key = $this->idempotencyKeyFor($row);
        $existing = $this->existingDocument($companyId, $key);

        if ($existing !== null) {
            return ReplayOutcome::posted($row->id, (int) $existing->id, null, true);
        }

        $task = $row->task;
        $currency = (string) config('accounting.engine.base_currency');

        try {
            $draft = new DocumentDraft(
                companyId: $companyId,
                branchId: (int) ($agent->branch_id ?? 0),
                docType: 'JV',
                subType: 'AGENT_COMMISSION',
                docDate: Carbon::parse($invoice->invoice_date),
                narration: 'Agent commission: '.$agent->name,
                lines: [
                    new LineDraft(
                        purposeCode: 'COMMISSION_EXPENSE',
                        accountId: null,
                        side: 'debit',
                        amount: $commission,
                        currency: $currency,
                        originalAmount: $commission,
                        exchangeRate: 1.0,
                        transactionType: 'AGENT_COMMISSION_EXPENSE',
                        partyAccountRef: $agent->id,
                        description: 'Agents Commissions for (Expenses): '.$agent->name,
                        invoiceId: $invoice->id,
                        invoiceDetailId: $row->id,
                        taskId: $task?->id,
                        ledgerType: 'expense',
                        partyName: $agent->name,
                    ),
                    new LineDraft(
                        purposeCode: 'COMMISSION_PAYABLE',
                        accountId: null,
                        side: 'credit',
                        amount: $commission,
                        currency: $currency,
                        originalAmount: $commission,
                        exchangeRate: 1.0,
                        transactionType: 'AGENT_COMMISSION_PAYABLE',
                        partyAccountRef: $agent->id,
                        description: 'Agents Commissions for (Liabilities): '.$agent->name,
                        invoiceId: $invoice->id,
                        invoiceDetailId: $row->id,
                        taskId: $task?->id,
                        ledgerType: 'payable',
                        partyName: $agent->name,
                    ),
                ],
                idempotencyKey: $key,
                invoiceId: $invoice->id,
            );

            $posted = $this->posting->post($draft);

            return ReplayOutcome::posted($row->id, (int) $posted->transaction->id, $commission);
        } catch (\Throwable $e) {
            return ReplayOutcome::refused($row->id, $e->getMessage(), $e, $commission);
        }
    }
}
