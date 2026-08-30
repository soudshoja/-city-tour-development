<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\SupplierStatusMap;
use Illuminate\Database\Seeder;

/**
 * W6.S "Per-supplier status map" (w6-brief.md, owner addition 2026-08-28). Seeds the DEFAULT rows
 * (global + channel-wide -- `company_id` always NULL here; a company adds its own
 * `company_id`-scoped override separately, via the W6.U supplier-status-map screen) that
 * TaskStatusService::mapStatus() must reproduce EXACTLY for every legacy hard-coded branch this
 * sub-wave deletes:
 *   - TaskController::store() / TaskWebhook::applyStatusMapping(): Jazeera Airways / Fly Dubai /
 *     VFS `confirmed`->`issued`, `on hold`->`confirmed` -- seeded as GLOBAL+SUPPLIER rows (level 3
 *     in mapStatus()'s resolution order) so they apply for every company that books these
 *     suppliers without a per-company row, on BOTH the `air` channel (store()) and the `webhook`
 *     channel (TaskWebhook) -- the two entry points that used to run byte-identical inline logic.
 *   - TaskController::store()'s own fallback (`else { $status = $request->status; }` for any OTHER
 *     supplier's `confirmed`): a GLOBAL DEFAULT (level 4) row maps `confirmed`->`issued` for the
 *     `air`/`webhook` channels when no supplier-specific row exists -- matching the brief's own
 *     table-driven test bullet "default (no matching row)->issued". Deliberately NO global default
 *     for `on hold` -- the owner's explicit intent ("no on-hold booking from GDS at this stage")
 *     means an unmapped `on hold` from any OTHER supplier correctly falls through to
 *     `needs_review`, not a silent pass-through.
 *   - AirFileParser::extractStatus()'s own near-canonical output (`VOID`/`FO`/`EMD`/`RF`): GLOBAL
 *     DEFAULT `air`-channel rows, identity/near-identity mappings (`RF`->`refund`, `VOID`->`void`,
 *     `FO`->`reissued`, `EMD`->`emd`, the last explicitly "no rewrite" per the brief).
 *   - Magic Holiday's OK/AM/RQ/XX/XP switch: GLOBAL DEFAULT `magic`-channel rows. `AM`+total<=0
 *     ->`refund` is NOT a separate row (cannot be expressed as a static raw_status match) -- it is
 *     TaskStatusService::mapStatus()'s own documented post-resolution override on top of the plain
 *     `AM`->`reissued` row seeded here.
 *
 * Idempotent: every row is `updateOrCreate`d by its unique key
 * (company_id, supplier_id, channel, raw_status) -- safe to re-run.
 */
class SupplierStatusMapSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGlobalSupplierRows();
        $this->seedGlobalDefaultRows();
    }

    /**
     * Level-3 rows (w6-brief.md's own resolution-order text calls this "global default", but it
     * is scoped to one specific supplier_id -- see TaskStatusService::mapStatus()'s own docblock
     * for why this is kept a distinct level from the true any-supplier fallback below).
     */
    private function seedGlobalSupplierRows(): void
    {
        $supplierNames = ['Jazeera Airways', 'Fly Dubai', 'VFS'];

        foreach ($supplierNames as $name) {
            $supplier = Supplier::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

            if ($supplier === null) {
                // Defensive, not fatal: a fresh environment / test database may not have this
                // supplier seeded yet. The row simply does not exist until the supplier does --
                // re-running this seeder after the supplier is created will pick it up.
                continue;
            }

            foreach (['air', 'webhook'] as $channel) {
                $this->upsert(null, $supplier->id, $channel, 'confirmed', 'issued', null, 10);
                $this->upsert(null, $supplier->id, $channel, 'on hold', 'confirmed', null, 10);
            }
        }
    }

    /**
     * Level-4 rows: company_id NULL, supplier_id NULL -- the true "no more specific row exists"
     * fallback for a given channel+raw_status.
     */
    private function seedGlobalDefaultRows(): void
    {
        foreach (['air', 'webhook'] as $channel) {
            // "default (no matching row)->issued" -- w6-brief.md's own table-driven test bullet.
            // Deliberately NO 'on hold' default row here -- see class docblock.
            $this->upsert(null, null, $channel, 'confirmed', 'issued');
        }

        // AIR channel: AirFileParser::extractStatus()'s own near-canonical raw codes.
        $this->upsert(null, null, 'air', 'RF', 'refund');
        $this->upsert(null, null, 'air', 'VOID', 'void');
        $this->upsert(null, null, 'air', 'FO', 'reissued');
        $this->upsert(null, null, 'air', 'EMD', 'emd');

        // Magic Holiday channel: the deleted OK/AM/RQ/XX/XP switch.
        $this->upsert(null, null, 'magic', 'OK', 'issued');
        $this->upsert(null, null, 'magic', 'AM', 'reissued');
        $this->upsert(null, null, 'magic', 'RQ', 'confirmed');
        $this->upsert(null, null, 'magic', 'XX', 'void');
        $this->upsert(null, null, 'magic', 'XP', 'void');
    }

    private function upsert(
        ?int $companyId,
        ?int $supplierId,
        string $channel,
        string $rawStatus,
        string $canonicalStatus,
        ?string $notes = null,
        int $priority = 0
    ): void {
        SupplierStatusMap::updateOrCreate(
            [
                'company_id' => $companyId,
                'supplier_id' => $supplierId,
                'channel' => $channel,
                'raw_status' => $rawStatus,
            ],
            [
                'canonical_status' => $canonicalStatus,
                'priority' => $priority,
                'active' => true,
                'notes' => $notes ?? 'W6.S seeded default -- reproduces the deleted hard-coded branch.',
            ]
        );
    }
}
