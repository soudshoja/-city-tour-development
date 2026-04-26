<?php
declare(strict_types=1);
namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:search-hotels', description: 'Search available hotels by city and dates')]
class SearchHotelsCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('city',        null, InputOption::VALUE_REQUIRED, 'DOTW city code (from xml:get-all-cities)', null)
            ->addOption('hotel',       null, InputOption::VALUE_REQUIRED, 'DOTW hotel ID — search a specific hotel instead of full city', null)
            ->addOption('from',        null, InputOption::VALUE_REQUIRED, 'Check-in date YYYY-MM-DD')
            ->addOption('to',          null, InputOption::VALUE_REQUIRED, 'Check-out date YYYY-MM-DD')
            ->addOption('adults',      null, InputOption::VALUE_REQUIRED, 'Number of adults', 2)
            ->addOption('children',    null, InputOption::VALUE_REQUIRED, 'Number of children (ages assumed 10)', 0)
            ->addOption('currency',    null, InputOption::VALUE_REQUIRED, 'DOTW currency code (default from config)', null)
            ->addOption('nationality', null, InputOption::VALUE_REQUIRED, 'DOTW nationality code (default from config)', null)
            ->addOption('residence',   null, InputOption::VALUE_REQUIRED, 'DOTW residence code (default from config)', null)
            ->addOption('rate-basis',  null, InputOption::VALUE_REQUIRED, 'Rate basis code (-1 = all)', -1)
            ->setHelp(
                "Search hotels by city or hotel ID.\n\n" .
                "Examples:\n" .
                "  bin/dotw-xml xml:search-hotels --city=364 --from=2026-09-01 --to=2026-09-03 --adults=2\n" .
                "  bin/dotw-xml xml:search-hotels --hotel=12345 --from=2026-09-01 --to=2026-09-03 --json\n\n" .
                "City codes: use xml:get-all-cities to find codes."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $from = $input->getOption('from');
        $to   = $input->getOption('to');

        if (!$from || !$to) {
            return $this->writeInputError($output, '--from and --to are required');
        }

        $adults      = (int) $input->getOption('adults');
        $children    = (int) $input->getOption('children');
        $currency    = (int) ($input->getOption('currency')    ?? $this->config->get('currency', 769));
        $nationality = (int) ($input->getOption('nationality') ?? $this->config->get('nationality', 66));
        $residence   = (int) ($input->getOption('residence')   ?? $this->config->get('residence', 66));
        $rateBasis   = (int) ($input->getOption('rate-basis')  ?? -1);
        $city        = $input->getOption('city');
        $hotel       = $input->getOption('hotel');

        if (!$city && !$hotel) {
            return $this->writeInputError($output, 'Either --city or --hotel is required');
        }

        $childrenXml = $children > 0
            ? sprintf('<children no="%d"><child runno="0">10</child></children>', $children)
            : '<children no="0"></children>';

        $filterXml = $city
            ? sprintf('<city>%d</city>', (int) $city)
            : sprintf(
                '<c:condition xmlns:a="http://us.dotwconnect.com/xsd/atomicCondition" xmlns:c="http://us.dotwconnect.com/xsd/complexCondition"><a:condition><fieldName>hotelId</fieldName><fieldTest>in</fieldTest><fieldValues><fieldValue>%s</fieldValue></fieldValues></a:condition></c:condition>',
                htmlspecialchars((string) $hotel)
            );

        $body = sprintf(
            '<bookingDetails>
    <fromDate>%s</fromDate>
    <toDate>%s</toDate>
    <currency>%d</currency>
    <rooms no="1">
        <room runno="0">
            <adultsCode>%d</adultsCode>
            %s
            <rateBasis>%d</rateBasis>
            <passengerNationality>%d</passengerNationality>
            <passengerCountryOfResidence>%d</passengerCountryOfResidence>
        </room>
    </rooms>
</bookingDetails>
<return>
    <filters>%s</filters>
</return>',
            htmlspecialchars((string) $from),
            htmlspecialchars((string) $to),
            $currency,
            $adults,
            $childrenXml,
            $rateBasis,
            $nationality,
            $residence,
            $filterXml
        );

        return $this->executeXml('searchhotels', $body, $output);
    }
}