<?php
declare(strict_types=1);
namespace Dotw\XmlCli\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Integration tests against DOTW sandbox.
 * Auto-skipped when DOTW_USERNAME env var is not set.
 *
 * Set env vars before running:
 *   export DOTW_USERNAME=... DOTW_PASSWORD=... DOTW_COMPANY_CODE=...
 *   vendor/bin/phpunit --testsuite=Integration
 */
class SandboxTest extends TestCase
{
    private string $binPath;

    protected function setUp(): void
    {
        if (empty(getenv('DOTW_USERNAME'))) {
            $this->markTestSkipped('DOTW_USERNAME env var not set — skipping integration tests');
        }
        $this->binPath = __DIR__ . '/../../bin/dotw-xml';
    }

    /**
     * Run the CLI binary with the given arguments.
     *
     * @param list<string> $args
     * @return array{exit: int|null, stdout: string, stderr: string}
     */
    private function runCli(array $args, int $timeout = 60): array
    {
        $cmd  = array_merge([PHP_BINARY, $this->binPath], $args);
        $proc = new Process($cmd);
        $proc->setTimeout($timeout);
        $proc->run(null, [
            'DOTW_USERNAME'     => (string) getenv('DOTW_USERNAME'),
            'DOTW_PASSWORD'     => (string) getenv('DOTW_PASSWORD'),
            'DOTW_COMPANY_CODE' => (string) (getenv('DOTW_COMPANY_CODE') ?: ''),
        ]);
        return [
            'exit'   => $proc->getExitCode(),
            'stdout' => $proc->getOutput(),
            'stderr' => $proc->getErrorOutput(),
        ];
    }

    public function test_get_all_countries_returns_exit_0(): void
    {
        $result = $this->runCli(['xml:get-all-countries', '--json']);
        $this->assertSame(0, $result['exit'],
            "Expected exit 0. stderr: {$result['stderr']}");
        $data = json_decode($result['stdout'], true);
        $this->assertIsArray($data, 'Output should be valid JSON');
    }

    public function test_search_hotels_by_city_returns_exit_0(): void
    {
        $result = $this->runCli([
            'xml:search-hotels',
            '--city=364',
            '--from=' . date('Y-m-d', strtotime('+90 days')),
            '--to='   . date('Y-m-d', strtotime('+92 days')),
            '--adults=2',
            '--json',
        ]);
        $this->assertSame(0, $result['exit'],
            "Expected exit 0. stderr: {$result['stderr']}");
        $this->assertNotEmpty($result['stdout']);
    }

    public function test_get_rooms_browse_mode_returns_exit_0_or_1(): void
    {
        // Hotel 12345 is a known DOTW test property — adjust if needed
        $result = $this->runCli([
            'xml:get-rooms',
            '--hotel=12345',
            '--from=' . date('Y-m-d', strtotime('+90 days')),
            '--to='   . date('Y-m-d', strtotime('+92 days')),
            '--adults=2',
        ]);
        // Exit 0 = success, exit 1 = API error (hotel may be unavailable). Both are acceptable.
        $this->assertContains($result['exit'], [0, 1],
            "Expected exit 0 or 1 (API). Got {$result['exit']}. stderr: {$result['stderr']}");
    }
}