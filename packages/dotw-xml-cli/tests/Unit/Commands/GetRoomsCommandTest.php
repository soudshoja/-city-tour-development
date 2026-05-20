<?php
declare(strict_types=1);
namespace Dotw\XmlCli\Tests\Unit\Commands;

use Dotw\XmlCli\Application;
use Dotw\XmlCli\Command\Xml\GetRoomsCommand;
use Dotw\XmlCli\Config\Loader;
use Dotw\XmlCli\Dotw\XmlClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[AsCommand(name: 'xml:get-rooms-test')]
class TestableGetRoomsCommand extends GetRoomsCommand
{
    public string $capturedBody = '';

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->jsonMode = false;
        $this->rawMode  = false;
        $this->config   = new Loader();
        $self = $this;
        $this->client = new class($self) extends XmlClient {
            public function __construct(private TestableGetRoomsCommand $cmd)
            {
                // Skip parent constructor
            }
            public function send(string $c, string $b): string
            {
                $this->cmd->capturedBody = $b;
                return '<customer><successful>TRUE</successful><request command="getrooms"></request></customer>';
            }
        };
    }
}

class GetRoomsCommandTest extends TestCase
{
    private function makeCommand(): array
    {
        $cmd = new TestableGetRoomsCommand();
        $app = new Application();
        $app->add($cmd);
        return [$cmd, new CommandTester($cmd)];
    }

    public function test_browse_mode_body_contains_room_fields(): void
    {
        [$cmd, $tester] = $this->makeCommand();
        $tester->execute(['--hotel' => '12345', '--from' => '2026-09-01', '--to' => '2026-09-03']);
        $this->assertStringContainsString('<roomField>cancellation</roomField>', $cmd->capturedBody);
        $this->assertStringNotContainsString('<roomTypeSelected>', $cmd->capturedBody);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_block_mode_body_contains_room_type_selected(): void
    {
        [$cmd, $tester] = $this->makeCommand();
        $tester->execute([
            '--hotel'      => '12345',
            '--from'       => '2026-09-01',
            '--to'         => '2026-09-03',
            '--block'      => true,
            '--room-type'  => 'DELUXE',
            '--rate'       => '0',
            '--allocation' => 'ALLOC123',
        ]);
        $this->assertStringContainsString('<roomTypeSelected>', $cmd->capturedBody);
        $this->assertStringContainsString('<code>DELUXE</code>', $cmd->capturedBody);
        $this->assertStringContainsString('<allocationDetails>ALLOC123</allocationDetails>', $cmd->capturedBody);
    }

    public function test_block_without_room_type_returns_input_error(): void
    {
        [, $tester] = $this->makeCommand();
        $tester->execute(['--hotel' => '12345', '--from' => '2026-09-01', '--to' => '2026-09-03', '--block' => true]);
        $this->assertSame(3, $tester->getStatusCode());
    }
}