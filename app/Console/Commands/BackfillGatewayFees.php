<?php

namespace App\Console\Commands;

use App\Models\Credit;
use App\Models\InvoicePartial;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\ChargeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillGatewayFees extends Command
{
    protected $signature = 'backfill:gateway-fees
                            {--dry-run : Preview without saving}
                            {--force : Skip confirmation}';

    protected $description = 'Backfill gateway_fee on payments, invoice_partials, and credits';

    private int $updatedPayments = 0;
    private int $updatedPartials = 0;
    private int $updatedCredits = 0;
    private int $updatedTopupCredits = 0;
    private int $updatedCreditPartials = 0;
    private int $skippedCreditPartials = 0;
    private int $errors = 0;

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Backfill Gateway Fees');
        $this->info('═══════════════════════════════════════════════════════');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE');
        }

        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm('This will update payments, invoice_partials, and credits. Proceed?')) {
                return 0;
            }
        }

        $this->backfillPayments($dryRun);
        $this->backfillInvoicePartials($dryRun);
        $this->backfillCredits($dryRun);
        $this->backfillTopupCredits($dryRun);
        $this->backfillCreditPartials($dryRun);

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info("  Payments updated:             {$this->updatedPayments}");
        $this->info("  Invoice Partials (gateway):   {$this->updatedPartials}");
        $this->info("  Credits updated (usage):      {$this->updatedCredits}");
        $this->info("  Credits updated (topup):      {$this->updatedTopupCredits}");
        $this->info("  Invoice Partials (credit):    {$this->updatedCreditPartials}");
        $this->info("  Credit Partials skipped:      {$this->skippedCreditPartials}");
        $this->info("  Errors:                       {$this->errors}");
        $this->info('═══════════════════════════════════════════════════════');

        return 0;
    }

    private function backfillPayments(bool $dryRun): void
    {
        $this->info('');
        $this->info('▶ Backfilling payments.gateway_fee...');

        $payments = Payment::whereNotNull('payment_gateway')
            ->where('gateway_fee', 0)
            ->with('agent.branch')
            ->get();

        $bar = $this->output->createProgressBar($payments->count());

        foreach ($payments as $payment) {
            try {
                $companyId = $payment->agent?->branch?->company_id;
                if (!$companyId) {
                    $bar->advance();
                    continue;
                }

                $result = ChargeService::calculate(
                    (float) $payment->amount,
                    $companyId,
                    $payment->payment_method_id,
                    $payment->payment_gateway
                );

                $fee = $result['accountingFee'] ?? 0;

                if ($fee > 0) {
                    if (!$dryRun) {
                        $payment->gateway_fee = $fee;
                        $payment->save();
                    }
                    $this->updatedPayments++;
                }
            } catch (\Exception $e) {
                $this->errors++;
                Log::error('BackfillGatewayFees: payment error', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function backfillInvoicePartials(bool $dryRun): void
    {
        $this->info('▶ Backfilling invoice_partials.gateway_fee (gateway payments)...');

        $partials = InvoicePartial::whereNotNull('payment_gateway')
            ->where('gateway_fee', 0)
            ->whereNotIn('payment_gateway', ['Credit', 'Cash'])
            ->with('invoice.agent.branch')
            ->get();

        $bar = $this->output->createProgressBar($partials->count());

        foreach ($partials as $partial) {
            try {
                $companyId = $partial->invoice?->agent?->branch?->company_id;
                if (!$companyId) {
                    $bar->advance();
                    continue;
                }

                $methodId = $partial->payment_method;
                if ($methodId && !is_numeric($methodId)) {
                    $method = PaymentMethod::where('name', $methodId)
                        ->where('company_id', $companyId)
                        ->first();
                    $methodId = $method?->id;
                }

                $result = ChargeService::calculate(
                    (float) $partial->amount,
                    $companyId,
                    $methodId,
                    $partial->payment_gateway
                );

                $fee = $result['accountingFee'] ?? 0;

                if ($fee > 0) {
                    if (!$dryRun) {
                        $partial->gateway_fee = $fee;
                        $partial->save();
                    }
                    $this->updatedPartials++;
                }
            } catch (\Exception $e) {
                $this->errors++;
                Log::error('BackfillGatewayFees: partial error', [
                    'partial_id' => $partial->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function backfillCredits(bool $dryRun): void
    {
        $this->info('▶ Backfilling credits.gateway_fee (proportional)...');

        // Only credit USAGE records (negative amount) that link to a payment
        $credits = Credit::where('amount', '<', 0)
            ->whereNotNull('payment_id')
            ->where('gateway_fee', 0)
            ->with('payment')
            ->get();

        $bar = $this->output->createProgressBar($credits->count());

        foreach ($credits as $credit) {
            try {
                $payment = $credit->payment;
                if (!$payment || $payment->gateway_fee <= 0 || $payment->amount <= 0) {
                    $bar->advance();
                    continue;
                }

                $proportion = abs($credit->amount) / $payment->amount;
                $fee = round($payment->gateway_fee * $proportion, 3);

                if ($fee > 0) {
                    if (!$dryRun) {
                        $credit->gateway_fee = $fee;
                        $credit->save();
                    }
                    $this->updatedCredits++;
                }
            } catch (\Exception $e) {
                $this->errors++;
                Log::error('BackfillGatewayFees: credit error', [
                    'credit_id' => $credit->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Backfill gateway_fee on Topup credit records.
     * Simply copy the payment's gateway_fee to the topup credit.
     *
     * Example:
     *   Payment #437 (amount=250, gateway_fee=0.150)
     *   Credit #370 (type=Topup, payment_id=437, amount=250, gateway_fee=0.000)
     *   → credit.gateway_fee = 0.150
     */
    private function backfillTopupCredits(bool $dryRun): void
    {
        $this->info('▶ Backfilling credits.gateway_fee (topup records)...');

        $credits = Credit::where('type', 'Topup')
            ->where('amount', '>', 0)
            ->whereNotNull('payment_id')
            ->where(function ($q) {
                $q->where('gateway_fee', 0)->orWhereNull('gateway_fee');
            })
            ->with('payment')
            ->get();

        $this->info("  Found {$credits->count()} topup credits to process");

        if ($credits->isEmpty()) return;

        $bar = $this->output->createProgressBar($credits->count());

        foreach ($credits as $credit) {
            $bar->advance();

            try {
                $payment = $credit->payment;
                if (!$payment || $payment->gateway_fee <= 0) {
                    continue;
                }

                if (!$dryRun) {
                    $credit->gateway_fee = $payment->gateway_fee;
                    $credit->save();
                }
                $this->updatedTopupCredits++;
            } catch (\Exception $e) {
                $this->errors++;
                Log::error('BackfillGatewayFees: topup credit error', [
                    'credit_id' => $credit->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /*
     * Sum the proportional gateway_fee from all credit usage records
     * linked to each partial.
     *
     * Example:
     *   Partial #860 (Credit, amount=2458)
     *     └── Credit #935 (amount=-2458, gateway_fee=0.056)
     *   → partial.gateway_fee = 0.056
     *
     *   Partial #1972 (Credit, amount=1950)
     *     └── Credit #2305 (amount=-1950, gateway_fee=0.150)
     *   → partial.gateway_fee = 0.150
     */
    private function backfillCreditPartials(bool $dryRun): void
    {
        $this->info('▶ Backfilling invoice_partials.gateway_fee (credit payments)...');

        $partials = InvoicePartial::where('payment_gateway', 'Credit')
            ->where(function ($q) {
                $q->where('gateway_fee', 0)->orWhereNull('gateway_fee');
            })
            ->get();

        $this->info("  Found {$partials->count()} credit partials to process");

        if ($partials->isEmpty()) return;

        $bar = $this->output->createProgressBar($partials->count());

        foreach ($partials as $partial) {
            $bar->advance();

            try {
                // Sum gateway_fee from credits linked to this specific partial
                $creditFeeSum = (float) Credit::where('invoice_partial_id', $partial->id)
                    ->where('amount', '<', 0)
                    ->sum('gateway_fee');

                // Fallback: match by invoice_id (for credits with mismatched or NULL invoice_partial_id)
                if ($creditFeeSum <= 0) {
                    $creditFeeSum = (float) Credit::where('invoice_id', $partial->invoice_id)
                        ->where('amount', '<', 0)
                        ->where('gateway_fee', '>', 0)
                        ->sum('gateway_fee');
                }

                $creditFeeSum = round(abs($creditFeeSum), 3);

                if ($creditFeeSum > 0) {
                    if (!$dryRun) {
                        $partial->gateway_fee = $creditFeeSum;
                        $partial->save();
                    }
                    $this->updatedCreditPartials++;
                } else {
                    $this->skippedCreditPartials++;
                }
            } catch (\Exception $e) {
                $this->errors++;
                Log::error('BackfillGatewayFees: credit partial error', [
                    'partial_id' => $partial->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $bar->finish();
        $this->newLine();
    }
}
