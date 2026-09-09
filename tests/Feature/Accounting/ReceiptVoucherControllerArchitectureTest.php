<?php

namespace Tests\Feature\Accounting;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * W5.X (w5-brief.md §W5.X "ArchitectureTest: no RV/PV code resolves accounts by name").
 *
 * Scoped to the POSTING PATH only -- the methods this sub-wave actually rewrote (store/update/
 * approve/destroy/clear/bounce and the private helpers they call to resolve an account or write a
 * line), PLUS `import()`/`invoiceJournalEntry()` (post-verify CRITICAL 1-3 fix: these were found to
 * be a live, engine-bypassing, name-LIKE-resolving, unauthorized route and are now rewritten to
 * route through `PostingSeam::post()` with purpose-code-only resolution, same as every other
 * governed method here -- see `invoiceJournalEntry()`'s own docblock). `create()`/`edit()`/
 * `fetchPaymentsByDate()` remain explicitly OUT of scope (UI-dropdown/legacy-feeder lookups no
 * build has touched) and still contain pre-existing `Account::where('name', ...)` lookups -- a
 * whole-file scan would fail on those for reasons unrelated to posting correctness, so this test
 * inspects only the methods it actually governs.
 *
 * `createReceiptVoucher()`/`autoGenerate()`/`writeLegacyReceiptVoucherTransaction()` were out of
 * W5.R's own scope but are now governed too (post-W5.R hotfix: InvoiceController::savePartial()'s
 * receipt branch was creating an unposted `invoice_receipts` row with no `transaction_id` at all
 * -- see those two methods' own docblocks) -- neither resolves an account by name either.
 */
class ReceiptVoucherControllerArchitectureTest extends TestCase
{
    private const GOVERNED_METHODS = [
        'store',
        'update',
        'approve',
        'destroy',
        'clear',
        'bounce',
        'import',
        'invoiceJournalEntry',
        'postVoucher',
        'buildVoucherDraft',
        'writeLegacyTransaction',
        'applyAllocationsToInvoices',
        'undoAllocationsForVoucher',
        'fillVoucherRow',
        'validateVoucherRequest',
        'createReceiptVoucher',
        'autoGenerate',
        'writeLegacyReceiptVoucherTransaction',
    ];

    /**
     * CT-A3 wave 2 (W2-2): the instrument leg moved OUT of this controller into
     * {@see \App\Services\Accounting\ReceiptPostingRule::instrumentAccountFor()}, so the ratchet
     * follows it. `resolveInstrumentLeg` was removed from GOVERNED_METHODS above and the rule is
     * scanned here instead -- otherwise the one method that actually picks the cash/bank account
     * would have walked out from under the guard that exists for it.
     */
    public function test_the_receipt_instrument_rule_never_resolves_an_account_by_name(): void
    {
        $class = new ReflectionClass(\App\Services\Accounting\ReceiptPostingRule::class);
        $file = $class->getFileName();
        $this->assertNotFalse($file);

        $body = file_get_contents($file);

        $this->assertDoesNotMatchRegularExpression(
            "/Account::where\\(\\s*'name'/",
            $body,
            'ReceiptPostingRule must resolve every account by purpose code or configured id, never by name.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/->where\\(\\s*[\'"]name[\'"]\\s*,\\s*[\'"]LIKE[\'"]/i',
            $body,
            'ReceiptPostingRule must never resolve an account by a name LIKE pattern.'
        );
    }

    public function test_posting_path_methods_never_resolve_an_account_by_name(): void
    {
        $class = new ReflectionClass(\App\Http\Controllers\ReceiptVoucherController::class);
        $file = $class->getFileName();
        $this->assertNotFalse($file);
        $lines = file($file);

        $offenders = [];

        foreach (self::GOVERNED_METHODS as $methodName) {
            $this->assertTrue($class->hasMethod($methodName), "Expected method {$methodName} to exist.");
            $method = $class->getMethod($methodName);

            $start = $method->getStartLine() - 1;
            $end = $method->getEndLine();
            $body = implode('', array_slice($lines, $start, $end - $start));

            if (preg_match("/Account::where\\(\\s*'name'/", $body)
                || preg_match('/->where\\(\\s*[\'"]name[\'"]\\s*,\\s*[\'"]LIKE[\'"]/i', $body)) {
                $offenders[] = $methodName;
            }
        }

        $this->assertSame([], $offenders, 'These W5.R posting-path methods resolve an account by name/LIKE: '.implode(', ', $offenders));
    }
}
