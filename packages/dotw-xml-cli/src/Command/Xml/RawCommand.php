<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:raw', description: 'Send a raw XML body to any DOTW method (escape hatch)')]
final class RawCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('method', null, InputOption::VALUE_REQUIRED,
                'DOTW command name (e.g. getallcountries, searchhotels)')
            ->addOption('body',   null, InputOption::VALUE_REQUIRED,
                'Raw XML body string (everything inside <request>)')
            ->setHelp(
                "Post a raw XML body to any DOTW method. For exploratory/debugging use.\n\n" .
                "  bin/dotw-xml xml:raw --method=getallcountries --body='<return></return>'\n" .
                "  bin/dotw-xml xml:raw --method=getrooms --body='<bookingDetails>...</bookingDetails>' --json\n\n" .
                "The body is inserted inside the <request command='METHOD'> envelope. Credentials are added automatically."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $method = $input->getOption('method');
        $body   = $input->getOption('body');

        if (!$method || $body === null) {
            $output->getErrorOutput()->writeln('[INPUT_ERROR] --method and --body are required');
            return self::EXIT_INPUT;
        }

        return $this->executeXml((string) $method, (string) $body, $output);
    }
}
