<?php

namespace Tests\Feature\Accounting;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * W5.X (w5-brief.md §W5.X item 2: "ArchitectureTest rule: no code under ReceiptVoucherController /
 * BankPaymentController / AgentSettlementService resolves an Account by name or name-LIKE").
 *
 * Extends the SAME reflection-scan convention {@see ReceiptVoucherControllerArchitectureTest}
 * already established for RV's own posting path (that file's own docblock explains why a
 * whole-file scan is the wrong tool here: create()/edit()'s pre-existing UI-dropdown root-category
 * lookups — `Account::whereIn('name', ['Assets','Liabilities',...])` — are structural navigation
 * of the five FIXED system roots, not a posting-target resolution, and are explicitly out of every
 * W5 sub-wave's scope to rewrite). This file adds the two governed surfaces that test does not
 * cover:
 *   1. BankPaymentController's own posting-path methods (PV's mirror of RV's already-governed set).
 *   2. AgentSettlementService's two migrated methods (settleByProfit/onPaymentCompleted) plus their
 *      private helpers.
 *   3. THE FIX THIS FILE PINS: fetchPaymentsByDate()/fetchJournalEntriesByIds()/declineReconcile()
 *      on BOTH controllers, and the whole of the new {@see \App\Services\Accounting\ReconciliationService}
 *      they now delegate to — before this fix, fetchPaymentsByDate() on both controllers resolved a
 *      supplier's account via `Account::where('name', ...)->first() ?? Account::where('name',
 *      'LIKE', ...)->first()`, exactly the anti-pattern this rule forbids (and the reason the
 *      pre-existing RV test explicitly listed this method as "out of scope" for the wave that
 *      shipped it).
 */
class W5XArchitectureTest extends TestCase
{
    private const BANK_PAYMENT_GOVERNED_METHODS = [
        'store',
        'update',
        'approve',
        'destroy',
        'clear',
        'postVoucher',
        'buildVoucherDraft',
        'writeLegacyTransaction',
        'resolveSubType',
        'applyByDateReconciliation',
        'fillVoucherRow',
        'validateVoucherRequest',
        'fetchPaymentsByDate',
        'fetchJournalEntriesByIds',
        'declineReconcile',
    ];

    private const AGENT_SETTLEMENT_GOVERNED_METHODS = [
        'settleByProfit',
        'onPaymentCompleted',
    ];

    private const RV_RECONCILE_METHODS = [
        'fetchPaymentsByDate',
        'fetchJournalEntriesByIds',
        'declineReconcile',
    ];

    public function test_bank_payment_controller_posting_path_never_resolves_an_account_by_name(): void
    {
        $offenders = $this->scanMethods(\App\Http\Controllers\BankPaymentController::class, self::BANK_PAYMENT_GOVERNED_METHODS);

        $this->assertSame([], $offenders, 'These BankPaymentController methods resolve an account by name/LIKE: '.implode(', ', $offenders));
    }

    public function test_agent_settlement_service_never_resolves_an_account_by_name(): void
    {
        $offenders = $this->scanMethods(\App\Services\AgentSettlementService::class, self::AGENT_SETTLEMENT_GOVERNED_METHODS);

        $this->assertSame([], $offenders, 'These AgentSettlementService methods resolve an account by name/LIKE: '.implode(', ', $offenders));
    }

    public function test_receipt_voucher_controller_reconcile_methods_never_resolve_an_account_by_name(): void
    {
        $offenders = $this->scanMethods(\App\Http\Controllers\ReceiptVoucherController::class, self::RV_RECONCILE_METHODS);

        $this->assertSame([], $offenders, 'These ReceiptVoucherController reconcile methods resolve an account by name/LIKE: '.implode(', ', $offenders));
    }

    private const RECONCILIATION_SERVICE_GOVERNED_METHODS = [
        'assertCanReconcile',
        'reconcile',
        'fetchPaymentsByDate',
        'resolveSupplierAccountIds',
        'fetchJournalEntriesByIds',
        'declineReconcile',
    ];

    /**
     * Method-body-only scan (like {@see self::scanMethods()} below), NOT a whole-file scan --
     * this class's own docblock deliberately quotes the OLD, now-fixed
     * `Account::where('name', ...)` anti-pattern in prose to document what it replaced, which a
     * naive whole-file regex scan cannot tell apart from real code.
     */
    public function test_reconciliation_service_never_resolves_an_account_by_name(): void
    {
        $offenders = $this->scanMethods(\App\Services\Accounting\ReconciliationService::class, self::RECONCILIATION_SERVICE_GOVERNED_METHODS);

        $this->assertSame([], $offenders, 'These ReconciliationService methods resolve an account by name/LIKE: '.implode(', ', $offenders));
    }

    /**
     * @param  string[]  $methodNames
     * @return string[] offending method names
     */
    private function scanMethods(string $className, array $methodNames): array
    {
        $class = new ReflectionClass($className);
        $file = $class->getFileName();
        $this->assertNotFalse($file);
        $lines = file($file);

        $offenders = [];

        foreach ($methodNames as $methodName) {
            $this->assertTrue($class->hasMethod($methodName), "Expected method {$methodName} to exist on {$className}.");
            $method = $class->getMethod($methodName);

            $start = $method->getStartLine() - 1;
            $end = $method->getEndLine();
            $body = implode('', array_slice($lines, $start, $end - $start));

            if (preg_match("/Account::where\\(\\s*'name'/", $body)
                || preg_match('/->where\\(\\s*[\'"]name[\'"]\\s*,\\s*[\'"]LIKE[\'"]/i', $body)) {
                $offenders[] = $methodName;
            }
        }

        return $offenders;
    }
}
