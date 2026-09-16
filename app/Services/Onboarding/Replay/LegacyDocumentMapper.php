<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Replay;

use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Onboarding\LegacyColumn;
use App\Services\Onboarding\LegacyStagingCast;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP3 -- one legacy header + its staged lines -> one
 * {@see DocumentDraft}.
 *
 * This class IS MAPPING-RULES.md §1 and §2, executable. Read that document
 * first; the section references below are load-bearing, not decoration.
 *
 * ── Rule A (§1.1), the single most important thing about this class ──────────
 * The lines of a replayed document are built ONE-FOR-ONE from `stg_acc_detail`
 * rows of that DocID, in AccDetailID order. `stg_tr_detail`,
 * `stg_forct_detail`, `stg_memo_detail` and `stg_tr_payment*` are NEVER read
 * here -- not for INV, not for FRV, not for ADM/ACM. The legacy ledger already
 * contains the double entry (Q12: zero unbalanced documents export-wide at
 * 0.0005); parity does not require Akeed to understand a ticket, it requires
 * Akeed to re-post the recorded lines through its own engine with accounts
 * remapped. Tier B re-derivation is a REPORT (§2.1 / §2.2, decision O10-tierB)
 * and is not built into this class, because a Tier B line that could post is a
 * line that could break the 532-row closing anchor.
 *
 * ── Refuse, never default (§0.3) ────────────────────────────────────────────
 * There is no suspense account, no `?? 0`, no "unknown currency" fallback and
 * no name-based account lookup anywhere below. Every unresolvable input throws
 * {@see LegacyDocumentRefused} with the classification token MAPPING-RULES
 * names, BEFORE the seam is called.
 *
 * ── No purpose codes (§4.1) ─────────────────────────────────────────────────
 * Every replayed line carries an explicit `accountId` and `purposeCode = ''`.
 * `PostingService::targetAccountId()` throws if a line supplies both, and
 * consults `AccountResolver` only when `accountId` is NULL -- so
 * `UnmappedPurposeException` can only fire from code paths the replay never
 * takes. The 93 purposes are a chart-readiness concern, not a parity one.
 *
 * ── Every staged column is TEXT ─────────────────────────────────────────────
 * See {@see LegacyStagingCast} and {@see LegacyAmount}. `Posted` is the literal
 * `'True'`; money is a decimal string summed as exact integer thousandths;
 * dates are parsed, never string-compared.
 */
final class LegacyDocumentMapper
{
    /** Legacy header column names, resolved through LegacyColumn so this class and the loader cannot drift. */
    private const H = [
        'doc_id' => 'DocID',
        'doc_no' => 'DocNo',
        'doc_type' => 'DocType',
        'sub_type' => 'SubType',
        'doc_dt' => 'DocDt',
        'branch' => 'BranchID_FK',
        'posted' => 'Posted',
        'doc_year' => 'DocYear',
        'ref_type' => 'RefType',
        'ref_no' => 'RefNo',
        'ref_code' => 'RefCode',
        'narration' => 'Narration',
    ];

    /** Legacy detail column names. */
    private const D = [
        'id' => 'AccDetailID',
        'doc_id' => 'DocID_FK',
        'acc_id' => 'AccID_FK',
        'debit' => 'Debit',
        'credit' => 'Credit',
        'fc_debit' => 'FCDebit',
        'fc_credit' => 'FCCredit',
        'fc_curr' => 'FcCurrID_FK',
        'fc_rate' => 'FcExchRate',
        'dc' => 'DC',
        'narration' => 'Narration',
        'transaction_type' => 'TransactionType',
        'transaction_dtl_no' => 'TransactionDtlNo',
        'branch' => 'BranchID_FK',
        'doc_dt' => 'DocDt',
        'cheque_no' => 'ChequeNo',
        'cheque_dt' => 'ChequeDt',
        'bank_name' => 'BankName',
        'auth_no' => 'AuthNo',
        'chq_clearance_dt' => 'ChqClearanceDt',
        'reconciled' => 'Reconciled',
        'debit_adj' => 'DebitAdj',
        'credit_adj' => 'CreditAdj',
    ];

