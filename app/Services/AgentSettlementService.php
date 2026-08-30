<?php

namespace App\Services;

use App\Http\Controllers\PaymentController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentSettlement;
use App\Models\AgentSettlementPayment;
use App\Models\Charge;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\VoucherSubTypeGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * W5.S (w5-brief.md §W5.S; Accounting Gap/22-plan-amendments.md rev 5 §11.2/§11.3 row 13; doc 20
 * L722 "AST doc_type" ruling). {@see settleByProfit()} and {@see onPaymentCompleted()} are the two
 * JE-writing methods this sub-wave routes through {@see PostingSeam} — {@see createSettlement()},
 * {@see settleByPaymentLink()} and {@see updateSettlementTotals()}/{@see generateSettlementNumber()}
 * write NO journal_entries rows and are explicitly OUT of W5.S's scope (the sub-wave brief names
 * only "settleByProfit, onPaymentCompleted"); `settlement_number`'s own `STL-{year}-NNNNNN` counter
 * is therefore left untouched here — see the two migrated methods' own docblocks for what "own
 * SequenceService series, replacing generateSettlementNumber()'s private counter" actually means
 * for THIS sub-wave (the accounting DOCUMENT's reference_number, not AgentSettlement's own
 * business-facing settlement_number field).
 *
 * Every call below is verified (repo-wide grep, 2026-08-29) to have ZERO existing callers anywhere
 * in app/ — this service is currently dead code, wired to no controller/route/job. That does not
 * change what P2's engine-sole-writer exit gate requires (w5-brief.md §11.1: "RV, PV and the
 * legacy AgentSettlementService are three more raw writers, so W5 must route all three through the
 * seam"), and it is exactly why the pre-existing `'reference_type' => 'Settlement'` value in both
 * methods below (see the "PRE-EXISTING BUG" note on each legacy closure) was never caught: nothing
 * has ever actually executed this code against a real, strict-mode MySQL connection before this
 * wave's own tests.
 */
class AgentSettlementService
{
    public function __construct(
        private readonly PostingSeam $seam,
    ) {}

