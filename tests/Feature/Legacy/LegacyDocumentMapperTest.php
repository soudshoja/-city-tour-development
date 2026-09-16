<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\Onboarding\Replay\LegacyDocumentMapper;
use App\Services\Onboarding\Replay\LegacyDocumentRefused;
use App\Services\Onboarding\Replay\MappedDocument;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP3 -- {@see LegacyDocumentMapper} (MAPPING-RULES.md §1, §2).
 *
 * The per-SubType tests below post ONE synthetic document of each of the 13
 * in-window SubTypes through the real seam and assert the resulting
 * journal_entries: Dr where the legacy line had a Debit, Cr where it had a
 * Credit, KWD, the party on a pooled control line, the branch tag, and the FC
 * metadata. That is PLAN §LP3's own acceptance sentence, made executable.
 */
class LegacyDocumentMapperTest extends LegacyReplayTestCase
{
    /**
     * @return array<string, array{0:string,1:string,2:string,3:?string}>
     */
    public static function subTypeProvider(): array
    {
        return [
            'INV' => ['INV', 'INV', 'LEGACY_INV', 'Invoice'],
            'FRV' => ['FRV', 'RV', 'LEGACY_FRV', 'Receipt'],
            'BPV' => ['BPV', 'PV', 'LEGACY_BPV', 'Payment'],
            'CRV' => ['CRV', 'RV', 'LEGACY_CRV', 'Receipt'],
            'BDS' => ['BDS', 'JV', 'LEGACY_BDS', 'Invoice'],
            'CRN' => ['CRN', 'CRN', 'LEGACY_CRN', 'Refund'],
            'RJV' => ['RJV', 'JV', 'LEGACY_RJV', 'Invoice'],
            'CPV' => ['CPV', 'PV', 'LEGACY_CPV', 'Payment'],
            'ADM' => ['ADM', 'DBN', 'LEGACY_ADM', 'Payment'],
            'JV' => ['JV', 'JV', 'LEGACY_JV', 'Invoice'],
            'ACM' => ['ACM', 'CRN', 'LEGACY_ACM', 'Refund'],
            'BRV' => ['BRV', 'RV', 'LEGACY_BRV', 'Receipt'],
            'OJV' => ['OJV', 'OJV', 'LEGACY_OJV', 'Invoice'],
        ];
    }