    private int $companyId = 0;

    private bool $prepared = false;

    /** @var array<int, array{account_id:?int,resolution:string,party_id:?int,party_role:?string}> */
    private array $accountMap = [];

    /** @var array<int, int|null> legacy Branch_ID => Akeed branch id */
    private array $branchMap = [];

    /** @var array<int, array{code:?string,poison:bool,status:string}> */
    private array $currencyMap = [];

    /**
     * Load every mapping table into memory once. 1,351 accounts / 4 branches /
     * 21 currencies -- an in-memory map is the difference between one query and
     * 179,421 of them, and none of these tables changes during a replay.
     */
    public function prepare(int $companyId): void
    {
        if ($this->prepared && $this->companyId === $companyId) {
            return;
        }

        $this->companyId = $companyId;
        $this->accountMap = [];
        $this->branchMap = [];
        $this->currencyMap = [];

        foreach (DB::connection('legacy_pilot')->table('legacy_acc_map')->where('company_id', $companyId)->get() as $row) {
            $this->accountMap[(int) $row->acc_id] = [
                'account_id' => $row->account_id === null ? null : (int) $row->account_id,
                'resolution' => (string) $row->resolution,
                'party_id' => $row->party_id === null ? null : (int) $row->party_id,
                'party_role' => $row->party_role === null ? null : (string) $row->party_role,
            ];
        }

        foreach (DB::connection('legacy_pilot')->table('map_branch')->where('company_id', $companyId)->get() as $row) {
            $this->branchMap[(int) $row->branch_id_fk] = $row->akeed_branch_id === null ? null : (int) $row->akeed_branch_id;
        }

        foreach (DB::connection('legacy_pilot')->table('map_currency')->where('company_id', $companyId)->get() as $row) {
            $this->currencyMap[(int) $row->curr_id_fk] = [
                'code' => $row->curr_code === null ? null : strtoupper(trim((string) $row->curr_code)),
                'poison' => (bool) $row->is_poison,
                // LP1e: an `unresolved` row is a DERIVATION that failed, not a
                // missing row -- see resolveCurrency().
                'status' => strtolower((string) ($row->status ?? 'mapped')),
            ];
        }

        $this->prepared = true;
    }

