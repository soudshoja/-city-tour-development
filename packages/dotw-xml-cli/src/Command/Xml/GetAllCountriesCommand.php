<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:get-all-countries', description: 'Download all DOTW country reference data')]
final class GetAllCountriesCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('Downloads all countries from DOTW. Large response. Pipe with --json | jq to filter.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeXml('getallcountries', '<return></return>', $output);
    }
}
