<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SupplierChargeRule;
use App\Models\SupplierCompany;
use App\Models\SupplierSurcharge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * W6.C one-time backfill (w6-brief.md "W6.C — Supplier-side charges" item 2;
 * supplier-charges-design.md Table 4's own "CT migration" note). Maps every existing
 * `supplier_surcharges` row 1:1 onto a new `supplier_charge_rules` row:
 *
 *   - `amount` -> `amount`, `basis` -> `fixed` (the only basis CT's legacy mechanism ever
 *     supported).
 *   - The five status booleans (`is_issued`/`is_reissued`/`is_void`/`is_refund`/`is_confirmed`)
 *     are DROPPED entirely, not mapped to any column — every new rule fires only at issue() time
 *     and reverses automatically with the sale (w6-brief.md's own collapse: "applies at issue
 *     only ... void/refund now reverse automatically instead of needing their own gate").
 *   - `charge_mode` + `charge_behavior` collapse onto the single `once_per_reference` flag:
 *       - charge_mode='task' -> once_per_reference=false (fires every task, unconditionally).
 *       - charge_mode='reference' + charge_behavior='single' -> once_per_reference=true.
 *       - charge_mode='reference' + charge_behavior='repetitive' -> once_per_reference=false
 *         (fires every task even though scoped to the same reference — identical runtime
 *         behaviour to charge_mode='task' once the dedicated reference-tracking table is retired;
 *         see class docblock note below).
 *   - `recharge_policy` -> `absorb` (the legacy mechanism never recharged a surcharge to the
 *     client — Table 1's own finding: it only ever adjusted `tasks.supplier_surcharge`/
 *     `invoice_details.supplier_price`, an internal cost figure never billed separately).
 *   - `commissionable` -> false (Rule 1e — unconditional for every backfilled rule, matching the
 *     column's own shipped default; the legacy mechanism never modeled commissionability at all).
 *   - `active` -> mirrors the owning `supplier_companies.is_active` flag, so a backfilled rule's
 *     effective behaviour matches what the legacy mechanism was actually doing for that
 *     supplier+company at backfill time (an inactive supplier-company pairing produced no
 *     surcharge before this backfill either).
 *   - `service_type` / `channel` / `tax_code` / `rounding_rule` / `effective_from` /
 *     `effective_to` / `cost_account` -> null (the legacy mechanism had no equivalent concept for
 *     any of these — every backfilled rule applies company/supplier-wide, to any service type, any
 *     channel, unbounded in time, using the default cost-purpose resolution).
 *   - `charge_kind` -> 'other' (the legacy `label` free-text field is preserved verbatim on the
 *     new `label` column for operator readability, but has no reliable mapping onto the fixed
 *     `charge_kind` enum — an operator retags a backfilled rule's `charge_kind` manually via the
 *     W6.U rule editor when a more specific classification is wanted; this backfill never guesses).
 *
 * Idempotent: keyed by `legacy_supplier_surcharge_id` (unique column on `supplier_charge_rules`)
 * — a supplier_surcharges row that already produced a rule is skipped on re-run, never duplicated.
 * Reports a row-count parity check: total `supplier_surcharges` rows vs. total
 * `supplier_charge_rules` rows carrying a non-null `legacy_supplier_surcharge_id` (equal on a
 * clean run; a mismatch after a run with failures is reported, never silently swallowed).
 *
 * Each row is processed inside its own DB transaction — one bad `supplier_companies` link (e.g. a
 * dangling `supplier_company_id` from a hard-deleted row) reports a per-row FAILED line and moves
 * to the next row, rather than aborting the whole backfill.
 */
class BackfillSupplierChargeRules extends Command
{
    protected $signature = 'supplier-charges:backfill-rules
                            {--dry-run : Preview what would be created without writing anything}';

    protected $description = 'One-time backfill: migrate every supplier_surcharges row 1:1 onto the new supplier_charge_rules table.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacyRows = SupplierSurcharge::query()->with('supplierCompany')->get();

        if ($legacyRows->isEmpty()) {
            $this->info('No supplier_surcharges rows found — nothing to backfill.');

            return self::SUCCESS;
        }

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Backfill supplier_charge_rules from supplier_surcharges');
        $this->info('═══════════════════════════════════════════════════════');
        $this->line("  Legacy rows: {$legacyRows->count()}");
        if ($dryRun) {
            $this->warn('  DRY RUN — no rows will be written.');
        }
        $this->newLine();

        $created = 0;
        $skippedExisting = 0;
        $failed = 0;

        foreach ($legacyRows as $legacy) {
            $existing = SupplierChargeRule::query()
                ->where('legacy_supplier_surcharge_id', $legacy->id)
                ->first();

            if ($existing !== null) {
                $this->line("  [surcharge #{$legacy->id}] SKIPPED (already backfilled as rule #{$existing->id}).");
                $skippedExisting++;

                continue;
            }

            $supplierCompany = $legacy->supplierCompany ?? SupplierCompany::find($legacy->supplier_company_id);

            if ($supplierCompany === null) {
                $this->error("  [surcharge #{$legacy->id}] FAILED: supplier_company_id={$legacy->supplier_company_id} does not resolve to a supplier_companies row — skipped, not counted as backfilled.");
                Log::error('accounting.supplier_charge_rule_backfill_failed', [
                    'supplier_surcharge_id' => $legacy->id,
                    'supplier_company_id' => $legacy->supplier_company_id,
                    'reason' => 'dangling supplier_company_id',
                ]);
                $failed++;

                continue;
            }

            $onceReference = $this->deriveOncePerReference($legacy->charge_mode, $legacy->charge_behavior);

            $attributes = [
                'company_id' => $supplierCompany->company_id,
                'supplier_id' => $supplierCompany->supplier_id,
                'service_type' => null,
                'channel' => null,
                'charge_kind' => 'other',
                'basis' => SupplierChargeRule::BASIS_FIXED,
                'amount' => (float) $legacy->amount,
                'currency' => null,
                'cost_account' => null,
                'recharge_policy' => SupplierChargeRule::RECHARGE_ABSORB,
                'commissionable' => false,
                'tax_code' => null,
                'rounding_rule' => null,
                'active' => (bool) ($supplierCompany->is_active ?? true),
                'effective_from' => null,
                'effective_to' => null,
                'once_per_reference' => $onceReference,
                'label' => $legacy->label,
                'legacy_supplier_surcharge_id' => $legacy->id,
            ];

            if ($dryRun) {
                $this->info("  [surcharge #{$legacy->id}] WOULD CREATE rule for supplier_id={$attributes['supplier_id']}, company_id={$attributes['company_id']}, amount={$attributes['amount']}, once_per_reference=".($onceReference ? 'true' : 'false').'.');
                $created++;

                continue;
            }

            try {
                DB::transaction(function () use ($attributes, &$created, $legacy) {
                    $rule = SupplierChargeRule::query()->create($attributes);
                    $this->info("  [surcharge #{$legacy->id}] CREATED rule #{$rule->id} (supplier_id={$rule->supplier_id}, company_id={$rule->company_id}, amount={$rule->amount}, once_per_reference=".($rule->once_per_reference ? 'true' : 'false').').');
                    $created++;
                });
            } catch (\Throwable $e) {
                $this->error("  [surcharge #{$legacy->id}] FAILED: {$e->getMessage()}");
                Log::error('accounting.supplier_charge_rule_backfill_failed', [
                    'supplier_surcharge_id' => $legacy->id,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '  %d %s, %d already backfilled (skipped), %d failed.',
            $created,
            $dryRun ? 'would be created' : 'created',
            $skippedExisting,
            $failed
        ));

        if (! $dryRun) {
            $totalLegacy = SupplierSurcharge::query()->count();
            $totalBackfilled = SupplierChargeRule::query()->whereNotNull('legacy_supplier_surcharge_id')->count();

            if ($totalLegacy === $totalBackfilled) {
                $this->info("  Row-count parity OK: {$totalLegacy} supplier_surcharges rows == {$totalBackfilled} backfilled supplier_charge_rules rows.");
            } else {
                $this->warn("  Row-count parity MISMATCH: {$totalLegacy} supplier_surcharges rows vs {$totalBackfilled} backfilled supplier_charge_rules rows — see the {$failed} FAILED line(s) above.");
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * See class docblock's "charge_mode + charge_behavior collapse" note.
     */
    private function deriveOncePerReference(?string $chargeMode, ?string $chargeBehavior): bool
    {
        if ($chargeMode !== 'reference') {
            return false;
        }

        return $chargeBehavior === 'single';
    }
}
