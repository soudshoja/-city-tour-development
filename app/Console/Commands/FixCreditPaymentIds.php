<?php

namespace App\Console\Commands;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Refund;
use App\Services\ChargeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixCreditPaymentIds extends Command
{
    protected $signature = 'fix:credit-payment-ids
                            {--company= : Specific company ID}
                            {--client= : Specific client ID}
                            {--dry-run : Preview changes without saving}
                            {--force : Skip confirmation}';

    protected $description = 'Link old credits (payment_id=NULL) to source topup payments using FIFO, calculate gateway_fee, and create missing payment_applications';

    private int $totalUnlinked = 0;
    private int $matched = 0;
    private int $unmatched = 0;
    private int $feesCalculated = 0;
    private int $applicationsCreated = 0;
    private int $orphanAppsCreated = 0;
    private int $paymentFeesBackfilled = 0;
    private array $unmatchedDetails = [];
    private array $matchDetails = [];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  Fix Credit Payment IDs');
        $this->info('═══════════════════════════════════════════════════════════');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be saved');
        }

        $this->newLine();
        $this->info('This command will:');
        $this->info('  1. Link old credits (payment_id=NULL) to source topup payments');
        $this->info('  2. Calculate proportional gateway_fee on linked credits');
        $this->info('  3. Create missing payment_application records (including orphans)');
        $this->newLine();

        // ═══════════════════════════════════════════════════════════
        // PHASE 1: Fix credits with NO payment_id
        // ═══════════════════════════════════════════════════════════
        $this->info('── Phase 1: Link unlinked credits to payments ──');

        // ─── Find all unlinked credit usage records ───
        $query = Credit::where('type', 'Invoice')
            ->where('amount', '<', 0)
            ->whereNull('payment_id')
            ->whereNull('refund_id'); // Skip refund-based credits

        if ($companyId = $this->option('company')) {
            $query->where('company_id', $companyId);
        }

        if ($clientId = $this->option('client')) {
            $query->where('client_id', $clientId);
        }

        $unlinkedCredits = $query->orderBy('created_at')->get();
        $this->totalUnlinked = $unlinkedCredits->count();

        $this->info("Found {$this->totalUnlinked} unlinked credit usage records");

        if ($this->totalUnlinked > 0) {
            if (!$dryRun && !$this->option('force')) {
                if (!$this->confirm('Do you want to proceed?')) {
                    $this->info('Aborted.');
                    return 0;
                }
            }

            // ─── Group by client and process ───
            $grouped = $unlinkedCredits->groupBy('client_id');
            $this->info("Processing credits for {$grouped->count()} clients...");

            $bar = $this->output->createProgressBar($grouped->count());
            $bar->start();

            foreach ($grouped as $cId => $clientCredits) {
                $this->processClient($cId, $clientCredits, $dryRun);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
        }

        // ═══════════════════════════════════════════════════════════
        // PHASE 2: Fix orphaned credits (have payment_id, no payment_application)
        // ═══════════════════════════════════════════════════════════
        $this->info('── Phase 2: Create missing payment_applications ──');

        $this->fixOrphanedApplications($dryRun);

        $this->newLine();
        $this->printSummary($dryRun);

        return 0;
    }

    /**
     * Find credits that have payment_id OR refund_id but no matching payment_application row.
     * Create the missing rows.
     */
    private function fixOrphanedApplications(bool $dryRun): void
    {
        // Find ALL invoice credit usages (both payment-based and refund-based)
        $query = Credit::where('type', 'Invoice')
            ->where('amount', '<', 0)
            ->where(function ($q) {
                $q->whereNotNull('payment_id')
                    ->orWhereNotNull('refund_id');
            });

        if ($companyId = $this->option('company')) {
            $query->where('company_id', $companyId);
        }

        if ($clientId = $this->option('client')) {
            $query->where('client_id', $clientId);
        }

        $linkedCredits = $query->with(['invoice', 'payment', 'refund'])->orderBy('created_at')->get();

        $orphanCount = 0;

        foreach ($linkedCredits as $credit) {
            // Check if payment_application already exists
            $existsQuery = PaymentApplication::where('invoice_id', $credit->invoice_id);

            if ($credit->payment_id) {
                $existsQuery->where('payment_id', $credit->payment_id);
            }

            if ($credit->invoice_partial_id) {
                $existsQuery->where('invoice_partial_id', $credit->invoice_partial_id);
            }

            // For refund credits without payment_id, check by credit_id
            if (!$credit->payment_id && $credit->refund_id) {
                $topupCredit = Credit::where('refund_id', $credit->refund_id)
                    ->where('type', 'Refund')
                    ->where('amount', '>', 0)
                    ->first();

                if ($topupCredit) {
                    $existsQuery = PaymentApplication::where('invoice_id', $credit->invoice_id)
                        ->where('credit_id', $topupCredit->id);

                    if ($credit->invoice_partial_id) {
                        $existsQuery->where('invoice_partial_id', $credit->invoice_partial_id);
                    }
                }
            }

            if ($existsQuery->exists()) {
                continue; // Already has payment_application — skip
            }

            $orphanCount++;

            $isRefund = !$credit->payment_id && $credit->refund_id;
            $paymentType = $this->getPaymentTypeLabel($credit->invoice);

            if ($isRefund) {
                // Refund credit — payment_id = NULL, use refund number
                $topupCredit = Credit::where('refund_id', $credit->refund_id)
                    ->where('type', 'Refund')
                    ->where('amount', '>', 0)
                    ->first();

                $refundNumber = $credit->refund->refund_number ?? ('RF-' . $credit->refund_id);
                $notes = "Applied from {$refundNumber} ({$paymentType})";

                if (!$dryRun) {
                    try {
                        PaymentApplication::create([
                            'payment_id' => null,
                            'credit_id' => $topupCredit->id ?? $credit->id,
                            'invoice_id' => $credit->invoice_id,
                            'invoice_partial_id' => $credit->invoice_partial_id,
                            'amount' => abs((float) $credit->amount),
                            'applied_by' => null,
                            'applied_at' => $credit->created_at,
                            'notes' => $notes,
                        ]);
                        $this->orphanAppsCreated++;
                    } catch (\Exception $e) {
                        Log::error('FixCreditPaymentIds: Failed to create orphan payment_application (refund)', [
                            'credit_id' => $credit->id,
                            'refund_id' => $credit->refund_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    $this->orphanAppsCreated++;
                }
            } else {
                // Voucher credit — payment_id set, use voucher number
                $topupCredit = Credit::where('payment_id', $credit->payment_id)
                    ->where('type', 'Topup')
                    ->where('amount', '>', 0)
                    ->first();

                $payment = $credit->payment;
                $voucherNumber = $payment->voucher_number ?? ('PAY-' . $credit->payment_id);
                $notes = "Applied from {$voucherNumber} ({$paymentType})";

                if (!$dryRun) {
                    try {
                        PaymentApplication::create([
                            'payment_id' => $credit->payment_id,
                            'credit_id' => $topupCredit->id ?? null,
                            'invoice_id' => $credit->invoice_id,
                            'invoice_partial_id' => $credit->invoice_partial_id,
                            'amount' => abs((float) $credit->amount),
                            'applied_by' => null,
                            'applied_at' => $credit->created_at,
                            'notes' => $notes,
                        ]);
                        $this->orphanAppsCreated++;
                    } catch (\Exception $e) {
                        Log::error('FixCreditPaymentIds: Failed to create orphan payment_application (voucher)', [
                            'credit_id' => $credit->id,
                            'payment_id' => $credit->payment_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    $this->orphanAppsCreated++;
                }
            }
        }

        $this->info("Found {$orphanCount} credits with payment_id/refund_id but no payment_application");
        $this->info("Orphan applications created: {$this->orphanAppsCreated}");
    }

    private function processClient(int $clientId, $credits, bool $dryRun): void
    {
        // ─── Step 1: Get all topup credits for this client ───
        // Topup credits ALWAYS have payment_id (set in addCredit())
        $topups = Credit::where('client_id', $clientId)
            ->where('type', 'Topup')
            ->where('amount', '>', 0)
            ->whereNotNull('payment_id')
            ->orderBy('created_at')
            ->get();

        if ($topups->isEmpty()) {
            // No topups found — these credits might be from manual adjustments
            foreach ($credits as $credit) {
                $this->unmatched++;
                $this->unmatchedDetails[] = [
                    'credit_id' => $credit->id,
                    'client_id' => $clientId,
                    'amount' => $credit->amount,
                    'invoice_id' => $credit->invoice_id,
                    'reason' => 'No topup credits found for client',
                ];
            }
            return;
        }

        // ─── Step 2: Calculate already-used balance per payment_id ───
        // This includes BOTH old linked credits AND new ones (from PaymentApplicationService)
        $usedMap = Credit::where('client_id', $clientId)
            ->where('type', 'Invoice')
            ->where('amount', '<', 0)
            ->whereNotNull('payment_id')
            ->groupBy('payment_id')
            ->selectRaw('payment_id, SUM(ABS(amount)) as total_used')
            ->pluck('total_used', 'payment_id')
            ->toArray();

        // ─── Step 3: Build balance tracker (FIFO order) ───
        $balances = [];
        foreach ($topups as $topup) {
            $used = (float) ($usedMap[$topup->payment_id] ?? 0);
            $remaining = round((float) $topup->amount - $used, 3);

            $balances[] = [
                'payment_id' => $topup->payment_id,
                'topup_credit_id' => $topup->id,
                'topup_amount' => (float) $topup->amount,
                'remaining' => $remaining,
                'created_at' => $topup->created_at,
            ];
        }

        // ─── Step 4: Smart matching (exact amount → close range → largest balance) ───
        $sortedCredits = $credits->sortBy('created_at');

        foreach ($sortedCredits as $credit) {
            $usageAmount = abs((float) $credit->amount);
            $matchFound = false;

            // Pass 1: EXACT amount match — topup amount == usage amount
            // This is the most common case: client tops up exact invoice amount
            foreach ($balances as &$topup) {
                if ($topup['remaining'] < 0.01) continue;
                if ($topup['created_at'] > $credit->created_at) continue;

                if (abs($topup['remaining'] - $usageAmount) <= 0.01) {
                    $this->linkCreditToPayment($credit, $topup, $usageAmount, $dryRun);
                    $topup['remaining'] = round($topup['remaining'] - $usageAmount, 3);
                    $matchFound = true;
                    break;
                }
            }
            unset($topup);

            // Pass 2: Close range — find topup with remaining balance within 10% of usage
            if (!$matchFound) {
                $bestMatch = null;
                $bestDiff = PHP_FLOAT_MAX;

                foreach ($balances as $idx => &$topup) {
                    if ($topup['remaining'] < 0.01) continue;
                    if ($topup['created_at'] > $credit->created_at) continue;
                    // Must have enough balance
                    if ($topup['remaining'] < ($usageAmount - 0.01)) continue;

                    $diff = abs($topup['remaining'] - $usageAmount);
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestMatch = $idx;
                    }
                }
                unset($topup);

                if ($bestMatch !== null && $bestDiff <= ($usageAmount * 0.10)) {
                    $this->linkCreditToPayment($credit, $balances[$bestMatch], $usageAmount, $dryRun);
                    $balances[$bestMatch]['remaining'] = round($balances[$bestMatch]['remaining'] - $usageAmount, 3);
                    $matchFound = true;
                }
            }

            // Pass 3: Any topup with enough balance (oldest first)
            if (!$matchFound) {
                foreach ($balances as &$topup) {
                    if ($topup['remaining'] < 0.01) continue;
                    if ($topup['created_at'] > $credit->created_at) continue;

                    if ($topup['remaining'] >= ($usageAmount - 0.01)) {
                        $this->linkCreditToPayment($credit, $topup, $usageAmount, $dryRun);
                        $topup['remaining'] = round($topup['remaining'] - $usageAmount, 3);
                        $matchFound = true;
                        break;
                    }
                }
                unset($topup);
            }

            // Pass 4: Last resort — topup with most remaining balance
            if (!$matchFound) {
                $bestIdx = null;
                $bestRemaining = 0;

                foreach ($balances as $idx => $topup) {
                    if ($topup['remaining'] < 0.01) continue;
                    if ($topup['created_at'] > $credit->created_at) continue;

                    if ($topup['remaining'] > $bestRemaining) {
                        $bestRemaining = $topup['remaining'];
                        $bestIdx = $idx;
                    }
                }

                if ($bestIdx !== null) {
                    $this->linkCreditToPayment($credit, $balances[$bestIdx], $usageAmount, $dryRun);
                    $balances[$bestIdx]['remaining'] = round($balances[$bestIdx]['remaining'] - $usageAmount, 3);
                    $matchFound = true;
                }
            }

            if (!$matchFound) {
                $this->unmatched++;
                $this->unmatchedDetails[] = [
                    'credit_id' => $credit->id,
                    'client_id' => $clientId,
                    'amount' => $credit->amount,
                    'invoice_id' => $credit->invoice_id,
                    'reason' => 'No available topup with balance before usage date',
                ];
            }
        }
    }

    private function linkCreditToPayment($credit, array $topup, float $usageAmount, bool $dryRun): void
    {
        $paymentId = $topup['payment_id'];
        $topupCreditId = $topup['topup_credit_id'];

        // ─── Calculate gateway_fee ───
        $payment = Payment::find($paymentId);
        $paymentFee = 0;
        $proportionalFee = 0;
        $voucherNumber = $payment->voucher_number ?? ('PAY-' . $paymentId);

        if ($payment) {
            $paymentFee = (float) $payment->gateway_fee;

            // If payment gateway_fee not yet backfilled, calculate it now
            if ($paymentFee <= 0 && $payment->payment_gateway && $payment->amount > 0) {
                $companyId = $credit->company_id;
                try {
                    $result = ChargeService::calculate(
                        (float) $payment->amount,
                        $companyId,
                        $payment->payment_method_id,
                        $payment->payment_gateway
                    );
                    $paymentFee = (float) ($result['accountingFee'] ?? 0);

                    // Also backfill the payment while we're at it
                    if (!$dryRun && $paymentFee > 0) {
                        $payment->gateway_fee = $paymentFee;
                        $payment->save();
                        $this->paymentFeesBackfilled++;
                    }
                } catch (\Exception $e) {
                    Log::warning('FixCreditPaymentIds: Could not calculate fee for payment', [
                        'payment_id' => $paymentId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Proportional fee
            if ($paymentFee > 0 && $payment->amount > 0) {
                $proportionalFee = round($paymentFee * ($usageAmount / (float) $payment->amount), 3);
            }
        }

        // ─── Build notes with voucher + payment type ───
        $invoice = $credit->invoice_id ? Invoice::find($credit->invoice_id) : null;
        $paymentType = $this->getPaymentTypeLabel($invoice);
        $notes = "Applied from {$voucherNumber} ({$paymentType})";

        $this->matchDetails[] = [
            'credit_id' => $credit->id,
            'client_id' => $credit->client_id,
            'invoice_id' => $credit->invoice_id,
            'amount' => $credit->amount,
            'payment_id' => $paymentId,
            'payment_fee' => $paymentFee,
            'proportional_fee' => $proportionalFee,
            'topup_amount' => $topup['topup_amount'],
        ];

        if (!$dryRun) {
            try {
                DB::beginTransaction();

                // ─── Update credit with payment_id + gateway_fee ───
                $credit->payment_id = $paymentId;
                $credit->gateway_fee = $proportionalFee;
                $credit->save();
                $this->matched++;

                if ($proportionalFee > 0) {
                    $this->feesCalculated++;
                }

                // ─── Create PaymentApplication if not exists ───
                $existingApp = PaymentApplication::where('payment_id', $paymentId)
                    ->where('invoice_id', $credit->invoice_id)
                    ->where('invoice_partial_id', $credit->invoice_partial_id)
                    ->first();

                if (!$existingApp) {
                    PaymentApplication::create([
                        'payment_id' => $paymentId,
                        'credit_id' => $topupCreditId,
                        'invoice_id' => $credit->invoice_id,
                        'invoice_partial_id' => $credit->invoice_partial_id,
                        'amount' => $usageAmount,
                        'applied_by' => null,
                        'applied_at' => $credit->created_at,
                        'notes' => $notes,
                    ]);
                    $this->applicationsCreated++;
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->unmatched++;
                Log::error('FixCreditPaymentIds: Failed to link credit', [
                    'credit_id' => $credit->id,
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $this->matched++;
            if ($proportionalFee > 0) {
                $this->feesCalculated++;
            }
            $this->applicationsCreated++;
        }
    }

    /**
     * Get the payment type label for notes.
     * Maps invoice->payment_type to human-readable format.
     */
    private function getPaymentTypeLabel(?Invoice $invoice): string
    {
        if (!$invoice || !$invoice->payment_type) {
            return 'full payment';
        }

        return match (strtolower($invoice->payment_type)) {
            'partial' => 'partial payment',
            'split'   => 'split payment',
            'credit'  => 'full payment',
            'full'    => 'full payment',
            default   => 'full payment',
        };
    }

    private function printSummary(bool $dryRun): void
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  Summary');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info("Total unlinked credits:     {$this->totalUnlinked}");
        $this->info("Successfully matched:       {$this->matched}");
        $this->info("Unmatched:                  {$this->unmatched}");
        $this->info("Gateway fees calculated:    {$this->feesCalculated}");
        $this->info("Payment apps (Phase 1):     {$this->applicationsCreated}");
        $this->info("Payment apps (Phase 2):     {$this->orphanAppsCreated}");
        $totalApps = $this->applicationsCreated + $this->orphanAppsCreated;
        $this->info("Payment apps total:         {$totalApps}");

        if ($this->paymentFeesBackfilled > 0) {
            $this->info("Payment fees backfilled:    {$this->paymentFeesBackfilled}");
        }

        // Show sample matches
        if (!empty($this->matchDetails) && $dryRun) {
            $this->newLine();
            $this->info('Sample matches (first 20):');
            $this->table(
                ['Credit ID', 'Client', 'Invoice', 'Amount', 'Payment ID', 'Topup Amt', 'Pay Fee', 'Credit Fee'],
                collect(array_slice($this->matchDetails, 0, 20))->map(fn($m) => [
                    $m['credit_id'],
                    $m['client_id'],
                    $m['invoice_id'],
                    number_format(abs($m['amount']), 3),
                    $m['payment_id'],
                    number_format($m['topup_amount'], 3),
                    number_format($m['payment_fee'], 3),
                    number_format($m['proportional_fee'], 3),
                ])->toArray()
            );

            if (count($this->matchDetails) > 20) {
                $this->info('... and ' . (count($this->matchDetails) - 20) . ' more matches');
            }
        }

        // Show unmatched
        if (!empty($this->unmatchedDetails)) {
            $this->newLine();
            $this->warn("Unmatched credits ({$this->unmatched}):");
            $this->table(
                ['Credit ID', 'Client', 'Invoice', 'Amount', 'Reason'],
                collect(array_slice($this->unmatchedDetails, 0, 20))->map(fn($u) => [
                    $u['credit_id'],
                    $u['client_id'],
                    $u['invoice_id'],
                    number_format(abs($u['amount']), 3),
                    $u['reason'],
                ])->toArray()
            );

            if (count($this->unmatchedDetails) > 20) {
                $this->info('... and ' . (count($this->unmatchedDetails) - 20) . ' more unmatched');
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Run without --dry-run to apply changes.');
        }

        Log::info('FixCreditPaymentIds completed', [
            'dry_run' => $dryRun,
            'total' => $this->totalUnlinked,
            'matched' => $this->matched,
            'unmatched' => $this->unmatched,
            'fees_calculated' => $this->feesCalculated,
            'applications_created' => $this->applicationsCreated,
        ]);
    }
}
