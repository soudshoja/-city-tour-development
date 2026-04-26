<?php
declare(strict_types=1);
namespace Dotw\XmlCli\Tests\Unit\Commands;

use Dotw\XmlCli\Application;
use Dotw\XmlCli\Command\Xml\SearchHotelsCommand;
use Dotw\XmlCli\Config\Loader;
use Dotw\XmlCli\Dotw\XmlClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[AsCommand(name: 'xml:search-hotels-test')]
class TestableSearchHotelsCommand extends SearchHotelsCommand
{
    public string $capturedBody = '';
    public string $capturedCmd  = '';

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->jsonMode = false;
        $this->rawMode  = false;
        $this->config   = new Loader();  // uses defaults; no network calls
        $self = $this;
        $this->client = new class($self) extends XmlClient {
            public function __construct(private TestableSearchHotelsCommand $cmd)
            {
                // Skip parent constructor
            }
            public function send(string $command, string $bodyXml): string
            {
                $this->cmd->capturedCmd  = $command;
                $this->cmd->capturedBody = $bodyXml;
                return '<customer><successful>TRUE</successful><request command="searchhotels"></request></customer>';
            }
        };
    }
}

class SearchHotelsCommandTest extends TestCase
{
    private function makeCommand(): array
    {
        $cmd = new TestableSearchHotelsCommand();
        $app = new Application();
        $app->add($cmd);
        return [$cmd, new CommandTester($cmd)];
    }

    public function test_city_mode_body_contains_city_element(): void
    {
        [$cmd, $tester] = $this->makeCommand();
        $tester->execute(['--city' => '364', '--from' => '2026-09-01', '--to' => '2026-09-03', '--adults' => '2']);
        $this->assertStringContainsString('<city>364</city>', $cmd->capturedBody);
        $this->assertStringContainsString('<adultsCode>2</adultsCode>', $cmd->capturedBody);
        $this->assertSame('searchhotels', $cmd->capturedCmd);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_hotel_mode_body_contains_hotel_id(): void
    {
        [$cmd, $tester] = $this->makeCommand();
        $tester->execute(['--hotel' => '12345', '--from' => '2026-09-01', '--to' => '2026-09-03']);
        $this->assertStringContainsString('12345', $cmd->capturedBody);
        $this->assertStringContainsString('hotelId', $cmd->capturedBody);
    }

    public function test_missing_from_returns_input_error(): void
    {
        [, $tester] = $this->makeCommand();
        $tester->execute(['--city' => '364', '--to' => '2026-09-03']);
        $this->assertSame(3, $tester->getStatusCode());
    }

    public function test_missing_city_and_hotel_returns_input_error(): void
    {
        [, $tester] = $this->makeCommand();
        $tester->execute(['--from' => '2026-09-01', '--to' => '2026-09-03']);
        $this->assertSame(3, $tester->getStatusCode());
    }
}