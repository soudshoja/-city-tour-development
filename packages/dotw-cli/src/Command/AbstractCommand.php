<?php

declare(strict_types=1);

namespace Dotw\Cli\Command;

use Dotw\Cli\Config\Loader;
use Dotw\Cli\State\Database;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command providing --json flag, --profile flag,
 * structured exit codes, and stderr error reporting.
 *
 * Exit codes:
 *   0 = success
 *   1 = input/validation error
 *   2 = DOTW API error
 *   3 = payment/financial error
 *   4 = internal/unexpected error
 */
abstract class AbstractCommand extends Command
{
    public const EXIT_SUCCESS   = 0;
    public const EXIT_INPUT     = 1;
    public const EXIT_DOTW_API  = 2;
    public const EXIT_PAYMENT   = 3;
    public const EXIT_INTERNAL  = 4;

    protected Loader   $config;
    protected Database $db;
    protected bool     $jsonMode = false;

    protected function configure(): void
    {
        $this->addOption('json',    null, InputOption::VALUE_NONE, 'Output as JSON (machine-readable)');
        $this->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Config profile name', null);
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->jsonMode = (bool) $input->getOption('json');
        $profile = $input->getOption('profile');

        $this->config = new Loader($profile);
        $this->db     = new Database($this->config->get('state_db', '~/.dotw-cli/state.db'));
        $this->db->migrate();
    }

    /**
     * Write a success payload. In --json mode writes JSON to stdout.
     * Otherwise returns the data array for human formatting by the concrete command.
     *
     * @param array<string,mixed> $data
     */
    protected function success(OutputInterface $output, array $data): int
    {
        if ($this->jsonMode) {
            $output->writeln(json_encode(['status' => 'success', 'data' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        return self::EXIT_SUCCESS;
    }

    /**
     * Write a structured error to stderr and optionally JSON to stdout.
     *
     * @param array<string,mixed> $context
     */
    protected function fail(
        OutputInterface $output,
        string $errorCode,
        string $message,
        int $exitCode = self::EXIT_INTERNAL,
        array $context = [],
    ): int {
        $payload = [
            'status'     => 'error',
            'error_code' => $errorCode,
            'message'    => $message,
            'context'    => $context,
        ];

        // Always write error to stderr
        $errOutput = $output instanceof \Symfony\Component\Console\Output\ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;
        $errOutput->writeln("[{$errorCode}] {$message}");

        if ($this->jsonMode) {
            $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $exitCode;
    }
}