    /**
     * @param  object  $header  one stg_acc_header row
     * @param  int  $userId  the staging replay user (§1.3, §8.3)
     *
     * @throws LegacyDocumentRefused
     */
    public function map(object $header, int $companyId, int $userId): MappedDocument
    {
        $this->prepare($companyId);

        $docId = $this->strictId($this->h($header, 'doc_id'));

        if ($docId === null) {
            throw new LegacyDocumentRefused('legacy.header_unreadable', null, 'stg_acc_header row has no readable DocID.');
        }

        $subType = LegacyStagingCast::toString($this->h($header, 'sub_type'));

        if ($subType === null) {
            throw new LegacyDocumentRefused('legacy.doctype_unmapped', $docId, sprintf('Document %d has no SubType.', $docId));
        }

        $subType = strtoupper($subType);

        if (in_array($subType, (array) config('legacy_pilot.replay.out_of_scope_sub_types', []), true)) {
            // §2.11: MAN_INV / MAN_CRN exist lifetime-wide but no document of
            // either falls in the 2025 window. One appearing here is a CENSUS
            // DEFECT -- stop, do not improvise a rule.
            throw new LegacyDocumentRefused(
                'legacy.subtype_out_of_scope',
                $docId,
                sprintf('Document %d is SubType %s, which is out of scope for this phase (MAPPING-RULES §2.11) — this is a census defect, not a document defect.', $docId, $subType)
            );
        }

        $typeMap = (array) config('legacy_pilot.replay.doc_type_map', []);

        if (! array_key_exists($subType, $typeMap)) {
            throw new LegacyDocumentRefused(
                'legacy.doctype_unmapped',
                $docId,
                sprintf('Document %d has SubType %s, which has no entry in legacy_pilot.replay.doc_type_map.', $docId, $subType)
            );
        }

        $docDate = LegacyStagingCast::toDate($this->h($header, 'doc_dt'));

        if ($docDate === null) {
            throw new LegacyDocumentRefused('legacy.docdate_unreadable', $docId, sprintf('Document %d has an unreadable DocDt.', $docId));
        }

        $this->assertOjvInWindow($subType, $header, $docId);

        // §1.2 (e) / O11-branch: the draft's branch is the HEADER's branch. Per
        // line branch is recorded in map_document_line and reported, never posted.
        $legacyBranchId = LegacyStagingCast::toInt($this->h($header, 'branch'));
        $branchId = $this->resolveBranch($legacyBranchId, $docId);

        $lines = DB::connection('legacy_pilot')->table('stg_acc_detail')
            ->where(LegacyColumn::name(self::D['doc_id']), (string) $docId)
            ->orderByRaw('CAST('.LegacyColumn::name(self::D['id']).' AS UNSIGNED) ASC')
            ->get();

        $stagedLineCount = $lines->count();

        if ($stagedLineCount === 0) {
            // §1.6 (3): 2,774 lifetime line-less headers (2,015 of them
            // auto-voided invoices whose lines were deleted). They contribute
            // 0.000 to every TB anchor, which is exactly what skipping produces.
            return $this->skip(MappedDocument::STATUS_SKIPPED_NO_LINES, $stagedLineCount, 0);
        }

        $lineDrafts = [];
        $lineAudits = [];
        $debitMillis = 0;
        $creditMillis = 0;
        $dropped = 0;
        $dcMismatches = 0;
        $fcRebased = 0;
        $offBranch = 0;
        $currencyUnresolved = 0;

        $docNo = LegacyStagingCast::toString($this->h($header, 'doc_no'));

        foreach ($lines as $line) {
            $built = $this->mapLine($line, $header, $subType, $docId, $docNo, $legacyBranchId);

            if ($built === null) {
                $dropped++;

                continue;
            }

            [$draftLine, $audit] = $built;

            $lineDrafts[] = $draftLine;
            $lineAudits[] = $audit;

            if ($audit['derived_side'] === 'debit') {
                $debitMillis += $audit['amount_millis'];
            } else {
                $creditMillis += $audit['amount_millis'];
            }

            $dcMismatches += $audit['dc_mismatch'] ? 1 : 0;
            $fcRebased += $audit['fc_rebased_to_kwd'] ? 1 : 0;
            $offBranch += ($audit['legacy_branch_id_fk'] !== null && $audit['legacy_branch_id_fk'] !== $legacyBranchId) ? 1 : 0;
            $currencyUnresolved += $audit['metadata_currency'] !== null ? 1 : 0;
        }

        if ($lineDrafts === []) {
            // §1.6 (2): every line was a zero-amount placeholder (BDS is where
            // this earns its keep -- 1,248 such lines). Skip, count, report.
            return $this->skip(MappedDocument::STATUS_SKIPPED_ALL_ZERO, $stagedLineCount, $dropped);
        }

        // §1.5 #4 / §5 / decision O6 -- REFUSE, never plug. The comparison is
        // over exact integer thousandths, so it is the staged decimal truth and
        // not a float sum: an imbalance reported here is real, and per §2.6 an
        // imbalance on a JV is the float-drift canary for our OWN loader.
        if ($debitMillis !== $creditMillis) {
            throw new LegacyDocumentRefused(
                'legacy.unbalanced',
                $docId,
                sprintf(
                    'Document %d is out of balance on the staged decimals: debit %s, credit %s, difference %s. O6: refuse, classify, report — never plug.',
                    $docId,
                    LegacyAmount::toDecimalString($debitMillis),
                    LegacyAmount::toDecimalString($creditMillis),
                    LegacyAmount::toDecimalString($debitMillis - $creditMillis)
                )
            );
        }

        $narration = LegacyStagingCast::toString($this->h($header, 'narration')) ?? '';

        // §7: the legacy DocNo rides on transactions.description as a [prefix]
        // so it is visible on every header listing without a join, AND on every
        // line's voucher_number. transactions.reference_number stays OURS.
        if ($docNo !== null) {
            $narration = '['.$docNo.'] '.$narration;
        }

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: $branchId,
            docType: $typeMap[$subType]['doc_type'],
            subType: $typeMap[$subType]['sub_type'],
            docDate: $docDate,
            narration: $narration,
            lines: $lineDrafts,
            // §8.1 -- NEVER a run id, batch number or timestamp.
            idempotencyKey: config('legacy_pilot.replay.idempotency_prefix').':'.$subType.':'.$docId,
            sourceType: $typeMap[$subType]['source_type'],
            sourceId: $docId,
            // §1.3 / §1.2 (d): NEVER set. No Akeed invoice exists for this data,
            // and transactions.invoice_id is a real FK.
            invoiceId: null,
            userId: $userId,
            costCenterId: null,
            // §6: 2025 periods are OPEN. Overriding a lock would log an override
            // and continue, hiding that a period was closed when it should not be.
            allowLockedPeriods: false,
            paymentReference: null,
            // §1.5 #14: a PV draft carrying a payment_id collides on
            // transactions_payment_id_reference_type_unique. Never set it.
            paymentId: null,
            overrideReason: null,
            // Null resolves to docDate. §6's shift trap is guarded by the runner.
            postingDate: null,
        );

