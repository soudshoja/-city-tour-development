<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Wallet;
use App\Models\AgentNotificationSetting;
use App\Services\IataEasyPayService;
use App\Services\IataWalletAlert;
use App\Http\Controllers\ResayilController;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polls IATA EasyPay wallet balances every 15 min (native cron + flock) and
 * WhatsApps opted-in agents whenever a wallet balance changes. IATA exposes no
 * webhook, so detection is poll-and-compare: the latest `wallets` snapshot per
 * `wallet_id` is the prior balance. On any non-zero delta a new snapshot is
 * recorded FIRST (so the same delta never re-fires) and then the alert is sent
 * to agents opted into notification_type 'iata_wallet_change' (channel
 * whatsapp/both, is_active) for that company. First sight of a wallet records a
 * baseline only — no alert. Unchanged balance = nothing written, nothing sent.
 */
class CheckIataWallet extends Command
{
    protected $signature = 'app:check-iata-wallet {--companyId= : Limit to one company}';

    protected $description = 'Poll IATA EasyPay wallet balances and WhatsApp opted-in agents on any change';

    public function handle(): int
    {
        $companies = Company::query()
            ->whereNotNull('iata_code')->where('iata_code', '!=', '')
            ->whereNotNull('iata_client_id')->where('iata_client_id', '!=', '')
            ->whereNotNull('iata_client_secret')->where('iata_client_secret', '!=', '')
            ->when($this->option('companyId'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        if ($companies->isEmpty()) {
            $this->info('No companies with IATA credentials.');
            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            try {
                $this->processCompany($company);
            } catch (Throwable $e) {
                // One company's API failure must never block the others.
                Log::error('[CheckIataWallet] company failed', ['company_id' => $company->id, 'err' => $e->getMessage()]);
                $this->error("Company {$company->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    private function processCompany(Company $company): void
    {
        $service = new IataEasyPayService($company->iata_client_id, $company->iata_client_secret);
        $data = $service->getWalletBalanceByCompany($company->iata_code, 'KWD');

        $wallets = collect($data['wallets'] ?? [])->where('status', 'OPEN')->values();
        if ($wallets->isEmpty()) {
            $this->info("Company {$company->id}: no OPEN wallets.");
            return;
        }

        foreach ($wallets as $w) {
            $walletId = $w['id'] ?? null;
            if (!$walletId) {
                continue;
            }
            $newBal   = (float) ($w['balance'] ?? 0);
            $currency = (string) ($w['currency'] ?? '');
            $iataNumber = null;
            if (!empty($w['name']) && preg_match('/- (\d+) -/', $w['name'], $m)) {
                $iataNumber = $m[1];
            }

            $prev = Wallet::where('wallet_id', $walletId)->orderByDesc('id')->first();

            // First sight of this wallet: record baseline, do NOT alert.
            if (!$prev) {
                $this->snapshot($walletId, $iataNumber, $currency, $newBal, $newBal, 0);
                $this->info("Company {$company->id} wallet {$walletId}: baseline recorded ({$newBal}).");
                continue;
            }

            $prevBal = (float) $prev->wallet_balance;
            $delta   = round($newBal - $prevBal, 3);

            if ($delta === 0.0) {
                $this->info("Company {$company->id} wallet {$walletId}: unchanged ({$newBal}).");
                continue;
            }

            // Record the snapshot FIRST so the same delta can never re-fire.
            $this->snapshot($walletId, $iataNumber, $currency, $newBal, $prevBal, $delta);
            $this->notify($company, $iataNumber, $prevBal, $newBal, $currency);
            $this->warn("Company {$company->id} wallet {$walletId}: {$prevBal} -> {$newBal} (delta {$delta}); alerted.");
        }
    }

    private function snapshot(string $walletId, ?string $iataNumber, string $currency, float $newBal, float $openingBal, float $delta): void
    {
        Wallet::create([
            'wallet_id'       => $walletId,
            'iata_number'     => $iataNumber,
            'currency'        => $currency,
            'wallet_balance'  => $newBal,
            'opening_balance' => $openingBal,
            'closing_balance' => $newBal,
            'task_amount'     => $delta,
        ]);
    }

    private function notify(Company $company, ?string $iataNumber, float $prev, float $new, string $currency): void
    {
        $recipients = DB::table('agents as a')
            ->join('agent_notification_settings as s', 's.agent_id', '=', 'a.id')
            ->where('s.notification_type', AgentNotificationSetting::TYPE_IATA_WALLET_CHANGE)
            ->where('s.company_id', $company->id)
            ->where('s.is_active', 1)
            ->whereIn('s.channel', ['whatsapp', 'both'])
            ->whereNotNull('a.phone_number')
            ->where('a.phone_number', '!=', '')
            ->select('a.id', 'a.phone_number', 'a.country_code')
            ->get();

        if ($recipients->isEmpty()) {
            Log::warning('[CheckIataWallet] wallet changed but no opt-in recipients', ['company_id' => $company->id]);
            return;
        }

        $msg = IataWalletAlert::build(
            $company->name ?? ('Company #' . $company->id),
            $iataNumber,
            $prev,
            $new,
            $currency,
            Carbon::now()->format('Y-m-d H:i')
        );

        foreach ($recipients as $r) {
            try {
                (new ResayilController())->message(
                    phone: $r->phone_number,
                    country_code: $r->country_code ?? '',
                    message: $msg,
                    isDummyNumber: false,
                );
            } catch (Throwable $e) {
                Log::error('[CheckIataWallet] send failed', [
                    'company_id' => $company->id, 'agent_id' => $r->id, 'err' => $e->getMessage(),
                ]);
            }
        }
    }
}
