<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Credit;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\MyFatoorahPayment;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PaymentIdempotencyKey;
use App\Services\Accounting\PostingSeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\ClientController;
use App\Services\GatewayConfigService;
use App\Services\TrialBalanceService;

class CheckMyFatoorahPayments extends Command
{
    protected $signature = 'app:myfatoorah-check-status {invoiceId?}';
    protected $description = 'Check MyFatoorah payment status for initiated payments (or a specific invoice).';

    public function handle(): int
    {
        $invoiceId = $this->argument('invoiceId');

        $query = Payment::query()
            ->where('payment_gateway', 'MyFatoorah')
            ->where('status', 'initiate');
        if ($invoiceId) {
            $query->where('payment_reference', $invoiceId);
        }
        $payments = $query->get();

        if ($payments->isEmpty()) {
            $this->info('No initiated MyFatoorah payments to check.');
            return self::SUCCESS;
        }

        $updatedPayments = [];
        $bar = $this->output->createProgressBar($payments->count());
        $bar->start();

        foreach ($payments as $payment) {
            $result = $this->getMFPaymentStatus($payment->payment_reference);

            if (empty($result['success'])) {
                Log::warning('MyFatoorah status check skipped', [
                    'payment_id' => $payment->id,
                    'invoice'    => $payment->payment_reference,
                    'message'    => $result['message'] ?? 'Unknown error',
                ]);
                $bar->advance();
                continue;
            }

            $status = $result['invoice_status'] ?? null;
            $map = [
                'Paid'     => 'completed',
                'Pending'  => 'pending',
                'Canceled' => 'canceled',
                'Expired'  => 'expired',
            ];
            $newStatus = $map[$status] ?? strtolower($status);

            if ($newStatus !== 'completed') {
                $bar->advance();
                continue;
            }

            // Computed before the try/DB::beginTransaction() so it is available to the catch
            // block below even when an exception is thrown before the seam is ever reached (e.g.
            // the addCredit() RuntimeException a few lines down) — an operator reading the error
            // log should always be able to see which payment's idempotency key this attempt was
            // for, not just when the failure happened to originate inside the seam itself.
            //
            // W2 fix (Task B / D2): was the bare literal "myfatoorah:payment:{id}". Now derived
            // from the shared PaymentIdempotencyKey helper so this cron and PaymentController's
            // gateway-webhook handlers compute the SAME key shape for the SAME kind of business
            // event (gateway + payment id + partial set) — see that class's own docblock. This
            // cron posts a plain advance with no InvoicePartial scoping, so $partialIds is always
            // null here, giving 'gateway:myfatoorah:payment:{id}:partials:none'. THE OLD SHAPE IS
            // RETIRED: any historical row's idempotency_key still reads the old
            // "myfatoorah:payment:{id}" string (this is a key-derivation change for FUTURE posts
            // only — no backfill/migration of existing rows is in this task's scope), so the
            // $alreadyPosted guard's own idempotency_key clause a few lines below (scoped to
            // $companyId + this new key) will not recognise an old-shaped row as "already
            // posted". Residual 20 fix (W2.1) — corrected: for an old-shaped LEGACY row this is
            // still caught by that guard's OTHER clause (payment_id + reference_type = 'Payment');
            // for an old-shaped ENGINE row it is NOT, by anything. That other clause filters on
            // reference_type = 'Payment', but the engine draft below posts under
            // sourceType/reference_type 'Receipt' (see that clause's own comment further down),
            // so it can never match an engine-posted row regardless of key shape — and
            // PostingService's own idempotency lookup is keyed on the idempotency_key IT is given
            // (this call site's NEW-shaped key), so it has no way to recognise an existing row
            // filed under the retired string either. A company that had the engine ON before this
            // key-derivation change shipped can double-post its next plain-advance cron run for
            // any payment the old key already covered.
            $idempotencyKey = PaymentIdempotencyKey::forGatewayPayment('myfatoorah', $payment->id);

            try {
                DB::beginTransaction();

                $data = $result['raw'] ?? [];
                $transaction = $data['InvoiceTransactions'][0] ?? [];
                $invoiceRef = $data['InvoiceReference'] ?? null;
                $authCode = $transaction['AuthorizationId'] ?? null;

                $payment->invoice_reference = $invoiceRef ?? $payment->invoice_reference;
                $payment->auth_code = $authCode ?? $payment->auth_code;
                if ($result['amount'] !== null) {
                    $payment->amount = (float)$result['amount'];
                }

                $ud = [];
                $udRaw = $data['UserDefinedField'] ?? null;
                if (is_string($udRaw) && $udRaw !== '') {
                    $ud = json_decode($udRaw, true) ?: [];
                }

                $process = $ud['process'] ?? null;

                if ($process === 'topup') {
                    $alreadyCredited = Credit::where('client_id', $payment->client_id)
                        ->where('description', 'Topup Credit via ' . $payment->voucher_number)
                        ->exists();
            
                    if (!$alreadyCredited) {
                        $clientController = app(ClientController::class);
                        $resp = $clientController->addCredit($payment);
                        if (is_array($resp) && ($resp['status'] ?? '') !== 'success') {
                            throw new \RuntimeException('addCredit failed: ' . (($resp['message'] ?? 'unknown')));
                        }
                    }
                }

                // R3 route-to-legacy seam (2026-08-26): companyId/branchId are resolved once,
                // up front, so BOTH the $alreadyPosted guard below and the engine draft (built
                // unconditionally — DocumentDraft construction has no side effects) can use them.
                // $idempotencyKey (computed above, before the try block) is the engine-path dedup
                // key AND, per item 4 of this task, the second half of the $alreadyPosted guard —
                // see that guard's own comment.
                //
                // W1.1 fix (M1 — REGRESSION vs HEAD, W1 lead report §3 myfatoorah): W1 hoisted
                // this computation ABOVE the $alreadyPosted guard as a bare `->` chain, so it ran
                // UNCONDITIONALLY, even for a payment whose agent has since been hard-deleted
                // (payments.agent_id is nullable()->nullOnDelete(), Agent has no SoftDeletes — a
                // real, reachable state). `Illuminate\Foundation\Bootstrap\HandleExceptions`
                // converts the resulting "Attempt to read property on null" PHP warning into a
                // thrown ErrorException, which the outer catch(\Throwable) rolls back — stranding
                // the payment at 'initiate' forever, since every future cron run hits the exact
                // same crash before ever reaching $alreadyPosted. At HEAD this computation lived
                // INSIDE `if (!$alreadyPosted)`, so an already-posted payment (the common case a
                // status-check re-run hits) never touched agent/branch/company at all and simply
                // finished (MyFatoorahPayment row + status='completed'). The null-safe operator
                // below removes the crash entirely — $companyId/$branchId end up null, with no
                // warning — and the `$companyId !== null` guard on the posting block (below) means
                // ONLY the posting block is skipped when the company can't be resolved; the
                // payment's own finalization below still runs either way.
                //
                // CORRECTION (W1.2 fix round, Task B): the sentence that used to end this comment
                // claimed this "restores HEAD's actual behaviour". It does not, and never did.
                // HEAD threw here (a raw ErrorException from the same null-property read, per the
                // paragraph above) — the outer catch(\Throwable) rolled the whole
                // DB::beginTransaction() back, and the payment was stranded at 'initiate' forever
                // (this command's own `->where('status', 'initiate')` filter means a retried cron
                // run hits the identical crash every time, with no way out short of fixing the
                // agent/branch data). Finalizing the payment here — while skipping ONLY the
                // accounting write — is a DIFFERENT, deliberately chosen P1 policy decision (see
                // the `elseif` branch immediately below for the full reasoning and its own
                // Log::critical), not a restoration of anything HEAD ever did.
                $branch = $payment->agent?->branch;
                $companyId = $branch?->company?->id;
                $branchId = $branch?->id;

                // W1 CUTOVER (R3 route-to-legacy): $alreadyPosted must recognise a posting made
                // through EITHER path, not just the legacy one. The legacy shape below
                // (Transaction::create() with 'payment_id' + 'reference_type' => 'Payment') is
                // preserved verbatim. UPDATED (W1.2, Task A): PostingService's own header write
                // (see PostingService::post() step 7) now writes `payment_id` too, when the draft
                // sets DocumentDraft::$paymentId — as the engine draft below does — but it does so
                // under `reference_type = 'Receipt'` (engine-derived; see the draft's own
                // `sourceType`/reference_type note below), never legacy's `'Payment'` label. This
                // clause's own `->where('reference_type', 'Payment')` filter therefore still never
                // matches an engine-posted row, so an engine-posted payment must still be
                // recognised by its idempotency_key, scoped to this company, exactly as before.
                // Without this second clause, a payment posted through the engine on one run would
                // look "never posted" to this guard on any later run for the same payment id, and
                // would be attempted again (the seam itself is separately idempotent via
                // PostingService's own idempotency-key lookup, so a retry would not double-write —
                // but it would waste a document number and, on the OFF path, double-write for
                // real if the flag had flipped since).
                //
                // W1.1 fix (M4 — W1 lead report §3 myfatoorah): both clauses are now
                // ->withTrashed(), so a SOFT-DELETED transaction (either the legacy shape or an
                // engine-posted one — Transaction uses SoftDeletes) still counts as "already
                // posted" on the next run. Before this fix, a reversed/soft-deleted engine
                // transaction was invisible to this guard while its idempotency_key still
                // occupied the unique(company_id, idempotency_key) index, so a retry would either
                // silently re-attempt a post that would collide on that index, or (for the legacy
                // clause) genuinely double-post a one-sided legacy entry for money already
                // recorded once.
                $alreadyPosted = Transaction::withTrashed()
                    ->where('payment_id', $payment->id)
                    ->where('reference_type', 'Payment')
                    ->exists()
                    || ($companyId !== null && Transaction::withTrashed()
                        ->where('company_id', $companyId)
                        ->where('idempotency_key', $idempotencyKey)
                        ->exists());

                if (!$alreadyPosted && $companyId !== null) {
                    // ── Legacy closure: VERBATIM HEAD behaviour, moved unmodified behind the
                    // seam. Runs only when the seam routes to legacy (engine off globally, off for
                    // this company, or a flag-flip race — see PostingSeam::post()'s own docblock).
                    // Every row, column, and value this writes is byte-identical to HEAD.
                    $legacy = function () use ($payment, $companyId, $branchId, $invoiceRef) {
                        $liabilitiesAccount = Account::where('name', 'like', '%Liabilities%')
                            ->where('company_id', $companyId)
                            ->first();
                        if (!$liabilitiesAccount) {
                            throw new \RuntimeException('Liabilities account not found.');
                        }

                        $clientAdvance = Account::where('name', 'Client')
                            ->where('company_id', $companyId)
                            ->where('root_id', $liabilitiesAccount->id)
                            ->first();
                        if (!$clientAdvance) {
                            throw new \RuntimeException('Client advance account not found.');
                        }

                        $paymentGateway = Account::where('name', 'Payment Gateway')
                                ->where('company_id', $companyId)
                                ->where('parent_id', $clientAdvance->id)
                                ->first();
                        if (!$paymentGateway) {
                            throw new \RuntimeException('Payment Gateway account not found');
                        }

                        $transaction = Transaction::create([
                            'branch_id'         => $branchId,
                            'company_id'        => $companyId,
                            'entity_id'         => $companyId,
                            'entity_type'       => 'company',
                            'transaction_type'  => 'debit',
                            'amount'            => $payment->amount,
                            'description'       => 'Topup success by ' . $payment->client->full_name,
                            'payment_id'        => $payment->id,
                            'invoice_id'        => $payment->invoice_id,
                            'payment_reference' => $invoiceRef,
                            'reference_type'    => 'Payment',
                            'transaction_date' => now(),
                        ]);

                        // Ledger-derived balance (accounts.actual_balance is a hand-maintained
                        // decimal(10,2) column that has drifted from the journal entries by as
                        // much as 41.5% of accounts; the 'balance' display value here must be
                        // computed from the ledger, not the stale column). This read happens
                        // before the JournalEntry below is inserted, so it reflects the balance
                        // *prior to* this transaction, matching the original actual_balance read.
                        //
                        // "Payment Gateway" lives under Liabilities -> Client -> Payment Gateway,
                        // i.e. a CREDIT-normal account (TrialBalanceService::getCurrentAccountBalance()
                        // derives this from the account's own root, same rule as
                        // CoaController.php's $rootConfig and JournalEntryController's running-balance
                        // switch). A credit INCREASES a credit-normal account's balance, so the entry
                        // below is created below with the canonical `+ $payment->amount` — not the
                        // `- $payment->amount` this used to read, which was silently treating a
                        // Liability like a debit-normal account.
                        $paymentGatewayLedgerBalance = app(TrialBalanceService::class)
                            ->getCurrentAccountBalance($companyId, $paymentGateway->id);

                        JournalEntry::create([
                            'transaction_id'     => $transaction->id,
                            'branch_id'          => $branchId,
                            'company_id'         => $companyId,
                            'invoice_id'         => $payment->invoice_id,
                            'account_id'         => $paymentGateway->id,
                            'transaction_date'   => now(),
                            'description'        => 'Advance Payment in voucher number: ' . $payment->voucher_number,
                            'debit'              => 0,
                            'credit'             => $payment->amount,
                            'balance'            => $paymentGatewayLedgerBalance + $payment->amount,
                            'name'               => $payment->client->full_name,
                            'type'               => 'receivable',
                            'voucher_number'     => $payment->voucher_number,
                            'type_reference_id'  => $paymentGateway->id,
                        ]);

                        // Legacy actual_balance write kept in place, arithmetic UNCHANGED —
                        // strangler posture: legacy-mode companies still read this column, and W1
                        // (cutting this feeder over to PostingService, which does not maintain
                        // actual_balance) has not happened yet. Note this line's `-=` disagrees in
                        // sign with ClientController::addCredit()'s
                        // `$clientAdvancePaymentGateway->actual_balance += $clientCreditAmount;`
                        // for the exact same account
                        // tree on the exact same operation (a credit to Payment Gateway) — a
                        // pre-existing co-writer conflict on the legacy column itself, documented
                        // in TrialBalanceService::getCurrentAccountBalance()'s docblock and
                        // deliberately NOT fixed here (out of scope: this column is being retired,
                        // not corrected).
                        $paymentGateway->actual_balance = ($paymentGateway->actual_balance ?? 0) - $payment->amount;
                        $paymentGateway->save();

                        return $transaction;
                    };

                    // W1.1 fix (P1 policy call, 2026-08-26 — W1 lead report §3 myfatoorah "account-
                    // role reclassification" finding): the legacy closure above always credits the
                    // client-advance LIABILITY (Liabilities > Advances > Client > Payment Gateway,
                    // 2632) regardless of $payment->invoice_id — it never looks at that column at
                    // all. The engine draft below is deliberately NOT byte-identical to that: when
                    // this payment is genuinely settling a specific invoice (invoice_id set), it is
                    // no longer an unearned advance, so the credit leg resolves to
                    // RECEIVABLE_CONTROL (a contra-receivable) instead. When invoice_id is NULL —
                    // the population every sampled dev row actually is (W1 lead report §3) — it
                    // stays exactly what legacy always posted: CLIENT_ADVANCE, mapped by
                    // SystemAccountsSeeder::resolveControls() via mapByChain() to that SAME 2632
                    // leaf, never RECEIVABLE_CONTROL. This is a policy decision, not a bug fix —
                    // see config('accounting.purpose_codes')'s own docblock for the full rationale.
                    $creditPurposeCode = $payment->invoice_id !== null ? 'RECEIVABLE_CONTROL' : 'CLIENT_ADVANCE';

                    // ── Engine draft (ON path): a BALANCED two-line document — Dr the MyFatoorah
                    // gateway clearing account (GATEWAY_CLEARING_MYFATOORAH, already seeded by
                    // SystemAccountsSeeder::resolveGatewayClearing() for every company that has a
                    // resolvable "Payment Gateway" leaf under Assets — no new purpose code was
                    // needed) / Cr $creditPurposeCode (RECEIVABLE_CONTROL or CLIENT_ADVANCE — see
                    // comment above; both seeded by SystemAccountsSeeder::resolveControls(), the
                    // former as the Accounts Receivable "Clients" leaf — never the ambiguous
                    // Refund Payable "Clients" of the same name — the latter as the Liabilities
                    // "Payment Gateway" leaf, never the Assets one GATEWAY_CLEARING_* resolves
                    // under). This replaces the legacy one-sided credit-only entry (R2.2b) with a
                    // real double-entry document: money received via the gateway clears into
                    // either the client's receivable balance or an advance liability.
                    $draft = new DocumentDraft(
                        companyId: $companyId,
                        branchId: $branchId,
                        docType: 'RV', // Receipt Voucher — money received from the client
                        subType: 'MYFATOORAH',
                        docDate: now(),
                        narration: 'Advance Payment in voucher number: ' . $payment->voucher_number,
                        lines: [
                            new LineDraft(
                                purposeCode: 'GATEWAY_CLEARING_MYFATOORAH',
                                accountId: null,
                                side: 'debit',
                                amount: (float) $payment->amount,
                                // W1.1 fix: config-driven, not a hardcoded literal — matches the
                                // salary feeder's own existing convention (identical value today,
                                // 'KWD', but no longer silently divergent from it).
                                currency: config('accounting.engine.base_currency'),
                                originalAmount: (float) $payment->amount,
                                exchangeRate: 1.0,
                                transactionType: 'GATEWAYDEBITED',
                                description: 'MyFatoorah gateway clearing for voucher ' . $payment->voucher_number,
                                // W1.1 fix (M3 — line attribution): the legacy JournalEntry row
                                // this replaces carried invoice_id and voucher_number even though
                                // it had no client attribution of its own (that lived only on the
                                // credit leg below) — carried here too so BOTH legs of this
                                // document stay traceable to the same payment/invoice/voucher.
                                invoiceId: $payment->invoice_id,
                                // Task B (W1.2): without $ledgerType, journal_entries.type falls
                                // back to $transactionType ('GATEWAYDEBITED'), which matches
                                // neither AccountingController screen filter
                                // (whereIn('type', ['payable','expenses']) / ['receivable','income'])
                                // — invisible on every accounting screen the moment a company is
                                // flipped on. There is no legacy counterpart leg to copy a type
                                // from (this is a brand-new, engine-only second leg — R2.2b's
                                // legacy write was one-sided credit-only), so the label is chosen
                                // on the account's own nature: the gateway clearing account is a
                                // receivable FROM the gateway (money owed to us until it settles).
                                ledgerType: 'receivable',
                                voucherNumber: $payment->voucher_number,
                            ),
                            new LineDraft(
                                purposeCode: $creditPurposeCode,
                                accountId: null,
                                side: 'credit',
                                amount: (float) $payment->amount,
                                currency: config('accounting.engine.base_currency'),
                                originalAmount: (float) $payment->amount,
                                exchangeRate: 1.0,
                                transactionType: 'CUSTOMERCREDITED',
                                partyAccountRef: $payment->client_id,
                                description: 'Advance Payment in voucher number: ' . $payment->voucher_number,
                                // W1.1 fix (M3): legacy's single JournalEntry row for this event
                                // carried invoice_id => $payment->invoice_id, name =>
                                // $payment->client->full_name, type => 'receivable', voucher_number
                                // => $payment->voucher_number — all now carried on this line too, so
                                // AccountingController::filterLedgers()'s per-client filter
                                // (type_reference_id, wired via partyAccountRef above) and its
                                // receipt-voucher screen (whereIn('type', ['receivable','income']))
                                // both keep finding this line once the engine is ON. Legacy's own
                                // type_reference_id value ($paymentGateway->id, a self-referencing
                                // account id) is NOT reproduced here — it never matched the
                                // client/supplier/agent id shape filterLedgers() actually filters
                                // on, so it was already a dead/wrong value, not a fact worth
                                // preserving.
                                invoiceId: $payment->invoice_id,
                                ledgerType: 'receivable',
                                partyName: $payment->client->full_name,
                                voucherNumber: $payment->voucher_number,
                            ),
                        ],
                        idempotencyKey: $idempotencyKey,
                        sourceType: 'Receipt',
                        sourceId: $payment->id,
                        invoiceId: $payment->invoice_id,
                        userId: null, // console command — no authenticated actor; never Auth::user()
                        // W1.1 fix (M3, header-level check): legacy's Transaction::create() wrote
                        // payment_reference => $invoiceRef (MyFatoorah's own invoice reference) —
                        // the one real header-level attribution the engine write was dropping.
                        paymentReference: $invoiceRef,
                        // W1.2 fix (Task A): legacy's Transaction::create() also wrote
                        // payment_id => $payment->id (see the legacy closure above) — the other
                        // real header-level gap PostingService::post() was dropping (W1 lead
                        // report §3 myfatoorah, G19 delta). transactions.reference_type stays
                        // 'Receipt' (engine-derived — see sourceType above), never legacy's
                        // 'Payment' label for this same event; that label was already inconsistent
                        // at HEAD (a receipt of money labelled as a payment OUT) and is not
                        // reproduced here. See DocumentDraft::$paymentId's own docblock for the
                        // (payment_id, reference_type) unique-index note this relies on.
                        paymentId: $payment->id,
                    );

                    // Return value deliberately discarded: PostingSeam::post() can return the
                    // legacy closure's own return value, a PostedDocument, OR a bare `null` (W1.1
                    // seam fix S1 — the engine already posted this exact idempotency key under a
                    // prior run, kill-switch flipped since, and the OFF path is now skipping
                    // legacy to avoid double-posting). This call site never branches on the
                    // result either way — a bare `null` is already "treat as posted": execution
                    // falls straight through to the MyFatoorahPayment/status finalization below,
                    // exactly as it does for a real PostedDocument or a legacy Transaction.
                    app(PostingSeam::class)->post($draft, $legacy, 'myfatoorah.payment');
                } elseif (!$alreadyPosted && $companyId === null) {
                    // M1 fix (W1.1) + Task B decision (W1.2, 2026-08-26 — owner decision, applied
                    // here): the company genuinely could not be resolved (agent hard-deleted, or
                    // its branch/company otherwise missing) — neither the legacy closure nor the
                    // engine draft can run without one. Two candidate behaviours were considered
                    // and BOTH rejected:
                    //   - HEAD's own behaviour: the unconditional property-chain read threw,
                    //     rolling back this whole DB::beginTransaction() and stranding the payment
                    //     at 'initiate' forever (this command's own `->where('status', 'initiate')`
                    //     filter means every future cron run hits the identical failure — never
                    //     finalized, never surfaced as anything an operator would notice).
                    //   - W1.1's own (pre-Task-B) behaviour: finalize to 'completed' with a
                    //     myfatoorah_payments row and log only a WARNING — easy to miss in a cron
                    //     log stream that runs every few minutes, and nothing else ever flags that
                    //     real money was collected with zero accounting rows behind it.
                    // APPLIED instead: finalize the payment below (the gateway fact is real —
                    // MyFatoorah genuinely collected this money) AND make the gap LOUD —
                    // Log::critical, not warning, with enough context (payment id, voucher number,
                    // amount, reason) for an accountant to find this payment and post it manually.
                    // This is a deliberately different, chosen outcome — not a restoration of
                    // HEAD's behaviour and not a silent continuation of W1.1's — see the comment
                    // above this branch's `if` for the parallel correction to the M1 comment.
                    $reason = match (true) {
                        $payment->agent_id === null => 'agent missing (agent_id NULL)',
                        $branch === null => 'agent has no branch',
                        default => 'branch has no company',
                    };

                    Log::critical('accounting.payment_unattributed', [
                        'payment_id' => $payment->id,
                        'voucher_number' => $payment->voucher_number,
                        'amount' => $payment->amount,
                        'reason' => $reason,
                        'agent_id' => $payment->agent_id,
                        'idempotency_key' => $idempotencyKey,
                    ]);
                    // No notes/remarks column exists on myfatoorah_payments (migrations
                    // 2025_05_30_035615 / 2025_06_24_101732 / 2025_10_19_182848 — payment_int_id,
                    // payment_id, invoice_id, invoice_ref, invoice_status, customer_reference,
                    // payload only) to also carry this context on that row; none is added here —
                    // out of this task's scope (P1 decision: do not invent a new column for this).
                }

                // W1.1 fix (M2 — P2 policy call, KEEP: W1 lead report §3/§6): HEAD had a variable-
                // shadowing bug here. $transaction at the top of this loop is the MyFatoorah API
                // response row (`$data['InvoiceTransactions'][0] ?? []`, an array). HEAD's legacy
                // code then clobbered that SAME function-scope variable with
                // `$transaction = Transaction::create([...])` (an Eloquent model) — but only
                // when the posting block ran (`!$alreadyPosted`). So `$transaction['PaymentId']`
                // below was reading an ARRAY OFFSET off an Eloquent model whenever the block ran
                // (silently null, since Transaction has no 'PaymentId' attribute), and the real
                // gateway PaymentId only whenever the block did NOT run. myfatoorah_payments.
                // payment_id has therefore always been "NULL for posted payments, populated for
                // already-posted ones" — a guard-dependent data bug, not a deliberate design.
                // The R3 cutover moved `Transaction::create()` into the legacy closure's own
                // scope (`function () use ($payment, $companyId, $branchId, $invoiceRef)` — no
                // `$transaction` in that `use` list), so the API-response `$transaction` array at
                // THIS scope is never clobbered any more. `payment_id` below now stores the real
                // gateway PaymentId on BOTH the legacy and engine paths, unconditionally. This is
                // a genuine improvement (KEPT, not reverted) and was an explicit W1.1 decision,
                // not an accidental side effect of the closure refactor — see the test asserting
                // the real PaymentId on both flag states.
                $existingMF = MyFatoorahPayment::where('payment_int_id', $payment->id)->first();
                if (!$existingMF) {
                    MyFatoorahPayment::create([
                        'payment_int_id'     => $payment->id,
                        'payment_id'         => $transaction['PaymentId'] ?? null,
                        'invoice_id'         => $data['InvoiceId'] ?? null,
                        'invoice_ref'        => $invoiceRef,
                        'invoice_status'     => $data['InvoiceStatus'] ?? null,
                        'customer_reference' => $process === 'invoice' ? ($payment->invoice?->invoice_number ?? null) : $payment->voucher_number,
                        'payload'            => $data,
                    ]);
                }

                $payment->status = 'completed';
                $payment->save();

                $updatedPayments[] = [
                    'id' => $payment->id,
                    'voucher' => $payment->voucher_number,
                    'reference' => $invoiceRef,
                    'client' => $payment->client->full_name ?? 'Not Set',
                ];

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                // R3 route-to-legacy seam: the seam itself already Log::critical's an ON-path
                // PostingException with feeder/company/idempotency-key/exception-class/message
                // (see PostingSeam::post()) before rethrowing it into this catch — but an operator
                // watching only THIS command's own log line, not the accounting.* channel, must
                // still be able to find the failure. exception_class + idempotency_key are logged
                // explicitly (not folded into a generic string) so a frozen/unmapped/unbalanced
                // engine failure is distinguishable at a glance from an unrelated failure (e.g. the
                // addCredit() RuntimeException above, or a legacy Account-not-found guard) that
                // never touched the engine at all. The idempotency key
                // ('gateway:myfatoorah:payment:{id}:partials:none' — W2 Task B; see
                // PaymentIdempotencyKey) makes the next run's retry safe either way: on the OFF
                // path it is the second half
                // of the $alreadyPosted guard above; on the ON path PostingService's own idempotency
                // lookup returns the same document rather than double-posting.
                Log::error('Failed to finalize MyFatoorah payment', [
                    'payment_id' => $payment->id,
                    'invoice'    => $payment->payment_reference,
                    'idempotency_key' => $idempotencyKey ?? null,
                    'exception_class' => get_class($e),
                    'error'      => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('MyFatoorah status check complete.');
        $this->info('-----------------------------------------');
        $this->info('Payments updated to status "completed": ' . count($updatedPayments));
        $this->info('-----------------------------------------');

        if (!empty($updatedPayments)) {
            foreach ($updatedPayments as $p) {
                $this->line("- Voucher: {$p['voucher']} | Client: {$p['client']} | InvoiceRef: {$p['reference']}");
            }
        } else {
            $this->line('No payments were updated.');
        }
        return self::SUCCESS;
    }

    private function getMFPaymentStatus($invoiceId): array
    {
        $configService = new GatewayConfigService();
        $myfatoorahConfig = $configService->getMyFatoorahConfig();

        if($myfatoorahConfig['status'] === 'error') {
            return $myfatoorahConfig;
        }

        $myfatoorahConfig = $myfatoorahConfig['data'];

        $apiKey  = $myfatoorahConfig['api_key'];
        $baseUrl = $myfatoorahConfig['base_url'];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->post("{$baseUrl}/getPaymentStatus", [
            'Key'     => $invoiceId,
            'KeyType' => 'InvoiceId',
        ]);

        Log::info('getPaymentStatusMyFatoorah Response: ' . json_encode($response->json()));

        if (!$response->successful() || !$response->json('IsSuccess')) {
            return [
                'success' => false,
                'message' => $response->json('Message') ?? 'Failed API response',
            ];
        }

        return [
            'success'        => true,
            'invoice_status' => $response->json('Data.InvoiceStatus'),
            'amount'         => $response->json('Data.InvoiceValue'),
            'invoice_id'     => $response->json('Data.InvoiceId'),
            'raw'            => $response->json('Data'),
        ];
    }
}
