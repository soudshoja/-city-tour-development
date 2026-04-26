<?php

declare(strict_types=1);

namespace Dotw\Cli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * dotw:voucher <booking_ref> [--pdf]
 *
 * Generates a bilingual Arabic/English booking voucher.
 * Mirrors the 8-section format of MessageBuilderService::formatVoucherMessage().
 *
 * Text mode: prints to stdout.
 * PDF mode (--pdf): writes to ~/.dotw-cli/vouchers/{booking_ref}.pdf and prints path.
 */
#[AsCommand(name: 'dotw:voucher', description: 'Generate a bilingual hotel booking voucher')]
class VoucherCommand extends AbstractCommand
{
    private const DOUBLE_RULE = '══════════════════════════════';
    private const SEPARATOR   = '──────────────────────────────';

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Generate a bilingual hotel booking voucher')
            ->addArgument('booking_ref', InputArgument::REQUIRED, 'DOTW booking reference from dotw:confirm')
            ->addOption('pdf', null, InputOption::VALUE_NONE, 'Write PDF file instead of printing text')
            ->addOption('agency-name', null, InputOption::VALUE_REQUIRED, 'Agency name for voucher footer', 'City Travelers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $bookingRef = $input->getArgument('booking_ref');

        // Load booking from SQLite
        $booking = $this->db->pdo()
            ->query('SELECT * FROM bookings WHERE booking_ref = ' . $this->db->pdo()->quote($bookingRef))
            ->fetch();

        if (!$booking) {
            return $this->fail($output, 'DOTW_E_BOOKING_NOT_FOUND', "Booking not found: {$bookingRef}", self::EXIT_INPUT);
        }

        $guestDetails = json_decode($booking['guest_details_json'] ?? '[]', true) ?? [];
        $agencyName   = $input->getOption('agency-name');

        $voucherText = $this->buildVoucherText($booking, $guestDetails, $agencyName);

        if ($input->getOption('pdf')) {
            return $this->writePdf($output, $bookingRef, $voucherText);
        }

        if ($this->jsonMode) {
            return $this->success($output, [
                'booking_ref'  => $bookingRef,
                'voucher_text' => $voucherText,
                'hotel_name'   => $booking['hotel_name'],
                'check_in'     => $booking['check_in'],
                'check_out'    => $booking['check_out'],
                'currency'     => $booking['currency'],
                'total_fare'   => $booking['total_fare'],
            ]);
        }

        $output->writeln($voucherText);
        return self::EXIT_SUCCESS;
    }

