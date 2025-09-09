<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Charge;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\ChargeService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixGatewayCharges extends Command
{
    protected $signature = 'fees:fix-gateway-charges
        {--company= : Company ID}
        {--gateway= : Filter gateway (tap|myfatoorah)}
        {--from= : Start date YYYY-MM-DD}
        {--to= : End date YYYY-MM-DD}
        {--dry-run : Show changes without saving}';

    protected $description = 'Recalculate gateway fee and UPDATE charges JE (description, debit, balance) only.';

    public function handle(): int
    {
        $company = $this->option('company');
        $gateway = $this->option('gateway') ? strtolower($this->option('gateway')) : null;
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = Payment::query()
            ->with(['paymentMethod', 'agent.branch.company'])
            ->when($company, fn($query) => $query->whereHas('agent.branch', fn($b) => $b->where('company_id', $company)))
            ->when($gateway, fn($query) => $query->whereRaw('LOWER(payment_gateway) = ?', [$gateway]))
            ->when($from, fn($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn($query) => $query->where('created_at', '<=', $to))
            ->where('status', 'completed')
            ->orderBy('id');

        $count = (clone $query)->count();
        $this->info("Payments found: {$count}" . ($dryRun ? ' [dry-run]' : ''));

        $stats = [ 'ELIGIBLE' => 0, 'CHANGE' => 0, 'NO_CHANGE' => 0, 'SKIP_NO_JE' => 0, 'ERR_CALC' => 0,
            'SKIP_NO_ACC_FEE_ID' => 0, 'SKIP_NO_CHARGES_ACCOUNT' => 0, 'SKIP_NO_COMPANY' => 0, 'SKIP_UNSUPPORTED' => 0];
        $processed = 0;
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(200, function ($payments) use (&$processed, $dryRun, $bar, &$stats) {
            foreach ($payments as $payment) {
                DB::transaction(function () use ($payment, $dryRun, &$stats) {
                    $this->fixOne($payment, $dryRun, $stats);
                });
                $processed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Processed: {$processed}");

        $this->line(sprintf(
            "Eligible (has JE): %d | CHANGE: %d | NO_CHANGE: %d",
            $stats['ELIGIBLE'], $stats['CHANGE'], $stats['NO_CHANGE']
        ));
        $this->line(sprintf(
            "Skipped → NO_JE: %d | ERR_CALC: %d | NO_ACC_FEE_ID: %d | NO_CHARGES_ACCOUNT: %d | NO_COMPANY: %d | UNSUPPORTED: %d",
            $stats['SKIP_NO_JE'], $stats['ERR_CALC'], $stats['SKIP_NO_ACC_FEE_ID'], $stats['SKIP_NO_CHARGES_ACCOUNT'], $stats['SKIP_NO_COMPANY'], $stats['SKIP_UNSUPPORTED']
        ));

        if ($dryRun) {
            $this->warn('This was a dry-run. Re-run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }

    protected function fixOne(Payment $payment, bool $dryRun, array &$stats): void
    {
        $gatewayName = strtolower($payment->payment_gateway ?? '');
        if (!in_array($gatewayName, ['tap', 'myfatoorah'])) {
            $stats['SKIP_UNSUPPORTED']++;
            if ($dryRun) $this->line("Payment #{$payment->id} | SKIP_UNSUPPORTED");
            return;
        }

        $companyId = $payment->agent?->branch?->company?->id;
        if (!$companyId) {
            $stats['SKIP_NO_COMPANY']++;
            if ($dryRun) $this->line("Payment #{$payment->id} | SKIP_NO_COMPANY");
            return;
        }

        $charge = Charge::where('name', 'LIKE', '%' . $payment->payment_gateway . '%')
            ->where('company_id', $companyId)
            ->select('id','name','acc_fee_id','paid_by')
            ->first();

        if (!$charge?->acc_fee_id) {
            $stats['SKIP_NO_ACC_FEE_ID']++;
            if ($dryRun) $this->line("Payment #{$payment->id} | " . strtoupper($gatewayName) . " | SKIP_NO_ACC_FEE_ID");
            return;
        }

        $chargesAccount = Account::where('id', $charge->acc_fee_id)
            ->where('company_id', $companyId)
            ->first();

        if (!$chargesAccount) {
            $stats['SKIP_NO_CHARGES_ACCOUNT']++;
            if ($dryRun) $this->line("Payment #{$payment->id} | " . strtoupper($gatewayName) . " | SKIP_NO_CHARGES_ACCOUNT");
            return;
        }

        $calc = $this->calc($payment, $companyId, $gatewayName);
        if (($calc['_error'] ?? false) === true) {
            $stats['ERR_CALC']++;
            if ($dryRun) $this->line("Payment #{$payment->id} | " . strtoupper($gatewayName) . " | ERR_CALC: {$calc['_error_msg']}");
            return;
        }

        $paidBy = $payment->paymentMethod?->paid_by ?? $charge?->paid_by ?? ($calc['paidBy'] ?? null) ?? 'Company';
        $gatewayFee = (float) $calc['gatewayFee'] ?? 0;

        // Find existing charges JE for this payment (charges account)
        $existing = JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('account_id', $chargesAccount->id)
            ->where('type', 'charges')
            ->when(!empty($payment->voucher_number),
                fn($query) => $query->where('voucher_number', $payment->voucher_number),
                fn($query) => $query->where('transaction_id', $payment->transaction_id ?? 0)
            )
            ->orderBy('id', 'desc')
            ->first();

        if (!$existing) {
            $stats['SKIP_NO_JE']++;
            if ($dryRun) $this->line(sprintf(
                "Payment #%d | %.2f | %s | SKIP_NO_JOURNAL_ENTRY",
                $payment->id, $payment->amount, strtoupper($gatewayName)
            ));
            return;
        }
        $stats['ELIGIBLE']++;

        $newDescription = ($paidBy === 'Company' ? 'Company Pays Gateway Fee: ' : 'Client Pays Gateway Fee: ') . $chargesAccount->name;
        $oldDebit = (float) $existing->debit;
        $newDebit = $gatewayFee;
        $debitAdjustment = $newDebit - $oldDebit;

        if ($dryRun) {
            $status = ($debitAdjustment != 0 || $existing->description !== $newDescription) ? 'CHANGE' : 'NO_CHANGE';
            $status === 'CHANGE' ? $stats['CHANGE']++ : $stats['NO_CHANGE']++;
            $this->line(sprintf(
                "Payment #%d | %.2f | %s | JE #%d | %s | debit %.2f → %.2f | %s",
                $payment->id, $payment->amount, strtoupper($gatewayName),
                $existing->id, $status, $oldDebit, $newDebit, $newDescription
            ));
            return;
        }

        if ($debitAdjustment != 0) {
            $existing->description = $newDescription;
            $existing->debit = $newDebit;
            $existing->balance = (float) $existing->balance + $debitAdjustment;
            $existing->save();

            $chargesAccount->actual_balance += $debitAdjustment;
            $chargesAccount->save();

            $stats['CHANGE']++;
        } else {
            $stats['NO_CHANGE']++;
        }
    }

    protected function calc(Payment $payment, int $companyId, string $gateway): array
    {
        try {
            if ($gateway === 'myfatoorah') {
                $methodId = $payment->payment_method_id ?? $payment->paymentMethod?->id;
                if (!$methodId) {
                    throw new \RuntimeException('Payment method id not found for MF calc');
                }
                return ChargeService::FatoorahCharge($payment->amount, $methodId, $companyId);
            }
    
            return ChargeService::TapCharge([
                'amount'    => $payment->amount,
                'client_id' => $payment->client_id,
                'agent_id'  => $payment->agent_id,
                'currency'  => $payment->currency,
            ], $payment->payment_gateway);
        } catch (\Throwable $e) {
            $this->warn("Calc failed for payment {$payment->id}: {$e->getMessage()}");
            return ['_error' => true, '_error_msg' => $e->getMessage()];
        }
    }
}