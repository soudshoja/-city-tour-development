<?php
declare(strict_types=1);
namespace Dotw\XmlCli\Tests\Unit\Commands;

use Dotw\XmlCli\Application;
use Dotw\XmlCli\Command\Xml\ConfirmBookingCommand;
use Dotw\XmlCli\Config\Loader;
use Dotw\XmlCli\Dotw\XmlClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[AsCommand(name: 'xml:confirm-booking-test')]
class TestableConfirmBookingCommand extends ConfirmBookingCommand
{
    public string $capturedBody = '';

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->jsonMode = false;
        $this->rawMode  = false;
        $this->config   = new Loader();
        $self = $this;
        $this->client = new class($self) extends XmlClient {
            public function __construct(private TestableConfirmBookingCommand $cmd)
            {
                // Skip parent constructor
            }
            public function send(string $c, string $b): string
            {
                $this->cmd->capturedBody = $b;
                return '<customer><successful>TRUE</successful><request command="confirmbooking"></request></customer>';
            }
        };
    }
}

class ConfirmBookingCommandTest extends TestCase
{
    private string $validRoomsJson = '[{"roomTypeCode":"DELUXE","selectedRateBasis":0,"allocationDetails":"ALLOC","adults":2,"nationality":66,"residence":66,"passengers":[{"salutation":147,"firstName":"John","lastName":"Doe","leading":true}]}]';

    private function makeCommand(): array
    {
        $cmd = new TestableConfirmBookingCommand();
        $app = new Application();
        $app->add($cmd);
        return [$cmd, new CommandTester($cmd)];
    }

    public function test_rooms_json_builds_passenger_details(): void
    {
        [$cmd, $tester] = $this->makeCommand();
        $tester->execute([
            '--hotel'      => '12345',
            '--from'       => '2026-09-01',
            '--to'         => '2026-09-03',
            '--rooms-json' => $this->validRoomsJson,
        ]);
        $this->assertStringContainsString('<passengersDetails>', $cmd->capturedBody);
        $this->assertStringContainsString('<firstName>John</firstName>', $cmd->capturedBody);
        $this->assertStringContainsString('<salutation>147</salutation>', $cmd->capturedBody);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_invalid_json_returns_input_error(): void
    {
        [, $tester] = $this->makeCommand();
        $tester->execute([
            '--hotel'      => '12345',
            '--from'       => '2026-09-01',
            '--to'         => '2026-09-03',
            '--rooms-json' => 'not-json',
        ]);
        $this->assertSame(3, $tester->getStatusCode());
    }
}