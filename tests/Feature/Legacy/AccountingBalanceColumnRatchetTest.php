<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * legacy-ledger-pilot ratchet (task brief): no code under
 * app/Services/Onboarding/** or app/Console/Commands/Legacy/** ever READS
 * accounts.actual_balance or journal_entries.balance, or writes
 * actual_balance to anything other than the literal 0.00 the NOT NULL
 * column requires at Account::create() time. Balances are always derived
 * via TrialBalanceService / journal_entries sums.
 *
 * MUTATION PROOF: adding a `$account->actual_balance` read, or an
 * `actual_balance` assignment carrying anything other than a bare `0`/`0.00`
 * literal, anywhere under the scanned paths makes this test fail.
 */
class AccountingBalanceColumnRatchetTest extends TestCase
{
    private const SCANNED_PATHS = [
        'app/Services/Onboarding',
        'app/Console/Commands/Legacy',
    ];

    public function test_no_read_of_actual_balance_or_journal_entries_balance(): void
    {
        $violations = [];

        foreach ($this->files() as $file) {
            $contents = file_get_contents($file->getPathname());

            // Property READ: ->actual_balance not immediately followed by an
            // assignment operator (a write is checked separately below).
            if (preg_match('/->actual_balance\s*(?!=[^=])/', $contents) && ! preg_match('/->actual_balance\s*=/', $contents)) {
                $violations[] = $file->getPathname().': reads ->actual_balance';
            }

            if (preg_match('/->balance\b/', $contents)) {
                $violations[] = $file->getPathname().': references journal_entries ->balance';
            }
        }

        $this->assertEmpty($violations, "Ratchet violation(s):\n".implode("\n", $violations));
    }

    public function test_every_actual_balance_write_is_a_literal_zero(): void
    {
        $violations = [];

        foreach ($this->files() as $file) {
            $contents = file_get_contents($file->getPathname());

            // Matches both `'actual_balance' => X` (array/create) and
            // `->actual_balance = X` (property write). X must be a bare
            // 0 / 0.0 / 0.00 literal -- anything else is a violation.
            if (preg_match_all("/(?:'actual_balance'\\s*=>|->actual_balance\\s*=)\\s*([^,\\)\\n;]+)/", $contents, $m)) {
                foreach ($m[1] as $value) {
                    $value = trim($value);

                    if (! preg_match('/^0(\.0+)?$/', $value)) {
                        $violations[] = $file->getPathname().": actual_balance set to non-zero literal '{$value}'";
                    }
                }
            }
        }

        $this->assertEmpty($violations, "Ratchet violation(s):\n".implode("\n", $violations));
    }

    /**
     * @return \SplFileInfo[]
     */
    private function files(): iterable
    {
        $found = [];

        foreach (self::SCANNED_PATHS as $relative) {
            $path = base_path($relative);

            if (! is_dir($path)) {
                continue;
            }

            foreach ((new Finder)->files()->in($path)->name('*.php') as $file) {
                $found[] = $file;
            }
        }

        return $found;
    }
}
