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
 * dotw:hotels:show <hotel_id> — get hotel details and amenities.
 *
 * Uses getrooms (browse pass) with a specified or default date window to retrieve
 * hotel-level data. DOTW does not provide a dedicated static hotel-details endpoint;
 * a short browse getRooms returns hotel metadata alongside available rooms.
 */
#[AsCommand(name: 'dotw:hotels:show', description: 'Show hotel details and available room rates')]
class HotelsShowCommand extends AbstractCommand
{

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Show hotel details and available room rates')
            ->addArgument('hotel_id', InputArgument::REQUIRED, 'DOTW hotel product ID')
            ->addOption('from',     null, InputOption::VALUE_REQUIRED, 'Check-in date (YYYY-MM-DD)')
            ->addOption('to',       null, InputOption::VALUE_REQUIRED, 'Check-out date (YYYY-MM-DD)')
            ->addOption('adults',   null, InputOption::VALUE_REQUIRED, 'Number of adults', 2)
            ->addOption('currency', null, InputOption::VALUE_REQUIRED, 'Currency code (DOTW numeric)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hotelId = $input->getArgument('hotel_id');
        $from    = $input->getOption('from') ?? date('Y-m-d', strtotime('+7 days'));
        $to      = $input->getOption('to')   ?? date('Y-m-d', strtotime('+8 days'));

        $params = [
            'hotelId'     => $hotelId,
            'fromDate'    => $from,
            'toDate'      => $to,
            'currency'    => (int) ($input->getOption('currency') ?? $this->config->get('currency', 769)),
            'adults'      => (int) $input->getOption('adults'),
            'children'    => 0,
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

            // V4 getrooms response: <hotel id="..." name="...">
            $hotelName = (string) ($xml->hotel['name'] ?? $hotelId);
            $amenities = [];
            foreach ($xml->hotel->amenities->amenity ?? [] as $a) {
                $amenities[] = (string) $a;
            }
        } catch (\RuntimeException $e) {
            return $this->fail($output, 'DOTW_E_API', $e->getMessage(), self::EXIT_DOTW_API);
        } catch (\Throwable $e) {
            return $this->fail($output, 'DOTW_E_INTERNAL', $e->getMessage(), self::EXIT_INTERNAL);
        }

        $data = [
            'hotel_id'   => $hotelId,
            'hotel_name' => $hotelName,
            'amenities'  => $amenities,
            'rooms'      => $rooms,
            'check_in'   => $from,
            'check_out'  => $to,
        ];

        if ($this->jsonMode) {
            return $this->success($output, $data);
        }

        $output->writeln("<info>{$hotelName} (ID: {$hotelId})</info>");
        $output->writeln('Amenities: ' . (empty($amenities) ? 'N/A' : implode(', ', $amenities)));
        $output->writeln(sprintf('Rooms available: %d rate option(s)', count($rooms)));
        return self::EXIT_SUCCESS;
    }
}
