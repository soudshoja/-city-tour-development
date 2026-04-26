<?php

declare(strict_types=1);

namespace Dotw\Cli;

use Dotw\Cli\Command\ConfirmCommand;
use Dotw\Cli\Command\HotelsShowCommand;
use Dotw\Cli\Command\PrebookCommand;
use Dotw\Cli\Command\RoomsBrowseCommand;
use Dotw\Cli\Command\SearchCommand;
use Dotw\Cli\Command\VoucherCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * DOTW CLI Application bootstrap.
 *
 * Registers all commands explicitly (no container auto-discovery) for portability.
 */
class Application extends SymfonyApplication
{
    public const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct('dotw-cli', self::VERSION);

        $this->add(new SearchCommand());
        $this->add(new HotelsShowCommand());
        $this->add(new RoomsBrowseCommand());
        $this->add(new PrebookCommand());
        $this->add(new ConfirmCommand());
        $this->add(new VoucherCommand());
    }
}
