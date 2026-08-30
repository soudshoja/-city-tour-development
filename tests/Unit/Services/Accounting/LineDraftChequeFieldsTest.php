<?php

namespace Tests\Unit\Services\Accounting;

use App\Services\Accounting\LineDraft;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * W5.L item 1 (w5-brief.md): LineDraft additive instrument fields (chequeNo/chequeDate/bankInfo/
 * authNo/chequeClearanceDate). Pure unit-level construction test — no DB needed, matches this
 * codebase's convention of a dedicated Unit test for a plain DTO's shape (e.g.
 * tests/Unit/Services/Accounting/CreditApplicationDraftBuilderTest.php).
 */
class LineDraftChequeFieldsTest extends TestCase
{
    /**
     * Every pre-existing named-argument call site omits every trailing optional parameter it
     * doesn't set — the additive contract every earlier fix round in this file's docblock also
     * pins. All five new fields must default to null so no existing LineDraft construction changes
     * behaviour.
     */
    public function test_new_instrument_fields_default_to_null(): void
    {
        $line = new LineDraft(
            purposeCode: 'CASH_IN_HAND',
            accountId: null,
            side: 'debit',
            amount: 10.0,
            currency: 'KWD',
            originalAmount: 10.0,
            exchangeRate: 1.0,
            transactionType: 'TEST',
        );

        $this->assertNull($line->chequeNo);
        $this->assertNull($line->chequeDate);
        $this->assertNull($line->bankInfo);
        $this->assertNull($line->authNo);
        $this->assertNull($line->chequeClearanceDate);
    }

    public function test_new_instrument_fields_are_carried_verbatim_when_set(): void
    {
        $chequeDate = Carbon::parse('2026-09-01');
        $clearanceDate = Carbon::parse('2026-09-15');

        $line = new LineDraft(
            purposeCode: 'CHEQUES_IN_HAND',
            accountId: null,
            side: 'debit',
            amount: 10.0,
            currency: 'KWD',
            originalAmount: 10.0,
            exchangeRate: 1.0,
            transactionType: 'TEST',
            chequeNo: 'CHQ-000123',
            chequeDate: $chequeDate,
            bankInfo: 'NBK - Fahaheel Branch',
            authNo: 'AUTH-9988',
            chequeClearanceDate: $clearanceDate,
        );

        $this->assertSame('CHQ-000123', $line->chequeNo);
        $this->assertSame($chequeDate, $line->chequeDate);
        $this->assertSame('NBK - Fahaheel Branch', $line->bankInfo);
        $this->assertSame('AUTH-9988', $line->authNo);
        $this->assertSame($clearanceDate, $line->chequeClearanceDate);
    }
}
