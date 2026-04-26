<?php

declare(strict_types=1);

namespace Dotw\Cli\Command;

use Dotw\Cli\Dotw\Client;
use Dotw\Cli\Dotw\RequestBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * dotw:cancel:execute <booking_ref> --penalty=<amount>
 *
 * Step 2 of 2-step cancellation. Executes the cancellation.
 *
 * Multi-room (CERT-13): loops one cancelbooking confirm=yes per code.
 * Per-code penalty is distributed evenly if multiple rooms
 * ($perCallPenalty = $penaltyAmount / count($bookingRefs) — per CERT-13 plan 27-04).
 * If any single code fails, logs the partial failure and continues
 * (same handling as CancellationService::executeConfirmedCancellation).
 *
 * On success:
 *  - Updates booking status to 'cancelled' in SQLite
 *  - Writes refund accounting_entry rows (penalty expense + cost reversal + refund credit)
 */
#[AsCommand(
    name: 'dotw:cancel:execute',
    description: 'Execute booking cancellation (step 2 — run after cancel:preview)',
)]
class CancelExecuteCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('booking_ref', InputArgument::REQUIRED, 'DOTW booking reference')
            ->addOption('penalty', null, InputOption::VALUE_REQUIRED, 'Total penalty amount (from cancel:preview output)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $bookingRef   = $input->getArgument('booking_ref');
        $totalPenalty = (float) $input->getOption('penalty');

        $booking = $this->db->pdo()
            ->query('SELECT * FROM bookings WHERE booking_ref = ' . $this->db->pdo()->quote($bookingRef))
            ->fetch();

        if (!$booking) {
            return $this->fail($output, 'DOTW_E_BOOKING_NOT_FOUND', "Booking not found: {$bookingRef}", self::EXIT_INPUT);
        }

        if ($booking['status'] === 'cancelled') {
            return $this->fail($output, 'DOTW_E_ALREADY_CANCELLED', 'Booking is already cancelled', self::EXIT_INPUT);
        }

        // CERT-13: split comma-separated codes
        $codes     = array_filter(array_map('trim', explode(',', $booking['booking_ref'])));
        $codeCount = count($codes);

        // Even split per CERT-13 pattern: $perCallPenalty = $penaltyAmount / count($bookingRefs)
        $perCodePenalty = $codeCount > 0 ? round($totalPenalty / $codeCount, 3) : 0.0;

        $client         = new Client($this->config->all());
        $cancelledCodes = [];
        $failedCodes    = [];

        foreach ($codes as $code) {
            try {
                $bodyXml = RequestBuilder::cancelBooking($code, confirm: true, penaltyApplied: $perCodePenalty);
                $xml     = $client->send('cancelbooking', $bodyXml);
                $client->assertSuccessful($xml);
                $cancelledCodes[] = $code;
            } catch (\RuntimeException $e) {
                // Log partial failure — continue per CERT-13 pattern
                $errOutput = $output instanceof \Symfony\Component\Console\Output\ConsoleOutputInterface
                    ? $output->getErrorOutput()
                    : $output;
                $errOutput->writeln("[DOTW_E_API] Cancel failed for code {$code}: " . $e->getMessage());
                $failedCodes[] = ['code' => $code, 'error' => $e->getMessage()];
            }
        }

        if (empty($cancelledCodes)) {
            return $this->fail($output, 'DOTW_E_API', 'All cancel calls failed — booking may still be active', self::EXIT_DOTW_API);
        }

        // Mark booking cancelled in SQLite (even on partial failure, reflect reality)
        $this->db->pdo()->exec(
            'UPDATE bookings SET status = \'cancelled\', updated_at = \'' . date('Y-m-d H:i:s') . '\' '
            . 'WHERE booking_ref = ' . $this->db->pdo()->quote($bookingRef)
        );

        // Accounting: refund journal entries
        $currency  = $booking['currency'];
        $totalFare = (float) $booking['total_fare'];
        $refund    = round($totalFare - $totalPenalty, 3);

        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO accounting_entries
                (booking_ref, entry_type, account_code, account_name, debit, credit, currency, description)
             VALUES (:booking_ref, :entry_type, :account_code, :account_name, :debit, :credit, :currency, :description)'
        );

        if ($totalPenalty > 0) {
            // Debit Cancellation Penalty Expense
            $stmt->execute([
                'booking_ref'  => $bookingRef,
                'entry_type'   => 'cancel',
                'account_code' => '5200',
                'account_name' => 'Cancellation Penalty',
                'debit'        => $totalPenalty,
                'credit'       => 0,
                'currency'     => $currency,
                'description'  => "Cancel {$bookingRef} penalty",
            ]);
        }

        if ($refund > 0) {
            // Credit Refund to B2B Credit Line or B2C Refund Receivable
            $refundAccount = $booking['track'] === 'b2b'
                ? ['4100', 'B2B Credit Line Refund']
                : ['4300', 'B2C Refund Receivable'];
            $stmt->execute([
                'booking_ref'  => $bookingRef,
                'entry_type'   => 'cancel',
                'account_code' => $refundAccount[0],
                'account_name' => $refundAccount[1],
                'debit'        => 0,
                'credit'       => $refund,
                'currency'     => $currency,
                'description'  => "Cancel {$bookingRef} refund",
            ]);
        }

        // Reverse original cost entry (credit Hotel Booking Cost)
        $stmt->execute([
            'booking_ref'  => $bookingRef,
            'entry_type'   => 'cancel',
            'account_code' => '5100',
            'account_name' => 'Hotel Booking Cost',
            'debit'        => 0,
            'credit'       => $totalFare,
            'currency'     => $currency,
            'description'  => "Cancel {$bookingRef} cost reversal",
        ]);

        $result = [
            'booking_ref'     => $bookingRef,
            'status'          => 'cancelled',
            'cancelled_codes' => $cancelledCodes,
            'failed_codes'    => $failedCodes,
            'total_penalty'   => $totalPenalty,
            'refund_amount'   => $refund,
            'currency'        => $currency,
        ];

        if ($this->jsonMode) {
            return $this->success($output, $result);
        }

        $output->writeln('<info>Cancellation Executed</info>');
        $output->writeln("Booking Ref    : {$bookingRef}");
        $output->writeln("Cancelled Codes: " . implode(', ', $cancelledCodes));
        if (!empty($failedCodes)) {
            $output->writeln('<comment>Partial failure — codes not cancelled: ' . implode(', ', array_column($failedCodes, 'code')) . '</comment>');
        }
        $output->writeln(sprintf('Penalty        : %s %s', $currency, number_format($totalPenalty, 3)));
        $output->writeln(sprintf('Refund Due     : %s %s', $currency, number_format($refund, 3)));

        return self::EXIT_SUCCESS;
    }
}