    public function createSettlement(array $items, Agent $agent, ?string $notes = null): AgentSettlement
    {
        if (! auth()->check()) {
            throw new \Exception('User must be authenticated to create settlement');
        }

        if (empty($items)) {
            throw new \Exception('Settlement must have at least one item');
        }

        return DB::transaction(function () use ($items, $agent, $notes) {
            $companyId = $agent->branch->company_id;
            $totalAmount = collect($items)->sum('amount');

            $settlement = AgentSettlement::create([
                'settlement_number' => $this->generateSettlementNumber($companyId),
                'agent_id' => $agent->id,
                'branch_id' => $agent->branch_id,
                'company_id' => $companyId,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'remaining_amount' => $totalAmount,
                'status' => 'unpaid',
                'settlement_date' => now(),
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $settlement->details()->create([
                    'invoice_id' => $item['invoice_id'],
                    'invoice_detail_id' => $item['invoice_detail_id'],
                    'amount' => $item['amount'],
                    'description' => $item['description'] ?? null,
                ]);
            }

            return $settlement;
        });
    }

    /**
     * W5.S. Debits the agent's per-agent profit-payable account and credits its paired
     * loss-receivable account for `$amount` — a settlement offsetting a prior loss against
     * profit the agent has already earned. Both accounts are per-agent FKs already resolved on
     * the `Agent` row (`profit_account_id`/`loss_account_id`) — an explicit `accountId` on each
     * {@see LineDraft}, never a purpose code (there is no single company-wide anchor for "this
     * one agent's own profit leaf"; every agent has its own), and never a name lookup — the hard
     * rule this build enforces throughout ("anchors not names") is satisfied here because the
     * account was never looked up by name in the first place, only by the FK the `Agent` row
     * itself already carries.
     *
     * PRE-EXISTING BUG fixed here, not merely preserved (w5-state.md's own as-is census did not
     * catch this — see class docblock, "zero existing callers"): HEAD's legacy closure set
     * `'reference_type' => 'Settlement'`, a value that has NEVER been legal —
     * `transactions.reference_type` is a strict-mode MySQL `ENUM('Receipt','Invoice','Payment',
     * 'Refund')` (verified against every migration touching that column, latest
     * 2025_09_20_071840_alter_reference_type_length_in_transactions_table.php; `config('database.
     * connections.mysql_testing.strict')` is `true`), so this INSERT would have thrown
     * `QueryException` (1265, "Data truncated for column") the FIRST time this dead method was
     * ever actually invoked against a real connection, engine on or off. Corrected to `'Payment'`
     * (the closest legal value — an agent settling a debt is a payment-shaped event, and every
     * other money-movement feeder in this codebase that HAS been exercised against the real
     * schema — e.g. AgentController's own salary closure — uses `'Payment'` too) in BOTH the
     * legacy closure (OFF path) and the engine draft's `$sourceType` (ON path,
     * {@see DocumentDraft::$sourceType}), so the two paths agree. This is a genuine defect fix,
     * not a "byte-for-byte HEAD parity" preservation like AgentController's documented one-sided-
     * salary defect — HEAD's own value was never once writable, so there is no working behaviour
     * to preserve.
     */
    public function settleByProfit(AgentSettlement $settlement, float $amount): AgentSettlementPayment
    {
        return DB::transaction(function () use ($settlement, $amount) {
            $agent = $settlement->agent;
            $companyId = $settlement->company_id;
            $profitAccount = $agent->profitAccount;
            $lossAccount = $agent->lossAccount;

            if (! $profitAccount || ! $lossAccount) {
                throw new \Exception('Agent must have both profit and loss accounts defined');
            }

            if ($amount > $settlement->remaining_amount) {
                throw new \Exception("Amount ({$amount}) exceeds remaining settlement ({$settlement->remaining_amount})");
            }

            // Profit account is LIABILITY: balance = credits - debits. A direct sum over
            // journal_entries.credit/.debit -- NOT accounts.actual_balance and NOT
            // journal_entries.balance (both forbidden reads in this build; see class docblock's
            // boundary note) -- unchanged from HEAD, out of W5.S's scope to redesign onto
            // TrialBalanceService.
            $profitBalance = JournalEntry::where('account_id', $profitAccount->id)->sum('credit')
                - JournalEntry::where('account_id', $profitAccount->id)->sum('debit');

            if ($amount > $profitBalance) {
                throw new \Exception("Insufficient profit balance. Available: {$profitBalance}");
            }

            $roundedAmount = round($amount, 3);
            $description = 'Loss settlement by profit offset: '.$agent->name;

            // Legacy path (R3 route-to-legacy seam). Kept verbatim except for the reference_type
            // fix documented in this method's own docblock (PRE-EXISTING BUG) and the removal of
            // no actual_balance write -- there never was one on this method (only
            // onPaymentCompleted() had that defect).
            $legacy = function () use ($companyId, $agent, $amount, $settlement, $profitAccount, $lossAccount, $description) {
                $transaction = Transaction::create([
                    'company_id' => $companyId,
                    'branch_id' => $agent->branch_id,
                    'entity_id' => $agent->id,
                    'entity_type' => 'agent',
                    'transaction_type' => 'debit',
                    'amount' => $amount,
                    'description' => "Loss settlement by profit offset - {$settlement->settlement_number}",
                    'reference_type' => 'Payment', // PRE-EXISTING BUG fix -- see method docblock.
                    'reference_number' => $settlement->settlement_number,
                    'transaction_date' => now(),
                ]);

                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $agent->branch_id ?? null,
                    'company_id' => $companyId,
                    'account_id' => $profitAccount->id,
                    'agent_id' => $agent->id,
                    'transaction_date' => now(),
                    'description' => $description,
                    'debit' => $amount,
                    'credit' => 0,
                    'balance' => 0,
                    'name' => $profitAccount->name ?? 'Agent Profit Payable',
                    'type' => 'payable',
                    'currency' => 'KWD',
                    'exchange_rate' => 1.00,
                    'amount' => $amount,
                ]);

                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $agent->branch_id ?? null,
                    'company_id' => $companyId,
                    'account_id' => $lossAccount->id,
                    'agent_id' => $agent->id,
                    'transaction_date' => now(),
                    'description' => $description,
                    'debit' => 0,
                    'credit' => $amount,
                    'balance' => 0,
                    'name' => $lossAccount->name ?? 'Agent Loss Receivable',
                    'type' => 'receivable',
                    'currency' => 'KWD',
                    'exchange_rate' => 1.00,
                    'amount' => $amount,
                ]);

                return $transaction;
            };