        return new MappedDocument(
            status: MappedDocument::STATUS_MAPPABLE,
            draft: $draft,
            lineAudits: $lineAudits,
            stagedDebitMillis: $debitMillis,
            stagedCreditMillis: $creditMillis,
            stagedLineCount: $stagedLineCount,
            droppedZeroLineCount: $dropped,
            dcMismatchLineCount: $dcMismatches,
            fcRebasedLineCount: $fcRebased,
            offBranchLineCount: $offBranch,
            currencyUnresolvedLineCount: $currencyUnresolved,
        );
    }

    /**
     * §2.10. The 2025 OJV is the opening document. The 2026 OJV is WITHHELD as
     * the expected output of LP6's own year-end close, and the 2018-2024 OJVs
     * are staged for reference only -- replaying them would double-count the
     * opening position, because the export DEFINES the 2024-12-31 anchor as the
     * 2025 OJV's own lines.
     *
     * @throws LegacyDocumentRefused
     */
    private function assertOjvInWindow(string $subType, object $header, int $docId): void
    {
        if ($subType !== 'OJV') {
            return;
        }

        $docYear = LegacyStagingCast::toInt($this->h($header, 'doc_year'));
        $openingYear = (int) config('legacy_pilot.replay.opening_ojv_doc_year');

        if ($docYear === $openingYear) {
            return;
        }

        if (in_array($docYear, array_map('intval', (array) config('legacy_pilot.replay.withheld_ojv_doc_years', [])), true)) {
            throw new LegacyDocumentRefused(
                'withheld_ojv',
                $docId,
                sprintf('OJV %d (DocYear %d) is deliberately WITHHELD as the expected output of the LP6 year-end close (PLAN §1.2 / O10).', $docId, $docYear)
            );
        }

        throw new LegacyDocumentRefused(
            'legacy.ojv_out_of_window',
            $docId,
            sprintf('OJV %d (DocYear %s) is an earlier-year opening journal: staged for reference, never replayed (MAPPING-RULES §2.10).', $docId, $docYear ?? 'NULL')
        );
    }

    /**
     * §1.2 (a)-(d). Returns null when the line is dropped as zero-amount.
     *
     * @return array{0: LineDraft, 1: array<string, mixed>}|null
     *
     * @throws LegacyDocumentRefused
     */
    private function mapLine(object $line, object $header, string $subType, int $docId, ?string $docNo, ?int $headerBranchId): ?array
    {
        $accDetailId = $this->strictId($this->d($line, 'id'));

        if ($accDetailId === null) {
            throw new LegacyDocumentRefused('legacy.line_unreadable', $docId, sprintf('Document %d has a line with no readable AccDetailID.', $docId));
        }

        $context = sprintf('doc %d line %d', $docId, $accDetailId);

        // (a) Side and amount come from WHICH MONEY COLUMN IS NON-ZERO, not from
        // DC. The legacy kernel never enforced DC/column agreement (02 §3), and
        // SpDirectRefundAutoinvoice demonstrably writes into the opposite column
        // while keeping DC='D'.
        $debit = LegacyAmount::millis($this->d($line, 'debit'), $context.' Debit', $docId);
        $credit = LegacyAmount::millis($this->d($line, 'credit'), $context.' Credit', $docId);

        if ($debit < 0 || $credit < 0) {
            throw new LegacyDocumentRefused(
                'legacy.negative_amount',
                $docId,
                sprintf('%s carries a negative money column (debit %s, credit %s); the engine cannot express it.', $context, LegacyAmount::toDecimalString($debit), LegacyAmount::toDecimalString($credit))
            );
        }

        if ($debit > 0 && $credit > 0) {
            throw new LegacyDocumentRefused(
                'legacy.both_columns_nonzero',
                $docId,
                sprintf('%s has BOTH Debit and Credit non-zero; it is unmappable to a one-sided LineDraft.', $context)
            );
        }

        if ($debit === 0 && $credit === 0) {
            // §1.6 (1): zero-amount placeholder. Dropped, counted, reported. A
            // dropped zero line cannot change any balance.
            return null;
        }

        $side = $debit > 0 ? 'debit' : 'credit';
        $amountMillis = $debit > 0 ? $debit : $credit;

        $dc = LegacyStagingCast::toString($this->d($line, 'dc'));
        $expectedDc = $side === 'debit' ? 'D' : 'C';
        $dcMismatch = $dc !== null && strtoupper($dc) !== $expectedDc;

        // (b) Account.
        $legacyAccId = LegacyStagingCast::toInt($this->d($line, 'acc_id'));
        [$accountId, $resolution, $partyRef] = $this->resolveAccount($legacyAccId, $docId, $context);

        // (c) Currency, FC and rate.
        $fc = $this->resolveCurrency($line, $docId, $context, $amountMillis);

        $branchOfLine = LegacyStagingCast::toInt($this->d($line, 'branch'));
        $ledgerTypePrefix = (string) config('legacy_pilot.replay.ledger_type_prefix');
        $transactionType = LegacyStagingCast::toString($this->d($line, 'transaction_type'));

        $draftLine = new LineDraft(
            // §4.1: ALWAYS the empty string. Every replayed line is an
            // explicit-account line; supplying both refuses in PostingService.
            purposeCode: '',
            accountId: $accountId,
            side: $side,
            amount: LegacyAmount::toFloat($amountMillis),
            currency: $fc['currency'],
            originalAmount: $fc['original_amount'],
            exchangeRate: $fc['exchange_rate'],
            transactionType: $transactionType,
            partyAccountRef: $partyRef,
            description: LegacyStagingCast::toString($this->d($line, 'narration')),
            serviceType: null,
            // §1.2 (d): always NULL. Akeed's invoice/task tables hold nothing here.
            invoiceId: null,
            invoiceDetailId: null,
            taskId: null,
            ledgerType: $transactionType ?? ($ledgerTypePrefix.$subType),
            partyName: $partyRef === null ? null : 'PARTY-'.$partyRef,
            // §7: the legacy document number, on every line.
            voucherNumber: $docNo,
            chequeNo: LegacyStagingCast::toString($this->d($line, 'cheque_no')),
            chequeDate: LegacyStagingCast::toDate($this->d($line, 'cheque_dt')),
            bankInfo: LegacyStagingCast::toString($this->d($line, 'bank_name')),
            // Blanked at export, so always NULL -- read anyway rather than
            // hard-coding the assumption.
            authNo: LegacyStagingCast::toString($this->d($line, 'auth_no')),
            chequeClearanceDate: LegacyStagingCast::toDate($this->d($line, 'chq_clearance_dt')),
            reconciled: LegacyStagingCast::toBool($this->d($line, 'reconciled')) ? 1 : null,
            settlementChannel: config('legacy_pilot.replay.settlement_channel_prefix').$subType,
        );

        $audit = [
            'legacy_acc_detail_id' => $accDetailId,
            'legacy_doc_id' => $docId,
            'legacy_acc_id_fk' => $legacyAccId,
            'resolution' => $resolution,
            'our_account_id' => $accountId,
            'party_account_ref' => $partyRef,
            'legacy_dc' => $dc === null ? null : strtoupper(substr($dc, 0, 1)),
            'derived_side' => $side,
            'dc_mismatch' => $dcMismatch,
            'amount_millis' => $amountMillis,
            // §1.2 (e) / O11-branch: the dropped per-line dimensions, preserved.
            'legacy_branch_id_fk' => $branchOfLine,
            'legacy_doc_dt' => LegacyStagingCast::toDate($this->d($line, 'doc_dt'))?->toDateString(),
            'legacy_fc_curr_id_fk' => $fc['legacy_curr_id'],
            'legacy_fc_exch_rate' => $fc['legacy_rate'],
            'fc_rebased_to_kwd' => $fc['rebased'],
            'posted_currency' => $fc['currency'],
            'posted_original_amount' => $fc['original_amount'],
            'posted_exchange_rate' => $fc['exchange_rate'],
            // LP1e / R-currency: `legacy_curr_<fk>` when the FC currency could
            // not be derived. The line still POSTED in the base currency --
            // posted_currency says so -- and this is the FC pair's only trace.
            'metadata_currency' => $fc['metadata_currency'],
            'legacy_transaction_dtl_no' => LegacyStagingCast::toString($this->d($line, 'transaction_dtl_no')),
            'legacy_transaction_type' => $transactionType,
            // §1.2 (e): allocation state, never a ledger amount. Cross-check only.
            'legacy_debit_adj' => LegacyAmount::toDecimalString(LegacyAmount::millis($this->d($line, 'debit_adj'), $context.' DebitAdj', $docId)),
            'legacy_credit_adj' => LegacyAmount::toDecimalString(LegacyAmount::millis($this->d($line, 'credit_adj'), $context.' CreditAdj', $docId)),
        ];

        return [$draftLine, $audit];
    }

    /**
     * §1.2 (b) + the party-required rule.
     *
     * @return array{0: int, 1: string, 2: ?int}
     *
     * @throws LegacyDocumentRefused
     */
    private function resolveAccount(?int $legacyAccId, int $docId, string $context): array
    {
        if ($legacyAccId === null || ! array_key_exists($legacyAccId, $this->accountMap)) {
            throw new LegacyDocumentRefused(
                'legacy.account_unmapped',
                $docId,
                sprintf('%s: AccID_FK %s has no legacy_acc_map row. No suspense, no guess.', $context, $legacyAccId ?? 'NULL')
            );
        }

        $entry = $this->accountMap[$legacyAccId];

        if ($entry['resolution'] === 'unclassified' || $entry['account_id'] === null) {
            throw new LegacyDocumentRefused(
                'legacy.account_unmapped',
                $docId,
                sprintf('%s: AccID_FK %d resolves to "%s" with account_id %s — unmappable.', $context, $legacyAccId, $entry['resolution'], $entry['account_id'] ?? 'NULL')
            );
        }

        $pooled = in_array($entry['resolution'], ['pooled_receivable', 'pooled_payable'], true);

        if ($pooled && $entry['party_id'] === null) {
            // The mapping's own invariant, stricter than the engine's
            // (journal_entries.type_reference_id is nullable). Without it, AR/AP
            // parity (LP4 check 3) is impossible: a pooled position that cannot
            // be decomposed by party is a position that cannot be compared to
            // the 109/75-leaf anchors.
            throw new LegacyDocumentRefused(
                'legacy.party_unresolved',
                $docId,
                sprintf('%s: AccID_FK %d pools onto a control leaf but no map_party row claims it.', $context, $legacyAccId)
            );
        }

        return [$entry['account_id'], $entry['resolution'], $pooled ? $entry['party_id'] : null];
    }

    /**
     * §1.2 (c). The three load-bearing points, in order:
     *   1. the rate comes from the LINE, never a master (tblCurrency is poison,
     *      tblMaster is not exported at all);
     *   2. a KWD line FORCES exchangeRate 1.0 and originalAmount == amount --
     *      asserted then normalised, because legacy KWD lines are FC == LC by
     *      kernel rule 3 but carry no guarantee that FcExchRate is exactly
     *      1.000000000000, and copying it would refuse valid documents;
     *   3. an FC line with FC == 0 (or rate <= 0) CANNOT be expressed --
     *      PostingService throws NonNegativeAmountException -- and is re-based
     *      to KWD, with the dropped FcCurrID_FK/FcExchRate recorded. The legacy
     *      RJV party line is exactly this shape, deliberately (03 §9: the FC
     *      balance must not move on a realised-difference posting). Base
     *      currency totals -- the only thing the TB anchors measure -- are
     *      unaffected.
     *
     * ── LP1e, ruling R-currency ─────────────────────────────────────────────
     * A currency that could not be DERIVED is metadata, not a refusal. The
     * currency master these FKs point at (`tblMaster`) was never exported, so
     * `map_currency` is built from line usage
     * ({@see \App\Services\Onboarding\LegacyCurrencyMapper}) and 25 of the
     * export's 27 distinct FKs legitimately resolve to nothing. Refusing them
     * would refuse documents whose LC (KWD) double entry is complete, balanced
     * and exactly what every trial-balance anchor measures -- to protect a
     * descriptive FC pair. So: the line posts in the base currency, the FK is
     * recorded as `legacy_curr_<fk>` in the line audit's `metadata_currency`,
     * and it is counted per document.
     *
     * TWO things still refuse, and only these two:
     *   - a POISON currency (the tblCurrency junk rows), which is R3 and is
     *     about a rate that would be catastrophically wrong if believed; and
     *   - a line with NO LC amount, because then the FC pair was the only
     *     money on the line and dropping it would silently lose it.
     *
     * @return array{currency:string,original_amount:float,exchange_rate:float,rebased:bool,legacy_curr_id:?int,legacy_rate:?float,metadata_currency:?string}
     *
     * @throws LegacyDocumentRefused
     */
    private function resolveCurrency(object $line, int $docId, string $context, int $amountMillis): array
    {
        $base = (string) config('accounting.engine.base_currency', 'KWD');
        $amount = LegacyAmount::toFloat($amountMillis);

        $currId = LegacyStagingCast::toInt($this->d($line, 'fc_curr'));
        $legacyRate = LegacyAmount::toDecimalFloat($this->d($line, 'fc_rate'), $context.' FcExchRate', $docId);

        $baseLine = [
            'currency' => $base,
            'original_amount' => $amount,
            'exchange_rate' => 1.0,
            'rebased' => false,
            'legacy_curr_id' => $currId,
            'legacy_rate' => $legacyRate,
            'metadata_currency' => null,
        ];

        if ($currId === null) {
            // No FC currency stamped at all: a base-currency line by definition.
            return $baseLine;
        }

        $known = array_key_exists($currId, $this->currencyMap);

        if ($known && $this->currencyMap[$currId]['poison']) {
            // R3: tblCurrency carries XYZ 2500, xyz 3500, abc 0.20121, a blank
            // code and a code literally "2". LP1.4 already proved no in-window
            // line references one -- meeting one here means that proof was wrong.
            throw new LegacyDocumentRefused(
                'legacy.currency_poison',
                $docId,
                sprintf('%s: FcCurrID_FK %d is a QUARANTINED poison currency row. LP1.4 proved no in-window line references one; this contradicts that proof.', $context, $currId)
            );
        }

        $code = $known ? $this->currencyMap[$currId]['code'] : null;
        $unresolved = ! $known
            || $code === null
            || $code === ''
            || $this->currencyMap[$currId]['status'] === 'unresolved';

        if ($unresolved) {
            if ($amountMillis <= 0) {
                // No LC amount: the FC pair was the only money on this line,
                // and posting it as a zero base-currency line would lose it.
                throw new LegacyDocumentRefused(
                    'legacy.currency_unmapped',
                    $docId,
                    sprintf('%s: FcCurrID_FK %d is unresolved AND the line carries no LC amount — there is nothing to post in the base currency.', $context, $currId)
                );
            }

            return array_replace($baseLine, ['metadata_currency' => 'legacy_curr_'.$currId]);
        }

        $fcAmount = LegacyAmount::toDecimalFloat($this->d($line, 'fc_debit'), $context.' FCDebit', $docId)
            + LegacyAmount::toDecimalFloat($this->d($line, 'fc_credit'), $context.' FCCredit', $docId);

        if ($code === $base) {
            // Legacy kernel rule 3: a base-currency line is FC == LC. Assert it,
            // THEN normalise -- a line that was never FC == LC is a data defect
            // we must surface, not silently rewrite.
            if (abs($fcAmount - $amount) > (float) config('legacy_pilot.replay.fc_lc_tolerance')) {
                throw new LegacyDocumentRefused(
                    'legacy.fc_lc_mismatch',
                    $docId,
                    sprintf('%s: base-currency line has FC %s but LC %s.', $context, $fcAmount, $amount)
                );
            }

            return $baseLine;
        }

        if ($fcAmount <= 0.0 || $legacyRate <= 0.0) {
            // §1.2 (c) rule 3 -- the RJV party-line shape. Re-base, record what
            // was dropped, move on.
            return [
                'currency' => $base,
                'original_amount' => $amount,
                'exchange_rate' => 1.0,
                'rebased' => true,
                'legacy_curr_id' => $currId,
                'legacy_rate' => $legacyRate,
                'metadata_currency' => null,
            ];
        }

        return [
            'currency' => $code,
            'original_amount' => $fcAmount,
            'exchange_rate' => $legacyRate,
            'rebased' => false,
            'legacy_curr_id' => $currId,
            'legacy_rate' => $legacyRate,
            'metadata_currency' => null,
        ];
    }

    /**
     * @throws LegacyDocumentRefused
     */
    private function resolveBranch(?int $legacyBranchId, int $docId): ?int
    {
        if ($legacyBranchId === null) {
            return null;
        }

        if (! array_key_exists($legacyBranchId, $this->branchMap)) {
            throw new LegacyDocumentRefused(
                'legacy.branch_unmapped',
                $docId,
                sprintf('Document %d: BranchID_FK %d has no map_branch row.', $docId, $legacyBranchId)
            );
        }

        return $this->branchMap[$legacyBranchId];
    }

    private function skip(string $status, int $stagedLineCount, int $dropped): MappedDocument
    {
        return new MappedDocument(
            status: $status,
            draft: null,
            lineAudits: [],
            stagedDebitMillis: 0,
            stagedCreditMillis: 0,
            stagedLineCount: $stagedLineCount,
            droppedZeroLineCount: $dropped,
            dcMismatchLineCount: 0,
            fcRebasedLineCount: 0,
            offBranchLineCount: 0,
            currencyUnresolvedLineCount: 0,
        );
    }

    /**
     * A staged IDENTITY column, strictly. {@see LegacyStagingCast::toInt()} is
     * deliberately lenient -- it casts, so `'abc'` reads as 0 -- which is right
     * for an FK (0 simply fails to resolve and refuses the document) and wrong
     * for `DocID` / `AccDetailID`, where a 0 would become a real idempotency key
     * and a real audit row. These two are the replay's identity, so they are
     * read strictly and a non-numeric value is a refusal.
     */
    private function strictId(mixed $value): ?int
    {
        $token = LegacyStagingCast::toString($value);

        return $token !== null && preg_match('/^\d+$/', $token) === 1 ? (int) $token : null;
    }

    private function h(object $row, string $key): mixed
    {
        return $row->{LegacyColumn::name(self::H[$key])} ?? null;
    }

    private function d(object $row, string $key): mixed
    {
        return $row->{LegacyColumn::name(self::D[$key])} ?? null;
    }
}
