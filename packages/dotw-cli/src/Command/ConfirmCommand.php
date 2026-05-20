<?php

declare(strict_types=1);

namespace Dotw\Cli\Command;

use Dotw\Cli\Dotw\Client;
use Dotw\Cli\Dotw\RequestBuilder;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * dotw:confirm <prebook_key> --track=b2b|b2c [--guest-name=...] [--guest-email=...]
 *
 * B2B: deducts fare from b2b_credit_balance config value (advisory warning only),
 *      calls DOTW confirmbooking immediately.
 *
 * B2C: calls MyFatoorah ExecutePayment, prints payment URL, polls
 *      GetPaymentStatus every 5s for up to 5 minutes, then calls
 *      DOTW confirmbooking on payment success.
 *
 * On success: inserts row in bookings table, two accounting_entries.
 */
#[AsCommand(name: 'dotw:confirm', description: 'Confirm a pre-booked rate (B2B credit or B2C MyFatoorah)')]
class ConfirmCommand extends AbstractCommand
{
    private const POLL_INTERVAL_SECONDS = 5;
    private const POLL_MAX_SECONDS      = 300; // 5 minutes

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Confirm a pre-booked rate (B2B credit or B2C MyFatoorah)')
            ->addArgument('prebook_key', InputArgument::REQUIRED, 'Prebook key from dotw:prebook')
            ->addOption('track',       null, InputOption::VALUE_REQUIRED, 'b2b or b2c', 'b2b')
            ->addOption('guest-name',  null, InputOption::VALUE_REQUIRED, 'Lead guest full name (Firstname Lastname)', 'Guest User')
            ->addOption('guest-email', null, InputOption::VALUE_REQUIRED, 'Guest email for DOTW communication', 'guest@example.com')
            ->addOption('salutation',  null, InputOption::VALUE_REQUIRED, 'Guest salutation DOTW ID (147=Mr, 148=Mrs)', 147);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $prebookKey = $input->getArgument('prebook_key');
        $track      = $input->getOption('track');

        if (!in_array($track, ['b2b', 'b2c'], true)) {
            return $this->fail($output, 'DOTW_E_INPUT', '--track must be b2b or b2c', self::EXIT_INPUT);
        }

        // Load prebook from SQLite
        $prebook = $this->db->pdo()
            ->query("SELECT * FROM prebooks WHERE prebook_key = " . $this->db->pdo()->quote($prebookKey))
            ->fetch();

        if (!$prebook) {
            return $this->fail($output, 'DOTW_E_PREBOOK_NOT_FOUND', "Prebook not found: {$prebookKey}", self::EXIT_INPUT);
        }

        if (strtotime($prebook['expires_at']) < time()) {
            return $this->fail($output, 'DOTW_E_PREBOOK_EXPIRED', "Prebook expired at {$prebook['expires_at']}. Run dotw:prebook again.", self::EXIT_INPUT);
        }

        // Parse guest name
        $guestFullName = $input->getOption('guest-name');
        $nameParts     = explode(' ', $guestFullName, 2);
        $firstName     = $nameParts[0] ?? 'Guest';
        $lastName      = $nameParts[1] ?? 'User';
        $salutation    = (int) $input->getOption('salutation');
        $guestEmail    = $input->getOption('guest-email');

        // B2C: generate payment URL and poll
        if ($track === 'b2c') {
            $paymentResult = $this->initiateMyfatoorahPayment($output, $prebook, $firstName, $lastName, $guestEmail);
            if (isset($paymentResult['error'])) {
                return $this->fail($output, 'DOTW_E_PAYMENT', $paymentResult['error'], self::EXIT_PAYMENT);
            }

            $output->writeln('');
            $output->writeln('<info>Payment URL (share with customer):</info>');
            $output->writeln($paymentResult['payment_url']);
            $output->writeln('');
            $output->writeln('<comment>Polling for payment confirmation (every 5s, up to 5 minutes)...</comment>');

            $paid = $this->pollPaymentStatus($output, $paymentResult['invoice_id']);
            if (!$paid) {
                return $this->fail($output, 'DOTW_E_PAYMENT', 'Payment not completed within 5 minutes', self::EXIT_PAYMENT);
            }
            $output->writeln('<info>Payment confirmed!</info>');
        }

        // B2B: advisory credit balance check
        if ($track === 'b2b') {
            $creditBalance = (float) $this->config->get('b2b_credit_balance', 0);
            $fare          = (float) $prebook['total_fare'];
            if ($creditBalance > 0 && $fare > $creditBalance) {
                $output->writeln("<comment>WARNING: Fare {$prebook['currency']} " . number_format($fare, 3) . " exceeds credit balance " . number_format($creditBalance, 3) . ". Proceeding anyway.</comment>");
            }
        }

