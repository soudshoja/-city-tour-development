<?php

use App\Models\VoucherTemplate;
use App\Services\Vouchers\VoucherCatalogue;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds the system (company_id NULL) voucher_templates rows for the five
 * designs shipped in this step (plan §5, §16 step 3): hotel, flight, visa,
 * insurance, generic — one row per design PER LANGUAGE (EN/ARB), same
 * `view_key`, mirroring the Term per-company+type+LANGUAGE convention the
 * plan explicitly copies (§3.1, §12). A data-only migration, not a
 * seeder, so it runs the same disciplined way every schema change in this
 * feature has (`--path=<this file>`, never a bare migrate) and is
 * reversible.
 *
 * Deliberately excludes `package` (plan §5 row 6, Phase B — not built
 * yet). VoucherCatalogue::entries() is the single source of truth for
 * "which five designs exist" — this migration reads it rather than
 * hand-duplicating the list, so the registry can never drift from what
 * the Settings gallery and the preview route both already read.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (VoucherCatalogue::entries() as $entry) {
            foreach (VoucherCatalogue::LANGUAGES as $language) {
                VoucherTemplate::withoutEvents(function () use ($entry, $language, $now) {
                    VoucherTemplate::updateOrCreate(
                        [
                            'company_id' => null,
                            'task_type' => $entry['task_type'],
                            'view_key' => $entry['view_key'],
                            'language' => $language,
                        ],
                        [
                            'name' => $entry['name'],
                            'is_default' => true, // the only design of its type+language in v1 — trivially default
                            'is_active' => true,
                            'show_price' => false,          // plan §9 recommendation: no prices by default
                            'show_payment_status' => false, // plan §9 recommendation: no payment chip by default
                            'term_id' => null,   // falls back to the company's own default term, §14.5
                            'options' => null,
                            'created_by' => null, // system-shipped, not created by any one user
                            'updated_at' => $now,
                        ]
                    );
                });
            }
        }
    }

    public function down(): void
    {
        foreach (VoucherCatalogue::entries() as $entry) {
            VoucherTemplate::where('company_id', null)
                ->where('task_type', $entry['task_type'])
                ->where('view_key', $entry['view_key'])
                ->whereIn('language', VoucherCatalogue::LANGUAGES)
                ->delete();
        }
    }
};