    /**
     * Build the bilingual text voucher.
     * Mirrors MessageBuilderService::formatVoucherMessage() 8-section structure.
     *
     * Mandatory sections (DOTW cert requirement):
     *   1. Header / booking confirmation title
     *   2. Booking reference (EN + AR)
     *   3. Hotel name + check-in/out dates (EN + AR)
     *   4. Guest list
     *   5. Total fare + status (EN + AR)
     *   6. Payment guaranteed by
     *   7. Cancellation policy (with restricted cancel rules)
     *   8. Footer with agency name
     */
    private function buildVoucherText(array $booking, array $guestDetails, string $agencyName): string
    {
        $lines = [];

        // Section 1: Header
        $lines[] = 'BOOKING CONFIRMATION | تأكيد الحجز';
        $lines[] = self::DOUBLE_RULE;
        $lines[] = '';

        // Section 2: Booking Reference
        $ref     = $booking['booking_ref'];
        $lines[] = "Booking Reference: {$ref}";
        $lines[] = "الرقم المرجعي: {$ref}";
        $lines[] = self::SEPARATOR;
        $lines[] = '';

        // Section 3: Hotel and Dates
        $hotelName   = $booking['hotel_name'] ?? 'N/A';
        $checkIn     = $booking['check_in']   ?? 'N/A';
        $checkOut    = $booking['check_out']  ?? 'N/A';
        $ciFormatted = $checkIn  !== 'N/A' ? date('d M Y', strtotime($checkIn))  : 'N/A';
        $coFormatted = $checkOut !== 'N/A' ? date('d M Y', strtotime($checkOut)) : 'N/A';

        $lines[] = "Hotel | الفندق: {$hotelName}";
        $lines[] = "Check-in | تسجيل الدخول: {$ciFormatted}";
        $lines[] = "Check-out | تسجيل الخروج: {$coFormatted}";
        $lines[] = self::SEPARATOR;
        $lines[] = '';

        // Section 4: Guest list
        $lines[] = 'Guest(s) | الضيوف:';
        if (!empty($guestDetails)) {
            foreach ($guestDetails as $guest) {
                $sal   = $this->salutationLabel((int) ($guest['salutation'] ?? 0));
                $fname = $guest['first_name'] ?? '';
                $lname = $guest['last_name']  ?? '';
                $name  = trim("{$sal} {$fname} {$lname}");
                $lines[] = '- ' . ($name ?: 'Guest');
            }
        } else {
            $lines[] = '- Guest';
        }
        $lines[] = self::SEPARATOR;
        $lines[] = '';

        // Section 5: Price and Status
        $currency  = $booking['currency'] ?? 'KWD';
        $fareFloat = (float) ($booking['total_fare'] ?? 0);
        $fare      = number_format($fareFloat, 3);
        $lines[] = "Total | المجموع: {$currency} {$fare}";
        $lines[] = 'Status | الحالة: Confirmed | مؤكد';
        $lines[] = self::SEPARATOR;
        $lines[] = '';

        // Section 6: Payment Guaranteed By
        $lines[] = "Payment Guaranteed By: {$agencyName}";
        $lines[] = self::SEPARATOR;
        $lines[] = '';

        // Section 7: Cancellation policy
        // In MVP the cancellation_rules are not stored per-booking in SQLite.
        // The section is present with a generic message; production builds will
        // store cancellation_rules from the getrooms browse response.
        $lines[] = 'سياسة الإلغاء | Cancellation Policy:';
        $lines[] = 'See cancellation policy | راجع سياسة الإلغاء';
        $lines[] = '';

        // Section 8: Footer
        $lines[] = self::DOUBLE_RULE;
        $lines[] = "{$agencyName} | سيتي ترافلرز";

        return implode("\n", $lines);
    }

    /**
     * Write an mpdf PDF to ~/.dotw-cli/vouchers/{booking_ref}.pdf
     */
    private function writePdf(OutputInterface $output, string $bookingRef, string $voucherText): int
    {
        try {
            $home       = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
            $voucherDir = $home . '/.dotw-cli/vouchers';
            if (!is_dir($voucherDir)) {
                mkdir($voucherDir, 0755, true);
            }
            $pdfPath = "{$voucherDir}/{$bookingRef}.pdf";

            $html = '<html><body style="font-family: DejaVu Sans, sans-serif; font-size: 12px; direction: ltr;">'
                . '<pre style="white-space: pre-wrap;">'
                . htmlspecialchars($voucherText, ENT_QUOTES, 'UTF-8')
                . '</pre></body></html>';

            $mpdf = new \Mpdf\Mpdf([
                'mode'         => 'utf-8',
                'format'       => 'A4',
                'margin_top'   => 15,
                'margin_left'  => 15,
                'margin_right' => 15,
            ]);
            $mpdf->WriteHTML($html);
            $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

            if ($this->jsonMode) {
                return $this->success($output, ['pdf_path' => $pdfPath, 'booking_ref' => $bookingRef]);
            }

            $output->writeln("<info>PDF written to:</info> {$pdfPath}");
            return self::EXIT_SUCCESS;
        } catch (\Throwable $e) {
            return $this->fail($output, 'DOTW_E_PDF', 'PDF generation failed: ' . $e->getMessage(), self::EXIT_INTERNAL);
        }
    }

    /** Map DOTW salutation ID to display label. */
    private function salutationLabel(int $id): string
    {
        return match ($id) {
            147 => 'Mr',
            148 => 'Mrs',
            149 => 'Ms',
            150 => 'Dr',
            default => '',
        };
    }
}
