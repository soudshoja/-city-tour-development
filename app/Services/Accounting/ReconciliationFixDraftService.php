<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\Account;
use App\Models\ReconciliationFixDraft;
use App\Models\User;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): FIX-NOW drill-down panel 3 — "actions that DRAFT a correcting
 * document for the normal approval path, never posted directly." {@see self::create()} is the
 * ONLY thing a fix-now click does: it writes one `reconciliation_fix_drafts` row and nothing else
 * — no ledger write happens here. {@see self::post()} is the distinct, later, "normal approval
 * path" step that actually calls {@see PostingService::post()} — matching the same
 * draft-then-approve shape every other voucher with a draft state in this codebase already uses
 * (e.g. ReceiptVoucherController::store(), which also creates a PENDING row first and posts it in
 * a separate step gated by {@see VoucherOptions::approvalThreshold()}).
 */
final class ReconciliationFixDraftService
{
    public function __construct(private readonly AccountResolver $accountResolver) {}

    public const KNOWN_KINDS = [
        ReconciliationFixDraft::KIND_BANK_CHARGE_PV,
        ReconciliationFixDraft::KIND_GATEWAY_TIMING_JV,
        ReconciliationFixDraft::KIND_UNAPPLY_REAPPLY_RECEIPT,
        ReconciliationFixDraft::KIND_WRITEOFF_PROPOSAL,
    ];

    private const DOC_TYPE_FOR_KIND = [
        ReconciliationFixDraft::KIND_BANK_CHARGE_PV => 'PV',
        ReconciliationFixDraft::KIND_GATEWAY_TIMING_JV => 'JV',
        ReconciliationFixDraft::KIND_UNAPPLY_REAPPLY_RECEIPT => 'JV',
        ReconciliationFixDraft::KIND_WRITEOFF_PROPOSAL => 'JV',
    ];

    public function create(
        int $companyId,
        int $branchId,
        int $accountId,
        string $kind,
        float $amount,
        string $narration,
        ?int $proposalId,
        ?User $actor,
    ): ReconciliationFixDraft {
        app(ReconciliationService::class)->assertCanReconcile($actor);

        if (! in_array($kind, self::KNOWN_KINDS, true)) {
            throw new \InvalidArgumentException("Unknown fix-now kind '{$kind}'.");
        }

        $target = (string) config("accounting.reconciliation.fix_now_targets.{$kind}", '');
        $isPurposeCode = str_starts_with($target, '@');

        $draft = ReconciliationFixDraft::create([
            'company_id' => $companyId,
            'proposal_id' => $proposalId,
            'account_id' => $accountId,
            'branch_id' => $branchId,
            'kind' => $kind,
            'doc_type' => self::DOC_TYPE_FOR_KIND[$kind],
            'amount' => $amount,
            'narration' => $narration,
            'target_purpose_code' => $isPurposeCode ? mb_substr($target, 1) : null,
            'target_account_code' => $isPurposeCode ? null : ($target !== '' ? $target : null),
            'status' => ReconciliationFixDraft::STATUS_DRAFT,
            'created_by' => $actor?->id,
        ]);

        AccountingLog::write(
            action: 'fix_now_draft_created',
            companyId: $companyId,
            subjectType: 'reconciliation_fix_draft',
            subjectId: (int) $draft->id,
            after: ['kind' => $kind, 'amount' => $amount, 'account_id' => $accountId],
            actorId: $actor?->id,
        );

        return $draft;
    }

