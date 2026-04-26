<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:delete-itinerary', description: 'Delete a sandbox itinerary (deleteitinerary)')]
final class DeleteItineraryCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('itinerary', null, InputOption::VALUE_REQUIRED, 'Itinerary code to delete')
            ->setHelp(
                "Delete an itinerary. Check <productsLeftOnItinerary> in response — if > 0, not all services were cancelled.\n\n" .
                "  bin/dotw-xml xml:delete-itinerary --itinerary=ITIN123"
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

        return $this->executeXml('deleteitinerary', $body, $output);
    }
}
