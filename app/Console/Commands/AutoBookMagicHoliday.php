<?php 

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Http\Traits\HttpRequestTrait;
use App\Http\Traits\NotificationTrait;
use App\Models\Payment;
use App\Models\Prebooking;
use App\Models\Client;
use App\Models\HotelBooking;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;
use App\Services\MagicHolidayService;
use Illuminate\Http\JsonResponse;

class AutoBookMagicHoliday extends Command
{
    use NotificationTrait;
    // protected $magicHolidayService;

    // public function __construct()
    // {
    //     parent::__construct();
    //     $this->magicHolidayService = app(MagicHolidayService::class);
    // }

    protected $signature = 'n8n:book-reservation
                            {--dry-run : Dry Run mode will make no changes to database}
                            {--proceed : Skip dry run mode, make changes to database}';

    protected $description = 'Book Magic Holiday reservation with paid payment link from n8n';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $proceed = $this->option('proceed');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made to database');
        }

        $this->info('Starting to fetch paid payments with Prebook Key');

        try {
            $paidPayments = $this->getPaidPayments();
            Log::info("Found {$paidPayments->count()} paid payment link with Prebook Key");

            if ($paidPayments->isEmpty()) {
                $this->info('No payment found that needs processing');
                return 0;
            }

            $this->table(
                ['ID', 'Prebook Key', 'Voucher Number', 'Payment Gateway', 'Payment Method ID', 'Status'],
                $paidPayments->map(function ($payment) {
                    $prebookKey = $this->getPrebookKey($payment);
                    return [
                        $payment->id,
                        $prebookKey,
                        $payment->voucher_number,
                        $payment->payment_gateway,
                        $payment->payment_method_id,
                        $payment->status
                    ];
                })->toArray()
            );

            if ($dryRun) {
                $this->info('DRY RUN completed - no changes made to the database');
                return 0;
            }

            if ($proceed) {
                $successCount = 0;
                $failedCount = 0;
                $skippedCount = 0;

                foreach ($paidPayments as $payment) {
                    try {
                        $prebookKey = $this->getPrebookKey($payment);
                        
                        $this->info("Processing payment ID: {$payment->id} with prebook key: {$prebookKey}");
                        
                        $bookingParams = $this->hotelBookingParameter($payment, $prebookKey);

                        if (!$bookingParams['success']) {
                            $this->warn("Failed to prepare booking params for payment {$payment->id}: {$bookingParams['message']}");
                            Log::warning('Booking params preparation failed', [
                                'payment_id' => $payment->id,
                                'prebook_key' => $prebookKey,
                                'error' => $bookingParams['message']
                            ]);
                            $skippedCount++;
                            continue;
                        }

                        // Get company_id through agent->branch->company relationship
                        $companyId = $payment->agent?->branch?->company_id;

                        if (!$companyId) {
                            $this->warn("Payment {$payment->id} has no company_id (agent->branch->company relationship missing), skipping booking");
                            Log::warning('Payment has no company_id through agent relationship', [
                                'payment_id' => $payment->id,
                                'prebook_key' => $prebookKey,
                                'agent_id' => $payment->agent_id,
                            ]);
                            $skippedCount++;
                            continue;
                        }

                        $magicHolidayService = new MagicHolidayService($companyId);

                        $this->info("Attempting booking with company ID: {$companyId} for payment {$payment->id}");

                        $bookingResponse = $magicHolidayService->storeBooking([
                            'srk'        => $bookingParams['srk'],
                            'hotelId'    => $bookingParams['hotelIndex'],
                            'offerIndex' => $bookingParams['offerIndex'],
                            'resultToken'=> $bookingParams['resultToken'],
                            'payload'    => $bookingParams['payload'],
                        ]);

                        $client = $payment->client;
                        $agent = $payment->agent;

                        if (isset($bookingResponse['status']) && $bookingResponse['status'] === 200) {
                            $this->info("✓ SUCCESS: Payment {$payment->id} booked successfully with Magic Holiday");
                            
                            // Get prebooking record
                            $prebooking = Prebooking::where('prebook_key', $prebookKey)->first();
                            
                            // Extract booking data from response
                            $bookingData = $bookingResponse['data'] ?? [];
                            $reservationId = $bookingData['id'] ?? null;
                            $bookingStatus = $bookingData['status'] ?? 'confirmed';
                            $bookingPrice = $bookingData['price']['value'] ?? ($bookingData['selling']['value'] ?? 0);
                            $bookingCurrency = $bookingData['price']['currency'] ?? ($bookingData['selling']['currency'] ?? 'KWD');
                            
                            // Create HotelBooking record
                            $hotelBooking = HotelBooking::create([
                                'prebook_id' => $prebooking?->id,
                                'client_id' => $payment->client_id,
                                'payment_id' => $payment->id,
                                'supplier_booking_id' => $reservationId,
                                'client_ref' => $bookingParams['payload']['clientRef'] ?? null,
                                'status' => $bookingStatus,
                                'price' => $bookingPrice,
                                'currency' => $bookingCurrency,
                                'booking_time' => now(),
                            ]);
                            
                            Log::info('Magic Holiday booking successful', [
                                'payment_id' => $payment->id,
                                'prebook_key' => $prebookKey,
                                'company_id' => $companyId,
                                'hotel_booking_id' => $hotelBooking->id,
                                'supplier_booking_id' => $reservationId,
                                'response' => $bookingResponse
                            ]);

                            $data = [
                                'client' => [
                                    'model' => $client,
                                    'message' => "Hotel booking successful for Payment ID: {$payment->id}, Booking ID: {$hotelBooking->id}, Supplier Ref: {$reservationId}"
                                ],
                                'agent' => [
                                    'model' => $agent,
                                    'message' => "Hotel booking successful for your client {$client->full_name}. Payment ID: {$payment->id}, Booking ID: {$hotelBooking->id}, Supplier Ref: {$reservationId}"
                                ],
                                'payment' => [
                                    'model' => $payment,
                                ]
                            ];

                            $this->notifyUser($data);
                            
                            $this->info("  → Hotel Booking ID: {$hotelBooking->id}, Supplier Ref: {$reservationId}");
                            
                            $successCount++;
                        } else {
                            $this->error("✗ FAILED: Payment {$payment->id} booking failed - Status: " . ($bookingResponse['status'] ?? 'unknown'));
                            Log::error('Magic Holiday booking failed', [
                                'payment_id' => $payment->id,
                                'prebook_key' => $prebookKey,
                                'company_id' => $companyId,
                                'response' => $bookingResponse
                            ]);
                            $failedCount++;

                            $data = [
                                'client' => [
                                    'model' => $client,
                                    'message' => "Hotel booking FAILED for Payment ID: {$payment->id}. Please contact support."
                                ],
                                'agent' => [
                                    'model' => $agent,
                                    'message' => "Hotel booking FAILED for your client {$client->full_name} (Payment ID: {$payment->id}). Please investigate."
                                ]
                            ];

                            $this->notifyUser($data);
                        }

                    } catch (Exception $e) {
                        $this->error("✗ EXCEPTION: Payment {$payment->id} failed with error: " . $e->getMessage());
                        Log::error('Exception during booking process', [
                            'payment_id' => $payment->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $failedCount++;

                        $data = [
                            'client' => [
                                'model' => $client,
                                'message' => "Hotel booking FAILED for Payment ID: {$payment->id}. Please contact support."
                            ],
                            'agent' => [
                                'model' => $agent,
                                'message' => "Hotel booking FAILED for your client {$client->full_name} (Payment ID: {$payment->id}). Please investigate."
                            ]
                        ];

                        $this->notifyUser($data);

                        continue; // Continue to next payment
                    }
                }

                // Summary
                $this->newLine();
                $this->info("=== Booking Process Summary ===");
                $this->info("Total Payments: " . $paidPayments->count());
                $this->info("✓ Successful: {$successCount}");
                $this->error("✗ Failed: {$failedCount}");
                $this->warn("⊘ Skipped: {$skippedCount}");
                $this->newLine();
            }
        } catch (Exception $e) {
            Log::error('Error processing payments: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function getPaidPayments()
    {
        Log::info('Starting to get paid payment with Prebook Key');

        $paidPayments = Payment::where('notes', 'like', '%PB-%')
                        ->where('status', 'completed')
                        ->get(); 
        return $paidPayments;
    }

    private function getPrebookKey($paidPayments) 
    {
        Log::info('Start to get the prebook key from paid payment');

        preg_match('/PB-[A-Za-z0-9]+/', $paidPayments->notes, $match);
        $prebookKey = $match[0] ?? null;

        if (!$prebookKey) {
            Log::info('No Prebook Key found in payment notes');
            return response()->json([
                'success' => false,
                'message' => 'No Prebook Key found in payment notes',
            ], 404);
        }

        return $prebookKey;
    }

    private function hotelBookingParameter($payment, $prebookKey) : array
    {
        Log::info('Starting create request parameter for reservation at Magic Holiday');
        $prebookData = Prebooking::where('prebook_key', $prebookKey)->first();

        if (!$prebookData) {
            Log::info('No Prebook data found for this key', ['key' => $prebookKey]);
            return [
                'success' => false,
                'message' => 'No Prebook data found for this key',
            ];
        }

        Log::info('Prebook data from system database', ['data' => $prebookData]);

        $srk = $prebookData->srk;
        $hotelIndex = $prebookData->hotel_id;
        $offerIndex = $prebookData->offer_index;
        $resultToken = $prebookData->result_token;

        $clientRef = (string) Str::uuid();
        $availabilityToken = $prebookData->availability_token;

        $client = Client::where('id', $payment->client_id)->first();
        
        if (!$client) {
            Log::error('Client not found', ['client_id' => $payment->client_id]);
            return [
                'success' => false,
                'message' => 'Client not found',
            ];
        }

        // Get the package to extract original occupancy
        $package = is_string($prebookData->package) ? json_decode($prebookData->package, true) : $prebookData->package;
        $packageRooms = $package['packageRooms'] ?? [];
        $rooms = $prebookData->rooms ?? [];

        if (empty($rooms)) {
            Log::error('No rooms found in prebook data', ['prebook_key' => $prebookKey]);
            return [
                'success' => false,
                'message' => 'No rooms found in prebook data',
            ];
        }

        // Build rooms array dynamically based on how many rooms were in the prebook
        $roomsPayload = [];
        $serviceDates = is_string($prebookData->service_dates) ? json_decode($prebookData->service_dates, true) : $prebookData->service_dates;
        $checkInDate = $serviceDates['startDate'] ?? $prebookData->checkin;
        
        foreach ($rooms as $index => $room) {
            $roomToken = $room['room_token'] ?? null;
            $occupancy = $room['occupancy'] ?? ($packageRooms[$index]['occupancy'] ?? null);

            if (!$roomToken) {
                Log::warning('Room token missing for room index', ['index' => $index]);
                continue;
            }

            if (!$occupancy) {
                Log::warning('Occupancy data missing for room index', ['index' => $index]);
                continue;
            }

            $adults = $occupancy['adults'] ?? 0;
            $childrenAges = $occupancy['childrenAges'] ?? [];

            Log::info('Building room payload', [
                'room_index' => $index,
                'room_token' => $roomToken,
                'adults' => $adults,
                'children' => count($childrenAges),
                'children_ages' => $childrenAges,
            ]);

            // Build travelers array for this room
            $travelers = [];
            
            // Add adult travelers
            for ($adultNum = 0; $adultNum < $adults; $adultNum++) {
                $isLead = ($adultNum === 0); // First adult is the lead
                
                $travelers[] = [
                    'reference' => $client->id . '-adult-' . $adultNum,
                    'type' => 'adult',
                    'lead' => $isLead,
                    'title' => 'mr',
                    'firstName' => $client->first_name,
                    'lastName' => $client->last_name,
                    'email' => $client->email,
                    'phonePrefix' => $client->country_code,
                    'phone' => $client->phone,
                    'address' => $client->address ?? 'Kuwait',
                ];
            }

            // Add child travelers with birthdates
            foreach ($childrenAges as $childIndex => $childAge) {
                // Calculate birthdate based on child's age and check-in date
                $checkIn = Carbon::parse($checkInDate);
                $birthDate = $checkIn->copy()->subYears($childAge)->format('Y-m-d');
                
                $travelers[] = [
                    'reference' => $client->id . '-child-' . $childIndex,
                    'type' => 'child',
                    'lead' => false,
                    'title' => 'mstr',
                    'firstName' => $client->first_name . ' Child ' . ($childIndex + 1),
                    'lastName' => $client->last_name,
                    'birthDate' => $birthDate,
                ];
                
                Log::info('Added child traveler', [
                    'child_index' => $childIndex,
                    'child_age' => $childAge,
                    'check_in_date' => $checkInDate,
                    'calculated_birthdate' => $birthDate,
                ]);
            }

            $roomsPayload[] = [
                'packageRoomToken' => $roomToken,
                'travelers' => $travelers,
            ];
            
            Log::info('Room payload built', [
                'room_index' => $index,
                'total_travelers' => count($travelers),
                'adult_count' => $adults,
                'child_count' => count($childrenAges),
            ]);
        }

        if (empty($roomsPayload)) {
            Log::error('Failed to build any room payloads', ['prebook_key' => $prebookKey]);
            return [
                'success' => false,
                'message' => 'Failed to build room payloads',
            ];
        }

        $payload = [
            'clientRef' => $clientRef,
            'availabilityToken' => $availabilityToken,
            'payment' => [
                'method' => 'prepaid'
            ],
            'rooms' => $roomsPayload,
            'comments' => 'Booking via API - details confirmed',
            'bosRef' => 'Booking via API',
            'agentRef' => 'Booking via API',
        ];

        Log::info('Final Payload Request', [
            'prebook_key' => $prebookKey,
            'total_rooms' => count($roomsPayload),
            'payload' => $payload
        ]);

        return [
            'success'       => true,
            'message'       => 'Booking payload prepared successfully',
            'srk'           => $srk,
            'hotelIndex'    => $hotelIndex,
            'offerIndex'    => $offerIndex,
            'resultToken'   => $resultToken,
            'payload'       => $payload,
        ];
    }

    private function notifyUser(array $data): void
    {
        $requestToN8n = [];

        if($data['agent']){
            $agent = $data['agent']['model'];

            $agentPhoneNumber = app()->environment() == 'production' ? $agent->country_code . $agent->phone_number : env('PHONE_LOCAL', '+60193058463');

            $requestToN8n['agent'] = [
                'name' => $agent->name,
                'phone_number' => $agentPhoneNumber,
                'message' => $data['agent']['message']
            ];

            $this->storeNotification([
                'user_id' => $agent->user_id,
                'title' => 'Hotel Booking Notification',
                'message' => $data['agent']['message'],
                'type' => 'booking_notification',
            ]);
        }

        if($data['client']){
            $client = $data['client']['model'];

            $clientPhoneNumber = app()->environment() == 'production' ? $client->country_code . $client->phone : env('PHONE_LOCAL', '+60193058463');

            $requestToN8n['client'] = [
                'name' => $client->full_name,
                'phone_number' => $clientPhoneNumber,
                'message' => $data['client']['message']
            ];
        }

        if($data['payment']){
            $payment = $data['payment']['model'];

            $requestToN8n['payment'] = [
                'voucher_number' => $payment->voucher_number,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
            ];
        }

        if($data['invoice']){
            $invoice = $data['invoice']['model'];

            $requestToN8n['invoice'] = [
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->amount,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
            ];
        }

        try {
            Log::info('Notifying to n8n about client ', $requestToN8n);

            $response = Http::post(env('N8N_WEBHOOK_TEST_URL'), $requestToN8n);

            Log::info('n8n response for client notification', [
                'response' => $response->json(),
            ]);
        } catch (Exception $e) {
            Log::error('Error notifying user via n8n', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