            VoucherSubTypeGuard::assertValid('AST', 'LEGACY');

            // W1.1 salary-feeder convention (S1): no queued job/webhook ever re-delivers a call
            // into this method -- it is one synchronous method call per real settlement event --
            // so the key's only job is "unique per real settlement event", not "de-duplicate a
            // retried delivery". Keyed on (settlement, amount, to-the-second timestamp) rather
            // than (settlement, amount) alone so two GENUINELY distinct partial settlements of
            // the identical amount (e.g. two separate 50.000 offsets against the same settlement
            // on different days) each still post their own document, matching AgentController::
            // update()'s own documented S1 rationale for the identical shape of risk.
            $idempotencyKey = sprintf(
                'agent-settlement:profit:%d:%s:%s',
                $settlement->id,
                number_format($roundedAmount, 3, '.', ''),
                now()->format('Y-m-d H:i:s')
            );

            $draft = new DocumentDraft(
                companyId: (int) $companyId,
                branchId: (int) ($agent->branch_id ?? 0),
                docType: 'AST',
                subType: 'LEGACY',
                docDate: now(),
                narration: $description,
                lines: [
                    new LineDraft(
                        purposeCode: '',
                        accountId: $profitAccount->id,
                        side: 'debit',
                        amount: $roundedAmount,
                        currency: 'KWD',
                        originalAmount: $roundedAmount,
                        exchangeRate: 1.0,
                        transactionType: 'AGENT_PROFIT_OFFSET_DEBIT',
                        description: $description,
                        partyAccountRef: $agent->id,
                        ledgerType: 'payable',
                        partyName: $agent->name,
                    ),
                    new LineDraft(
                        purposeCode: '',
                        accountId: $lossAccount->id,
                        side: 'credit',
                        amount: $roundedAmount,
                        currency: 'KWD',
                        originalAmount: $roundedAmount,
                        exchangeRate: 1.0,
                        transactionType: 'AGENT_LOSS_RECEIVABLE_CREDIT',
                        description: $description,
                        partyAccountRef: $agent->id,
                        ledgerType: 'receivable',
                        partyName: $agent->name,
                    ),
                ],
                idempotencyKey: $idempotencyKey,
                sourceType: 'Payment', // PRE-EXISTING BUG fix -- see method docblock.
                sourceId: $settlement->id,
            );

            // Balanced or rejected -- no Log::error-and-continue (w5-brief.md §W5.S). The
            // returned PostedDocument/Transaction/null is intentionally not consumed here: this
            // method has no legacy-only field (like ReceiptVoucherController's transaction_id
            // backfill) that depends on which path ran, and PostingSeam's own contract guarantees
            // a thrown PostingException on any genuine engine-path failure -- see PostingSeam's
            // "every feeder must tolerate this null" note for why a bare `null` return (the S1
            // already-posted short-circuit) is not an error condition to special-case here either.
            $this->seam->post($draft, $legacy, 'agent-settlement.profit');

