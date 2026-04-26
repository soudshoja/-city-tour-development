<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:get-all-cities', description: 'Download all DOTW city reference data')]
final class GetAllCitiesCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('Downloads all cities from DOTW. Use --json | jq to search by name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeXml('getallcities', '<return></return>', $output);
    }
}
