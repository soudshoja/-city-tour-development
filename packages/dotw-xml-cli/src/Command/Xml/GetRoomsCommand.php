<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:get-rooms', description: 'Get room details for a hotel (browse or blocking mode)')]
final class GetRoomsCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('hotel',       null, InputOption::VALUE_REQUIRED, 'DOTW hotel ID (productId)')
            ->addOption('from',        null, InputOption::VALUE_REQUIRED, 'Check-in date YYYY-MM-DD')
            ->addOption('to',          null, InputOption::VALUE_REQUIRED, 'Check-out date YYYY-MM-DD')
            ->addOption('adults',      null, InputOption::VALUE_REQUIRED, 'Number of adults', 2)
            ->addOption('children',    null, InputOption::VALUE_REQUIRED, 'Number of children', 0)
            ->addOption('currency',    null, InputOption::VALUE_REQUIRED, 'DOTW currency code', null)
            ->addOption('nationality', null, InputOption::VALUE_REQUIRED, 'DOTW nationality code', null)
            ->addOption('residence',   null, InputOption::VALUE_REQUIRED, 'DOTW residence code', null)
            ->addOption('rate-basis',  null, InputOption::VALUE_REQUIRED, 'Rate basis code (-1 = all)', -1)
            ->addOption('block',       null, InputOption::VALUE_NONE,     'Enable blocking mode (lock a specific room + rate)')
            ->addOption('room-type',   null, InputOption::VALUE_REQUIRED, '[--block] Room type code from browse response', null)
            ->addOption('rate',        null, InputOption::VALUE_REQUIRED, '[--block] selectedRateBasis runno from browse response', null)
            ->addOption('allocation',  null, InputOption::VALUE_REQUIRED, '[--block] allocationDetails string from browse response', null)
            ->setHelp(
                "Get rooms for a hotel.\n\n" .
                "Browse mode (default — shows available rooms and rates):\n" .
                "  bin/dotw-xml xml:get-rooms --hotel=12345 --from=2026-09-01 --to=2026-09-03\n\n" .
                "Blocking mode (lock a specific rate before confirmbooking):\n" .
                "  bin/dotw-xml xml:get-rooms --hotel=12345 --from=2026-09-01 --to=2026-09-03 \\n" .
                "    --block --room-type=ROOMCODE --rate=0 --allocation='ALLOC_STRING'\n\n" .
                "After blocking, check <status>checked</status> — if not 'checked', rate is unavailable."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hotel = $input->getOption('hotel');
        $from  = $input->getOption('from');
        $to    = $input->getOption('to');

        if (!$hotel || !$from || !$to) {
            $output->getErrorOutput()->writeln('[INPUT_ERROR] --hotel, --from, and --to are required');
            return self::EXIT_INPUT;
        }

        $adults      = (int) ($input->getOption('adults') ?? 2);
        $children    = (int) ($input->getOption('children') ?? 0);
        $currency    = (int) ($input->getOption('currency')    ?? $this->config->get('currency', 769));
        $nationality = (int) ($input->getOption('nationality') ?? $this->config->get('nationality', 66));
        $residence   = (int) ($input->getOption('residence')   ?? $this->config->get('residence', 66));
        $rateBasis   = (int) ($input->getOption('rate-basis')  ?? -1);
        $block       = (bool) $input->getOption('block');

        $childrenXml = $children > 0
            ? sprintf('<children no="%d"><child runno="0">10</child></children>', $children)
            : '<children no="0"></children>';

        if ($block) {
            $roomType   = $input->getOption('room-type');
            $rate       = $input->getOption('rate');
            $allocation = $input->getOption('allocation');

            if ($roomType === null || $rate === null || $allocation === null) {
                $output->getErrorOutput()->writeln('[INPUT_ERROR] --block requires --room-type, --rate, and --allocation');
                return self::EXIT_INPUT;
            }

            $roomTypeSelectedXml = sprintf(
                '<roomTypeSelected><code>%s</code><selectedRateBasis>%d</selectedRateBasis><allocationDetails>%s</allocationDetails></roomTypeSelected>',
                htmlspecialchars((string) $roomType),
                (int) $rate,
                htmlspecialchars((string) $allocation)
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
            %s
        </room>
    </rooms>
    <productId>%s</productId>
</bookingDetails>
<return></return>',
                htmlspecialchars((string) $from),
                htmlspecialchars((string) $to),
                $currency,
                $adults,
                $childrenXml,
                $rateBasis,
                $nationality,
                $residence,
                $roomTypeSelectedXml,
                htmlspecialchars((string) $hotel)
            );
        } else {
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
    <productId>%s</productId>
</bookingDetails>
<return>
    <fields>
        <roomField>cancellation</roomField>
        <roomField>name</roomField>
        <roomField>tariffNotes</roomField>
    </fields>
</return>',
                htmlspecialchars((string) $from),
                htmlspecialchars((string) $to),
                $currency,
                $adults,
                $childrenXml,
                $rateBasis,
                $nationality,
                $residence,
                htmlspecialchars((string) $hotel)
            );
        }

        return $this->executeXml('getrooms', $body, $output);
    }
}
