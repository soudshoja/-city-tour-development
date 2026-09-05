<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\GatewaySettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * accounting-builds T7 (Lane D — PLAN.md §5): operator entry point for recording a real gateway
 * payout, mirroring the "Record settlement" screen action for anyone who prefers the CLI (or a
 * scheduled import). Two shapes, matching the plan's own signature:
 *
 *   - Single payout: `--gateway= --payout-ref= --gross= --fee= --net= --date= --bank=`
 *   - Batch import:  `--file=` — a CSV with one payout per row, columns
 *     `gateway,payout_reference,payout_date,gross,fee,net,bank_account_id[,currency][,recognised_fee]`
 *     (header row required; `--gateway`/`--payout-ref`/etc. are ignored when `--file` is given).
 *
 * All logic lives in {@see GatewaySettlementService} — this is a thin CLI wrapper, same
 * convention every command in this file family follows (see {@see YearClose}).
 */
class GatewaySettleCommand extends Command
{
    protected $signature = 'accounting:gateway-settle
                            {company : Company id}
                            {--gateway= : Gateway key, e.g. TAP/KNET/MYFATOORAH/HESABE/UPAYMENT}
                            {--payout-ref= : The gateway\'s own payout/batch reference}
                            {--gross= : Total collected, before fees}
                            {--fee= : Total fee actually charged by the gateway}
                            {--net= : Actual amount paid into the bank (gross - fee)}
                            {--date= : Payout date, YYYY-MM-DD}
                            {--bank= : Bank account id (must be a leaf under the Bank Accounts group)}
                            {--currency= : Defaults to the engine base currency}
                            {--recognised-fee=0 : Fee already recognised at receipt time for the underlying charges (0 when unknown)}
                            {--channel= : Explicit settlement_channel; derived from --gateway when omitted}
                            {--file= : CSV of payouts (header row: gateway,payout_reference,payout_date,gross,fee,net,bank_account_id[,currency][,recognised_fee])}
                            {--user= : Acting user id, recorded on the audit trail}';

    protected $description = 'Record a real gateway payout (and, when the engine is on, post the clearing->bank settlement with a fee true-up).';

    public function handle(GatewaySettlementService $service): int
    {
        $companyId = (int) $this->argument('company');
        $userOption = $this->option('user');
        $actor = $userOption !== null ? User::find((int) $userOption) : null;

        $file = $this->option('file');

        $rows = $file !== null
            ? $this->rowsFromCsv((string) $file)
            : [[
                'gateway' => (string) $this->option('gateway'),
                'payout_reference' => (string) $this->option('payout-ref'),
                'payout_date' => (string) $this->option('date'),
                'gross' => (string) $this->option('gross'),
                'fee' => (string) $this->option('fee'),
                'net' => (string) $this->option('net'),
                'bank_account_id' => (string) $this->option('bank'),
                'currency' => $this->option('currency'),
                'recognised_fee' => (string) $this->option('recognised-fee'),
            ]];

        if ($rows === null) {
            $this->error("Could not read CSV file: {$file}");

            return self::FAILURE;
        }

        $ok = 0;
        $failed = 0;

        foreach ($rows as $i => $row) {
            $missing = array_filter(
                ['gateway', 'payout_reference', 'payout_date', 'gross', 'fee', 'net', 'bank_account_id'],
                fn (string $key) => trim((string) ($row[$key] ?? '')) === ''
            );

            if ($missing !== []) {
                $this->error('Row '.($i + 1).': missing '.implode(', ', $missing));
                $failed++;

                continue;
            }

            $bankAccountId = (int) $row['bank_account_id'];
            if (Account::withoutGlobalScopes()->find($bankAccountId) === null) {
                $this->error('Row '.($i + 1).": bank account #{$bankAccountId} does not exist.");
                $failed++;

                continue;
            }

            try {
                $settlement = $service->record(
                    companyId: $companyId,
                    gateway: (string) $row['gateway'],
                    payoutReference: (string) $row['payout_reference'],
                    payoutDate: Carbon::parse((string) $row['payout_date']),
                    gross: (float) $row['gross'],
                    fee: (float) $row['fee'],
                    net: (float) $row['net'],
                    bankAccountId: $bankAccountId,
                    currency: ($row['currency'] ?? null) !== null && trim((string) $row['currency']) !== '' ? (string) $row['currency'] : null,
                    settlementChannel: $file === null ? $this->option('channel') : null,
                    recognisedFee: (float) ($row['recognised_fee'] ?? 0),
                    source: $file !== null ? \App\Models\GatewaySettlement::SOURCE_CSV : \App\Models\GatewaySettlement::SOURCE_MANUAL,
                    actor: $actor,
                );

                $this->info(sprintf(
                    'Row %d: settlement #%d (%s %s) status=%s%s',
                    $i + 1,
                    $settlement->id,
                    $settlement->gateway,
                    $settlement->payout_reference,
                    $settlement->status,
                    $settlement->transaction_id !== null ? " transaction=#{$settlement->transaction_id}" : ''
                ));
                $ok++;
            } catch (\Throwable $e) {
                $this->error('Row '.($i + 1).': '.$e->getMessage());
                $failed++;
            }
        }

        $this->line("Done: {$ok} recorded, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function rowsFromCsv(string $path): ?array
    {
        if (! is_readable($path)) {
            return null;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return null;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(fn ($h) => trim((string) $h), $header);
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) === 1 && trim((string) $line[0]) === '') {
                continue; // blank line
            }

            $rows[] = array_combine($header, array_pad($line, count($header), null));
        }

        fclose($handle);

        return $rows;
    }
}
