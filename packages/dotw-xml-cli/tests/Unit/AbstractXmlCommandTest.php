<?php
declare(strict_types=1);
namespace Dotw\XmlCli\Tests\Unit;

use Dotw\XmlCli\Application;
use Dotw\XmlCli\Command\AbstractXmlCommand;
use Dotw\XmlCli\Dotw\XmlClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/** Minimal stub command for testing AbstractXmlCommand behavior */
class StubXmlCommand extends AbstractXmlCommand
{
    public string $mockResponse = '<customer><successful>TRUE</successful><request command="test"><data>hello</data></request></customer>';
    public ?\Throwable $mockException = null;

    protected function configure(): void
    {
        parent::configure();
        $this->setName('xml:stub-test');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // Override to skip file-system config loading
        $this->jsonMode = (bool) $input->getOption('json');
        $this->rawMode  = !$this->jsonMode && (bool) $input->getOption('raw');

        $this->client = $this->createMockClient();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeXml('test', '<body/>', $output);
    }

    private function createMockClient(): XmlClient
    {
        $mockResponse  = $this->mockResponse;
        $mockException = $this->mockException;

        return new class($mockResponse, $mockException) extends XmlClient {
            public function __construct(private string $resp, private ?\Throwable $ex)
            {
                // Skip parent constructor — no Guzzle needed
            }
            public function send(string $command, string $bodyXml): string
            {
                if ($this->ex !== null) {
                    throw $this->ex;
                }
                return $this->resp;
            }
        };
    }
}

class AbstractXmlCommandTest extends TestCase
{
    private StubXmlCommand $command;
    private CommandTester  $tester;

    protected function setUp(): void
    {
        $app           = new Application();
        $this->command = new StubXmlCommand();
        $app->add($this->command);
        $this->tester = new CommandTester($this->command);
    }

    public function test_default_mode_outputs_pretty_xml(): void
    {
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('<data>hello</data>', $output);
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function test_json_mode_outputs_valid_json(): void
    {
        $this->tester->execute(['--json' => true]);
        $output = $this->tester->getDisplay();
        $data   = json_decode($output, true);
        $this->assertIsArray($data, 'Output should be valid JSON');
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function test_raw_mode_outputs_unformatted_xml(): void
    {
        $this->tester->execute(['--raw' => true]);
        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('<successful>TRUE</successful>', $output);
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function test_dotw_api_failure_returns_exit_1(): void
    {
        $this->command->mockResponse = '<customer><successful>FALSE</successful><request command="test"><error><code>404</code><details>Not found</details></error></request></customer>';
        $this->tester->execute([]);
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function test_network_error_returns_exit_2(): void
    {
        $this->command->mockException = new class('timeout') extends \RuntimeException implements \GuzzleHttp\Exception\GuzzleException {};
        $this->tester->execute([]);
        $this->assertSame(2, $this->tester->getStatusCode());
    }
}