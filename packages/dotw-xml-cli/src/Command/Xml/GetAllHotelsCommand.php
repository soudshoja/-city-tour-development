<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:get-all-hotels', description: 'Download full DOTW hotel static data (large response)')]
final class GetAllHotelsCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('city',    null, InputOption::VALUE_REQUIRED, 'Filter by DOTW city code', null)
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Filter by DOTW country code', null);
        $this->setHelp(
            "Download hotel static data.\n" .
            "  --city=364      Filter to a single city\n" .
            "  --country=66    Filter to a country\n" .
            "  Without filters: returns ALL hotels (very large, may time out — increase timeout in config.yaml)"
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filters = '';
        if ($city = $input->getOption('city')) {
            $filters .= sprintf('<city>%s</city>', htmlspecialchars((string) $city));
        }
        if ($country = $input->getOption('country')) {
            $filters .= sprintf('<country>%s</country>', htmlspecialchars((string) $country));
        }

        $body = sprintf('<return><filters>%s</filters></return>', $filters);
        return $this->executeXml('getallhotels', $body, $output);
    }
}
