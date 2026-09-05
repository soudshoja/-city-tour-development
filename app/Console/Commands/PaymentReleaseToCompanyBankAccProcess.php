<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Charge;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\GatewaySettlementService;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PaymentIdempotencyKey;
use App\Services\Accounting\PostingSeam;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * W7.X cutover (w7-final-gate.md §1a, BLOCKER 2). Previously wrote raw `Transaction`/
 * `JournalEntry` unconditionally, with zero `PostingSeam` awareness, from this command's own
 * scheduled daily run (`app/Console/Kernel.php`) for EVERY company with a completed-but-
 * unreleased payment group. Cut over the same way every other W1-W7 feeder was: constructor DI
 * is safe here (unlike `ClientController::addCredit()` one file over — this class has no `new
 * PaymentReleaseToCompanyBankAccProcess` call site anywhere; it is always resolved through the
 * container, by the scheduler/Artisan).
 *
 * Per group (companyId, gateway, date):
 *   - OFF: `$legacy` is the ORIGINAL per-group body, moved verbatim (byte parity vs HEAD) --
 *     still resolving accounts by id/FK (`Charge::acc_bank_id`/`acc_fee_bank_id`/`acc_fee_id`),
 *     exactly as HEAD did (these were already id-based lookups, never name-based -- the census
 *     finding was "zero PostingSeam awareness", not "resolves by name").
 *   - ON: a plain JV -- no client/party is involved, this is a pure bank reclassification (money
 *     that already cleared into the gateway's clearing account settles into the company's real
 *     bank account) -- mirroring BankPaymentController::clear()'s own JV shape (w5-brief.md
 *     §W5.P cheque-clearance convention: "a plain JV -- no sub_type is minted for this ... not
 *     itself a new payment event, only a bank reclassification"). Dr the company's configured
 *     bank leaf (`Charge::acc_bank_id`, passed as an explicit `LineDraft::$accountId` -- a
 *     company-chosen bank leaf has no single canonical purpose code of its own, since a company
 *     can have more than one bank account; unlike `BankPaymentController`/`CreditController`,
 *     this feeder does NOT pre-validate it via `AccountResolver::assertUnderBankGroup()` before
 *     the seam call -- see the ON-draft-building block's own comment for why that would risk the
 *     OFF path) / Cr `GATEWAY_CLEARING_{GATEWAY}` (purpose code -- the SAME canonical per-gateway
 *     clearing leaf `ClientController::addCredit()`'s own W7.X ON-path draft debits on receipt,
 *     and the same leaf `PaymentController`'s W2 cutover / `CheckMyFatoorahPayments`'s W1 cutover
 *     already debit for a gateway payment -- this command is that pipeline's other end: the
 *     clearing leaf draining as funds move to the real bank). Purpose codes only for the clearing
 *     leg, never `Account::where('name', ...)`.
 *   - Idempotency key: `PaymentIdempotencyKey::forPaymentReleaseGroup()` -- keyed on the exact SET
 *     of payment ids in this group (see that method's own docblock for why a bare
 *     company/gateway/date key would silently swallow a later, genuinely separate batch).
 */
class PaymentReleaseToCompanyBankAccProcess extends Command
{
    protected $signature = 'app:payment-release-to-company-bankacc-process';
    protected $description = 'Process daily payments and generate journal entries to complete pay invoice';

    public function __construct(
        private PostingSeam $seam,
    ) {
        parent::__construct();
    }

