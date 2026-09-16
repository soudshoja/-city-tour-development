<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Exceptions\Accounting\FrozenAccountException;
use App\Models\Transaction;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP3 -- the ONE pilot flag that changes
 * {@see PostingService}'s own behaviour, and the proof it is off by default.
 *
 * MAPPING-RULES.md §1.5 #6 requires that a legacy `IsFreeze` must not refuse
 * valid 2025 history ("freezing them would refuse valid 2025 history"), and the
 * coordinator's 2026-09-08 ruling makes it concrete: 12 of the 21 frozen legacy
 * accounts carry 2025 lines and those lines MUST replay. LegacyCoaImporter
 * preserves the freeze as `accounts.disabled = 1`, which PostingService step 3e
 * refuses -- so the exception is real, and it is gated on two config keys, the
 * engine-facing one NULL by default.
 */
class LegacyFrozenLeafBypassTest extends LegacyReplayTestCase
{
    private function draftOnDisabledAccount(?string $subType): DocumentDraft
    {
        DB::table('accounts')->where('id', $this->bankAccount->id)->update(['disabled' => true]);

        return new DocumentDraft(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            docType: 'JV',
            subType: $subType,
            docDate: new \DateTimeImmutable('2025-03-01'),
            narration: 'frozen-leaf bypass fixture',
            lines: [
                new LineDraft(purposeCode: '', accountId: $this->bankAccount->id, side: 'debit', amount: 5.0, currency: 'KWD', originalAmount: 5.0, exchangeRate: 1.0, transactionType: null),
                new LineDraft(purposeCode: '', accountId: $this->incomeAccount->id, side: 'credit', amount: 5.0, currency: 'KWD', originalAmount: 5.0, exchangeRate: 1.0, transactionType: null),
            ],
            idempotencyKey: 'test:frozen:'.($subType ?? 'null'),
            userId: $this->user->id,
        );
    }

    /**
     * THE DEFAULT-OFF PROOF. With no pilot env var set, a disabled account is
     * refused exactly as it always was -- even for a LEGACY_* document, and even
     * though `ignore_frozen_for_legacy_docs` itself defaults to true.
     */
    public function test_the_bypass_is_off_by_default_and_a_disabled_account_is_still_refused(): void
    {
        $this->assertNull(config('legacy_pilot.replay.frozen_leaf_bypass_sub_type_like'));
        $this->assertTrue(config('legacy_pilot.replay.ignore_frozen_for_legacy_docs'));

        $this->expectException(FrozenAccountException::class);

        app(PostingService::class)->post($this->draftOnDisabledAccount('LEGACY_JV'));
    }

    /** And for an ordinary, non-legacy document, with or without the pilot env var. */
    public function test_a_non_legacy_document_is_refused_even_when_the_pilot_pattern_is_set(): void
    {
        config(['legacy_pilot.replay.frozen_leaf_bypass_sub_type_like' => 'LEGACY_%']);

        $this->expectException(FrozenAccountException::class);

        app(PostingService::class)->post($this->draftOnDisabledAccount('INVOICE'));
    }

    /** A null sub_type can never match the pattern either. */
    public function test_a_document_with_no_sub_type_is_refused_even_when_the_pilot_pattern_is_set(): void
    {
        config(['legacy_pilot.replay.frozen_leaf_bypass_sub_type_like' => 'LEGACY_%']);

        $this->expectException(FrozenAccountException::class);

        app(PostingService::class)->post($this->draftOnDisabledAccount(null));
    }

    /** Both keys set: a replayed legacy document posts to the frozen leaf, as the ruling requires. */
    public function test_with_both_pilot_keys_set_a_legacy_document_posts_to_a_frozen_leaf(): void
    {
        config(['legacy_pilot.replay.frozen_leaf_bypass_sub_type_like' => 'LEGACY_%']);

        $posted = app(PostingService::class)->post($this->draftOnDisabledAccount('LEGACY_JV'));

        $this->assertSame('LEGACY_JV', $posted->transaction->sub_type);
        $this->assertSame(1, Transaction::withoutGlobalScopes()->count());
    }

    /** The semantic half still governs: turning the ruling off restores the refusal. */
    public function test_turning_the_ruling_flag_off_restores_the_refusal(): void
    {
        config([
            'legacy_pilot.replay.frozen_leaf_bypass_sub_type_like' => 'LEGACY_%',
            'legacy_pilot.replay.ignore_frozen_for_legacy_docs' => false,
        ]);

        $this->expectException(FrozenAccountException::class);

        app(PostingService::class)->post($this->draftOnDisabledAccount('LEGACY_JV'));
    }

    /** End to end: the whole replay runs against a frozen leaf once the pilot instance is configured. */
    public function test_the_replay_posts_a_document_on_a_frozen_leaf_when_configured(): void
    {
        config(['legacy_pilot.replay.frozen_leaf_bypass_sub_type_like' => 'LEGACY_%']);

        $this->mapLegacyAccount($this->company->id, 5555, $this->bankAccount->id, 'direct', null, true);
        DB::table('accounts')->where('id', $this->bankAccount->id)->update(['disabled' => true]);

        $docId = $this->stageHeader(['SubType' => 'CPV', 'DocType' => 'PV', 'DocDt' => '2025-03-10', 'DocNo' => 'CPV/CO/25/frozen']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Debit' => '4.000', 'DC' => 'D', 'DocDt' => '2025-03-10']);
        $this->stageLine($docId, ['AccID_FK' => '5555', 'Credit' => '4.000', 'DC' => 'C', 'DocDt' => '2025-03-10']);

        $this->assertSame(1, $this->replay()['posted']);
    }
}