            $settlementPayment = AgentSettlementPayment::create([
                'agent_settlement_id' => $settlement->id,
                'amount' => $amount,
                'method' => 'profit',
                'payment_id' => null,
                'created_by' => auth()->id(),
            ]);

            $this->updateSettlementTotals($settlement, $amount);

            return $settlementPayment;
        });
    }

    public function settleByPaymentLink(AgentSettlement $settlement, float $amount, string $currency = 'KWD')
    {
        if ($amount > $settlement->remaining_amount) {
            throw new \Exception("Amount ({$amount}) exceeds remaining settlement ({$settlement->remaining_amount})");
        }

        // TODO: reuse existing gateway logic from PaymentController
        $paymentMethodsId = $settlement->company->paymentMethodChoses()->get('payment_method_id')->pluck('payment_method_id')->toArray();

        $paymentController = new PaymentController;

        // 1. Create Payment record with settlement_id (new column on payments table)
        $requestData = new Request([
            'payment_methods' => $paymentMethodsId,
            'amount' => $amount,
            'currency' => $currency,
            'client_id' => null, // client_id must be null for settlement payments
            'agent_id' => $settlement->agent_id,
            'settlement_id' => $settlement->id,
            'send_payment_receipt' => false, // payment receipt use client info, so set to false for settlement payments because client_id is null
        ]);

        $response = $paymentController->multiPaymentMethodProcess($requestData);

        if (! $response['success']) {
            throw new \Exception('Failed to create payment link: '.$response['message']);
        }

        $payment = Payment::find($response['payment_id']);

        return route('payments.link.show', ['companyId' => $settlement->company_id, 'voucherNumber' => $payment->voucher_number]);
    }

    /**
     * W5.S. Rule 3b (doc 20 L123-126, w5-brief.md §W5.S): "collection is never automatic" --
     * this method may only post what an explicit `AgentSettlement` row requests, never as an
     * automatic side effect of ANY completed payment. The one durable, DB-enforced signal that a
     * `Payment` was genuinely earmarked for THIS settlement (as opposed to a caller passing an
     * unrelated `$settlement` object it merely happens to hold a reference to) is
     * `payments.settlement_id` -- the same column {@see settleByPaymentLink()} populates when it
     * creates the `Payment` in the first place, and the ONLY column that can ever hold it: the
     * 2026_04_01_155631_add_column_in_payments_table.php migration's `chk_payment_owner` CHECK
     * constraint enforces `(client_id IS NOT NULL AND settlement_id IS NULL) OR (client_id IS
     * NULL AND settlement_id IS NOT NULL)` at the DB level, so a `Payment` can never carry a
     * `client_id` AND a `settlement_id` at once -- there is no ambiguity about which kind of
     * money movement a given `Payment` row represents. This method refuses (no write, no
     * `DB::transaction()` side effect) whenever `$payment->settlement_id` does not match
     * `$settlement->id` -- including a `Payment` that has no `settlement_id` at all (a plain
     * client payment routed here by mistake, or a future generic "payment completed" listener
     * that has not been taught to set it). A future automatic wiring of this method MUST set
     * `payments.settlement_id` itself for the deduction to count as an explicit request -- this
     * method never infers or defaults it from the agent, the amount, or anything else.
     *
     * PRE-EXISTING BUGS fixed here, not merely preserved (see class docblock, "zero existing
     * callers" -- this method has never actually run against a real strict-mode connection):
     *   1. `'reference_type' => 'Settlement'` -- identical defect and identical fix as
     *      {@see settleByProfit()}'s own docblock documents; see that note for the full
     *      citation. Corrected to `'Payment'` in both the legacy closure and the engine draft.
     *   2. `'balance' => $gatewayAssetAccount->actual_balance + $netAmount` (and the identical
     *      shape for `$gatewayExpenseAccount`) -- this build's hard rule is "REMOVE every
     *      `actual_balance +=` you meet in scope", and additionally "never READ
     *      accounts.actual_balance" (feedback_accounting_boundary: the column is ~41.5% wrong).
     *      HEAD's `journal_entries.balance` write for these two lines both READ actual_balance
     *      (to compute the new figure) and — via the two `$account->actual_balance += ...;
     *      $account->save();` calls immediately below each JournalEntry::create() — WROTE it.
     *      Both are removed unconditionally, in BOTH the legacy closure and (implicitly, since
     *      LineDraft has no 'balance' field for a feeder to set) the engine path. `'balance'` in
     *      the legacy closure is now a literal `0`, the same convention AgentController::
     *      update()'s own salary-feeder legacy closure already established for a column with no
     *      reliable source (`'balance' => $salaryExpenseAccount->balance ?? 0`, i.e. always `0`
     *      since `Account` has no `balance` accessor) — this is a genuine defect fix, not a
     *      preserved-on-purpose HEAD quirk, per the hard rule's own unconditional wording.
     */
    public function onPaymentCompleted(Payment $payment, AgentSettlement $settlement): void
    {
        DB::transaction(function () use ($payment, $settlement) {
            if ((int) $payment->settlement_id !== (int) $settlement->id) {
                throw new \RuntimeException(sprintf(
                    'AgentSettlementService::onPaymentCompleted() refused: Payment #%d is not attached '
                        .'to AgentSettlement #%d (payments.settlement_id = %s). Rule 3b -- collection is '
                        .'never automatic; no deduction is posted unless the Payment itself carries an '
                        .'explicit settlement_id matching this settlement.',
                    $payment->id,
                    $settlement->id,
                    $payment->settlement_id === null ? 'NULL' : (string) $payment->settlement_id
                ));
            }

            $agent = $settlement->agent;
            $companyId = $settlement->company_id;
            $lossAccount = $agent->lossAccount;
            $gatewayName = $payment->payment_gateway;

            if (! $lossAccount) {
                throw new \Exception('Agent must have a loss account defined');
            }

            $chargeRecord = Charge::where('name', 'LIKE', "%{$gatewayName}%")
                ->where('company_id', $companyId)
                ->first();

            if (! $chargeRecord) {
                throw new \Exception("Charge record not found for gateway: {$gatewayName}");
            }

            $gatewayAssetAccount = Account::find($chargeRecord->acc_fee_bank_id);
            $gatewayExpenseAccount = Account::find($chargeRecord->acc_fee_id);

            if (! $gatewayAssetAccount || ! $gatewayExpenseAccount) {
                throw new \Exception('Gateway asset or expense account not found');
            }

            $chargeResult = ChargeService::calculate($payment->amount, $companyId, $payment->payment_method_id, $gatewayName);
            $accountingFee = round((float) ($chargeResult['accountingFee'] ?? 0), 3);
            $netAmount = round((float) $payment->amount - $accountingFee, 3);

            $payment->gateway_fee = $accountingFee;
            $payment->save();

            $description = 'Loss settlement payment received from agent: '.$agent->name;

            // Legacy path (R3 route-to-legacy seam). Kept verbatim except for the two
            // PRE-EXISTING BUG fixes this method's own docblock documents (reference_type,
            // balance/actual_balance).
            $legacy = function () use (
                $companyId, $agent, $payment, $settlement, $lossAccount, $gatewayAssetAccount,
                $gatewayExpenseAccount, $netAmount, $accountingFee, $gatewayName, $description
            ) {
                $transaction = Transaction::create([
                    'company_id' => $companyId,
                    'branch_id' => $agent->branch_id,
                    'entity_id' => $agent->id,
                    'entity_type' => 'agent',
                    'transaction_type' => 'debit',
                    'amount' => $payment->amount,
                    'description' => "Loss settlement by payment - {$settlement->settlement_number}",
                    'reference_type' => 'Payment', // PRE-EXISTING BUG fix -- see method docblock.
                    'reference_number' => $settlement->settlement_number,
                    'payment_id' => $payment->id,
                    'transaction_date' => now(),
                ]);

                // CR Agent Loss Receivable (agent owes less)
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $agent->branch_id ?? null,
                    'company_id' => $companyId,
                    'account_id' => $lossAccount->id,
                    'agent_id' => $agent->id,
                    'transaction_date' => now(),
                    'description' => $description,
                    'debit' => 0,
                    'credit' => $payment->amount,
                    'balance' => 0,
                    'name' => $lossAccount->name ?? 'Agent Loss Receivable',
                    'type' => 'receivable',
                    'currency' => 'KWD',
                    'exchange_rate' => 1.00,
                    'amount' => $payment->amount,
                ]);

                // DR Gateway Asset (net amount after fee)
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $agent->branch_id ?? null,
                    'company_id' => $companyId,
                    'account_id' => $gatewayAssetAccount->id,
                    'agent_id' => $agent->id,
                    'transaction_date' => now(),
                    'description' => 'Net settlement payment received via '.$gatewayName,
                    'debit' => $netAmount,
                    'credit' => 0,
                    'balance' => 0, // PRE-EXISTING BUG fix -- see method docblock (item 2).
                    'name' => $gatewayAssetAccount->name,
                    'type' => 'bank',
                    'voucher_number' => $payment->voucher_number,
                    'currency' => 'KWD',
                    'exchange_rate' => 1.00,
                    'amount' => $netAmount,
                ]);

                // DR Gateway Fee Expense (cost of using gateway)
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $agent->branch_id ?? null,
                    'company_id' => $companyId,
                    'account_id' => $gatewayExpenseAccount->id,
                    'agent_id' => $agent->id,
                    'transaction_date' => now(),
                    'description' => 'Gateway fee on settlement payment: '.$gatewayExpenseAccount->name,
                    'debit' => $accountingFee,
                    'credit' => 0,
                    'balance' => 0, // PRE-EXISTING BUG fix -- see method docblock (item 2).
                    'name' => $gatewayExpenseAccount->name,
                    'type' => 'charges',
                    'voucher_number' => $payment->voucher_number,
                    'currency' => 'KWD',
                    'exchange_rate' => 1.00,
                    'amount' => $accountingFee,
                ]);

                Log::info('[SETTLEMENT COA] Journal entries created', [
                    'transaction_id' => $transaction->id,
                    'payment_id' => $payment->id,
                    'settlement_number' => $settlement->settlement_number,
                    'credit_loss_receivable' => $payment->amount,
                    'debit_gateway_asset' => $netAmount,
                    'debit_gateway_fee' => $accountingFee,
                    'balanced' => ($payment->amount == ($netAmount + $accountingFee)) ? 'YES' : 'NO',
                ]);

                return $transaction;
            };

            VoucherSubTypeGuard::assertValid('AST', 'LEGACY');

            // Payment::$id already exists and is stable before this call (unlike settleByProfit's
            // event, which has no persisted identity of its own to key on) -- a genuinely
            // webhook/retry-safe key per PaymentIdempotencyKey's own established convention
            // ("NEVER a timestamp"), so a redelivered "payment completed" notification for the
            // SAME payment/settlement pair naturally short-circuits at PostingSeam's own S1 check
            // instead of double-posting.
            $idempotencyKey = sprintf('agent-settlement:payment:%d:settlement:%d', $payment->id, $settlement->id);

            $amountLine = round((float) $payment->amount, 3);

            $lines = [
                new LineDraft(
                    purposeCode: '',
                    accountId: $lossAccount->id,
                    side: 'credit',
                    amount: $amountLine,
                    currency: 'KWD',
                    originalAmount: $amountLine,
                    exchangeRate: 1.0,
                    transactionType: 'AGENT_LOSS_RECEIVABLE_CREDIT',
                    description: $description,
                    partyAccountRef: $agent->id,
                    ledgerType: 'receivable',
                    partyName: $agent->name,
                ),
            ];

            // Both the net-asset leg and the fee-expense leg are omitted from the ENGINE draft
            // when zero (PostingService rejects a zero-amount line outright --
            // NonNegativeAmountException, `amount <= 0`) -- unlike the legacy closure above,
            // which HEAD always wrote unconditionally even at 0. A gateway with no configured fee
            // (accountingFee == 0) therefore posts a clean 2-line balanced document on the ON
            // path instead of a 3rd zero-amount line the engine would otherwise refuse outright.
            // Matches the same >0.0005 tolerance ReceiptVoucherController's own remainder-line
            // guard uses.
            if ($netAmount > 0.0005) {
                $lines[] = new LineDraft(
                    purposeCode: '',
                    accountId: $gatewayAssetAccount->id,
                    side: 'debit',
                    amount: $netAmount,
                    currency: 'KWD',
                    originalAmount: $netAmount,
                    exchangeRate: 1.0,
                    transactionType: 'AGENT_SETTLEMENT_GATEWAY_NET',
                    description: 'Net settlement payment received via '.$gatewayName,
                    partyAccountRef: $agent->id,
                    ledgerType: 'bank',
                    voucherNumber: $payment->voucher_number,
                );
            }

            if ($accountingFee > 0.0005) {
                $lines[] = new LineDraft(
                    purposeCode: '',
                    accountId: $gatewayExpenseAccount->id,
                    side: 'debit',
                    amount: $accountingFee,
                    currency: 'KWD',
                    originalAmount: $accountingFee,
                    exchangeRate: 1.0,
                    transactionType: 'AGENT_SETTLEMENT_GATEWAY_FEE',
                    description: 'Gateway fee on settlement payment: '.$gatewayExpenseAccount->name,
                    partyAccountRef: $agent->id,
                    ledgerType: 'charges',
                    voucherNumber: $payment->voucher_number,
                );
            }

            $draft = new DocumentDraft(
                companyId: (int) $companyId,
                branchId: (int) ($agent->branch_id ?? 0),
                docType: 'AST',
                subType: 'LEGACY',
                docDate: now(),
                narration: 'Loss settlement by payment - '.$settlement->settlement_number,
                lines: $lines,
                idempotencyKey: $idempotencyKey,
                sourceType: 'Payment', // PRE-EXISTING BUG fix -- see method docblock.
                sourceId: $settlement->id,
                paymentId: $payment->id,
            );

            // Balanced or rejected -- no Log::error-and-continue (w5-brief.md §W5.S).
            $this->seam->post($draft, $legacy, 'agent-settlement.payment');

            AgentSettlementPayment::create([
                'agent_settlement_id' => $settlement->id,
                'amount' => $payment->amount,
                'method' => 'payment_link',
                'payment_id' => $payment->id,
                'created_by' => auth()->id(),
            ]);

            $this->updateSettlementTotals($settlement, $payment->amount);
        });
    }

    private function updateSettlementTotals(AgentSettlement $settlement, float $amount): void
    {
        $settlement->paid_amount += $amount;
        $settlement->remaining_amount -= $amount;
        $settlement->status = $settlement->remaining_amount <= 0 ? 'paid' : 'partial';
        $settlement->updated_by = auth()->id();
        $settlement->save();
    }

    private function generateSettlementNumber(int $companyId): string
    {
        $thisYear = now()->year;

        $latestSettlementNumber = AgentSettlement::where('company_id', $companyId)
            ->where('settlement_number', 'like', "STL-{$thisYear}-%")
            ->latest('id')
            ->value('settlement_number');

        if (! $latestSettlementNumber) {
            return "STL-{$thisYear}-000001";
        }

        $number = (int) substr($latestSettlementNumber, -6);
        $number++;

        return "STL-{$thisYear}-".str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
