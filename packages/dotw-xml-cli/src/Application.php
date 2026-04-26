<?php

declare(strict_types=1);

namespace Dotw\XmlCli;

use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('dotw-xml-cli', '1.0.0');
        // Commands are registered here in plan 29-02.
    }
}
