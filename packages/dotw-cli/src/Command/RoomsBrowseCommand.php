<?php

declare(strict_types=1);

namespace Dotw\Cli\Command;

use Dotw\Cli\Dotw\Client;
use Dotw\Cli\Dotw\RequestBuilder;
use Dotw\Cli\Dotw\ResponseParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * dotw:rooms:browse <hotel_id> — show available rooms and rates (browse pass only, no blocking).
 *
 * Returns indexed room options suitable for passing as <option_index> to dotw:prebook.
 */
#[AsCommand(name: 'dotw:rooms:browse', description: 'Browse available room rates for a hotel (no rate lock)')]
class RoomsBrowseCommand extends AbstractCommand
{

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Browse available room rates for a hotel (no rate lock)')
            ->addArgument('hotel_id', InputArgument::REQUIRED, 'DOTW hotel product ID')
            ->addOption('from',     null, InputOption::VALUE_REQUIRED, 'Check-in date (YYYY-MM-DD)')
            ->addOption('to',       null, InputOption::VALUE_REQUIRED, 'Check-out date (YYYY-MM-DD)')
            ->addOption('adults',   null, InputOption::VALUE_REQUIRED, 'Number of adults', 2)
            ->addOption('children', null, InputOption::VALUE_REQUIRED, 'Number of children', 0)
            ->addOption('currency', null, InputOption::VALUE_REQUIRED, 'Currency code (DOTW numeric)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hotelId = $input->getArgument('hotel_id');
        $from    = $input->getOption('from');
        $to      = $input->getOption('to');

        if (!$from || !$to) {
            return $this->fail($output, 'DOTW_E_INPUT', '--from and --to are required', self::EXIT_INPUT);
        }

        $params = [
            'hotelId'     => $hotelId,
            'fromDate'    => $from,
            'toDate'      => $to,
            'currency'    => (int) ($input->getOption('currency') ?? $this->config->get('currency', 769)),
            'adults'      => (int) $input->getOption('adults'),
            'children'    => (int) $input->getOption('children'),
            'nationality' => (int) $this->config->get('nationality', 66),
            'residence'   => (int) $this->config->get('residence', 66),
            'rateBasis'   => -1,
        ];

        try {
            $client  = new Client($this->config->all());
            $bodyXml = RequestBuilder::getRoomsBrowse($params);
            $xml     = $client->send('getrooms', $bodyXml);
            $client->assertSuccessful($xml);
            $rooms   = ResponseParser::rooms($xml);
        } catch (\RuntimeException $e) {
            return $this->fail($output, 'DOTW_E_API', $e->getMessage(), self::EXIT_DOTW_API);
        } catch (\Throwable $e) {
            return $this->fail($output, 'DOTW_E_INTERNAL', $e->getMessage(), self::EXIT_INTERNAL);
        }

        if ($this->jsonMode) {
            return $this->success($output, ['hotel_id' => $hotelId, 'rooms' => $rooms]);
        }

        $output->writeln(sprintf('<info>Hotel %s — %d rate option(s)</info>', $hotelId, count($rooms)));
        foreach ($rooms as $i => $r) {
            $output->writeln(sprintf(
                '  [%d] %s — %s — %s %s',
                $i,
                $r['room_name'],
                $r['rate_basis_name'],
                $r['currency'],
                number_format($r['total_fare'], 3)
            ));
        }
        return self::EXIT_SUCCESS;
    }
}
