<?php

namespace Tests\Unit\Console;

use Tests\TestCase;

/**
 * W6.C item 3 (w6-brief.md "W6.C — Supplier-side charges"): `ApplySupplierSurcharge` — the
 * one-off, hardcoded-account-id (73/43/107/280), hardcoded-supplier (supplier_id=1,
 * issued_by='KWIKT2843') fix script — is retired: deleted (grep confirmed zero live callers
 * before deletion; App\Console\Commands\ApplySupplierSurcharge.php and its
 * 'supplier:apply-surcharge' signature no longer exist anywhere in app/, routes/, or
 * console.php). Plain static grep, no DB — same convention as
 * {@see \Tests\Feature\Accounting\ArchitectureTest}'s own scan helper.
 */
class ApplySupplierSurchargeRetiredTest extends TestCase
{
    private function appRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_the_command_class_file_no_longer_exists(): void
    {
        $this->assertFileDoesNotExist(
            $this->appRoot().'/app/Console/Commands/ApplySupplierSurcharge.php',
            'ApplySupplierSurcharge must be deleted, not left dead in place, per this sub-wave\'s build report.'
        );
    }

    public function test_no_php_file_under_app_references_the_retired_class_or_its_signature(): void
    {
        $violations = $this->grepAppTree(['ApplySupplierSurcharge', 'supplier:apply-surcharge']);

        $this->assertEmpty(
            $violations,
            "Retired ApplySupplierSurcharge reference(s) found:\n".implode("\n", $violations)
        );
    }

    public function test_the_hardcoded_account_ids_are_not_referenced_by_any_live_supplier_surcharge_posting_path(): void
    {
        // 73/43/107/280 were ApplySupplierSurcharge's own hardcoded account ids (w6-brief.md /
        // supplier-charges-design.md Table 1). The new posting path
        // (App\Services\Accounting\SupplierChargeLineBuilder) resolves accounts exclusively by
        // PURPOSE CODE (SUPPLIER_CHARGE_EXPENSE / SERVICE_COST / SERVICE_PAYABLE /
        // SUPPLIER_CHARGE_RECHARGE_INCOME via AccountResolver) — it contains no literal account id
        // at all, so a plain string search for the four numbers inside the new builder/resolver
        // files is itself a meaningful "never hardcoded again" proof.
        $newFiles = [
            $this->appRoot().'/app/Services/Accounting/SupplierChargeLineBuilder.php',
            $this->appRoot().'/app/Services/Accounting/SupplierChargeRuleResolver.php',
            $this->appRoot().'/app/Console/Commands/BackfillSupplierChargeRules.php',
        ];

        foreach ($newFiles as $file) {
            $this->assertFileExists($file);
            $contents = file_get_contents($file);

            foreach (['73', '43', '107', '280'] as $hardcodedId) {
                // Word-boundary match -- avoids false positives on unrelated numbers that happen
                // to contain these digits as a substring (e.g. line numbers in a docblock).
                $this->assertDoesNotMatchRegularExpression(
                    '/\baccountId:\s*'.$hardcodedId.'\b/',
                    $contents,
                    basename($file)." must never hardcode account id {$hardcodedId}."
                );
            }
        }
    }

    /**
     * @return string[] "relative/path.php:lineNo: matched line" for every hit.
     */
    private function grepAppTree(array $needles): array
    {
        $violations = [];
        $searchRoots = [$this->appRoot().'/app', $this->appRoot().'/routes'];

        foreach ($searchRoots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                // This test file itself legitimately names the retired class/signature in prose
                // (its own class docblock and assertions) -- exclude the tests tree from the scan
                // (searchRoots above only covers app/ and routes/ anyway, so this is belt-and-braces).
                if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $lines = file($file->getPathname());

                foreach ($lines as $lineNumber => $line) {
                    foreach ($needles as $needle) {
                        if (str_contains($line, $needle)) {
                            $violations[] = $file->getPathname().':'.($lineNumber + 1).': '.trim($line);
                        }
                    }
                }
            }
        }

        return $violations;
    }
}
