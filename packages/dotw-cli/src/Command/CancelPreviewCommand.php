<?php

declare(strict_types=1);

namespace Dotw\Cli\Command;

use Dotw\Cli\Dotw\Client;
use Dotw\Cli\Dotw\RequestBuilder;
use Dotw\Cli\Dotw\ResponseParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * dotw:cancel:preview <booking_ref>
 *
 * Step 1 of 2-step cancellation.
 * Calls DOTW cancelbooking with confirm=no for each booking code.
 *
 * Multi-room (CERT-13): if booking_ref contains comma-separated codes
 * (e.g. "953106803,953106804"), fires a separate API call per code and
 * aggregates total charge + refund. Mirrors CancellationService::executePreviewCancellation().
 *
 * Does NOT modify the booking status (preview only).
 */
#[AsCommand(
    name: 'dotw:cancel:preview',
    description: 'Preview cancellation penalty for a booking (no cancellation executed)',
)]
class CancelPreviewCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('booking_ref', InputArgument::REQUIRED, 'DOTW booking reference from dotw:confirm');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $bookingRef = $input->getArgument('booking_ref');

        // Load booking
        $booking = $this->db->pdo()
            ->query('SELECT * FROM bookings WHERE booking_ref = ' . $this->db->pdo()->quote($bookingRef))
            ->fetch();

        if (!$booking) {
            return $this->fail($output, 'DOTW_E_BOOKING_NOT_FOUND', "Booking not found: {$bookingRef}", self::EXIT_INPUT);
        }

        if ($booking['status'] === 'cancelled') {
            return $this->fail($output, 'DOTW_E_ALREADY_CANCELLED', 'Booking is already cancelled', self::EXIT_INPUT);
        }

        // CERT-13: split on comma for multi-room bookings
        $codes = array_filter(array_map('trim', explode(',', $booking['booking_ref'])));

        $totalCharge    = 0.0;
        $perCodeResults = [];
        $currency       = $booking['currency'];

        $client = new Client($this->config->all());

        foreach ($codes as $code) {
            try {
                $bodyXml = RequestBuilder::cancelBooking($code, confirm: false);
                $xml     = $client->send('cancelbooking', $bodyXml);
                $client->assertSuccessful($xml);
                $parsed  = ResponseParser::cancelPreview($xml);
                $first   = $parsed[0] ?? ['charge' => 0.0, 'currency' => $currency];

                $charge           = (float) $first['charge'];
                $totalCharge     += $charge;
                $perCodeResults[] = [
                    'booking_code' => $code,
                    'charge'       => $charge,
                    'currency'     => $first['currency'] ?? $currency,
                ];
            } catch (\RuntimeException $e) {
                return $this->fail(
                    $output,
                    'DOTW_E_API',
                    "Cancel preview failed for code {$code}: " . $e->getMessage(),
                    self::EXIT_DOTW_API
                );
            } catch (\Throwable $e) {
                return $this->fail($output, 'DOTW_E_INTERNAL', $e->getMessage(), self::EXIT_INTERNAL);
            }
        }

        $result = [
            'booking_ref'      => $bookingRef,
            'hotel_name'       => $booking['hotel_name'],
            'total_charge'     => $totalCharge,
            'currency'         => $currency,
            'per_code_results' => $perCodeResults,
            'rooms_count'      => count($codes),
        ];

        if ($this->jsonMode) {
            return $this->success($output, $result);
        }

        $output->writeln("<info>Cancellation Preview — {$booking['hotel_name']}</info>");
        $output->writeln("Booking Ref  : {$bookingRef}");
        $output->writeln("Rooms        : " . count($codes));
        foreach ($perCodeResults as $r) {
            $output->writeln(sprintf('  Code %s — Charge: %s %s', $r['booking_code'], $r['currency'], number_format($r['charge'], 3)));
        }
        $output->writeln('─────────────────────────────');
        $output->writeln(sprintf('Total Penalty: %s %s', $currency, number_format($totalCharge, 3)));

        if ($totalCharge == 0.0) {
            $output->writeln('<info>Free cancellation — no penalty</info>');
        } else {
            $output->writeln('<comment>Penalty applies. Confirm with: bin/dotw dotw:cancel:execute ' . $bookingRef . ' --penalty=' . $totalCharge . '</comment>');
        }

        return self::EXIT_SUCCESS;
    }
}