    public function handle()
    {
        Log::info('[Info] Starting daily payment release task...');

        $payments = Payment::where('completed', 0)
            ->where('status', 'completed')
            ->with(['client.agent.branch.company'])
            ->get();

        // Group by payment_date (formatted) and payment_gateway
        $grouped = $payments->groupBy(function ($payment) {
            $date = Carbon::parse($payment->payment_date)->format('Y-m-d');
            $gateway = $payment->payment_gateway ?? 'unknown';
            return $date . '|' . $gateway;
        });

        foreach ($grouped as $key => $groupPayments) {
            [$date, $gateway] = explode('|', $key);

            try {
                $firstPayment = $groupPayments->first();
                $client = $firstPayment->client;
                $company = $client->agent->branch->company ?? null;

                if (!$company) {
                    Log::warning("[Warning] Skipped group $key: Company not found.");
                    continue;
                }

                $chargeRecord = Charge::where('name', 'LIKE', "%$gateway%")
                    ->where('company_id', $company->id)
                    ->first();

                if (!$chargeRecord) {
                    Log::warning("[Warning] No charge record found for gateway: {$gateway} in group $key");
                    continue;
                }

                // Retrieve Accounts
                $bankAccountAccRecord = Account::where('id', $chargeRecord->acc_bank_id)
                    ->where('company_id', $company->id)->first();

                $tapAccount = Account::where('id', $chargeRecord->acc_fee_id)
                    ->where('company_id', $company->id)->first();

                $bankPaymentFee = Account::where('id', $chargeRecord->acc_fee_bank_id)
                    ->where('company_id', $company->id)->first();

                if (!$bankAccountAccRecord || !$tapAccount || !$bankPaymentFee) {
                    Log::warning("[Warning] One or more account records missing for group $key");
                    continue;
                }

                // Shared, needed-by-both-paths: how much this group actually settles. A pure
                // read/aggregation over already-posted JournalEntry rows -- not itself a ledger
                // write, so computing it once here (rather than duplicating inside $legacy)
                // changes nothing about which path performs which write.
                $totalAmount = 0;

                foreach ($groupPayments as $payment) {
                    $journalEntry = JournalEntry::where('company_id', $company->id)
                        ->where('voucher_number', $payment->voucher_number)
                        ->where('type', 'charges')
                        ->first();

                    if (!$journalEntry) {
                        Log::warning("[Warning] Journal entry not found for Payment ID {$payment->id}");
                        continue;
                    }

                    $netAmount = $payment->amount - $journalEntry->debit;

                    // W7.Y fix (gate item 3, CRITICAL CONSISTENCY REQUIREMENT): this uniform
                    // `amount - fee` formula is correct ONLY for a company-borne payment, whose
                    // topup draft (both legacy's ENTRY1/addCredit()'s legacy closure, and
                    // ClientController::addCredit()'s ON-path draft) debits GATEWAY_CLEARING for
                    // exactly that net figure. A CLIENT-borne payment's topup draft debits clearing
                    // for MORE than that (A + g - f, not A - f -- see addCredit()'s own STEP 4
                    // docblock) because the client's own gateway fee (g) also cleared through the
                    // SAME gateway-clearing account; crediting clearing here for only A - f on
                    // release would strand a residual g in GATEWAY_CLEARING_{gateway} forever.
                    //
                    // Rather than re-deriving g from $paidBy/ChargeService here (duplicating
                    // addCredit()'s own bearer logic and risking drift from it), read back what was
                    // ACTUALLY recognised as fee-recovery income for this exact voucher — the same
                    // voucher_number both the legacy closure's ENTRY3 ("Gateway Fee Recovery from
                    // Client: ...", credits f) and the ON-path's GATEWAY_FEE_RECOVERY line (credits
                    // g) post under `type`/`ledgerType` 'income'. Adding that credit back onto the
                    // net figure recovers the EXACT amount that was actually credited to clearing's
                    // counterpart advance/income legs for this payment, on EITHER path, without
                    // needing to know which path posted it or hard-code the bearer formula twice:
                    //   - company-borne (either path): no such entry exists -> +0, unchanged.
                    //   - client-borne, ON path: entry credits g -> nets to (A - f) + g = A + g - f,
                    //     exactly matching addCredit()'s own client-bears clearing debit.
                    //   - client-borne, OFF/legacy path: entry credits f -> nets to (A - f) + f = A,
                    //     exactly matching legacy's own ENTRY1 debit (assetAmount = A for a
                    //     client-borne payment) -- closing a pre-existing legacy-side residual this
                    //     release job carried before this fix (release credited A - f while legacy
                    //     had debited the full A), as a beneficial side effect of reading real
                    //     posted data instead of re-deriving the formula.
                    // GENERALIZES to client-borne INVOICE payments too, not just topups: verified
                    // against InvoiceController::createGatewayFeeRecoveryEntries()'s own legacy
                    // closure, which credits the SAME 'Gateway Fee Recovery' account under
                    // type='income' and voucher_number=$payment->voucher_number for
                    // $grossUpAmount = round($accountingFee + $markupProfit + $roundingProfit, 3)
                    // -- i.e. grossUp = f+markup+rounding ≡ g, the identical "what the client was
                    // actually charged at the gateway" quantity this docblock derives above for
                    // the topup path. This lookup requires no special-casing to pick that up.
                    //
                    // OPUS GATE HARDENING (W7.Y re-verify, item A): the lookup is guarded on a
                    // NON-NULL voucher_number, and ordered (the ternary just below short-circuits
                    // to `null` before ever querying when voucher_number is null — see its own
                    // reasoning immediately following). `payments.voucher_number` is a
                    // NULLABLE column (2025_03_24_112254_update_columns_in_payments_table.php),
                    // and Eloquent rewrites `->where('col', null)` into `whereNull('col')`
                    // (Illuminate\Database\Query\Builder::where(), the `is_null($value)` branch).
                    // Without the guard, a payment with a NULL voucher_number would match ANY
                    // income-typed journal_entries row in this company that also carries a NULL
                    // voucher_number -- and the ordinary legacy revenue rows written by
                    // InvoiceController (e.g. its 'Invoice created for (Income)' entry, which
                    // never sets voucher_number) are exactly that shape, crediting a whole task's
                    // SELLING price. Picking one of those up here would inflate this group's bank
                    // settlement by an unrelated invoice's revenue. Only a real, voucher-carrying
                    // fee-recovery credit -- legacy addCredit()'s ENTRY3, or the ON-path
                    // GATEWAY_FEE_RECOVERY line, both of which always set voucher_number -- may
                    // ever contribute here. orderBy('id') additionally makes the choice
                    // deterministic if more than one income row ever shares a voucher.
                    $feeRecoveryEntry = $payment->voucher_number === null
                        ? null
                        : JournalEntry::where('company_id', $company->id)
                            ->where('voucher_number', $payment->voucher_number)
                            ->where('type', 'income')
                            ->orderBy('id')
                            ->first();

                    if ($feeRecoveryEntry) {
                        $netAmount += (float) $feeRecoveryEntry->credit;
                    }

                    $totalAmount += $netAmount;
                }

                if ($totalAmount <= 0) {
                    Log::warning("[Warning] Skipped group $key: total amount is zero.");
                    continue;
                }

                $branchId = $company->branches->first()->id ?? null;
                $entryDescription = "{$bankPaymentFee->name} Settles to Bank (After 24h) for {$gateway} on {$date} released to {$bankAccountAccRecord->name}";

                // ── Legacy closure: VERBATIM HEAD behaviour. Runs only when the seam routes to
                // legacy (engine off globally, off for this company, or a flag-flip race). Every
                // row, column, and value this writes is byte-identical to HEAD. ────────────────
                $legacy = function () use ($company, $branchId, $bankAccountAccRecord, $bankPaymentFee, $totalAmount, $gateway, $date, $entryDescription) {
                    // Transaction for the whole group
                    $transaction = Transaction::create([
                        'branch_id' => $branchId,
                        'company_id' => $company->id,
                        'entity_id' => $company->id,
                        'entity_type' => 'company',
                        'transaction_type' => 'payment',
                        'amount' => $totalAmount,
                        'description' => "{$bankPaymentFee->name} Settles to Bank (After 24h) (Assets) for {$gateway} on {$date}",
                    ]);

                    Log::info("[Info] Group Transaction ID {$transaction->id} created for group {$date}|{$gateway}");

                    // Journal Entries for the group
                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $branchId,
                        'company_id' => $company->id,
                        'account_id' => $bankAccountAccRecord->id,
                        'transaction_date' => $date,
                        'description' => $entryDescription,
                        'debit' => $totalAmount,
                        'credit' => 0,
                        'balance' => 0,
                        'name' => $bankAccountAccRecord->name,
                        'type' => 'receivable',
                    ]);

                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $branchId,
                        'company_id' => $company->id,
                        'account_id' => $bankPaymentFee->id,
                        'transaction_date' => $date,
                        'description' => $entryDescription,
                        'debit' => 0,
                        'credit' => $totalAmount,
                        'balance' => 0,
                        'name' => $bankPaymentFee->name,
                        'type' => 'receivable',
                    ]);

