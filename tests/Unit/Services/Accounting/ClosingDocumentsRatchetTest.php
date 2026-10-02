<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use PHPUnit\Framework\TestCase;

/**
 * XBRL-X9r (X9R-VERIFY M-2): the closing-document families have ONE definition,
 * {@see \App\Services\Accounting\ClosingDocuments}. The GL and the parity oracle drifted from the
 * P&L because each wrote its own `doc_type = 'YEC'` test; this ratchet fails the moment any file
 * under app/ spells the 'YEC' or 'PPA' doc type as a string literal in code again (comments are
 * ignored). Use ClosingDocuments' constants and helpers instead.
 */
class ClosingDocumentsRatchetTest extends TestCase
{
    private const ALLOWED = ['app/Services/Accounting/ClosingDocuments.php'];

    public function test_no_reader_spells_the_closing_doc_types_outside_closing_documents(): void
    {
        $this->assertSame([], $this->offenders(dirname(__DIR__, 4)), 'closing doc types spelled outside ClosingDocuments');
    }

    /**
     * CT port (U1): the mutation proof this ratchet shipped without (City Travelers' rule: a
     * ratchet and its mutation proof land together, or the ratchet is decorative). A throwaway
     * tree carries the forbidden shape in code, the same words in a comment and a docblock, a
     * longer literal that merely contains them, and the one allow-listed file; the scanner must
     * report exactly the two code literals, by file and line.
     */
    public function test_the_scanner_reports_a_planted_literal_and_nothing_else(): void
    {
        $root = sys_get_temp_dir().'/ctport-u1-closing-ratchet-'.bin2hex(random_bytes(4));
        mkdir($root.'/app/Services/Accounting', 0777, true);

        file_put_contents($root.'/app/Offender.php', implode("\n", [
            '<?php',
            "// doc_type = 'YEC' in a comment is fine",
            "/** 'PPA' in a docblock is fine */",
            "\$a = 'YEC';",
            '$b = "PPA";',
            "\$c = 'YECX';",
            '',
        ]));
        file_put_contents($root.'/app/Services/Accounting/ClosingDocuments.php', "<?php\n\$allowed = 'YEC';\n");

        try {
            $this->assertSame(["app/Offender.php:4 'YEC'", 'app/Offender.php:5 "PPA"'], $this->offenders($root));
        } finally {
            unlink($root.'/app/Offender.php');
            unlink($root.'/app/Services/Accounting/ClosingDocuments.php');
            rmdir($root.'/app/Services/Accounting');
            rmdir($root.'/app/Services');
            rmdir($root.'/app');
            rmdir($root);
        }
    }

    /** @return list<string> "relative/path.php:line literal" for every code literal found, sorted */
    private function offenders(string $root): array
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/app', \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            if (in_array($relative, self::ALLOWED, true)) {
                continue;
            }

            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING
                    && preg_match('/^([\'"])(YEC|PPA)\1$/', $token[1]) === 1) {
                    $offenders[] = "{$relative}:{$token[2]} {$token[1]}";
                }
            }
        }

        sort($offenders);

        return $offenders;
    }
}
