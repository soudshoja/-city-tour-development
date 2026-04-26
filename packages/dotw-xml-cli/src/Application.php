<?php

declare(strict_types=1);

namespace Dotw\XmlCli;

use Dotw\XmlCli\Command\Xml\BookItineraryCommand;
use Dotw\XmlCli\Command\Xml\CancelBookingCommand;
use Dotw\XmlCli\Command\Xml\ConfirmBookingCommand;
use Dotw\XmlCli\Command\Xml\DeleteItineraryCommand;
use Dotw\XmlCli\Command\Xml\GetAllCitiesCommand;
use Dotw\XmlCli\Command\Xml\GetAllCountriesCommand;
use Dotw\XmlCli\Command\Xml\GetAllHotelsCommand;
use Dotw\XmlCli\Command\Xml\GetBookingDetailsCommand;
use Dotw\XmlCli\Command\Xml\GetRoomsCommand;
use Dotw\XmlCli\Command\Xml\GetSalutationsIdsCommand;
use Dotw\XmlCli\Command\Xml\RawCommand;
use Dotw\XmlCli\Command\Xml\SearchBookingsCommand;
use Dotw\XmlCli\Command\Xml\SearchHotelsCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('dotw-xml-cli', '1.0.0');

        $this->addCommands([
            new GetAllCountriesCommand(),
            new GetAllCitiesCommand(),
            new GetAllHotelsCommand(),
            new GetSalutationsIdsCommand(),
            new SearchHotelsCommand(),
            new GetRoomsCommand(),
            new ConfirmBookingCommand(),
            new CancelBookingCommand(),
            new GetBookingDetailsCommand(),
            new SearchBookingsCommand(),
            new DeleteItineraryCommand(),
            new BookItineraryCommand(),
            new RawCommand(),
        ]);
    }
}