                    return $transaction;
                };

                $paymentIds = $groupPayments->pluck('id')->all();
                $idempotencyKey = PaymentIdempotencyKey::forPaymentReleaseGroup((int) $company->id, $gateway, $date, $paymentIds);

                $gatewayKey = strtoupper($gateway);

                // accounting-builds T7 (Lane D, L12): a grouped daily batch spans every receipt
                // for this (company, gateway, date), which can mix rails — no single PaymentMethod
                // applies to the group as a whole, so this stamps the bare gateway key only (the
                // same graceful-degradation GatewaySettlementService::channelFor() already
                // documents for the no-rail-known case). A payout-driven GWS document
                // (GatewaySettlementService::post()) carries the settlement's own, more specific
                // channel instead once a gateway's payouts move onto that path.
                $settlementChannel = GatewaySettlementService::channelFor($gatewayKey, null);

                // Deliberately NOT AccountResolver::assertUnderBankGroup() here: that call can
                // THROW (AccountNotUnderGroupException/NonLeafAccountException/…), and unlike
                // BankPaymentController::clear() (which reuses this SAME resolved account for
                // BOTH its legacy and engine writes, so a throw failing both paths identically is
                // correct there) this command's $legacy closure above resolves
                // $bankAccountAccRecord entirely independently (plain id/company_id lookup, no
                // leaf/frozen/bank-group check) — eagerly validating it here, unconditionally,
                // before the seam even decides which path to take, would newly break a
                // currently-working OFF-path company's release run over a structural COA detail
                // legacy never checked. PostingService::post() (step 3d/3e, {@see
                // \App\Services\Accounting\PostingService}) already re-validates ANY line's
                // resolved account — explicit accountId or purpose code alike — for exactly this
                // (leaf, frozen, same-tenant) BEFORE writing, and that check only ever runs on the
                // ON path (inside $seam->post()'s own try block below). Passing the plain id
                // straight through defers the entire risk to the one path where a new failure is
                // actually a genuine engine-correctness signal, never a legacy regression.
                $draft = new DocumentDraft(
                    companyId: (int) $company->id,
                    // W7.Y fix (gate item 5, low): was `(int) $branchId`, coercing a genuinely
                    // unresolvable branch ($company->branches->first()->id ?? null, above) to the
                    // 0 sentinel instead of preserving NULL -- diverging from $legacy's own
                    // Transaction::create(['branch_id' => $branchId, ...]) a few lines up, which
                    // already writes a real NULL for the identical case (transactions.branch_id is
                    // nullable, no FK). DocumentDraft::$branchId is now `?int` (see its own
                    // docblock) specifically so the ON path can match legacy here.
                    branchId: $branchId,
                    docType: 'JV',
                    subType: null,
                    docDate: Carbon::parse($date),
                    narration: $entryDescription,
                    lines: [
                        new LineDraft(
                            purposeCode: '',
                            accountId: (int) $bankAccountAccRecord->id,
                            side: 'debit',
                            amount: (float) $totalAmount,
                            currency: (string) config('accounting.engine.base_currency'),
                            originalAmount: (float) $totalAmount,
                            exchangeRate: 1.0,
                            transactionType: 'BANKSETTLED',
                            description: $entryDescription,
                            ledgerType: 'bank',
                            voucherNumber: null,
                            settlementChannel: $settlementChannel,
                        ),
                        new LineDraft(
                            purposeCode: "GATEWAY_CLEARING_{$gatewayKey}",
                            accountId: null,
                            side: 'credit',
                            amount: (float) $totalAmount,
                            currency: (string) config('accounting.engine.base_currency'),
                            originalAmount: (float) $totalAmount,
                            exchangeRate: 1.0,
                            transactionType: 'GATEWAYCLEARED',
                            description: $entryDescription,
                            ledgerType: 'bank',
                            voucherNumber: null,
                            settlementChannel: $settlementChannel,
                        ),
                    ],
                    idempotencyKey: $idempotencyKey,
                    userId: null, // scheduled console command -- no authenticated actor; never Auth::user()
                );

                $this->seam->post($draft, $legacy, 'payment-release.bank-settlement');

                // Mark all payments as completed
                foreach ($groupPayments as $payment) {
                    $payment->completed = 1;
                    $payment->save();
                }

                Log::info("[Info] Group $key processed and all payments marked as completed.");
            } catch (Exception $e) {
                Log::error("[Error] Exception in group $key: " . $e->getMessage());
            }
        }


        Log::info('[Info] Daily payment release task completed.');
    }
}
