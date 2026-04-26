<?php
declare(strict_types=1);
namespace Dotw\XmlCli\Tests\Unit\Commands;

use Dotw\XmlCli\Application;
use Dotw\XmlCli\Command\Xml\CancelBookingCommand;
use Dotw\XmlCli\Config\Loader;
use Dotw\XmlCli\Dotw\XmlClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[AsCommand(name: 'xml:cancel-booking-test')]
class TestableCancelBookingCommand extends CancelBookingCommand
{
    public string $capturedBody = '';

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->jsonMode = false;
        $this->rawMode  = false;
        $this->config   = new Loader();
        $self = $this;
        $this->client = new class($self) extends XmlClient {
            public function __construct(private TestableCancelBookingCommand $cmd)
            {
                // Skip parent constructor
            }
            public function send(string $c, string $b): string
            {
                $this->cmd->capturedBody = $b;
                return '<customer><successful>TRUE</successful><request command="cancelbooking"></request></customer>';
            }
        };
    }
}

class CancelBookingCommandTest extends TestCase
{
    private function makeCommand(): array
    {
        $cmd = new TestableCancelBookingCommand();
        $app = new Application();
        $app->add($cmd);
        return [$cmd, new CommandTester($cmd)];
    }

    public function test_confirm_no_sends_no_penalty(): void
    {
        [$cmd, $tester] = $this->makeCommand();
        $tester->execute(['--booking' => 'REF123', '--confirm' => 'no']);
        $this->assertStringContainsString('<confirm>no</confirm>', $cmd->capturedBody);
        $this->assertStringNotContainsString('<penaltyApplied>', $cmd->capturedBody);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_confirm_yes_includes_penalty(): void
    {
        [$cmd, $tester] = $this->makeCommand();
        $tester->execute(['--booking' => 'REF123', '--confirm' => 'yes', '--penalty' => '150.00']);
        $this->assertStringContainsString('<confirm>yes</confirm>', $cmd->capturedBody);
        $this->assertStringContainsString('<penaltyApplied>150.00</penaltyApplied>', $cmd->capturedBody);
    }

    public function test_confirm_yes_without_penalty_returns_input_error(): void
    {
        [, $tester] = $this->makeCommand();
        $tester->execute(['--booking' => 'REF123', '--confirm' => 'yes']);
        $this->assertSame(3, $tester->getStatusCode());
    }
}