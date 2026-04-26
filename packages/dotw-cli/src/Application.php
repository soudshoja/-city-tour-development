<?php

declare(strict_types=1);

namespace Dotw\Cli;

use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * DOTW CLI Application bootstrap.
 *
 * Registers all commands. Commands are added here explicitly
 * (no container auto-discovery) for portability.
 */
class Application extends SymfonyApplication
{
    public const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct('dotw-cli', self::VERSION);

        // Commands are registered in subsequent plans (28-02 through 28-05).
        // Scaffold only — bin/dotw list returns empty command list.
    }
}
