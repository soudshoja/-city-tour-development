<?php

namespace App\GraphQL\Mutations;

use App\AI\AIManager;
use App\Models\Client;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\User;
use App\Models\Prebooking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sequence;
use App\Services\HotelSearchService;
use App\Http\Controllers\PaymentController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Exception;

class CreateFullB2CBooking
{
    protected HotelSearchService $hotelSearchService;

    public function __construct(HotelSearchService $hotelSearchService)
    {
        $this->hotelSearchService = $hotelSearchService;
    }

    public function __invoke($_, array $args)
    {
        $input = $args['input'];
        $hasPrebookKey = !empty($input['prebookKey']);

        $validator = Validator::make($input, [
            'telephone' => 'required|string|max:20',
            'hotel' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'checkIn' => 'required|date',
            'checkOut' => 'required|date|after:checkIn',
            'occupancy' => 'required|array',
            'roomCount' => 'nullable|integer',
            'nonRefundable' => 'nullable|boolean',
            'boardBasis' => 'nullable|string|max:4',
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:20',
            'notes' => 'nullable|string|max:255',
            'payment_gateway' => 'nullable|string|max:50',
            'payment_method' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'passport' => 'nullable',
            'prebookKey' => 'nullable',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
            ];
        }

        try {
            Log::info('CreateFullB2CBooking started', ['input' => $input]);

            $client = Client::query()
                ->where('phone', $input['phone'])
                ->where('country_code', $input['country_code'])
                ->when(!empty($input['email']), fn($q) => $q->orWhere('email', $input['email']))
                ->first();

            if (!$client) {
                Log::info('Client not found — processing passport to create client');

                if (empty($input['passport']) || !$input['passport'] instanceof UploadedFile) {
                    return [
                        'success' => false,
                        'message' => 'Client not found. Passport file and email are required for new clients.',
                        'needs_passport' => true,
                        'next_step' => 'upload_passport',
                    ];
                }

                $file = $input['passport'];
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads', $fileName, 'public');
                $fullFilePath = storage_path('app/public/' . $filePath);

                $aiManager = new AIManager();
                $response = $aiManager->extractPassportData($fullFilePath, $fileName);

                if ($response['status'] !== 'success') {
                    return [
                        'success' => false,
                        'message' => 'Failed to read passport. Please re-upload a clearer image.',
                    ];
                }

                $passportData = $response['data'] ?? [];
                $agent = $this->getOrCreateAIAgent();

                $client = Client::create([
                    'first_name' => $passportData['first_name'] ?? 'Guest',
                    'last_name' => $passportData['last_name'] ?? '',
                    'passport_no' => $passportData['passport_no'] ?? null,
                    'date_of_birth' => $passportData['date_of_birth'] ?? null,
                    'email' => $input['email'],
                    'phone' => $input['phone'],
                    'country_code' => $input['country_code'],
                    'agent_id' => $agent->id,
                    'company_id' => $agent->branch->company_id,
                ]);

                Log::info('Client created from passport', ['client_id' => $client->id]);
            }

            // If user sends a prebookKey, skip hotel search
            if ($hasPrebookKey) {
                $prebook = Prebooking::where('prebook_key', $input['prebookKey'])->first();

                if (!$prebook) {
                    Log::warning('Invalid prebook key attempt', ['prebook_key' => $input['prebookKey']]);
                    return [
                        'success' => false,
                        'message' => 'Invalid prebook key. Please start a new booking.',
                        'next_step' => 'restart_booking',
                        'needs_passport' => false,
                        'client_id' => $client->id,
                    ];
                }

                // Expiration check (30 minutes)
                if (Carbon::parse($prebook->created_at)->diffInMinutes(now()) > 30) {
                    Log::info('Prebook expired', ['prebook_key' => $prebook->prebook_key]);
                    return [
                        'success' => false,
                        'message' => 'Prebook expired. Please make a new booking.',
                        'next_step' => 'restart_booking',
                        'needs_passport' => false,
                        'client_id' => $client->id,
                    ];
                }

                $rooms = $prebook->rooms ?? [];
                $totalPrice = collect($rooms)->sum('price');
                $currency = !empty($rooms) ? ($rooms[0]['currency'] ?? 'KWD') : 'KWD';

                $paymentResponse = $this->createClientPaymentLink([
                    'country_code' => $input['country_code'],
                    'phone' => $input['phone'],
                    'amount' => $totalPrice,
                    'currency' => $currency,
                    'notes' => ($input['notes'] ?? 'Magic Holiday B2C booking') . '. Prebook Key: ' . $input['prebookKey'],
                    'payment_gateway' => $input['payment_gateway'] ?? 'MyFatoorah',
                    'payment_method' => $input['payment_method'] ?? 'KNET',
                ]);

                if (empty($paymentResponse['success']) || !$paymentResponse['success']) {
                    return [
                        'success' => false,
                        'message' => $paymentResponse['message'] ?? 'Failed to create payment link.',
                        'client_id' => $client->id,
                    ];
                }

                Log::info('Prebook validated successfully', ['prebook_key' => $prebook->prebook_key]);

                return [
                    'success' => true,
                    'message' => 'Prebook confirmed successfully. Proceed to payment.',
                    'next_step' => 'make_payment',
                    'needs_passport' => false,
                    'client_id' => $client->id,
                    'hotel_name' => $input['hotel'],
                    'room_count' => count($rooms),
                    'total_price' => $totalPrice,
                    'currency' => $currency,
                    'payment_link' => $paymentResponse['payment_link'] ?? null,
                    'rooms' => [
                        [
                            'room' => $rooms,
                            'prebook' => [
                                'prebookKey' => $prebook->prebook_key,
                                'checkin' => $prebook->checkin,
                                'checkout' => $prebook->checkout,
                                'serviceDates' => is_string($prebook->service_dates) ? json_decode($prebook->service_dates, true) : $prebook->service_dates,
                                'autocancelDate' => $prebook->autocancel_date,
                                'package' => is_string($prebook->package) ? json_decode($prebook->package, true) : $prebook->package,
                                'paymentMethods' => is_string($prebook->payment_methods) ? json_decode($prebook->payment_methods, true) : $prebook->payment_methods,
                                'bookingOptions' => is_string($prebook->booking_options) ? json_decode($prebook->booking_options, true) : $prebook->booking_options,
                                'cancelPolicy' => is_string($prebook->cancel_policy) ? json_decode($prebook->cancel_policy, true) : $prebook->cancel_policy,
                                'priceBreakdown' => is_string($prebook->price_breakdown) ? json_decode($prebook->price_breakdown, true) : $prebook->price_breakdown,
                                'taxes' => is_string($prebook->taxes) ? json_decode($prebook->taxes, true) : $prebook->taxes,
                                'remarks' => is_string($prebook->remarks) ? json_decode($prebook->remarks, true) : $prebook->remarks,
                            ],
                        ],
                    ],
                ];
            }

            $searchResult = $this->hotelSearchService->searchHotelRooms(
                $input['telephone'],
                $input['hotel'],
                $input['checkIn'],
                $input['checkOut'],
                $input['occupancy'],
                $input['city'] ?? null,
                $input['roomCount'] ?? 1,
                $input['nonRefundable'] ?? null,
                $input['boardBasis'] ?? null
            );

            if (empty($searchResult['success']) || !$searchResult['success']) {
                return [
                    'success' => false,
                    'message' => $searchResult['message'] ?? 'Hotel search failed.',
                    'client_id' => $client->id ?? null,
                ];
            }

            $data = $searchResult['data'] ?? [];
            $rooms = $data['rooms'] ?? [];
            $roomCount = count($rooms);
            $totalPrice = collect($rooms)->sum(function ($r) {
                return collect($r['room'] ?? [])->sum('price');
            });
            $currency = $rooms[0]['room'][0]['currency'] ?? ($input['currency'] ?? 'KWD');

            if (!$hasPrebookKey) {
                return [
                    'success' => true,
                    'message' => 'Prebook step completed. Please confirm by sending the prebookKey to proceed with payment.',
                    'next_step' => 'confirm_prebook',
                    'needs_passport' => false,
                    'client_id' => $client->id,
                    'hotel_name' => $data['hotel_name'] ?? $input['hotel'],
                    'room_count' => count($rooms),
                    'total_price' => $totalPrice,
                    'currency' => $currency,
                    'rooms' => $rooms,
                ];
            }

            $paymentResponse = $this->createClientPaymentLink([
                'country_code' => $input['country_code'],
                'phone' => $input['phone'],
                'amount' => $totalPrice,
                'currency' => $currency,
                'notes' => 
                    (!empty($input['notes']) ? $input['notes'] : 'Magic Holiday B2C booking payment') .
                    (!empty($input['prebookKey']) ? ' | Prebook Key: ' . $input['prebookKey'] : ''),                'payment_gateway' => $input['payment_gateway'] ?? 'MyFatoorah',
                'payment_method' => $input['payment_method'] ?? 'KNET',
            ]);

            if (empty($paymentResponse['success']) || !$paymentResponse['success']) {
                return [
                    'success' => false,
                    'message' => $paymentResponse['message'] ?? 'Failed to create payment link.',
                    'client_id' => $client->id,
                ];
            }

            return [
                'success' => true,
                'message' => 'B2C booking flow completed successfully.',
                'next_step' => 'make_payment',
                'needs_passport' => false,
                'client_id' => $client->id,
                'hotel_name' => $data['hotel_name'] ?? $input['hotel'],
                'room_count' => $roomCount,
                'total_price' => $totalPrice,
                'currency' => $currency,
                'payment_link' => $paymentResponse['payment_link'] ?? null,
                'rooms' => $rooms,
            ];
        } catch (Exception $e) {
            Log::error('CreateFullB2CBooking failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    private function createClientPaymentLink(array $input): array
    {
        try {
            $aiAgent = $this->getOrCreateAIAgent();

            $client = Client::where('phone', $input['phone'])
                ->where('country_code', $input['country_code'])
                ->first();

            if (!$client) {
                Log::info('Client not found for payment creation');
                return [
                    'success' => false,
                    'message' => 'Client not found. Please upload passport to create CRM.',
                ];
            }

            $companyId = $aiAgent->branch->company_id;
            $voucherSequence = Sequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
            $voucherNumber = app(PaymentController::class)->generateVoucherNumber($voucherSequence->current_sequence++);
            $voucherSequence->save();

            $paymentMethod = PaymentMethod::where([
                ['type', strtolower($input['payment_gateway'] ?? 'myfatoorah')],
                ['english_name', strtoupper($input['payment_method'] ?? 'KNET')],
                ['company_id', $companyId],
            ])->first();

            $marginPrice = (0.2 * ($input['amount'])) + $input['amount'];
            $marginPrice = ceil($marginPrice);

            $payment = Payment::create([
                'voucher_number' => $voucherNumber,
                'from' => $client->full_name,
                'pay_to' => $aiAgent->branch->company->name,
                'currency' => $input['currency'] ?? 'KWD',
                'payment_date' => now(),
                'amount' => $marginPrice,
                'status' => 'pending',
                'client_id' => $client->id,
                'agent_id' => $aiAgent->id,
                'notes' => $input['notes'] ?? 'B2C booking payment link',
                'payment_gateway' => $input['payment_gateway'] ?? 'MyFatoorah',
                'payment_method_id' => $paymentMethod?->id,
                'company_id' => $companyId,
            ]);

            $paymentLink = route('payment.link.show', [
                'companyId' => $companyId,
                'voucherNumber' => $payment->voucher_number,
            ]);

            return [
                'success' => true,
                'message' => 'Payment link created successfully.',
                'client_id' => $client->id,
                'payment_id' => $payment->id,
                'payment_link' => $paymentLink,
            ];
        } catch (Exception $e) {
            Log::error('createClientPaymentLink failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    private function getOrCreateAIAgent(): Agent
    {
        $user = User::firstOrCreate(
            ['email' => 'testBranch@gmail.com'],
            ['name' => 'AI Branch', 'password' => Hash::make('Alphia1234'), 'role_id' => 3]
        );

        $branch = Branch::firstOrCreate(
            ['user_id' => $user->id],
            ['company_id' => 1, 'name' => $user->name, 'email' => $user->email, 'phone' => '+60176508034']
        );

        $agentUser = User::firstOrCreate(
            ['email' => 'testAgent@gmail.com'],
            ['name' => 'AI Agent', 'password' => Hash::make('Alphia1234'), 'role_id' => 4]
        );

        return Agent::firstOrCreate(
            ['name' => 'AI Agent', 'branch_id' => $branch->id],
            ['user_id' => $agentUser->id, 'email' => $agentUser->email, 'phone_number' => '+60176508034', 'country_code' => '+60', 'type_id' => 1]
        );
    }
}
