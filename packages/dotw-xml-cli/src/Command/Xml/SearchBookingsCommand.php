<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:search-bookings', description: 'Search booking history by date range or reference')]
final class SearchBookingsCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('from',    null, InputOption::VALUE_REQUIRED, 'Start date YYYY-MM-DD', null)
            ->addOption('to',      null, InputOption::VALUE_REQUIRED, 'End date YYYY-MM-DD', null)
            ->addOption('booking', null, InputOption::VALUE_REQUIRED, 'Filter by booking reference', null)
            ->addOption('status',  null, InputOption::VALUE_REQUIRED, 'Filter by status (confirmed/cancelled)', null)
            ->setHelp(
                "Search booking history.\n\n" .
                "  bin/dotw-xml xml:search-bookings --from=2026-01-01 --to=2026-12-31\n" .
                "  bin/dotw-xml xml:search-bookings --booking=REF123"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $from    = $input->getOption('from');
        $to      = $input->getOption('to');
        $booking = $input->getOption('booking');
        $status  = $input->getOption('status');

        $filters = '';
        if ($from) {
            $filters .= sprintf('<fromDate>%s</fromDate>', htmlspecialchars((string) $from));
        }
        if ($to) {
            $filters .= sprintf('<toDate>%s</toDate>', htmlspecialchars((string) $to));
        }
        if ($booking) {
            $filters .= sprintf('<bookingCode>%s</bookingCode>', htmlspecialchars((string) $booking));
        }
        if ($status) {
            $filters .= sprintf('<status>%s</status>', htmlspecialchars((string) $status));
        }

        $body = sprintf('<bookingDetails>%s</bookingDetails>', $filters);
        return $this->executeXml('searchbookings', $body, $output);
    }
}
