<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtPortU1;

use App\Exceptions\Accounting\InvalidPriorPeriodAdjustmentException;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\X9rLedgerTestCase;

/**
 * CT port U1 - independent verifier (UNIT1-VERIFY.md).
 *
 * PostingService::assertPriorPeriodAdjustmentShape() rule 4 ("never with the locked-period
 * bypass, which stays reserved for the YEC job") had no test on CT (or on Akeed): replacing the
 * check with `if (false)` left every X9r file green. This pins it: a PPA draft that carries
 * allowLockedPeriods is refused with the rule's own message, and nothing is written.
 */
class PpaLockedPeriodBypassVerifyTest extends X9rLedgerTestCase
{
    public function test_a_ppa_with_the_locked_period_bypass_is_refused_and_writes_nothing(): void
    {
        $this->closedFy2025();

        $plain = $this->draft('PPA', '2026-01-01', [
            $this->line($this->acc('1201'), 'debit', 500.0),
            $this->line($this->re(), 'credit', 500.0),
        ], 'verify-ppa-bypass');

        $bypass = new DocumentDraft(
            companyId: $plain->companyId,
            branchId: $plain->branchId,
            docType: $plain->docType,
            subType: $plain->subType,
            docDate: Carbon::parse('2026-01-01'),
            narration: $plain->narration,
            lines: $plain->lines,
            idempotencyKey: $plain->idempotencyKey,
            userId: $plain->userId,
            allowLockedPeriods: true,
        );

        $before = DB::table('transactions')->count();

        try {
            app(PostingService::class)->post($bypass, $this->accountant->id);
            $this->fail('A PPA carrying the locked-period bypass was posted.');
        } catch (InvalidPriorPeriodAdjustmentException $e) {
            $this->assertStringContainsString('may not use the locked-period bypass', $e->getMessage());
        }

        $this->assertSame($before, DB::table('transactions')->count(), 'the refusal wrote a transaction');
    }
}
