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
use App\Models\Charge;
use App\Models\Country;
use App\Services\HotelSearchService;
use App\Http\Controllers\PaymentController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Error;
use Exception;

class CreateFullB2CBooking
{
    protected HotelSearchService $hotelSearchService;

    public function __construct(HotelSearchService $hotelSearchService)
    {
        $this->hotelSearchService = $hotelSearchService;
    }

    public function __invoke($_, array $args): array
    {
        $input = $args['input'];
        $hasPrebookKey = !empty($input['prebookKey']);

        $validator = Validator::make($input, [
            'phone' => 'required|string|max:20',
            // 'hotel' => 'required|string|max:255',
            // 'city' => 'nullable|string|max:255',
            // 'checkIn' => 'required|date',
            // 'checkOut' => 'required|date|after:checkIn',
            // 'occupancy' => 'required|array',
            // 'roomCount' => 'nullable|integer',
            // 'nonRefundable' => 'nullable|boolean',
            // 'boardBasis' => 'nullable|string|max:4',
            // 'country_code' => 'required|string|max:10',
            // 'phone' => 'required|string|max:20',
            // 'notes' => 'nullable|string|max:255',
            'payment_gateway' => 'nullable|string|max:50',
            'payment_method' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'passport' => 'nullable|file',
            'prebookKey' => 'required|exists:prebookings,prebook_key',
        ]);

        /**
         * New workflow:
         * 
         * client will only confirmed booking for this api
         * 
         * if client is not found based on the phone number, they need to send passport file and email to create client
         * 
         * Payment method and gateway are optional, default to MyFatoorah and KNET, and we will tell client what payment gateway and method we support and they can choose from there
         * 
         * if they don't choose payment gateway and method , we will tell them what default we use
         * 
         */

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
            ];
        }

        try {
            Log::info('CreateFullB2CBooking started', ['input' => $input]);

            $codes = Country::pluck('dialing_code')->toArray();
            usort($codes, fn($a, $b) => strlen($b) <=> strlen($a));

            $countryCode = '+000';
            $phone = $input['phone'];

            foreach ($codes as $code) {
                if (str_starts_with($input['phone'], $code)) {
                    $countryCode = $code;
                    $phone = substr($input['phone'], strlen($code));
                    break;
                }
            }

            if (!$hasPrebookKey) {
                return [
                    'success' => false,
                    'message' => 'This API only handles booking confirmation. Prebook key is required.',
                    'next_step' => 'provide_prebook_key',
                ];
            }

            $prebook = Prebooking::where('prebook_key', $input['prebookKey'])->first();

            if (!$prebook) {
                return [
                    'success' => false,
                    'message' => 'Invalid prebook key. Please start a new booking.',
                    'next_step' => 'restart_booking',
                ];
            }

            if ($prebook->telephone !== $input['phone']) {
                return [
                    'success' => false,
                    'message' => 'This prebooking belongs to another client. Please use your own prebooking to continue.',
                    'next_step' => 'provide_prebook',
                ];
            }

            $client = Client::query()
                ->where('phone', $phone)
                ->where('country_code', $countryCode)
                ->when(!empty($input['email']), fn($q) => $q->orWhere('email', $input['email']))
                ->first();

            if (!$client) {
                $clientValidator = Validator::make($input, [
                    'passport' => 'nullable|file',
                    'first_name' => 'required_without:passport|string|filled',
                    'email' => 'required_without:passport|email|filled',
                    'phone' => 'required_without:passport|string|max:20|filled',
                ], [
                    'first_name.required_without' => 'Client first name is required.',
                    'email.required_without' => 'Client email is required.',
                    'phone.required_without' => 'Client phone number is required.',
                ]);

                if ($clientValidator->fails()) {
                    return [
                        'success' => false,
                        'message' => 'Client creation failed: ' . $clientValidator->errors()->first(),
                        'next_step' => 'create_client',
                    ];
                }

                Log::info('Client not found — creating new one via passport or manual details');

                if (!empty($input['passport']) && $input['passport'] instanceof UploadedFile) {
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
                        'first_name' => $passportData['first_name'],
                        'middle_name' => $passportData['first_name'] ?? null,
                        'last_name' => $passportData['last_name'] ?? null,
                        'passport_no' => $passportData['passport_no'] ?? null,
                        'date_of_birth' => $passportData['date_of_birth'] ?? null,
                        'email' => $input['email'],
                        'phone' => $input['phone'],
                        'country_code' => $countryCode,
                        'agent_id' => $agent->id,
                        'company_id' => $agent->branch->company_id,
                    ]);

                    Log::info('Client created from passport', ['client_id' => $client->id]);
                } elseif (!empty($input['first_name']) && !empty($input['email']) && !empty($input['phone'])) {
                    $agent = $this->getOrCreateAIAgent();

                    $client = Client::create([
                        'first_name' => $input['first_name'],
                        'middle_name' => $input['middle_name'] ?? null,
                        'last_name' => $input['last_name'] ?? null,
                        'email' => $input['email'],
                        'phone' => $phone,
                        'country_code' => $countryCode,
                        'agent_id' => $agent->id,
                        'company_id' => $agent->branch->company_id,
                    ]);
                } else {
                    return [
                        'success' => false,
                        'message' => 'Client not found. Please provide EITHER a passport document OR all of these text fields: first_name, email, phone.',
                        'next_step' => 'create_client',
                    ];
                }

                Log::info('Client created', [
                    'method' => !empty($input['passport']) ? 'passport' : 'manual',
                    'client_id' => $client->id
                ]);
            }

            // If an existing payment link exists within the last 30 minutes, reuse it
            $existingPayment = Prebooking::where('prebook_key', $input['prebookKey'])
                ->where('telephone', $input['phone'])
                ->whereNotNull('payment_link')
                ->where('created_at', '>=', now()->subMinutes(30))
                ->first();

            if ($existingPayment) {
                return [
                    'success' => true,
                    'message' => 'You already have an active payment link for this booking. Please proceed to payment.',
                    'next_step' => 'make_payment',
                    'client_id' => $client->id,
                    'payment_link' => $existingPayment->payment_link,
                ];
            }

            // Expiration check (30 minutes)
            if (Carbon::parse($prebook->created_at)->diffInMinutes(now()) > 30) {
                Log::info('Prebook expired', ['prebook_key' => $prebook->prebook_key]);
                return [
                    'success' => false,
                    'message' => 'Prebook expired. Please make a new booking.',
                    'next_step' => 'restart_booking',
                    'client_id' => $client->id,
                ];
            }

            // If user did NOT send gateway or method, show available options instead
            if (empty($input['payment_gateway'])) {
                $availableGateways = Charge::where('is_active', true)
                    ->where('can_generate_link', true)
                    ->get(['id', 'name', 'type'])
                    ->map(function ($gateway) {
                        $methods = PaymentMethod::where('is_active', true)
                            ->where('charge_id', $gateway->id)
                            ->get(['code', 'english_name'])
                            ->map(function ($m) {
                                return [
                                    'code' => $m->code,
                                    'name' => $m->english_name,
                                ];
                            })
                            ->values()
                            ->all();

                        return [
                            'id' => $gateway->id,
                            'name' => $gateway->name,
                            'type' => $gateway->type,
                            'methods' => $methods,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'success' => false,
                    'message' => 'Please choose your preferred payment gateway or method below to continue with your booking.',
                    'next_step' => 'choose_gateway',
                    'available_gateways' => $availableGateways,
                    'client_id' => $client->id,
                ];
            }

            $rooms = $prebook->rooms ?? [];
            $roomsWithMarkup = collect($rooms)->map(function ($room) {
                $room['price'] = ceil($room['price'] * 1.2);
                return $room;
            })->values()->all();
            $totalPrice = ceil(collect($roomsWithMarkup)->sum('price'));
            $currency = !empty($rooms) ? ($rooms[0]['currency'] ?? 'KWD') : 'KWD';

            $paymentResponse = $this->createClientPaymentLink([
                'country_code' => $countryCode,
                'phone' => $input['phone'],
                'amount' => $totalPrice,
                'currency' => $currency,
                'notes' => ($input['notes'] ?? 'Magic Holiday B2C booking') . '. Prebook Key: ' . $input['prebookKey'],
                'payment_gateway' => $input['payment_gateway'],
                'payment_method' => $input['payment_method'] ?? null,
            ]);

            // Save newly generated payment link to the prebooking
            $prebook->update([
                'payment_id' => $paymentResponse['payment_id'] ?? null,
                'payment_link' => $paymentResponse['payment_link'] ?? null,
            ]);

            if (empty($paymentResponse['success']) || !$paymentResponse['success']) {
                return [
                    'success' => false,
                    'message' => $paymentResponse['message'] ?? 'Failed to create payment link.',
                    'client_id' => $client->id,
                ];
            }

            Log::info('Prebook validated successfully', ['prebook_key' => $prebook->prebook_key]);

            $message = 'Prebook confirmed successfully. Proceed to payment.';

            if (!empty($input['payment_gateway']) && !empty($input['payment_method'])) {
                $message .= ' Using ' . $input['payment_gateway'] . ' with ' . $input['payment_method'] . '.';
            } elseif (!empty($input['payment_gateway'])) {
                $message .= ' Using ' . $input['payment_gateway'] . '.';
            }

            return [
                'success' => true,
                'message' => $message,
                'next_step' => 'make_payment',
                'client_id' => $client->id,
                'hotel_name' => $prebook->hotel->name,
                'room_count' => count($rooms),
                'total_price' => $totalPrice,
                'currency' => $currency,
                'payment_link' => $paymentResponse['payment_link'] ?? null,
                'rooms' => [
                    [
                        'room' => $roomsWithMarkup,
                        'prebook' => [
                            'prebookKey' => $prebook->prebook_key,
                            'serviceDates' => is_string($prebook->service_dates) ? json_decode($prebook->service_dates, true) : $prebook->service_dates,
                            'autocancelDate' => $prebook->autocancel_date,
                            'package' => [
                                'status' => (is_string($prebook->package) ? json_decode($prebook->package, true)['status'] ?? null : $prebook->package['status'] ?? null),
                                'complete' => (is_string($prebook->package) ? json_decode($prebook->package, true)['complete'] ?? null : $prebook->package['complete'] ?? null),
                                'price' => (is_string($prebook->package)
                                    ? array_merge(json_decode($prebook->package, true)['price'] ?? [], [
                                        'selling' => [
                                            'value' => ceil(json_decode($prebook->package, true)['price']['selling']['value'] ?? 0),
                                            'currency' => json_decode($prebook->package, true)['price']['selling']['currency'] ?? 'KWD',
                                        ],
                                    ])
                                    : array_merge($prebook->package['price'] ?? [], [
                                        'selling' => [
                                            'value' => ceil(($prebook->package['price']['selling']['value'] ?? 0) * 1.2),
                                            'currency' => $prebook->package['price']['selling']['currency'] ?? 'KWD',
                                        ],
                                    ])
                                ),
                                'rate' => (is_string($prebook->package) ? json_decode($prebook->package, true)['rate'] ?? [] : $prebook->package['rate'] ?? []),
                                'packageRooms' => array_map(function ($room) {
                                    return [
                                        'occupancy' => $room['occupancy'] ?? [],
                                    ];
                                }, (
                                    is_string($prebook->package) ? (json_decode($prebook->package, true)['packageRooms'] ?? []) : ($prebook->package['packageRooms'] ?? [])
                                )),
                            ],
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

            // $searchResult = $this->hotelSearchService->searchHotelRooms(
            //     $input['telephone'],
            //     $input['hotel'],
            //     $input['checkIn'],
            //     $input['checkOut'],
            //     $input['occupancy'],
            //     $input['city'] ?? null,
            //     $input['roomCount'] ?? 1,
            //     $input['nonRefundable'] ?? null,
            //     $input['boardBasis'] ?? null
            // );

            // if (empty($searchResult['success']) || !$searchResult['success']) {
            //     return [
            //         'success' => false,
            //         'message' => $searchResult['message'] ?? 'Hotel search failed.',
            //         'client_id' => $client->id ?? null,
            //     ];
            // }

            // $data = $searchResult['data'] ?? [];
            // $rooms = $data['rooms'] ?? [];
            // $roomCount = count($rooms);
            // $totalPrice = collect($rooms)->sum(function ($r) {
            //     return collect($r['room'] ?? [])->sum('price');
            // });
            // $currency = $rooms[0]['room'][0]['currency'] ?? ($input['currency'] ?? 'KWD');

            // if (!$hasPrebookKey) {
            //     return [
            //         'success' => true,
            //         'message' => 'Prebook step completed. Please confirm by sending the prebookKey to proceed with payment.',
            //         'next_step' => 'confirm_prebook',
            //         'client_id' => $client->id,
            //         'hotel_name' => $data['hotel_name'] ?? $input['hotel'],
            //         'room_count' => count($rooms),
            //         'total_price' => $totalPrice,
            //         'currency' => $currency,
            //         'rooms' => $rooms,
            //     ];
            // }

            // $paymentResponse = $this->createClientPaymentLink([
            //     'country_code' => $input['country_code'],
            //     'phone' => $input['phone'],
            //     'amount' => $totalPrice,
            //     'currency' => $currency,
            //     'notes' => 
            //         (!empty($input['notes']) ? $input['notes'] : 'Magic Holiday B2C booking payment') .
            //         (!empty($input['prebookKey']) ? ' | Prebook Key: ' . $input['prebookKey'] : ''),                'payment_gateway' => $input['payment_gateway'] ?? 'MyFatoorah',
            //     'payment_method' => $input['payment_method'] ?? 'KNET',
            // ]);

            // if (empty($paymentResponse['success']) || !$paymentResponse['success']) {
            //     return [
            //         'success' => false,
            //         'message' => $paymentResponse['message'] ?? 'Failed to create payment link.',
            //         'client_id' => $client->id,
            //     ];
            // }

            // return [
            //     'success' => true,
            //     'message' => 'B2C booking flow completed successfully.',
            //     'next_step' => 'make_payment',
            //     'client_id' => $client->id,
            //     'hotel_name' => $data['hotel_name'] ?? $input['hotel'],
            //     'room_count' => $roomCount,
            //     'total_price' => $totalPrice,
            //     'currency' => $currency,
            //     'payment_link' => $paymentResponse['payment_link'] ?? null,
            //     'rooms' => $rooms,
            // ];
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

            $codes = Country::pluck('dialing_code')->toArray();
            usort($codes, fn($a, $b) => strlen($b) <=> strlen($a));

            $countryCode = '+000';
            $phone = $input['phone'];

            foreach ($codes as $code) {
                if (str_starts_with($input['phone'], $code)) {
                    $countryCode = $code;
                    $phone = substr($input['phone'], strlen($code));
                    break;
                }
            }

            $client = Client::where('phone', $phone)
                ->where('country_code', $countryCode)
                ->first();

            if (!$client) {
                return [
                    'success' => false,
                    'message' => 'Client not found. Please ensure the phone number and country code are correct.',
                ];
            }

            $companyId = $aiAgent->branch->company_id;
            $voucherSequence = Sequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
            $voucherNumber = app(PaymentController::class)->generateVoucherNumber($voucherSequence->current_sequence++);
            $voucherSequence->save();

            if (empty($input['payment_method'])) {
                $paymentMethod = null;
            } else {
                $paymentMethod = PaymentMethod::where('is_active', true)
                    ->where('type', strtolower($input['payment_gateway']))
                    ->where('english_name', 'LIKE', $input['payment_method'])
                    ->first();
            }

            $marginPrice = (0.02 * ($input['amount'])) + $input['amount'];
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
                'payment_gateway' => $input['payment_gateway'],
                'payment_method_id' => $paymentMethod?->id ?? null,
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