    /**
     * The "normal approval path" step — the ONLY place a fix-now draft becomes a real posted
     * document. Refuses {@see ReconciliationFixDraft::KIND_UNAPPLY_REAPPLY_RECEIPT} outright (see
     * that model's own {@see ReconciliationFixDraft::isPostable()} docblock — deferred to the
     * Receipt Voucher screen's own un-apply action, out of this v0's generic-posting scope).
     */
    public function post(ReconciliationFixDraft $draft, ?User $actor): ReconciliationFixDraft
    {
        app(ReconciliationService::class)->assertCanReconcile($actor);

        if ($draft->status !== ReconciliationFixDraft::STATUS_DRAFT) {
            throw new \RuntimeException('Only a draft fix-now document can be posted.');
        }

        if (! $draft->isPostable()) {
            throw new \RuntimeException(
                "Fix-now kind '{$draft->kind}' cannot be auto-posted here; action it from its own module."
            );
        }

        $gapAccount = Account::withoutGlobalScopes()->findOrFail($draft->account_id);
        $targetAccount = $this->resolveTargetAccount($draft);

        [$debitAccountId, $creditAccountId] = $this->linesFor($draft->kind, (int) $gapAccount->id, (int) $targetAccount->id, $draft->amount);

        $amount = abs($draft->amount);

        $lines = [
            new LineDraft(
                purposeCode: '',
                accountId: $debitAccountId,
                side: 'debit',
                amount: $amount,
                currency: 'KWD',
                originalAmount: $amount,
                exchangeRate: 1.0,
                transactionType: 'RECONCILIATION_FIX',
                description: $draft->narration,
            ),
            new LineDraft(
                purposeCode: '',
                accountId: $creditAccountId,
                side: 'credit',
                amount: $amount,
                currency: 'KWD',
                originalAmount: $amount,
                exchangeRate: 1.0,
                transactionType: 'RECONCILIATION_FIX',
                description: $draft->narration,
            ),
        ];

        $documentDraft = new DocumentDraft(
            companyId: (int) $draft->company_id,
            branchId: (int) $draft->branch_id,
            docType: $draft->doc_type,
            subType: null,
            docDate: now(),
            narration: $draft->narration,
            lines: $lines,
            sourceType: 'ReconciliationFixDraft',
            sourceId: $draft->id,
            userId: $actor?->id,
        );

        $posted = app(PostingService::class)->post($documentDraft, $actor?->id);

        $draft->status = ReconciliationFixDraft::STATUS_POSTED;
        $draft->transaction_id = $posted->transaction->id;
        $draft->posted_by = $actor?->id;
        $draft->posted_at = now();
        $draft->save();

        AccountingLog::write(
            action: 'post',
            companyId: (int) $draft->company_id,
            subjectType: 'reconciliation_fix_draft',
            subjectId: (int) $draft->id,
            transactionId: (int) $posted->transaction->id,
            after: ['status' => 'posted', 'kind' => $draft->kind],
            actorId: $actor?->id,
        );

        return $draft->refresh();
    }

    public function discard(ReconciliationFixDraft $draft, string $reason, ?User $actor): ReconciliationFixDraft
    {
        app(ReconciliationService::class)->assertCanReconcile($actor);

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required to discard a fix-now draft.');
        }

        if ($draft->status !== ReconciliationFixDraft::STATUS_DRAFT) {
            throw new \RuntimeException('Only a draft fix-now document can be discarded.');
        }

        $draft->status = ReconciliationFixDraft::STATUS_DISCARDED;
        $draft->reason = $reason;
        $draft->save();

        AccountingLog::write(
            action: 'discard',
            companyId: (int) $draft->company_id,
            subjectType: 'reconciliation_fix_draft',
            subjectId: (int) $draft->id,
            reason: $reason,
            actorId: $actor?->id,
        );

        return $draft->refresh();
    }

    private function resolveTargetAccount(ReconciliationFixDraft $draft): Account
    {
        if ($draft->target_purpose_code !== null) {
            try {
                return $this->accountResolver->resolve($draft->target_purpose_code, (int) $draft->company_id);
            } catch (UnmappedPurposeException $e) {
                throw new \RuntimeException(
                    "Fix-now target purpose code '{$draft->target_purpose_code}' is not configured for company #{$draft->company_id}.",
                    previous: $e
                );
            }
        }

        $account = Account::withoutGlobalScopes()
            ->where('company_id', $draft->company_id)
            ->where('code', $draft->target_account_code)
            ->first();

        if ($account === null) {
            throw new \RuntimeException(
                "Fix-now target account code '{$draft->target_account_code}' is not configured for company #{$draft->company_id}."
            );
        }

        return $account;
    }

    /**
     * @return array{0:int,1:int} [debitAccountId, creditAccountId]
     */
    private function linesFor(string $kind, int $gapAccountId, int $targetAccountId, float $amount): array
    {
        return match ($kind) {
            // Bank-charge PV: Dr Bank Charges Expense / Cr the bank/cash leaf — a charge the
            // company's own bank silently deducted reduces the book side to match reality.
            ReconciliationFixDraft::KIND_BANK_CHARGE_PV => [$targetAccountId, $gapAccountId],

            // Gateway-timing JV: absorbs the difference against 5147, sign-aware — a POSITIVE
            // gap (book > confirmed, i.e. the leaf's normal-balance-signed balance is too high)
            // means the leaf must be credited down and 5147 debited to balance; negative is the
            // mirror.
            ReconciliationFixDraft::KIND_GATEWAY_TIMING_JV => $amount >= 0
                ? [$targetAccountId, $gapAccountId]
                : [$gapAccountId, $targetAccountId],

            // Write-off proposal: Dr 5218 Write Off (expense) / Cr the stale control/clearing leaf
            // when the leaf's balance is positive (an asset-side stale item being written down);
            // mirrored when negative.
            ReconciliationFixDraft::KIND_WRITEOFF_PROPOSAL => $amount >= 0
                ? [$targetAccountId, $gapAccountId]
                : [$gapAccountId, $targetAccountId],

            default => throw new \RuntimeException("No posting shape defined for fix-now kind '{$kind}'."),
        };
    }
}
