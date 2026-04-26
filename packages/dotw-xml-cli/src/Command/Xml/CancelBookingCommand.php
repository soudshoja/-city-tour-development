<?php
declare(strict_types=1);
namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:cancel-booking', description: 'Cancel a booking — preview charges (--confirm=no) or execute (--confirm=yes)')]
class CancelBookingCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('booking', null, InputOption::VALUE_REQUIRED, 'DOTW booking reference code')
            ->addOption('confirm', null, InputOption::VALUE_REQUIRED,
                '"no" = check charges only (safe); "yes" = execute cancellation', 'no')
            ->addOption('penalty', null, InputOption::VALUE_REQUIRED,
                'Penalty amount from step 1 (required when --confirm=yes)', null)
            ->setHelp(
                "Two-step cancellation:\n\n" .
                "Step 1 — check cancellation charges (safe, no side effects):\n" .
                "  bin/dotw-xml xml:cancel-booking --booking=REF123 --confirm=no\n\n" .
                "Step 2 — execute cancellation (irreversible):\n" .
                "  bin/dotw-xml xml:cancel-booking --booking=REF123 --confirm=yes --penalty=0\n\n" .
                "Use the <charge> value (NOT <formatted>) from step 1 as --penalty."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $booking = $input->getOption('booking');
        $confirm = strtolower((string) ($input->getOption('confirm') ?? 'no'));

        if (!$booking) {
            return $this->writeInputError($output, '--booking is required');
        }

        if (!in_array($confirm, ['yes', 'no'], true)) {
            return $this->writeInputError($output, '--confirm must be "yes" or "no"');
        }

        $penaltyLine = '';
        if ($confirm === 'yes') {
            $penalty = $input->getOption('penalty');
            if ($penalty === null) {
                return $this->writeInputError($output, '--penalty is required when --confirm=yes');
            }
            $penaltyLine = sprintf('<penaltyApplied>%s</penaltyApplied>', htmlspecialchars((string) $penalty));
        }

        $body = sprintf(
            '<bookingDetails>
    <bookingType>booking</bookingType>
    <bookingCode>%s</bookingCode>
    <confirm>%s</confirm>
    %s
</bookingDetails>',
            htmlspecialchars((string) $booking),
            $confirm,
            $penaltyLine
        );

        return $this->executeXml('cancelbooking', $body, $output);
    }
}