        // Call DOTW confirmbooking
        $confirmParams = [
            'fromDate'          => $prebook['check_in'],
            'toDate'            => $prebook['check_out'],
            'currency'          => (int) $this->config->get('currency', 769),
            'hotelId'           => $prebook['hotel_id'],
            'customerEmail'     => $guestEmail,
            'customerReference' => $prebookKey,
            'roomTypeCode'      => $prebook['room_type_code'],
            'selectedRateBasis' => (int) $prebook['rate_basis'],
            'allocationDetails' => $prebook['allocation_details'],
            'adults'            => 2, // default for MVP; adults count stored in raw_rooms_json
            'nationality'       => (int) $this->config->get('nationality', 66),
            'residence'         => (int) $this->config->get('residence', 66),
            'passengers'        => [
                ['salutation' => $salutation, 'firstName' => $firstName, 'lastName' => $lastName, 'leading' => true],
            ],
        ];

        try {
            $client  = new Client($this->config->all());
            $bodyXml = RequestBuilder::confirmBooking($confirmParams);
            $xml     = $client->send('confirmbooking', $bodyXml);
            $client->assertSuccessful($xml);

            // DOTW V4 confirmbooking RS: booking ref at $xml->bookings->booking->bookingReferenceNumber
            $bookingRef = (string) ($xml->bookings->booking->bookingReferenceNumber ?? '');
            if (empty($bookingRef)) {
                // Fallback: use returnedCode or prebook_key as ref if the primary path is empty
                $returnedCode = (string) ($xml->returnedCode ?? '');
                $bookingRef   = $returnedCode !== '' ? 'DOTW-' . $returnedCode : $prebookKey . '-REF';
            }
        } catch (\RuntimeException $e) {
            return $this->fail($output, 'DOTW_E_API', $e->getMessage(), self::EXIT_DOTW_API);
        } catch (\Throwable $e) {
            return $this->fail($output, 'DOTW_E_INTERNAL', $e->getMessage(), self::EXIT_INTERNAL);
        }

