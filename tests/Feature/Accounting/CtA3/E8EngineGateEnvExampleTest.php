<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Services\Accounting\PostingSeam;
use Tests\TestCase;

/**
 * CT-A3 E8 (CT-A1 §1.7, CT-A2 §1.1) — `ACCOUNTING_ENGINE_ENABLED`, the GLOBAL half of the
 * engine's two-sided kill switch, was absent from `.env.example` entirely, which is exactly why
 * the dev site's `.env` never carried it and `PostingSeam::isEnabledFor()` returned false for
 * every company regardless of `companies.posting_engine_enabled`.
 *
 * This test pins the documentation half only. `PostingSeam::isEnabledFor()` itself is UNCHANGED
 * by CT-A3 (the second test below is the regression guard proving that).
 */
class E8EngineGateEnvExampleTest extends TestCase
{
    private function envExample(): string
    {
        return (string) file_get_contents(base_path('.env.example'));
    }

    public function test_env_example_declares_the_global_engine_gate(): void
    {
        $contents = $this->envExample();

        $this->assertMatchesRegularExpression(
            '/^ACCOUNTING_ENGINE_ENABLED=/m',
            $contents,
            '.env.example must declare ACCOUNTING_ENGINE_ENABLED — without it a deployer has no '
            .'way to know the engine has a second, global gate (CT-A1 §1.7).'
        );
    }

    public function test_env_example_ships_the_gate_off_by_default(): void
    {
        $this->assertMatchesRegularExpression(
            '/^ACCOUNTING_ENGINE_ENABLED=false\s*$/m',
            $this->envExample(),
            'The shipped default must be false — matching config/accounting.php\'s own '
            ."env('ACCOUNTING_ENGINE_ENABLED', false) default. Shipping it true would silently "
            .'flip the engine on for every new deployment.'
        );
    }

    public function test_env_example_explains_that_the_gate_is_two_sided(): void
    {
        $contents = $this->envExample();
        $offset = strpos($contents, 'ACCOUNTING_ENGINE_ENABLED=');
        $this->assertIsInt($offset);

        $preamble = substr($contents, max(0, $offset - 800), 800);

        $this->assertStringContainsString(
            'posting_engine_enabled',
            $preamble,
            'The comment above the key must name the per-company half of the gate, so an operator '
            .'setting only one of the two knows the engine still will not post.'
        );
    }

    public function test_posting_seam_gate_semantics_are_unchanged(): void
    {
        // E8 is documentation-only: PostingSeam::isEnabledFor() must still short-circuit on the
        // global flag before it ever looks at a company row. Company 0 can never resolve, so a
        // false here with the global flag ON proves the company half still runs too.
        config(['accounting.engine.enabled' => false]);
        $this->assertFalse(app(PostingSeam::class)->isEnabledFor(1));

        config(['accounting.engine.enabled' => true]);
        $this->assertFalse(
            app(PostingSeam::class)->isEnabledFor(0),
            'An unresolvable company must still gate to false with the global flag on.'
        );
    }
}
