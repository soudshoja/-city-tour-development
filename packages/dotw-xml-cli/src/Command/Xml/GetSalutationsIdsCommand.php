<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:get-salutations-ids', description: 'Download DOTW salutation codes (Mr=147, Mrs=148, Ms=149, Miss=150)')]
final class GetSalutationsIdsCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('Returns salutation ID list. Standard codes: Mr=147, Mrs=148, Ms=149, Miss=150.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeXml('getsalutationsids', '<return></return>', $output);
    }
}
