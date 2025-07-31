<?php

namespace App\Console\Commands;

use App\Http\Controllers\TaskController;
use App\Http\Traits\HttpRequestTrait;
use App\Models\Company;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

class getMagicHolidayReservationList extends Command
{
    use HttpRequestTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:magic-holiday-reservation 
                            { --from= : Start date for reservations in format YYYY-MM-DD }
                            { --to= : End date for reservations in format YYYY-MM-DD }
                            { --toSql : Generate SQL insert statements instead of storing to database }';
    protected $companies;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Magic Holiday Reservation List';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        if($this->option('to') && !$this->option('from')) {
            $this->error('If you provide the --to option, you must also provide the --from option.');
            return 0;
        }

        $fromDate = $this->option('from') ?? date('Y-m-d');
        $toDate = $this->option('to') ?? null;

        $companies = Company::whereHas('suppliers', function ($query) {
            $query->where('name', 'Magic Holiday');
        })->with(['suppliers' => function ($query) {
            $query->where('name', 'Magic Holiday');
        }, 'suppliers.credentials'])->get();

        foreach ($companies as $company) {

            $companyId = $company->id;

            $this->info("Processing company: " . $company->name);

            if ($company->suppliers->isNotEmpty()) {

                foreach ($company->suppliers as $supplier) {

                    $this->info("Magic Holiday found for company: " . $company->name);

                    if ($supplier->credentials->isEmpty()) {
                        $this->error('Magic Holiday credentials not found for company: ' . $company->name);
                        continue;
                    }

                    if ($supplier->name == 'Magic Holiday') {

                        foreach ($supplier->credentials as $credential) {

                            $this->info("Processing Magic Holiday credentials for company: " . $company->name);

                            if ($credential->type == 'oauth') {

                                $this->info("Processing Magic Holiday OAuth credentials for company: " . $company->name);

                                if ($credential->client_id == null || $credential->client_secret == null) {
                                    $this->error('Magic Holiday OAuth credentials not found for company: ' . $company->name);
                                    continue;
                                }

                                $allReservations = $this->getAllReservations($credential->client_id, $credential->client_secret, $fromDate, $toDate);
                                
                                if ($allReservations['status'] == 'error') {
                                    Log::channel('magic_holidays')->error('Error getting reservations from supplier: ', $allReservations);
                                    $this->error('Error getting reservations from supplier: ' . $allReservations['message']);
                                    return 0;
                                }

                                $reservations = $allReservations['data'];
                                $this->info('Total reservations found: ' . count($reservations));

                                if ($this->option('toSql')) {
                                    $this->generateSqlInserts($reservations, $companyId);
                                } else {
                                    $this->processReservations($reservations, $credential->client_id, $credential->client_secret, $companyId);
                                }
                            } else {
                                $this->error('Unsupported credential type for Magic Holiday: ' . $credential->type);
                            }
                        }
                    }
                }
            } else {
                $this->error("No suppliers found for this company.");
            }
        }

        return 1;
    }

    public function getMagicHoliday($clientId, $clientSecret, $fromDate = null, $toDate = null, $page = 1, $size = 100)
    {
        $url = config('services.magic-holiday.url') . '/reservationsApi/v1/reservations';

        $scopes = ['read:reservations'];

        $params = [
            'page' => $page,
            'size' => $size
        ];

        if($fromDate) {
            $params['reservationDate'] = $fromDate;
        }

        if($toDate) {
            $params['reservationDate'] .= '|' . $toDate;
        }

        $response = $this->magicApiRequest(
            $clientId,
            $clientSecret,
            'GET',
            $url,
            [],
            [ ],
            $scopes,
            $params
        );

        return $response;
    }

    public function magicApiRequest(
        string $clientId,
        string $clientSecret,
        string $method = 'GET',
        string $url,
        array $header = [],
        array $data = [],
        array $scopes = ['read:reservations'],
        array $params = []
    ) {

        $responseCredential = $this->getClientCredential(
            $clientId,
            $clientSecret,
            $scopes
        );

        if (isset($responseCredential['error'])) {
            return [
                'status' => 'error',
                'data' => $responseCredential,
                'message' => $responseCredential['error']
            ];
        }

        $accessToken = $responseCredential['token_type'] . ' ' . $responseCredential['access_token'];

        $header = [
            'Authorization: ' . $accessToken,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        Log::channel('magic_holidays')->info('Request', [
            'method' => $method,
            'url' => $url,
            'header' => $header,
            'data' => $data
        ]);

        $data = json_encode($data);

        switch ($method) {
            case 'GET':
                $response = $this->getRequest($url, $header, $params);
                break;
            case 'POST':
                $response = $this->postRequest($url, $header, $data);
                break;
            case 'PUT':
                $response = $this->putRequest($url, $header, $data);
                break;
            case 'DELETE':
                $response = $this->deleteRequest($url, $header);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported HTTP method: $method");
        }

        Log::channel('magic_holidays')->info('Response', $response);

        if (isset($response['status']) && $response['status'] !== 200) {
            return [
                'status' => 'error',
                'data' => $response,
                'message' => $response['detail']
            ];
        }

        return [
            'status' => 'success',
            'data' => $response
        ];
    }

    public function getClientCredential(
        string $clientId,
        string $clientSecret,
        array $scopes
    ) {
        $tokenUrl = config('services.magic-holiday.token-url');

        $data = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
            'scope' => $scopes,
        ];

        Log::channel('magic_holidays')->info('Credential Request', [
            'token-url' => $tokenUrl,
            'data' => $data
        ]);

        $response = $this->postRequest($tokenUrl, [], $data);

        Log::channel('magic_holidays')->info('Credential Response', $response);

        return $response;
    }


    public function magicReserveWebhook($clientId, $clientSecret, $id)
    {

        $data = $this->getClientCredential($clientId, $clientSecret, ['write:reservations-webhooks']);


        if (isset($data['error'])) {
            return;
        }

        $accessToken = $data['token_type'] . ' ' . $data['access_token'];

        $url = config('services.magic-holiday.url') . '/reservationsApi/v1/reservations/' . $id . '/webhooks';

        $header = [
            'Authorization: ' . $accessToken,
            'Accept: application/json',
        ];
        $data = [
            'url' => route('suppliers.magic-webhook-callback'),
        ];

        Log::channel('magic_holidays')->info('Magic Holiday Webhook Request', [
            'url' => $url,
            'header' => $header,
            'data' => $data
        ]);

        $response = $this->magicApiRequest(
            $clientId,
            $clientSecret,
            'PUT',
            $url,
            $header,
            $data,
            ['write:reservations-webhooks']
        );

        Log::channel('magic_holidays')->info('Magic Holiday Webhook Response', $response);

        return;
    }

    /**
     * Get all reservations with pagination support
     */
    public function getAllReservations($clientId, $clientSecret, $fromDate = null, $toDate = null)
    {
        $allReservations = [];
        $page = 1;
        $size = 100; // API default page size
        
        do {
            $this->info("Fetching page {$page}...");
            
            $response = $this->getMagicHoliday($clientId, $clientSecret, $fromDate, $toDate, $page, $size);
            
            if ($response['status'] == 'error') {
                return $response;
            }
            
            $data = $response['data'];
            
            if (isset($data['_embedded']['reservation'])) {
                $reservations = $data['_embedded']['reservation'];
                $allReservations = array_merge($allReservations, $reservations);
                
                $this->info("Retrieved " . count($reservations) . " reservations from page {$page}");
            } else {
                break; // No more reservations
            }
            
            // Check if there are more pages
            $hasNext = isset($data['_links']['next']);
            $page++;
            
        } while ($hasNext);
        
        return [
            'status' => 'success',
            'data' => $allReservations
        ];
    }

    /**
     * Process reservations using the existing TaskController
     */
    public function processReservations($reservations, $clientId, $clientSecret, $companyId)
    {
        if (empty($reservations)) {
            $this->error('No reservations found in the response');
            return;
        }

        $this->info('Magic Holiday task received successfully');
        foreach ($reservations as $reservation) {
            Log::channel('magic_holidays')->info('Processing reservation: ', $reservation);
            $taskController = new TaskController();

            try {
                $response = $taskController->processSingleReservation($reservation, null, $companyId);

                if ($response['status'] == 'error') {
                    $this->error('Error processing reservation: ' . $response['message']);
                }

                if (isset($reservation['id'])) {
                    $this->magicReserveWebhook($clientId, $clientSecret, $reservation['id']);
                } else {
                    $this->error('Reservation ID not found in the response');
                }
            } catch (Exception $e) {
                Log::channel('magic_holidays')->error('Error processing reservation: ', ['error' => $e->getMessage()]);
                $this->error('Error processing reservation: ' . $e->getMessage());
                continue;
            }

            $this->info('Reservation processed successfully: ' . $reservation['id']);
        }
    }

    /**
     * Generate SQL INSERT statements for reservations
     */
    public function generateSqlInserts($reservations, $companyId)
    {
        if (empty($reservations)) {
            $this->error('No reservations found to generate SQL');
            return;
        }

        $this->info('Generating SQL INSERT statements...');
        
        $sqlFile = base_path('magic_holiday.sql');
        $sqlContent = '';
        
        foreach ($reservations as $reservation) {
            try {
                $sql = $this->generateSingleReservationSql($reservation, $companyId);
                $sqlContent .= $sql . "\n";
            } catch (Exception $e) {
                $this->error('Error generating SQL for reservation ' . ($reservation['id'] ?? 'unknown') . ': ' . $e->getMessage());
                continue;
            }
        }
        
        // Write SQL content to file (overwrite existing file)
        file_put_contents($sqlFile, $sqlContent);
        
        $this->info('SQL generation completed for ' . count($reservations) . ' reservations');
        $this->info('SQL statements saved to: ' . $sqlFile);
    }

    /**
     * Generate SQL INSERT statement for a single reservation
     */
    private function generateSingleReservationSql($reservation, $companyId)
    {
        // Extract data following the same logic as TaskController@processSingleReservation
        $clientName = $reservation['service']['passengers'][0]['firstName'] ? 
            $reservation['service']['passengers'][0]['firstName'] . ' ' . $reservation['service']['passengers'][0]['lastName'] : null;
        
        $hotel = $reservation['service']['hotel'] ?? null;
        $serviceDates = $reservation['service']['serviceDates'] ?? null;
        $prices = $reservation['service']['prices'] ?? null;
        
        // Determine status based on cancellation date logic (same as TaskController)
        $status = $this->determineReservationStatus($reservation);
        
        // Map to actual Task model columns
        $taskData = [
            'client_id' => 'NULL',
            'agent_id' => 'NULL', // Will need to be set manually or via separate logic
            'company_id' => $companyId,
            'supplier_id' => $this->getSupplierIdForCompany($companyId),
            'type' => "'hotel'",
            'status' => "'" . $status . "'",
            'supplier_status' => "'" . addslashes($reservation['service']['status'] ?? '') . "'",
            'original_task_id' => 'NULL',
            'client_name' => $clientName ? "'" . addslashes($clientName) . "'" : 'NULL',
            'passenger_name' => $clientName ? "'" . addslashes($clientName) . "'" : 'NULL',
            'reference' => "'" . addslashes((string)($reservation['id'] ?? '')) . "'",
            'gds_reference' => 'NULL',
            'airline_reference' => 'NULL',
            'created_by' => 'NULL',
            'issued_by' => 'NULL',
            'duration' => $serviceDates['duration'] ?? 'NULL',
            'payment_type' => isset($reservation['service']['payment']['type']) ? "'" . addslashes($reservation['service']['payment']['type']) . "'" : 'NULL',
            'payment_method_account_id' => 'NULL',
            'price' => $prices['issue']['selling']['value'] ?? 0,
            'exchange_currency' => 'NULL',
            'original_price' => $prices['issue']['selling']['value'] ?? 0,
            'original_currency' => isset($prices['issue']['selling']['currency']) ? "'" . addslashes($prices['issue']['selling']['currency']) . "'" : 'NULL',
            'tax' => '0.00',
            'surcharge' => '0.00',
            'penalty_fee' => 'NULL',
            'total' => $prices['total']['selling']['value'] ?? 0,
            'cancellation_policy' => isset($reservation['service']['cancellationPolicy']) ? "'" . addslashes(json_encode($reservation['service']['cancellationPolicy'])) . "'" : 'NULL',
            'cancellation_deadline' => isset($reservation['service']['cancellationPolicy']['date']) ? "'" . date('Y-m-d H:i:s', strtotime($reservation['service']['cancellationPolicy']['date'])) . "'" : 'NULL',
            'additional_info' => ($hotel['name'] ?? '') && $clientName ? "'" . addslashes($hotel['name'] . ' - ' . $clientName) . "'" : 'NULL',
            'venue' => isset($hotel['name']) ? "'" . addslashes($hotel['name']) . "'" : 'NULL',
            'invoice_price' => 'NULL',
            'voucher_status' => 'NULL',
            'refund_date' => 'NULL',
            'enabled' => '1',
            'taxes_record' => 'NULL',
            'refund_charge' => 'NULL',
            'ticket_number' => 'NULL',
            'file_name' => 'NULL',
            'issued_date' => isset($reservation['added']['time']) ? "'" . date('Y-m-d H:i:s', strtotime($reservation['added']['time'])) . "'" : 'NULL',
            'created_at' => "'" . now()->toDateTimeString() . "'",
            'updated_at' => "'" . now()->toDateTimeString() . "'",
            'deleted_at' => 'NULL'
        ];

        // Generate the INSERT statement for tasks table
        $columns = implode(', ', array_keys($taskData));
        $values = implode(', ', array_values($taskData));
        
        $sql = "INSERT INTO tasks ({$columns}) VALUES ({$values});\n";

        // Generate INSERT statements for task_hotel_details for each room
        if (isset($reservation['service']['rooms']) && is_array($reservation['service']['rooms'])) {
            foreach ($reservation['service']['rooms'] as $room) {
                $sql .= $this->generateHotelDetailsSql($reservation, $room, $hotel, $serviceDates);
            }
        }

        return $sql;
    }

    /**
     * Generate SQL INSERT statement for task_hotel_details
     */
    private function generateHotelDetailsSql($reservation, $room, $hotel, $serviceDates)
    {
        $hotelDetailsData = [
            'task_id' => 'LAST_INSERT_ID()', // Reference to the task we just inserted
            'hotel_id' => 'NULL',
            'booking_time' => isset($reservation['added']['time']) ? "'" . date('Y-m-d H:i:s', strtotime($reservation['added']['time'])) . "'" : 'NULL',
            'check_in' => isset($serviceDates['startDate']) ? "'" . date('Y-m-d H:i:s', strtotime($serviceDates['startDate'])) . "'" : 'NULL',
            'check_out' => isset($serviceDates['endDate']) ? "'" . date('Y-m-d H:i:s', strtotime($serviceDates['endDate'])) . "'" : 'NULL',
            'room_reference' => isset($room['id']) ? "'" . addslashes((string)$room['id']) . "'" : 'NULL',
            'room_number' => isset($room['number']) ? "'" . addslashes($room['number']) . "'" : 'NULL',
            'room_type' => isset($room['type']) ? "'" . addslashes($room['type']) . "'" : 'NULL',
            'room_amount' => isset($room['passengers']) ? count($room['passengers']) : 0,
            'room_details' => "'" . addslashes(json_encode($room)) . "'",
            'room_promotion' => 'NULL',
            'rate' => isset($reservation['service']['prices']['issue']['selling']['value']) ? $reservation['service']['prices']['issue']['selling']['value'] : 0,
            'meal_type' => isset($room['board']) ? "'" . addslashes($room['board']) . "'" : 'NULL',
            'is_refundable' => isset($room['info']) && strpos(strtolower($room['info']), 'non-refundable') !== false ? '0' : '1',
            'supplements' => 'NULL',
            'created_at' => "'" . now()->toDateTimeString() . "'",
            'updated_at' => "'" . now()->toDateTimeString() . "'",
            'deleted_at' => 'NULL'
        ];

        $columns = implode(', ', array_keys($hotelDetailsData));
        $values = implode(', ', array_values($hotelDetailsData));
        
        return "INSERT INTO task_hotel_details ({$columns}) VALUES ({$values});\n";
    }

    /**
     * Determine reservation status based on cancellation date logic
     * Following the same logic as TaskController@processSingleReservation
     */
    private function determineReservationStatus($reservation)
    {
        $status = 'issued'; // Default status
        
        if (isset($reservation['service']['cancellationPolicy'])) {
            $cancellationDate = $reservation['service']['cancellationPolicy']['date'];

            if ($cancellationDate) {
                $cancellationDate = Carbon::parse($cancellationDate)->toDateTimeString();

                if (Date::now()->greaterThanOrEqualTo($cancellationDate)) {
                    $status = 'issued';
                } else {
                    $status = 'confirmed';
                }
            } else {
                // If no cancellation date found, default to issued
                $status = 'issued';
            }
        } else {
            // If no cancellation policy found, default to issued
            $status = 'issued';
        }
        
        return $status;
    }

    /**
     * Build description from reservation data
     */
    private function buildDescription($reservation)
    {
        $description = [];
        
        if (isset($reservation['service']['hotel']['name'])) {
            $description[] = 'Hotel: ' . $reservation['service']['hotel']['name'];
        }
        
        if (isset($reservation['service']['destination']['city']['name'])) {
            $description[] = 'Destination: ' . $reservation['service']['destination']['city']['name'];
        }
        
        if (isset($reservation['service']['serviceDates']['duration'])) {
            $description[] = 'Duration: ' . $reservation['service']['serviceDates']['duration'] . ' nights';
        }
        
        if (isset($reservation['service']['rooms'])) {
            $roomCount = count($reservation['service']['rooms']);
            $description[] = 'Rooms: ' . $roomCount;
        }
        
        if (isset($reservation['service']['passengers'])) {
            $passengerCount = count($reservation['service']['passengers']);
            $description[] = 'Passengers: ' . $passengerCount;
        }
        
        return implode(' | ', $description);
    }

    /**
     * Get supplier ID for Magic Holiday for the given company
     */
    private function getSupplierIdForCompany($companyId)
    {
        $company = Company::with(['suppliers' => function ($query) {
            $query->where('name', 'Magic Holiday');
        }])->find($companyId);
        
        if ($company && $company->suppliers->isNotEmpty()) {
            return $company->suppliers->first()->id;
        }
        
        return 'NULL';
    }
}
