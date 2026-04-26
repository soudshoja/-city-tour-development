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
 * dotw:prebook <hotel_id> <option_index>
 *
 * Two-step rate-lock: browse getRooms (no blocking) to list options, then block
 * the selected option_index. Persists prebook_key + 3min expiry to SQLite.
 *
 * option_index is 0-based index into the rooms array returned by dotw:rooms:browse.
 */
#[AsCommand(name: 'dotw:prebook', description: 'Lock a rate for 3 minutes (dual getRooms pattern)')]
class PrebookCommand extends AbstractCommand
{

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Lock a rate for 3 minutes (dual getRooms pattern)')
            ->addArgument('hotel_id',     InputArgument::REQUIRED, 'DOTW hotel product ID')
            ->addArgument('option_index', InputArgument::REQUIRED, 'Zero-based index from dotw:rooms:browse output')
            ->addOption('from',     null, InputOption::VALUE_REQUIRED, 'Check-in date (YYYY-MM-DD)')
            ->addOption('to',       null, InputOption::VALUE_REQUIRED, 'Check-out date (YYYY-MM-DD)')
            ->addOption('adults',   null, InputOption::VALUE_REQUIRED, 'Number of adults', 2)
            ->addOption('children', null, InputOption::VALUE_REQUIRED, 'Number of children', 0)
            ->addOption('currency', null, InputOption::VALUE_REQUIRED, 'Currency code (DOTW numeric)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hotelId     = $input->getArgument('hotel_id');
        $optionIndex = (int) $input->getArgument('option_index');
        $from        = $input->getOption('from');
        $to          = $input->getOption('to');

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
            $client = new Client($this->config->all());

            // Step 1: Browse pass (no blocking)
            if (!$this->jsonMode) {
                $output->writeln('<comment>Step 1/2: Browsing rooms...</comment>');
            }
            $browseXml = $client->send('getrooms', RequestBuilder::getRoomsBrowse($params));
            $client->assertSuccessful($browseXml);
            $rooms = ResponseParser::rooms($browseXml);

            if (empty($rooms)) {
                return $this->fail($output, 'DOTW_E_NO_INVENTORY', 'No rooms available for this hotel and dates', self::EXIT_DOTW_API);
            }

            if (!isset($rooms[$optionIndex])) {
                return $this->fail(
                    $output,
                    'DOTW_E_INPUT',
                    "Option index {$optionIndex} out of range (0-" . (count($rooms) - 1) . ')',
                    self::EXIT_INPUT
                );
            }

            $selected = $rooms[$optionIndex];

            // Step 2: Block pass (blocking=true) — locks rate for 3 minutes
            if (!$this->jsonMode) {
                $output->writeln('<comment>Step 2/2: Blocking rate...</comment>');
            }
            $blockParams = array_merge($params, [
                'roomTypeCode'      => $selected['room_type_code'],
                'selectedRateBasis' => $selected['rate_basis_id'],
                'allocationDetails' => $selected['allocation_details'],
            ]);
            $blockXml     = $client->send('getrooms', RequestBuilder::getRoomsBlock($blockParams));
            $client->assertSuccessful($blockXml);
            $blockedRooms = ResponseParser::rooms($blockXml);
            $blockedRoom  = $blockedRooms[0] ?? $selected;

            // Verify the block succeeded by checking allocationDetails is populated
            if (empty($blockedRoom['allocation_details'])) {
                return $this->fail($output, 'DOTW_E_BLOCK_FAILED', 'Rate block did not return allocationDetails', self::EXIT_DOTW_API);
            }
        } catch (\RuntimeException $e) {
            return $this->fail($output, 'DOTW_E_API', $e->getMessage(), self::EXIT_DOTW_API);
        } catch (\Throwable $e) {
            return $this->fail($output, 'DOTW_E_INTERNAL', $e->getMessage(), self::EXIT_INTERNAL);
        }

        // Persist to SQLite
        $prebookKey    = 'PB-' . strtoupper(bin2hex(random_bytes(8)));
        $expiresAt     = date('Y-m-d H:i:s', strtotime('+3 minutes'));
        $hotelName     = (string) ($blockXml->hotel['name'] ?? '');
        $markupPercent = (float) $this->config->get('markup_percent', 0);
        $rawFare       = $blockedRoom['total_fare'];
        $markupFare    = round($rawFare * (1 + $markupPercent / 100), 3);

        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO prebooks
                (prebook_key, hotel_id, hotel_name, check_in, check_out,
                 room_type_code, rate_basis, allocation_details, currency,
                 total_fare, markup_fare, raw_rooms_json, expires_at)
             VALUES
                (:prebook_key, :hotel_id, :hotel_name, :check_in, :check_out,
                 :room_type_code, :rate_basis, :allocation_details, :currency,
                 :total_fare, :markup_fare, :raw_rooms_json, :expires_at)'
        );
        $stmt->execute([
            'prebook_key'        => $prebookKey,
            'hotel_id'           => $hotelId,
            'hotel_name'         => $hotelName,
            'check_in'           => $from,
            'check_out'          => $to,
            'room_type_code'     => $blockedRoom['room_type_code'],
            'rate_basis'         => $blockedRoom['rate_basis_id'],
            'allocation_details' => $blockedRoom['allocation_details'],
            'currency'           => $blockedRoom['currency'],
            'total_fare'         => $rawFare,
            'markup_fare'        => $markupFare,
            'raw_rooms_json'     => json_encode($blockedRoom),
            'expires_at'         => $expiresAt,
        ]);

        $record = [
            'prebook_key'        => $prebookKey,
            'hotel_id'           => $hotelId,
            'hotel_name'         => $hotelName,
            'check_in'           => $from,
            'check_out'          => $to,
            'room_name'          => $blockedRoom['room_name'],
            'rate_basis'         => $blockedRoom['rate_basis_name'],
            'allocation_details' => $blockedRoom['allocation_details'],
            'currency'           => $blockedRoom['currency'],
            'total_fare'         => $rawFare,
            'markup_fare'        => $markupFare,
            'expires_at'         => $expiresAt,
        ];

        if ($this->jsonMode) {
            return $this->success($output, $record);
        }

        $output->writeln('<info>Rate locked successfully!</info>');
        $output->writeln("Prebook Key : {$prebookKey}");
        $output->writeln("Hotel       : {$hotelName} (ID: {$hotelId})");
        $output->writeln("Room        : {$blockedRoom['room_name']} — {$blockedRoom['rate_basis_name']}");
        $output->writeln("Fare        : {$blockedRoom['currency']} " . number_format($rawFare, 3));
        $output->writeln("Expires At  : {$expiresAt}");
        $output->writeln('<comment>Use this prebook_key with dotw:confirm</comment>');
        return self::EXIT_SUCCESS;
    }
}
