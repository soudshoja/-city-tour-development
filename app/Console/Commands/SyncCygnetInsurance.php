<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCygnetInsurance extends Command
{
    protected $signature = 'app:sync-cygnet-insurance {--dry : List actions without writing}';
    protected $description = 'Pull Cygnet Swan travel-insurance policies into Wethaq insurance tasks (no ledger). Creates VALID policies issued on/after create_from; dedup on policy code in reference/ticket/file_name.';

    private string $base;
    private ?string $token = null;

    public function handle(): int
    {
        $cfg = config('cygnet');
        $this->base = rtrim($cfg['base_url'] ?? '', '/');
        if (empty($cfg['username']) || empty($cfg['password'])) { $this->error('Cygnet credentials not configured.'); return self::FAILURE; }

        $login = Http::acceptJson()->asJson()->post("{$this->base}/api/v5/login", ['username' => $cfg['username'], 'password' => $cfg['password']]);
        $this->token = $login->json('access_token');
        if (!$login->ok() || !$this->token) { $this->error('Cygnet login failed: HTTP '.$login->status()); Log::warning('Cygnet sync: login failed', ['s' => $login->status()]); return self::FAILURE; }

        $supplier = Supplier::find($cfg['supplier_id']);
        if (!$supplier) { $this->error('Supplier not found: '.$cfg['supplier_id']); return self::FAILURE; }

        $tenant = (string) $cfg['tenant'];
        $zones  = config('cygnet.zones', []);
        $from   = (string) ($cfg['create_from'] ?? '2026-05-30');
        $dry    = (bool) $this->option('dry');

        $created = 0; $skipped = 0; $tooOld = 0;
        foreach ($this->fetch($tenant) as $c) {
            if (($c['status'] ?? '') !== 'VALID') { continue; }
            $code = $c['code'] ?? null;
            if (!$code) { continue; }
            if (substr((string)($c['issue_date'] ?? ''), 0, 10) < $from) { $tooOld++; continue; }
            if (Task::where('reference', $code)->orWhere('ticket_number', $code)->orWhere('file_name', 'like', '%'.$code.'%')->exists()) { $skipped++; continue; }

            $ben    = $c['beneficiaries'][0] ?? [];
            $name   = trim(($ben['first_name'] ?? '').' '.($ben['last_name'] ?? ''));
            $retail = (float) ($c['price'] ?? 0);
            $net    = $supplier->netOf($retail);
            $issue  = $c['issue_date'] ?? null;
            $dest   = $c['destinations'][0] ?? [];
            $cur    = $c['currency_code'] ?? 'KWD';
            $plan   = $c['plan'] ?? 'Travel Insurance';
            $dur    = $dest['duration'] ?? null;
            $codes  = $dest['country_code'] ?? [];
            $codes  = is_array($codes) ? $codes : [$codes];
            $destLabel = implode(', ', array_filter(array_map(fn($z) => $zones[$z] ?? $z, $codes)));
            if ($destLabel === '') { $destLabel = $c['residence_country'] ?? null; }
            $info   = trim($plan
                . (isset($dest['start_period']) ? " | {$dest['start_period']} -> ".($dest['end_period'] ?? '') : '')
                . " | retail {$retail} {$cur}, agency commission {$supplier->agency_commission}%");

            if ($dry) { $this->line("CREATE  $code  $name  net $net $cur"); $created++; continue; }

            $task = Task::create([
                'company_id' => $cfg['company_id'], 'supplier_id' => $supplier->id, 'agent_id' => null, 'client_id' => null,
                'type' => 'insurance', 'status' => 'issued', 'supplier_status' => 'issued',
                'client_name' => $name, 'passenger_name' => $name,
                'reference' => $code, 'original_reference' => $code, 'ticket_number' => $code,
                'price' => $net, 'total' => $net, 'tax' => 0, 'surcharge' => 0, 'penalty_fee' => 0, 'refund_charge' => 0,
                'exchange_currency' => $cur, 'issued_date' => $issue, 'supplier_pay_date' => $issue,
                'cancellation_policy' => 'Non Refundable', 'additional_info' => $info, 'file_name' => 'API-CYGNET-'.$code, 'enabled' => 1,
            ]);
            $task->insuranceDetails()->create([
                'document_reference' => $code, 'insurance_type' => 'Travel', 'destination' => $destLabel,
                'plan_type' => $plan, 'package' => $plan, 'duration' => $dur,
            ]);
            $created++;
            $this->line("CREATED  $code  $name  net $net $cur");
        }

        $this->info("Done. created={$created} skipped(existing)={$skipped} older-than-{$from}={$tooOld}");
        Log::info('Cygnet insurance sync', compact('created', 'skipped', 'tooOld'));
        return self::SUCCESS;
    }

    private function fetch(string $tenant): array
    {
        $resp = Http::acceptJson()->withToken($this->token)->withHeaders(['Tenant' => $tenant])->get("{$this->base}/api/v5/travel/contract");
        if (!$resp->ok()) { $this->warn('Cygnet list failed: HTTP '.$resp->status()); return []; }
        return $resp->json('contracts') ?? [];
    }
}
