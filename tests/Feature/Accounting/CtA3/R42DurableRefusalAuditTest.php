<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Services\Accounting\AccountingLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 **R4-2** — `CT-A3-R3-2026-09-10.md` §4.1: *"A refusal's DB audit row does not survive."*
 *
 * `RefundPostingService::refuseNothingOutstanding()`'s own docblock promised *"an audit row that
 * survives the rollback the throw triggers, so the refusal is findable afterwards rather than only
 * visible to whoever was watching the screen"*. The R3 lane measured the opposite:
 * `RefundPostingService::post()` wraps the whole composition in one `DB::transaction()`, so the
 * `AccountingLog::event(...)` row went in on the same connection and came straight back out with
 * the rollback the refusal's own throw caused. Two refusals were affected — R2-1's
 * `NothingOutstandingToCreditException` and R3-3's `RefundExceedsOutstandingException` — and the
 * docblock has been asserting a durability nothing provided since R2.
 *
 * The fix reuses infrastructure this codebase already has for exactly this problem rather than
 * inventing a second one: `config/database.php`'s `accounting_audit` connection, a second,
 * independent PDO handle onto the SAME physical database, which {@see \App\Models\IdempotencyKeyRejection}
 * uses to record a rejected `post()` attempt so the record outlives the rollback that same attempt
 * triggers. {@see AccountingLog::eventDurable()} routes a refusal's row through it.
 *
 * ── Reading these assertions ────────────────────────────────────────────────────────────────────
 * The DEFAULT connection deliberately does not see the row inside the test, and that is not a bug
 * being tolerated — it is the proof. Under `RefreshDatabase` the test body runs inside an open
 * transaction on the default connection, whose REPEATABLE READ snapshot predates the durable
 * INSERT; a row that were merely part of that same transaction would be visible to it and would
 * vanish on rollback, which is precisely the behaviour being fixed. Seeing it on `accounting_audit`
 * and NOT on the default connection is what "a genuinely different session committed this" looks
 * like from in here. In production the caller reads it back in a LATER transaction and sees it
 * normally.
 */
class R42DurableRefusalAuditTest extends AccountingTestCase
{
    /** @return \Illuminate\Database\Query\Builder */
    private function durableRows(string $action)
    {
        return DB::connection(AccountingLog::DURABLE_CONNECTION)
            ->table('accounting_audit_log')
            ->where('action', $action);
    }

    /**
     * THE DEFECT, reproduced on the mechanism itself: an ordinary `event()` row written inside a
     * transaction that then rolls back leaves nothing behind.
     */
    public function test_an_ordinary_event_row_does_not_survive_the_rollback_its_caller_triggers(): void
    {
        $action = 'r4_probe_plain_'.Str::lower(Str::random(8));

        try {
            DB::transaction(function () use ($action): void {
                AccountingLog::event($action, ['company_id' => 1, 'reason' => 'probe']);

                throw new \RuntimeException('the refusal');
            });
        } catch (\RuntimeException) {
            // expected — this is what a refusal does
        }

        $this->assertSame(0, $this->durableRows($action)->count(), 'gone, exactly as R3 §4.1 measured');
    }

    /** THE FIX. Same shape, same rollback, `eventDurable()` instead. */
    public function test_a_durable_event_row_survives_the_rollback_its_caller_triggers(): void
    {
        $action = 'r4_probe_durable_'.Str::lower(Str::random(8));

        try {
            DB::transaction(function () use ($action): void {
                AccountingLog::eventDurable($action, [
                    'company_id' => 4242,
                    'refund_id' => 77,
                    'reason' => 'credit_exceeds_outstanding',
                ]);

                throw new \RuntimeException('the refusal');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $row = $this->durableRows($action)->first();

        $this->assertNotNull($row, 'the refusal is findable afterwards — which is what the docblock always claimed');
        $this->assertSame(4242, (int) $row->company_id);
        $this->assertSame('credit_exceeds_outstanding', (string) $row->reason);
        $this->assertSame('refund', (string) $row->subject_type, 'and the subject inference is the same one event() applies');
        $this->assertSame(77, (int) $row->subject_id);

        $this->assertSame(
            0,
            DB::table('accounting_audit_log')->where('action', $action)->count(),
            'not visible on the DEFAULT connection from inside this test — see the class docblock: '
            .'that is what proves it was committed by a different session rather than enrolled in this one'
        );
    }

    /** A nested transaction is the real call shape, and it must not change the answer. */
    public function test_it_survives_a_rollback_two_transactions_deep(): void
    {
        $action = 'r4_probe_nested_'.Str::lower(Str::random(8));

        try {
            DB::transaction(function () use ($action): void {
                DB::transaction(function () use ($action): void {
                    AccountingLog::eventDurable($action, ['company_id' => 4243]);
                });

                throw new \RuntimeException('the outer refusal');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(1, $this->durableRows($action)->count());
    }

    /**
     * An audit row is a record OF a refusal, never a precondition FOR it. If the durable write
     * cannot happen at all, the caller must still get its own named exception — not a
     * `QueryException` about logging.
     */
    public function test_a_broken_durable_connection_never_replaces_the_callers_own_exception(): void
    {
        config(['database.connections.'.AccountingLog::DURABLE_CONNECTION.'.database' => 'city_tour_test_no_such_database_r4']);
        DB::purge(AccountingLog::DURABLE_CONNECTION);

        $thrown = null;

        try {
            DB::transaction(function (): void {
                AccountingLog::eventDurable('r4_probe_broken', ['company_id' => 4244]);

                throw new \DomainException('the refusal the caller cares about');
            });
        } catch (\Throwable $e) {
            $thrown = $e;
        } finally {
            DB::purge(AccountingLog::DURABLE_CONNECTION);
        }

        $this->assertInstanceOf(\DomainException::class, $thrown);
        $this->assertSame('the refusal the caller cares about', $thrown->getMessage());
    }
}