        // Persist booking to SQLite
        $fare = $track === 'b2c' ? (float) $prebook['markup_fare'] : (float) $prebook['total_fare'];
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO bookings
                (prebook_key, booking_ref, hotel_id, hotel_name, check_in, check_out,
                 currency, total_fare, track, status, guest_details_json)
             VALUES
                (:prebook_key, :booking_ref, :hotel_id, :hotel_name, :check_in, :check_out,
                 :currency, :total_fare, :track, :status, :guest_details_json)'
        );
        $stmt->execute([
            'prebook_key'        => $prebookKey,
            'booking_ref'        => $bookingRef,
            'hotel_id'           => $prebook['hotel_id'],
            'hotel_name'         => $prebook['hotel_name'],
            'check_in'           => $prebook['check_in'],
            'check_out'          => $prebook['check_out'],
            'currency'           => $prebook['currency'],
            'total_fare'         => $fare,
            'track'              => $track,
            'status'             => 'confirmed',
            'guest_details_json' => json_encode([['salutation' => $salutation, 'first_name' => $firstName, 'last_name' => $lastName]]),
        ]);

        // Accounting journal entries (double-entry bookkeeping)
        $accountingStmt = $this->db->pdo()->prepare(
            'INSERT INTO accounting_entries
                (booking_ref, entry_type, account_code, account_name, debit, credit, currency, description)
             VALUES (:booking_ref, :entry_type, :account_code, :account_name, :debit, :credit, :currency, :description)'
        );
        // Debit: Hotel Booking Cost (expense)
        $accountingStmt->execute([
            'booking_ref'  => $bookingRef,
            'entry_type'   => 'confirm',
            'account_code' => '5100',
            'account_name' => 'Hotel Booking Cost',
            'debit'        => $fare,
            'credit'       => 0,
            'currency'     => $prebook['currency'],
            'description'  => "Booking {$bookingRef}: {$prebook['hotel_name']}",
        ]);
        // Credit: B2B Credit Line or B2C Revenue
        $creditAccount = $track === 'b2b'
            ? ['4100', 'B2B Credit Line Utilised']
            : ['4200', 'B2C Revenue'];
        $accountingStmt->execute([
            'booking_ref'  => $bookingRef,
            'entry_type'   => 'confirm',
            'account_code' => $creditAccount[0],
            'account_name' => $creditAccount[1],
            'debit'        => 0,
            'credit'       => $fare,
            'currency'     => $prebook['currency'],
            'description'  => "Booking {$bookingRef}: {$prebook['hotel_name']}",
        ]);

        $result = [
            'booking_ref' => $bookingRef,
            'hotel_name'  => $prebook['hotel_name'],
            'check_in'    => $prebook['check_in'],
            'check_out'   => $prebook['check_out'],
            'currency'    => $prebook['currency'],
            'total_fare'  => $fare,
            'track'       => $track,
            'status'      => 'confirmed',
        ];

        if ($this->jsonMode) {
            return $this->success($output, $result);
        }

        $output->writeln('<info>Booking Confirmed!</info>');
        $output->writeln("Booking Ref : {$bookingRef}");
        $output->writeln("Hotel       : {$prebook['hotel_name']}");
        $output->writeln("Check-in    : {$prebook['check_in']}  ->  {$prebook['check_out']}");
        $output->writeln("Fare        : {$prebook['currency']} " . number_format($fare, 3));
        $output->writeln('<comment>Generate voucher: bin/dotw dotw:voucher ' . $bookingRef . '</comment>');
        return self::EXIT_SUCCESS;
    }

    /**
     * Call MyFatoorah ExecutePayment with plain Guzzle (no Laravel).
     *
     * @return array{payment_url: string, invoice_id: string}|array{error: string}
     */
    private function initiateMyfatoorahPayment(
        OutputInterface $output,
        array $prebook,
        string $firstName,
        string $lastName,
        string $email,
    ): array {
        $apiKey   = $this->config->get('myfatoorah_api_key', '');
        $baseUrl  = rtrim((string) $this->config->get('myfatoorah_base_url', 'https://api.myfatoorah.com/v2'), '/');
        $methodId = (int) $this->config->get('myfatoorah_payment_method_id', 2);
        $fare     = (float) $prebook['markup_fare'] ?: (float) $prebook['total_fare'];
        $currency = $prebook['currency'];

        if (empty($apiKey)) {
            return ['error' => 'myfatoorah_api_key not set in config. Add it to ~/.dotw-cli/config.yaml'];
        }

        $expiryDate = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $payload = [
            'PaymentMethodId'    => $methodId,
            'InvoiceValue'       => $fare,
            'CustomerName'       => "{$firstName} {$lastName}",
            'CustomerEmail'      => $email,
            'DisplayCurrencyIso' => $currency,
            'ExpiryDate'         => $expiryDate,
            'CallBackUrl'        => 'https://example.com/payment-callback',
            'ErrorUrl'           => 'https://example.com/payment-error',
            'Language'           => 'en',
            'InvoiceItems'       => [[
                'ItemName'  => "Hotel: {$prebook['hotel_name']}",
                'Quantity'  => 1,
                'UnitPrice' => $fare,
            ]],
        ];

        try {
            $http     = new GuzzleClient(['timeout' => 20]);
            $response = $http->post("{$baseUrl}/ExecutePayment", [
                'json'    => $payload,
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ],
            ]);
            $data = json_decode((string) $response->getBody(), true);
            if (!isset($data['Data']['InvoiceId'], $data['Data']['PaymentURL'])) {
                return ['error' => 'Invalid MyFatoorah response: ' . json_encode($data)];
            }
            return [
                'payment_url' => $data['Data']['PaymentURL'],
                'invoice_id'  => (string) $data['Data']['InvoiceId'],
            ];
        } catch (\Throwable $e) {
            return ['error' => 'MyFatoorah ExecutePayment failed: ' . $e->getMessage()];
        }
    }

    /**
     * Poll MyFatoorah GetPaymentStatus until Paid or timeout.
     * Returns true if paid, false if timeout or failure.
     */
    private function pollPaymentStatus(OutputInterface $output, string $invoiceId): bool
    {
        $apiKey  = $this->config->get('myfatoorah_api_key', '');
        $baseUrl = rtrim((string) $this->config->get('myfatoorah_base_url', 'https://api.myfatoorah.com/v2'), '/');
        $http    = new GuzzleClient(['timeout' => 10]);
        $elapsed = 0;

        while ($elapsed < self::POLL_MAX_SECONDS) {
            sleep(self::POLL_INTERVAL_SECONDS);
            $elapsed += self::POLL_INTERVAL_SECONDS;

            try {
                $response = $http->get("{$baseUrl}/GetPaymentStatus", [
                    'query'   => ['paymentId' => $invoiceId],
                    'headers' => ['Authorization' => "Bearer {$apiKey}"],
                ]);
                $data   = json_decode((string) $response->getBody(), true);
                $status = $data['Data']['InvoiceStatus'] ?? 'Pending';

                $output->write("  [{$elapsed}s] Status: {$status}\r");

                if ($status === 'Paid') {
                    $output->writeln('');
                    return true;
                }
                if ($status === 'Failed') {
                    $output->writeln('');
                    return false;
                }
            } catch (\Throwable) {
                // Network blip — continue polling
            }
        }

        $output->writeln('');
        return false;
    }
}