    /**
     * PLAN §LP3 acceptance: "one document per SubType posts and produces the
     * expected journal lines (Dr = legacy Debit, Cr = legacy Credit, KWD, party
     * on control lines, branch tag, FC metadata)".
     *
     * `$expectedReferenceType` is what `transactions.reference_type` ends up as
     * once PostingService's own DOC_TYPE_REFERENCE_TYPE fallback has run for the
     * two SubTypes MAPPING-RULES §1.4 deliberately leaves with a null
     * sourceType (JV/RJV/BDS: "the map default for JV", "a label with no
     * semantic weight").
     *
     * @dataProvider subTypeProvider
     */
    public function test_one_document_per_sub_type_posts_the_recorded_lines(
        string $subType,
        string $expectedDocType,
        string $expectedSubType,
        ?string $expectedReferenceType
    ): void {
        $docDate = $subType === 'OJV' ? '2025-01-01' : '2025-03-15';

        $docId = $this->stageHeader([
            'SubType' => $subType,
            'DocType' => $subType,
            'DocNo' => $subType.'/CO/25/0001',
            'DocDt' => $docDate,
            'DocYear' => '2025',
            'Narration' => $subType.'-synthetic',
        ]);

        // Dr the receivable POOL (a party line), Cr an ordinary income leaf.
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '150.750', 'DC' => 'D', 'TransactionType' => 'CUSTOMERDEBITED']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '150.750', 'DC' => 'C', 'TransactionType' => 'INCOME']);

        $summary = $this->replay();

        $this->assertSame(1, $summary['posted'], json_encode($summary));

        $transaction = Transaction::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($expectedDocType, $transaction->doc_type);
        $this->assertSame($expectedSubType, $transaction->sub_type);
        $this->assertSame($expectedReferenceType, $transaction->reference_type);
        $this->assertSame($this->branch->id, (int) $transaction->branch_id, 'branch tag rides on the document draft');
        $this->assertSame('legacy:'.$subType.':'.$docId, $transaction->idempotency_key);
        // §7: the legacy DocNo is visible on the header without a join, and
        // transactions.reference_number stays OURS.
        $this->assertStringStartsWith('['.$subType.'/CO/25/0001]', (string) $transaction->description);
        $this->assertNotSame($subType.'/CO/25/0001', $transaction->reference_number);
        $this->assertNull($transaction->invoice_id, 'MAPPING-RULES §1.3: invoiceId is NEVER set');
        $this->assertNull($transaction->payment_id, 'MAPPING-RULES §1.5 #14: paymentId is NEVER set');

        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $transaction->id)->orderBy('id')->get();
        $this->assertCount(2, $lines);

        $debit = $lines->firstWhere('account_id', $this->receivableControl->id);
        $credit = $lines->firstWhere('account_id', $this->incomeAccount->id);

        $this->assertSame('150.750', (string) $debit->debit);
        $this->assertSame('0.000', (string) $debit->credit);
        $this->assertSame('0.000', (string) $credit->debit);
        $this->assertSame('150.750', (string) $credit->credit);

        foreach ($lines as $line) {
            $this->assertSame('KWD', $line->currency);
            $this->assertSame('1.000000', (string) $line->exchange_rate);
            $this->assertSame($subType.'/CO/25/0001', $line->voucher_number, '§7: the legacy DocNo rides on every line');
            $this->assertSame('legacy:'.$subType, $line->settlement_channel);
            $this->assertSame($this->branch->id, (int) $line->branch_id);
        }

        // §1.2 (b): the pooled control line carries the party; the direct line does not.
        $this->assertSame(self::PARTY_ID, (int) $debit->type_reference_id);
        $this->assertNull($credit->type_reference_id);
    }

    /**
     * §1.2 (c) rule 3 / §2.7 -- the RJV party line: FC = 0 with a NON-BASE
     * currency stamped on it. PostingService rejects `originalAmount <= 0` with
     * NonNegativeAmountException, so the line is re-based to KWD and the dropped
     * FcCurrID_FK/FcExchRate are recorded in the line audit. The base-currency
     * TB -- the only thing the anchors measure -- is unaffected.
     */
    public function test_an_rjv_party_line_with_zero_fc_is_rebased_to_the_base_currency(): void
    {
        $docId = $this->stageHeader(['SubType' => 'RJV', 'DocType' => 'JV', 'DocNo' => 'RJV/CO/25/1']);

        $partyLine = $this->stageLine($docId, [
            'AccID_FK' => self::LEGACY_CUSTOMER_ACC,
            'Debit' => '12.345',
            'DC' => 'D',
            // Exactly the legacy shape: FC zero, but the source line's currency
            // and rate still stamped on the row.
            'FCDebit' => '0.000',
            'FCCredit' => '0.000',
            'FcCurrID_FK' => (string) self::LEGACY_USD_CURR,
            'FcExchRate' => '0.307500000000',
        ]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '12.345', 'DC' => 'C']);

        $this->assertSame(1, $this->replay()['posted']);

        $audit = DB::connection('legacy_pilot')->table('map_document_line')
            ->where('legacy_acc_detail_id', $partyLine)->first();

        $this->assertTrue((bool) $audit->fc_rebased_to_kwd);
        $this->assertSame(self::LEGACY_USD_CURR, (int) $audit->legacy_fc_curr_id_fk, 'the dropped currency is recorded');
        $this->assertSame('0.307500000000', (string) $audit->legacy_fc_exch_rate, 'the dropped rate is recorded');
        $this->assertSame('KWD', $audit->posted_currency);

        $line = JournalEntry::withoutGlobalScopes()->findOrFail((int) $audit->our_journal_entry_id);
        $this->assertSame('KWD', $line->currency);
        $this->assertSame('1.000000', (string) $line->exchange_rate);
        $this->assertSame('12.345', (string) $line->debit);
    }

    /**
     * §1.2 (c): a genuine FC line keeps its currency, its FC amount and the
     * rate FROM THAT LINE -- never from a master table.
     */
    public function test_a_genuine_fc_line_carries_the_line_s_own_rate(): void
    {
        $docId = $this->stageHeader(['SubType' => 'BPV', 'DocType' => 'PV', 'DocNo' => 'BPV/CO/25/1']);

        $fcLine = $this->stageLine($docId, [
            'AccID_FK' => self::LEGACY_SUPPLIER_ACC,
            'Debit' => '30.750',
            'DC' => 'D',
            'FCDebit' => '100.000',
            'FCCredit' => '0.000',
            'FcCurrID_FK' => (string) self::LEGACY_USD_CURR,
            'FcExchRate' => '0.307500000000',
        ]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_BANK_ACC, 'Credit' => '30.750', 'DC' => 'C']);

        $this->assertSame(1, $this->replay()['posted'], 'a valid FC line must post');

        $audit = DB::connection('legacy_pilot')->table('map_document_line')->where('legacy_acc_detail_id', $fcLine)->first();
        $this->assertFalse((bool) $audit->fc_rebased_to_kwd);

        $line = JournalEntry::withoutGlobalScopes()->findOrFail((int) $audit->our_journal_entry_id);
        $this->assertSame('USD', $line->currency);
        $this->assertSame('100.000', (string) $line->original_amount);
        $this->assertSame('0.307500', (string) $line->exchange_rate);
    }

    /**
     * §1.6 (3): 2,774 lifetime line-less headers, 2,015 of them auto-voided
     * invoices whose lines were deleted. Skip, count, report -- NOT an error.
     */
    public function test_a_header_with_no_staged_lines_is_skipped_not_refused(): void
    {
        $docId = $this->stageHeader(['Narration' => 'Auto Voided by System']);

        $summary = $this->replay();

        $this->assertSame(0, $summary['posted']);
        $this->assertSame(0, $summary['refused']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(MappedDocument::STATUS_SKIPPED_NO_LINES, $this->auditFor($docId)->status);
        $this->assertTrue($summary['passed']);
    }

    /**
     * §1.6 (1)+(2): zero-amount lines are dropped and counted; a document left
     * with none is skipped. BDS is where this earns its keep (1,248 such lines).
     */
    public function test_zero_amount_lines_are_dropped_and_an_all_zero_document_is_skipped(): void
    {
        $allZero = $this->stageHeader(['SubType' => 'BDS', 'DocType' => 'BDS', 'DocNo' => 'BDS/CO/25/1']);
        $this->stageLine($allZero, ['AccID_FK' => self::LEGACY_BANK_ACC, 'Debit' => '0.000', 'Credit' => '0.000']);
        $this->stageLine($allZero, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Debit' => '0.000', 'Credit' => '0.000']);

        $mixed = $this->stageHeader(['SubType' => 'BDS', 'DocType' => 'BDS', 'DocNo' => 'BDS/CO/25/2']);
        $this->stageLine($mixed, ['AccID_FK' => self::LEGACY_BANK_ACC, 'Debit' => '5.000', 'DC' => 'D']);
        $this->stageLine($mixed, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '5.000', 'DC' => 'C']);
        $this->stageLine($mixed, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Debit' => '0.000', 'Credit' => '0.000']);

        $summary = $this->replay();

        $this->assertSame(1, $summary['posted']);
        $this->assertSame(0, $summary['refused']);

        $this->assertSame(MappedDocument::STATUS_SKIPPED_ALL_ZERO, $this->auditFor($allZero)->status);
        $this->assertSame(2, (int) $this->auditFor($allZero)->dropped_zero_line_count);

        $mixedAudit = $this->auditFor($mixed);
        $this->assertSame('posted', $mixedAudit->status);
        $this->assertSame(3, (int) $mixedAudit->staged_line_count);
        $this->assertSame(1, (int) $mixedAudit->dropped_zero_line_count);
        $this->assertSame(2, (int) $mixedAudit->posted_line_count, '§4.2: exactly the non-zero staged lines, no more');
    }

    /**
     * §5 / decision O6: a document out of balance at 3 dp is REFUSED, classified
     * and reported. No rounding line is ever posted, to SUSPENSE or anywhere.
     */
    public function test_an_unbalanced_document_is_refused_and_never_plugged(): void
    {
        $docId = $this->stageHeader(['SubType' => 'JV', 'DocType' => 'JV', 'DocNo' => 'JV/CO/25/1']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '100.000', 'DC' => 'D']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '99.999', 'DC' => 'C']);

        $summary = $this->replay();

        $this->assertSame(0, $summary['posted']);
        $this->assertSame(1, $summary['refused']);
        $this->assertSame(0, Transaction::withoutGlobalScopes()->count(), 'nothing may be written for a refused document');
        $this->assertSame(0, JournalEntry::withoutGlobalScopes()->count());

        $audit = $this->auditFor($docId);
        $this->assertSame('refused', $audit->status);
        $this->assertSame('legacy.unbalanced', $audit->failure_code);
        $this->assertSame('legacy_data_defect', $audit->refusal_class);
        $this->assertStringContainsString('0.001', (string) $audit->exception_message);
    }

    /**
     * §2.14 / O11: the 37 `Posted = 0` headers are excluded, COUNTED and
     * reported. `Posted` is the literal string 'False' -- a SQL `where('posted',
     * 1)` would read false against 'True' too and silently select nothing.
     */
    public function test_an_unposted_header_is_excluded_counted_and_reported(): void
    {
        $unposted = $this->stageHeader(['Posted' => 'False', 'DocNo' => 'INV/CO/25/unposted']);
        $this->stageLine($unposted, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '10.000', 'DC' => 'D']);
        $this->stageLine($unposted, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '10.000', 'DC' => 'C']);

        $posted = $this->stageHeader(['Posted' => 'True', 'DocNo' => 'INV/CO/25/posted']);
        $this->stageLine($posted, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '10.000', 'DC' => 'D']);
        $this->stageLine($posted, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '10.000', 'DC' => 'C']);

        $summary = $this->replay();

        $this->assertSame(1, $summary['posted']);
        $this->assertSame('skipped_unposted', $this->auditFor($unposted)->status);
        $this->assertSame('posted', $this->auditFor($posted)->status);
        $this->assertSame(1, Transaction::withoutGlobalScopes()->count());
    }

    /**
     * §2.10 (b) / PLAN O10. The 2025 OJV is the opening document; the 2026 OJV is
     * WITHHELD as the expected output of LP6's own year-end close. Both halves
     * are asserted: the selector never yields it, AND the mapper refuses it by
     * rule with class `withheld_ojv` so a hand-run `--type=OJV` cannot post it
     * either.
     */
    public function test_the_2026_ojv_is_withheld_by_rule(): void
    {
        $opening = $this->stageHeader(['SubType' => 'OJV', 'DocType' => 'JV', 'DocDt' => '2025-01-01', 'DocYear' => '2025', 'DocNo' => 'OJV/CO/25/1']);
        $this->stageLine($opening, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '77.000', 'DC' => 'D', 'DocDt' => '2025-01-01']);
        $this->stageLine($opening, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '77.000', 'DC' => 'C', 'DocDt' => '2025-01-01']);

        $withheld = $this->stageHeader(['SubType' => 'OJV', 'DocType' => 'JV', 'DocDt' => '2026-01-01', 'DocYear' => '2026', 'DocNo' => 'OJV/CO/26/1']);
        $this->stageLine($withheld, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '88.000', 'DC' => 'D', 'DocDt' => '2026-01-01']);
        $this->stageLine($withheld, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '88.000', 'DC' => 'C', 'DocDt' => '2026-01-01']);

        $summary = $this->replay();

        $this->assertSame(1, $summary['selected'], 'the selector must not even yield the withheld OJV');
        $this->assertSame(1, $summary['posted']);
        $this->assertSame('posted', $this->auditFor($opening)->status);
        $this->assertNull($this->auditFor($withheld));

        // And directly at the mapper, which is what protects a hand-run.
        $header = DB::connection('legacy_pilot')->table('stg_acc_header')->where('docid', (string) $withheld)->first();

        try {
            app(LegacyDocumentMapper::class)->map($header, $this->company->id, $this->user->id);
            $this->fail('The 2026 OJV must be refused by rule.');
        } catch (LegacyDocumentRefused $e) {
            $this->assertSame('withheld_ojv', $e->failureCode);
        }
    }

    /**
     * §1.2 (b) party-required rule -- the mapping's own invariant, stricter than
     * the engine's, because AR/AP parity is impossible without it.
     */
    public function test_a_pooled_line_with_no_party_is_refused(): void
    {
        $this->mapLegacyAccount($this->company->id, self::LEGACY_ORPHAN_POOL_ACC, $this->receivableControl->id, 'pooled_receivable', null);

        $docId = $this->stageHeader(['DocNo' => 'INV/CO/25/orphan']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_ORPHAN_POOL_ACC, 'Debit' => '5.000', 'DC' => 'D']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '5.000', 'DC' => 'C']);

        $summary = $this->replay();

        $this->assertSame(1, $summary['refused']);
        $this->assertSame('legacy.party_unresolved', $this->auditFor($docId)->failure_code);
    }

    /**
     * §0.3 / LP2 acceptance mutation proof: remove ONE account from
     * legacy_acc_map and the mapper must refuse that document. No suspense
     * account, no default, no `?? 0`.
     */
    public function test_an_unmapped_account_refuses_the_document(): void
    {
        $docId = $this->stageHeader(['DocNo' => 'INV/CO/25/unmapped']);
        $this->stageLine($docId, ['AccID_FK' => '99999', 'Debit' => '5.000', 'DC' => 'D']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '5.000', 'DC' => 'C']);

        $summary = $this->replay();

        $this->assertSame(0, $summary['posted']);
        $this->assertSame('legacy.account_unmapped', $this->auditFor($docId)->failure_code);
        $this->assertSame('mapping_defect', $this->auditFor($docId)->refusal_class);
    }

    /**
     * §1.2 (a): the DERIVED SIDE (the money) wins over `DC`; a disagreement is
     * tagged and counted, never fatal -- the legacy kernel never enforced
     * agreement and SpDirectRefundAutoinvoice demonstrably writes into the
     * opposite column while keeping DC='D'.
     */
    public function test_a_dc_disagreement_is_tagged_and_the_money_wins(): void
    {
        $docId = $this->stageHeader(['SubType' => 'CRN', 'DocType' => 'CRN', 'DocNo' => 'CRN/CO/25/1']);
        // DC says 'D' but the money is in the Credit column.
        $flipped = $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '9.000', 'DC' => 'D']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '9.000', 'DC' => 'D']);

        $this->assertSame(1, $this->replay()['posted']);

        $audit = DB::connection('legacy_pilot')->table('map_document_line')->where('legacy_acc_detail_id', $flipped)->first();
        $this->assertSame('credit', $audit->derived_side);
        $this->assertSame('D', $audit->legacy_dc);
        $this->assertTrue((bool) $audit->dc_mismatch);
        $this->assertSame(1, (int) $this->auditFor($docId)->dc_mismatch_line_count);

        $line = JournalEntry::withoutGlobalScopes()->findOrFail((int) $audit->our_journal_entry_id);
        $this->assertSame('9.000', (string) $line->credit);
    }

    /**
     * §1.2 (a): a line with BOTH money columns non-zero is unmappable to a
     * one-sided LineDraft; the document is refused.
     */
    public function test_a_line_with_both_money_columns_is_refused(): void
    {
        $docId = $this->stageHeader(['DocNo' => 'INV/CO/25/both']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '5.000', 'Credit' => '2.000', 'DC' => 'D']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '3.000', 'DC' => 'C']);

        $this->replay();

        $this->assertSame('legacy.both_columns_nonzero', $this->auditFor($docId)->failure_code);
        $this->assertSame('legacy_data_defect', $this->auditFor($docId)->refusal_class, 'a single-line defect is a TAG class, not a structural one');
    }

    /**
     * R3: `tblCurrency` carries junk rows (XYZ 2500, xyz 3500, abc 0.20121, a
     * blank code, a code literally "2"). LP1.4 proves no in-window line
     * references one -- meeting one at replay time contradicts that proof, so it
     * is a STRUCTURAL refusal.
     */
    public function test_a_poison_currency_refuses_structurally(): void
    {
        $this->mapLegacyCurrency($this->company->id, self::LEGACY_POISON_CURR, 'XYZ', true);

        $docId = $this->stageHeader(['DocNo' => 'INV/CO/25/poison']);
        $this->stageLine($docId, [
            'AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '5.000', 'DC' => 'D',
            'FCDebit' => '12.000', 'FcCurrID_FK' => (string) self::LEGACY_POISON_CURR, 'FcExchRate' => '2500.000000000000',
        ]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '5.000', 'DC' => 'C']);

        $this->replay();

        $this->assertSame('legacy.currency_poison', $this->auditFor($docId)->failure_code);
        $this->assertSame('mapping_defect', $this->auditFor($docId)->refusal_class);
    }

    /**
     * §2.11: MAN_INV / MAN_CRN are lifetime-wide but NOT in the 2025 window.
     * One appearing in-window is a CENSUS DEFECT -- refuse structurally, stop,
     * do not improvise a rule.
     */
    public function test_an_out_of_scope_sub_type_refuses_structurally(): void
    {
        $docId = $this->stageHeader(['SubType' => 'MAN_INV', 'DocType' => 'INV', 'DocNo' => 'MAN/CO/25/1']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '5.000', 'DC' => 'D']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '5.000', 'DC' => 'C']);

        $summary = $this->replay();

        $this->assertSame('legacy.subtype_out_of_scope', $this->auditFor($docId)->failure_code);
        $this->assertContains('MAN_INV', $summary['type_stops']);
    }

    /**
     * §1.2 (e) / O11-branch: per-line branch is LOST at posting
     * (journal_entries.branch_id comes from the draft), accepted as a loss, and
     * every original line branch is recorded in map_document_line and counted.
     */
    public function test_a_line_on_another_branch_posts_on_the_header_branch_and_is_recorded(): void
    {
        $this->mapLegacyBranch($this->company->id, self::LEGACY_BRANCH_SH, $this->branch->id);

        $docId = $this->stageHeader(['BranchID_FK' => '1', 'DocNo' => 'INV/CO/25/branch']);
        $offBranch = $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '6.000', 'DC' => 'D', 'BranchID_FK' => (string) self::LEGACY_BRANCH_SH]);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '6.000', 'DC' => 'C', 'BranchID_FK' => '1']);

        $this->assertSame(1, $this->replay()['posted']);

        $audit = DB::connection('legacy_pilot')->table('map_document_line')->where('legacy_acc_detail_id', $offBranch)->first();
        $this->assertSame(self::LEGACY_BRANCH_SH, (int) $audit->legacy_branch_id_fk);
        $this->assertSame(1, (int) $this->auditFor($docId)->off_branch_line_count);

        $line = JournalEntry::withoutGlobalScopes()->findOrFail((int) $audit->our_journal_entry_id);
        $this->assertSame($this->branch->id, (int) $line->branch_id);
    }
}
