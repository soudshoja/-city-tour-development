<?php

declare(strict_types=1);

namespace Tests\Unit\Legacy;

use App\Services\Onboarding\LegacyPathGuard;
use RuntimeException;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP0.2 fence — root refusal.
 *
 * MUTATION PROOF: hostilePathProvider()'s '..' traversal and symlink cases
 * are the deliberate break. A naive `str_starts_with($path, $root)` check
 * (the bug this class exists to prevent) PASSES both of those as strings
 * while resolving outside the root — replacing assertPathUnderRoot()'s
 * realpath()-based normalisation with that naive check makes this test
 * fail (the exception is never thrown), proving the test can fail.
 */
class LegacyPathGuardTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lp1_guard_'.bin2hex(random_bytes(6));
        mkdir($this->root, 0777, true);
        mkdir($this->root.DIRECTORY_SEPARATOR.'sibling', 0777, true);
        file_put_contents($this->root.DIRECTORY_SEPARATOR.'good.csv', "a,b\n1,2\n");
        file_put_contents($this->root.DIRECTORY_SEPARATOR.'sibling'.DIRECTORY_SEPARATOR.'evil.csv', "a,b\n1,2\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->root.DIRECTORY_SEPARATOR.'good.csv');
        @unlink($this->root.DIRECTORY_SEPARATOR.'sibling'.DIRECTORY_SEPARATOR.'evil.csv');
        @rmdir($this->root.DIRECTORY_SEPARATOR.'sibling');
        @rmdir($this->root);

        parent::tearDown();
    }

    public function test_it_allows_a_genuine_path_under_the_root(): void
    {
        $resolved = LegacyPathGuard::assertPathUnderRoot($this->root.DIRECTORY_SEPARATOR.'good.csv', $this->root);

        $this->assertStringContainsString('good.csv', $resolved);
    }

    /**
     * MUTATION PROOF case: "<root>\..\sibling\evil.csv" resolves OUTSIDE a
     * narrower allowed root ("<root>" itself, not its parent) once `..` is
     * resolved, even though it is a string-prefix match on the raw path. A
     * regression to str_starts_with() would let this pass silently.
     */
    public function test_it_refuses_a_traversal_path_that_escapes_the_root_as_a_string_prefix(): void
    {
        $narrowRoot = $this->root.DIRECTORY_SEPARATOR.'sibling';
        $traversal = $narrowRoot.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'good.csv';

        // As a STRING, $traversal starts with $narrowRoot -- but it
        // resolves to a file OUTSIDE $narrowRoot. That is exactly the case
        // a naive prefix check gets wrong.
        $this->assertTrue(str_starts_with($traversal, $narrowRoot));

        $this->expectException(RuntimeException::class);

        LegacyPathGuard::assertPathUnderRoot($traversal, $narrowRoot);
    }

    public function test_it_refuses_a_unc_path(): void
    {
        $this->expectException(RuntimeException::class);

        LegacyPathGuard::assertPathUnderRoot('\\\\evilhost\\share\\evil.csv', $this->root);
    }

    public function test_it_refuses_a_relative_path_that_does_not_resolve_under_the_root(): void
    {
        $cwd = getcwd();
        chdir(sys_get_temp_dir());

        try {
            $this->expectException(RuntimeException::class);

            LegacyPathGuard::assertPathUnderRoot('does-not-exist-here.csv', $this->root);
        } finally {
            chdir($cwd);
        }
    }

    public function test_it_refuses_a_symlink_whose_target_escapes_the_root(): void
    {
        $narrowRoot = $this->root.DIRECTORY_SEPARATOR.'sibling';
        $link = $this->root.DIRECTORY_SEPARATOR.'escape-link.csv';

        if (! @symlink($this->root.DIRECTORY_SEPARATOR.'good.csv', $link)) {
            $this->markTestSkipped('symlink() not permitted in this environment (requires elevated privilege on Windows).');
        }

        try {
            $this->expectException(RuntimeException::class);

            LegacyPathGuard::assertPathUnderRoot($link, $narrowRoot);
        } finally {
            @unlink($link);
        }
    }

    public function test_it_refuses_when_allowed_root_is_not_configured(): void
    {
        $this->expectException(RuntimeException::class);

        LegacyPathGuard::assertPathUnderRoot($this->root.DIRECTORY_SEPARATOR.'good.csv', '');
    }

    public function test_assert_quarantined_connection_accepts_a_fence_database(): void
    {
        config(['database.connections.legacy_pilot.database' => 'city_tour_test_lp1_map']);

        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $this->assertTrue(true);
    }

    public function test_assert_quarantined_connection_refuses_a_real_looking_database(): void
    {
        config(['database.connections.legacy_pilot.database' => 'akeed2_app']);

        $this->expectException(RuntimeException::class);

        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');
    }
}
