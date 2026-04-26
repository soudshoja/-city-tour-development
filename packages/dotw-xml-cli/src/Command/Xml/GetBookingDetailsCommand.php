<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:get-booking-details', description: 'Retrieve full details for a booking (voucher lookup)')]
final class GetBookingDetailsCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('booking', null, InputOption::VALUE_REQUIRED, 'DOTW booking reference code')
            ->setHelp(
                "Get full booking details including hotel name, dates, passengers, status, paymentGuaranteedBy.\n\n" .
                "  bin/dotw-xml xml:get-booking-details --booking=REF123 --json"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $booking = $input->getOption('booking');
        if (!$booking) {
            $output->getErrorOutput()->writeln('[INPUT_ERROR] --booking is required');
            return self::EXIT_INPUT;
        }

        $body = sprintf(
            '<bookingDetails><bookingCode>%s</bookingCode></bookingDetails>',
            htmlspecialchars((string) $booking)
        );

        return $this->executeXml('getbookingdetails', $body, $output);
    }
}
