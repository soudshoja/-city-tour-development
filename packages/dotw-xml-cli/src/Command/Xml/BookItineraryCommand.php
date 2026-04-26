<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:book-itinerary', description: 'Confirm all items saved in an itinerary (bookitinerary)')]
final class BookItineraryCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('itinerary', null, InputOption::VALUE_REQUIRED, 'Itinerary code from savebooking response')
            ->setHelp(
                "Confirm all items in a CC-flow itinerary.\n\n" .
                "  bin/dotw-xml xml:book-itinerary --itinerary=ITIN123"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $itinerary = $input->getOption('itinerary');
        if (!$itinerary) {
            $output->getErrorOutput()->writeln('[INPUT_ERROR] --itinerary is required');
            return self::EXIT_INPUT;
        }

        $body = sprintf(
            '<bookingDetails><itineraryCode>%s</itineraryCode></bookingDetails>',
            htmlspecialchars((string) $itinerary)
        );

        return $this->executeXml('bookitinerary', $body, $output);
    }
}
