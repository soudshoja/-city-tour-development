<?php

declare(strict_types=1);

namespace App\Services\Accounting\Replay;

use App\Models\InvoiceDetail;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * CT-A3 wave 2 (W2-1) — the `sale` class: one `INV`/`SALE` document per live `invoice_details`
 * row, under `InvoiceController::postSaleJournalEntries()`'s own key
 * `invoice-detail:{id}:sale`.
 *
 * The line shape is built by the SAME {@see SaleDraftBuilder} the live feeder uses, fed a
 * {@see SaleDraftInput} assembled from the same columns
 * (`InvoiceController.php:1949-1976`) — R-CT1's gross four-line document, with the cost pair
 * omitted when there is no supplier cost and the AR/revenue pair omitted when there is no sell
 * (CT-A3 wave 1 §5.2). An empty line array is `NOTHING_TO_POST`, not a refusal: on the City
 * Travelers population that is the 7 units carrying `task_price = 0.000` AND `tasks.total = 0.000`.
 *
 * Deliberately NOT replayed here: the `supplier_charge_rules` lines
 * (`InvoiceController.php:2003-2039`). Those fire once per reference through
 * {@see \App\Services\Accounting\SupplierChargeRuleResolver::recordFiring()}, whose dedup contract
 * requires the firing row to be written inside the posting transaction; a backfill that re-fired
 * them would post charges the live feeder had already posted under a different document. A
 * historical backfill replays the base sale; live traffic keeps its own charge rules.
 */
final class SaleReplaySource implements ReplaySource
{
    use ChecksExistingDocument;

    public function __construct(private readonly PostingService $posting) {}

    public function name(): string
    {
        return 'sale';
    }

    public function idempotencyKeyFor(Model $row): string
    {
        return 'invoice-detail:'.$row->getKey().':sale';
    }

    public function describe(Model $row): string
    {
        return 'invoice_detail #'.$row->getKey();
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
            ->when($from, fn ($q) => $q->whereDate('invoices.invoice_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('invoices.invoice_date', '<=', $to))
            ->orderBy('invoices.invoice_date')
            ->orderBy('invoice_details.id')
            ->select('invoice_details.*')
            ->with(['invoice.client', 'invoice.agent', 'task.supplier']);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->cursor();
    }

    public function replay(Model $row): ReplayOutcome
    {
        /** @var InvoiceDetail $row */
        $invoice = $row->invoice;
        $task = $row->task;
        $agent = $invoice?->agent;

        if ($invoice === null || $task === null || $agent === null) {
            return ReplayOutcome::skipped($row->id, 'no_invoice_task_or_agent');
        }

        $companyId = (int) ($agent->branch?->company_id ?? 0);

        if ($companyId <= 0) {
            return ReplayOutcome::refused($row->id, 'unresolved_company');
        }

        $key = $this->idempotencyKeyFor($row);
        $existing = $this->existingDocument($companyId, $key);

        if ($existing !== null) {
            return ReplayOutcome::posted($row->id, (int) $existing->id, null, true);
        }

        $selling = round((float) ($row->task_price ?? 0), 3);
        $cost = round((float) ($task->total ?? 0), 3);
        $serviceType = (string) $task->type;
        $clientName = $invoice->client?->name ?? $invoice->client?->full_name ?? '';
        $supplier = $task->supplier;

        try {
            $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
                serviceType: $serviceType,
                sellAmount: $selling,
                costAmount: $cost,
                postingBasis: SaleDraftBuilder::resolvePostingBasis($companyId, $serviceType),
                recognitionTiming: SaleDraftBuilder::resolveRecognitionTiming($companyId, $serviceType),
                clientId: $invoice->client_id,
                clientName: $clientName,
                supplierId: $supplier?->id,
                supplierName: $supplier?->name,
                agentId: $agent->id,
                agentName: $agent->name,
                invoiceId: $invoice->id,
                invoiceDetailId: $row->id,
                taskId: $task->id,
                currency: (string) config('accounting.engine.base_currency'),
                receivableDescription: 'Invoice created for (Assets): '.$clientName,
                payableDescription: 'Cost of '.$task->reference.' owed to supplier: '.($supplier?->name ?? 'Unknown Supplier'),
                revenueDescription: 'Invoice created for (Income): '.$task->reference,
                marginPositiveDescription: 'Margin earned on '.$task->reference,
                marginNegativeDescription: 'Margin shortfall (sold below cost) on '.$task->reference,
                costDescription: 'Supplier cost booked for '.$task->reference,
            ));

            if ($lines === []) {
                return ReplayOutcome::skipped($row->id, 'nothing_to_post');
            }

            $draft = new DocumentDraft(
                companyId: $companyId,
                branchId: (int) ($agent->branch_id ?? 0),
                docType: 'INV',
                subType: 'SALE',
                docDate: Carbon::parse($invoice->invoice_date),
                narration: 'Invoice created for (Income): '.$task->reference,
                lines: $lines,
                idempotencyKey: $key,
                invoiceId: $invoice->id,
            );

            $posted = $this->posting->post($draft);

            return ReplayOutcome::posted($row->id, (int) $posted->transaction->id, $selling);
        } catch (\Throwable $e) {
            return ReplayOutcome::refused($row->id, $e->getMessage(), $e, $selling);
        }
    }
}
