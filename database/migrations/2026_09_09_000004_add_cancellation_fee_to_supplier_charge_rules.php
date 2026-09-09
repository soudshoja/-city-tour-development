<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CT-A3 wave 2, item W2-5 — the supplier's own CANCELLATION FEE, expressed the way owner ruling
 * R-CT3 requires: as configured master data, not as a constant in a feeder.
 *
 * ── The gap ─────────────────────────────────────────────────────────────────────────────────────
 * {@see \App\Services\TaskStatusService::void()} reverses the sale (under wave 1's GROSS basis
 * that reversal carries the cost and the supplier payable with it), posts the agency's OWN void
 * fee to the client (`VOID_FEE_INCOME`, 4134), un-earns the commission and disposes of the client
 * balance. What it has never modelled is the money the SUPPLIER keeps when a booking is cancelled:
 * after a void the supplier payable goes to zero, as though every cancellation were free.
 *
 * ── What already existed, and why this is one enum value rather than a new table ─────────────────
 * `supplier_charge_rules` (W6.C, migration 2026_08_29_...) is ALREADY the per company × supplier ×
 * service-type × channel fee-policy table, with `basis` (fixed / percent_of_fare /
 * percent_of_total / per_passenger / per_segment), `amount`, `currency`, an optional `cost_account`
 * purpose-code override, `recharge_policy` (absorb / recharge_client / recharge_agent),
 * `effective_from`/`effective_to`, `active` and `once_per_reference`. It already has a resolver
 * with a documented precedence ({@see \App\Services\Accounting\SupplierChargeRuleResolver}) and a
 * line builder that emits exactly the shape a cancellation fee needs — Dr cost / Cr
 * `SERVICE_PAYABLE` with the party attached, plus a client recharge pair when the policy says so
 * ({@see \App\Services\Accounting\SupplierChargeLineBuilder}).
 *
 * Everything a cancellation fee needs is therefore already there EXCEPT a name for it: the
 * `charge_kind` enum stops at iata_fee / rounding / service_fee / booking_fee / card_surcharge /
 * resort_fee / other. Adding a table, or a `suppliers.cancellation_fee` column, would have been a
 * second fee mechanism competing with the one that exists — precisely the duplication CT-A1 §1.7
 * found twenty variants of on the refund path. So this migration adds ONE enum value.
 *
 * `other` was deliberately not reused: the void feeder has to select the cancellation rule
 * specifically (a supplier can have an `other` rule that fires at issue AND a cancellation fee),
 * and a charge kind that cannot be selected on cannot be configured.
 *
 * ── Raw ALTER, not a Blueprint change ───────────────────────────────────────────────────────────
 * `$table->enum(...)->change()` needs doctrine/dbal to introspect the column, which is the same
 * machinery behind the three pre-existing MariaDB migration failures this branch already carries
 * (PR #2 body). A raw MODIFY states the whole enum explicitly, is deterministic on MariaDB 10.11,
 * and reads as exactly what it does. Existing rows are untouched: no current value is removed.
 */
return new class extends Migration
{
    private const KINDS = [
        'iata_fee',
        'rounding',
        'service_fee',
        'booking_fee',
        'card_surcharge',
        'resort_fee',
        'cancellation_fee',
        'other',
    ];

    private const KINDS_BEFORE = [
        'iata_fee',
        'rounding',
        'service_fee',
        'booking_fee',
        'card_surcharge',
        'resort_fee',
        'other',
    ];

    public function up(): void
    {
        DB::statement($this->modifyStatement(self::KINDS));
    }

    public function down(): void
    {
        // Any row already using the new value would violate the narrowed enum, so it is retired
        // to 'other' first -- a down() that can throw is a down() nobody can run.
        DB::table('supplier_charge_rules')->where('charge_kind', 'cancellation_fee')->update(['charge_kind' => 'other']);

        DB::statement($this->modifyStatement(self::KINDS_BEFORE));
    }

    /** @param  string[]  $kinds */
    private function modifyStatement(array $kinds): string
    {
        $values = implode(', ', array_map(static fn (string $k) => "'".$k."'", $kinds));

        return 'ALTER TABLE `supplier_charge_rules` MODIFY `charge_kind` ENUM('.$values.') NOT NULL';
    }
};
