<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Arr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use App\Services\HesabeCrypt;
use App\Services\GatewayConfigService;
use App\Services\ChargeService;
use App\Support\PaymentGateway\Tap;
use App\Support\PaymentGateway\MyFatoorah;
use App\Support\PaymentGateway\Hesabe;
use App\Support\PaymentGateway\UPayment;
use App\Http\Traits\NotificationTrait;
use App\Http\Traits\CurrencyExchangeTrait;
use App\Http\Controllers\ClientController;
use App\Http\Traits\EmailNotificationTrait;
use App\Models\HesabePayment;
use App\Models\UpaymentPayment;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\JournalEntry;
use App\Models\TapPayment;
use App\Models\Sequence;
use App\Models\Supplier;
use App\Models\Client;
use App\Models\Agent;
use App\Models\Task;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Account;
use App\Models\Accountant;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\Charge;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Company;
use App\Models\MyFatoorahPayment;
use App\Models\PaymentMethodChose;
use App\Models\PaymentMethodGroup;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\TBO;
use App\Models\SupplierCompany;
use App\Models\UserSetting;
use App\Services\TBOHolidayService;
use App\Support\PaymentGateway\Knet;
use Carbon\Carbon;
use Exception;
use Throwable;

class PaymentController extends Controller
{
    use NotificationTrait, EmailNotificationTrait;
    use CurrencyExchangeTrait;

    public function show(int $id)
    {
        // Gate::authorize('view', $user, Payment::class);

        $payment = Payment::with([
            'client',
            'agent.branch.company',
            'invoice',
            'paymentMethod',
            'createdBy',
            'tapPayment',
            'myFatoorahPayment',
            'paymentItems'
        ])->findOrFail($id);

        return view('payment.show', compact('payment'));
    }

    /**
     * Get payment partials for lazy loading
     */
    public function getPartials(int $id): JsonResponse
    {
        try {
            $payment = Payment::findOrFail($id);

            $partials = $payment->partials()
                ->select('id', 'invoice_id', 'amount', 'status', 'due_date', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($partial) {
                    return [
                        'id' => $partial->id,
                        'amount' => number_format($partial->amount, 3),
                        'status' => $partial->status,
                        'due_date' => $partial->due_date ? $partial->due_date->format('d/m/Y') : null,
                        'created_at' => $partial->created_at->format('d/m/Y H:i'),
                    ];
                });

            return response()->json([
                'success' => true,
                'partials' => $partials
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading payment partials', [
                'payment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading partials',
                'partials' => []
            ], 500);
        }
    }

    /**
     * Get payment transactions for lazy loading
     */
    public function getTransactions(int $id): JsonResponse
    {
        try {
            $payment = Payment::findOrFail($id);

            $transactions = $payment->transactions()
                ->select('id', 'transaction_type', 'amount', 'description', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'transaction_type' => ucfirst($transaction->transaction_type),
                        'amount' => number_format($transaction->amount, 3) . ' KWD',
                        'description' => $transaction->description,
                        'created_at' => $transaction->created_at->format('d/m/Y H:i'),
                    ];
                });

            return response()->json([
                'success' => true,
                'transactions' => $transactions
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading payment transactions', [
                'payment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading transactions',
                'transactions' => []
            ], 500);
        }
    }

    public function create($companyId, $invoiceNumber, Request $request)
    {
        $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email',
            'client_phone' => 'required|string|max:15',
            'total_amount' => 'required|numeric',
            'payment_gateway' => 'required|string',
            'payment_method' => 'nullable|string',
            'invoice_partial_id' => 'required'
        ]);

        Log::info('Received payment request', $request->all());

        $auth = Auth::user();

        $invoice = Invoice::with(['agent.branch', 'client'])
            ->where('invoice_number', $invoiceNumber)
            ->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId))
            ->first();

        if (!$invoice) {
            return Auth::user() ? redirect()->back()->with('error', 'Invoice not found!') : abort(404, 'Invoice not found!');
        }

        if (!$invoice->client) {
            return Auth::user() ? redirect()->back()->with('error', 'Client not found for this invoice!') : abort(404, 'Client not found for this invoice!');
        }

        $client = $invoice->client;

        $companyId = $invoice->agent->branch->company_id;

        if (!$companyId) {
            Log::error('InvoiceController@create: Company not found for the invoice', ['invoice_id' => $invoice->id]);
            return Auth::user() ? redirect()->back()->with('error', 'Company not found for this invoice!') : abort(404);
        }

        $company = $companyId ? Company::find($companyId) : null;
        $companyEmail = $company?->email ?? 'admin@citytravelers.co';

        $data = [
            'invoice' => $invoice,
            'client_id' => $client->id,
            'client_name' => $client->full_name,
            'client_email' => $companyEmail,
            'client_phone' => $client->phone,
            'total_amount' => $request->total_amount,
            'payment_gateway' => $request->payment_gateway,
            'payment_method' => $request->payment_method,
            'invoice_partial_id' =>  $request->invoice_partial_id,
        ];


        if ($clientMiddleName = $request->client_middle_name) {
            $data['client_middle_name'] = $clientMiddleName;
        }

        if ($clientLastName = $request->client_last_name) {
            $data['client_last_name'] = $clientLastName;
        }

        if ($clientMiddleName = $request->client_middle_name) {
            $data['customer']['middle_name'] = $clientMiddleName;
        }

        $response = json_decode($this->initiatePayment($data)->content(), true);

        if ((isset($response['error'])) || (isset($response['status']) && $response['status'] === 'error')) {
            $errorMessage = $response['message'] ?? ($response['error'] ?? 'Payment initiation failed');

            if (Auth::user()) {
                return redirect()->back()->with('error', $errorMessage);
            }

            return abort(400, $errorMessage);
        }

        $this->storeNotification([
            'user_id' => $invoice->agent->user_id,
            'title' => 'Payment Initiated',
            'message' => 'Payment has been initiated for invoice: ' . $invoiceNumber,
        ]);

        return redirect($response['url']);
    }

    public function generateVoucherNumber($sequence)
    {
        $year = now()->year;
        return sprintf('VOU-%s-%05d', $year, $sequence);
    }

    /**
     * Process TBO booking after payment success
     * This method is called from all payment gateway callbacks
     * 
     * @param Payment $payment
     * @return array|null
     */
    private function processTBOBookingAfterPayment(Payment $payment): ?array
    {
        try {
            $hotelBooking = $payment->hotelBooking;

            if (!$hotelBooking) {

                Log::info('No hotel booking linked, not a TBO payment', [
                    'payment_id' => $payment->id
                ]);

                return null;
            }

            $tboBooking = TBO::where('hotel_booking_id', $hotelBooking->id)->first();

            if (!$tboBooking) {

                Log::info('No TBO booking found for the hotel booking', [
                    'payment_id' => $payment->id,
                    'hotel_booking_id' => $hotelBooking->id
                ]);

                return null;
            }

            if ($tboBooking->confirmation_no) {
                Log::info('TBO booking already confirmed', [
                    'payment_id' => $payment->id,
                    'confirmation_no' => $tboBooking->confirmation_no
                ]);
                return [
                    'success' => true,
                    'message' => 'TBO booking already confirmed',
                    'confirmation_no' => $tboBooking->confirmation_no,
                    'already_booked' => true
                ];
            }

            Log::info('Processing TBO booking after payment success', [
                'payment_id' => $payment->id,
                'hotel_booking_id' => $hotelBooking->id,
                'tbo_id' => $tboBooking->id,
                'prebook_key' => $tboBooking->prebook_key
            ]);

            $customerDetails = [];
            foreach ($tboBooking->rooms as $roomIndex => $room) {
                $customers = [];

                for ($i = 0; $i < $room->adult_quantity; $i++) {
                    $customers[] = [
                        'FirstName' => $payment->client->first_name ?? 'Guest',
                        'LastName' => $payment->client->last_name ?? 'Customer',
                        'Title' => 'Mr',
                        'Type' => 'Adult'
                    ];
                }

                for ($i = 0; $i < $room->child_quantity; $i++) {
                    $customers[] = [
                        'FirstName' => 'Child' . ($i + 1),
                        'LastName' => $payment->client->last_name ?? 'Customer',
                        'Title' => 'Mstr',
                        'Type' => 'Child'
                    ];
                }

                $customerDetails[] = [
                    'CustomerNames' => $customers
                ];
            }

            $clientReferenceId = $tboBooking->prebook_key . '-' . time();

            $totalFareForTBO = $tboBooking->original_total_fare ?? $tboBooking->total_fare;

            $bookingPayload = [
                'BookingCode' => $tboBooking->booking_code,
                'BookingType' => $tboBooking->is_refundable ? 'Confirm' : 'Voucher',
                'CustomerDetails' => $customerDetails,
                'ClientReferenceId' => $clientReferenceId,
                'BookingReferenceId' => $tboBooking->prebook_key,
                'TotalFare' => (float)$totalFareForTBO,
                'EmailId' => $payment->client->email ?? 'noreply@example.com',
                'PhoneNumber' => $payment->client->phone ?? '',
                'PaymentMode' => 'Limit',
                'PaymentInfo' => [
                    'PaymentType' => 'FullPayment'
                ]
            ];

            Log::info('TBO Booking Price Breakdown', [
                'original_total_fare' => $tboBooking->original_total_fare,
                'original_currency' => $tboBooking->original_currency,
                'total_fare_after_conversion' => $tboBooking->total_fare,
                'currency_after_conversion' => $tboBooking->currency,
                'sending_to_tbo' => $totalFareForTBO
            ]);

            Log::info('Calling TBO Book API', [
                'payload' => $bookingPayload
            ]);

            $tboService = new TBOHolidayService();
            $bookingResponse = $tboService->book($bookingPayload);

            Log::info('TBO Book API Response', $bookingResponse);

            if (($bookingResponse['Status']['Code'] ?? null) !== 200) {
                Log::error('TBO booking failed', [
                    'payment_id' => $payment->id,
                    'response' => $bookingResponse
                ]);

                $hotelBooking->update(['status' => 'failed']);
                $tboBooking->update([
                    'payment_status' => 'paid',
                    'supplier_status' => 'failed'
                ]);

                return [
                    'success' => false,
                    'message' => 'TBO booking failed: ' . ($bookingResponse['Status']['Description'] ?? 'Unknown error'),
                    'response' => $bookingResponse
                ];
            }

            $confirmationNo = $bookingResponse['ConfirmationNumber'] ?? null;
            $bookingReferenceId = $bookingResponse['ClientReferenceId'] ?? null;

            Log::info('TBO metadata', [
                'confirmation_no' => $confirmationNo,
                'booking_reference_id' => $bookingReferenceId
            ]);

            $hotelBooking->update([
                'supplier_booking_id' => $confirmationNo,
                'status' => 'confirmed'
            ]);

            $tboBooking->update([
                'confirmation_no' => $confirmationNo,
                'booking_reference_id' => $bookingReferenceId,
                'payment_status' => 'paid',
                'supplier_status' => 'confirmed'
            ]);

            Log::info('TBO booking completed successfully', [
                'payment_id' => $payment->id,
                'confirmation_no' => $confirmationNo,
                'booking_reference_id' => $bookingReferenceId
            ]);

            // Retry mechanism for TBO BookingDetail API (handles propagation delay)
            $detailResponse = null;
            $maxRetries = 3;
            $retryDelay = 3;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    if ($attempt > 1) {
                        Log::info("TBO BookingDetail retry attempt {$attempt}/{$maxRetries}", [
                            'confirmation_no' => $confirmationNo,
                            'delay' => $retryDelay . 's'
                        ]);
                        sleep($retryDelay);
                    }

                    $detailResponse = $tboService->getBookingDetail([
                        'ConfirmationNumber' => $confirmationNo,
                    ]);

                    if (isset($detailResponse['Status']['Code']) && $detailResponse['Status']['Code'] == 200) {
                        Log::info('TBO BookingDetail API Response (success)', [
                            'attempt' => $attempt,
                            'response' => $detailResponse
                        ]);
                        break;
                    } else {
                        $errorMsg = $detailResponse['Status']['Description'] ?? 'Unknown error';
                        Log::warning("TBO BookingDetail API returned error on attempt {$attempt}", [
                            'error' => $errorMsg,
                            'response' => $detailResponse
                        ]);

                        // If it's "does not exist" error and we have retries left, continue
                        if ($attempt < $maxRetries && strpos($errorMsg, 'does not exist') !== false) {
                            continue;
                        }
                    }
                } catch (Exception $e) {
                    Log::warning("TBO BookingDetail API exception on attempt {$attempt}", [
                        'error' => $e->getMessage(),
                        'confirmation_no' => $confirmationNo
                    ]);

                    if ($attempt >= $maxRetries) {
                        Log::error('TBO BookingDetail API failed after all retries', [
                            'confirmation_no' => $confirmationNo,
                            'total_attempts' => $maxRetries,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }


            $bookingResult = [
                'confirmation_no' => $confirmationNo,
                'booking_reference_id' => $bookingReferenceId,
                'booking_detail' => $detailResponse['BookingDetail'] ?? null
            ];

            $taskResult = $this->createTaskFromTBOBooking($payment, $tboBooking, $bookingResult);

            if ($taskResult && $taskResult['success']) {
                Log::info('Task and Invoice created from TBO booking', [
                    'task_id' => $taskResult['task']['id'] ?? null,
                    'invoice_number' => $taskResult['invoice']->invoice_number ?? null
                ]);

                return [
                    'success' => true,
                    'message' => 'TBO booking confirmed successfully',
                    'confirmation_no' => $confirmationNo,
                    'booking_reference_id' => $bookingReferenceId,
                    'task' => $taskResult['task'] ?? null,
                    'invoice' => $taskResult['invoice'] ?? null,
                    'response' => $bookingResponse
                ];
            } else {
                Log::warning('TBO booking confirmed but task creation failed', [
                    'task_result' => $taskResult
                ]);

                return [
                    'success' => true,
                    'message' => 'TBO booking confirmed but task creation failed',
                    'confirmation_no' => $confirmationNo,
                    'booking_reference_id' => $bookingReferenceId,
                    'task_creation_failed' => true,
                    'task_error' => $taskResult['message'] ?? 'Unknown error',
                    'response' => $bookingResponse
                ];
            }
        } catch (Exception $e) {
            Log::error('Exception in processTBOBookingAfterPayment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'TBO booking exception: ' . $e->getMessage()
            ];
        }
    }

    private function createTaskFromTBOBooking(Payment $payment, TBO $tboBooking, array $bookingResult): ?array
    {
        try {
            Log::info('Creating Task from TBO booking', [
                'payment_id' => $payment->id,
                'tbo_id' => $tboBooking->id,
                'booking_result' => $bookingResult
            ]);

            $companyId = $payment->agent->branch->company_id ?? null;
            if (!$companyId) {
                Log::error('Company ID not found for payment agent', [
                    'payment_id' => $payment->id,
                    'agent_id' => $payment->agent_id
                ]);
                return [
                    'success' => false,
                    'message' => 'Company ID not found for agent'
                ];
            }

            $supplierCompany = SupplierCompany::whereHas('supplier', function ($query) {
                $query->where('name', 'LIKE', '%TBO%')
                    ->orWhere('name', 'LIKE', '%tbo%')
                    ->orWhere('name', 'TBO Holiday');
            })->where('company_id', $companyId)
                ->where('is_active', true)
                ->with('supplier')
                ->first();

            if (!$supplierCompany || !$supplierCompany->supplier) {
                Log::error('TBO supplier not found in supplier_companies', [
                    'company_id' => $companyId,
                    'payment_id' => $payment->id
                ]);
                return [
                    'success' => false,
                    'message' => 'TBO supplier not configured for this company'
                ];
            }

            $tboSupplier = $supplierCompany->supplier;

            $taskData = $this->buildTaskRequestFromTBO($payment, $tboBooking, $bookingResult, $tboSupplier->id);

            $request = new Request($taskData);

            $taskController = new TaskController();
            $response = $taskController->store($request);

            $responseData = $response->getData(true);

            // TaskController returns 'status' not 'success'
            $isSuccess = ($responseData['status'] ?? '') === 'success' || ($responseData['success'] ?? false);

            if (!$isSuccess) {
                Log::error('Failed to create task from TBO booking', [
                    'response' => $responseData
                ]);
                return [
                    'success' => false,
                    'message' => $responseData['message'] ?? 'Task creation failed'
                ];
            }

            $task = $responseData['data'] ?? $responseData['task'] ?? null;
            $invoice = $responseData['invoice'] ?? null;

            Log::info('Task created successfully from TBO booking', [
                'task_id' => $task['id'] ?? null,
                'invoice_id' => $invoice['id'] ?? null
            ]);

            // Generate invoice for TBO task if not already invoiced
            if (isset($task['id'])) {
                try {
                    $taskModel = Task::with('invoiceDetail.invoice')->find($task['id']);

                    if ($taskModel) {
                        // Check if task already has an invoice through invoiceDetail relationship
                        $hasInvoice = $taskModel->invoiceDetail && $taskModel->invoiceDetail->invoice;

                        if ($hasInvoice) {
                            Log::info('Task already has an invoice, skipping generation', [
                                'task_id' => $taskModel->id,
                                'invoice_id' => $taskModel->invoiceDetail->invoice->id,
                                'invoice_number' => $taskModel->invoiceDetail->invoice->invoice_number
                            ]);

                            $invoice = $taskModel->invoiceDetail->invoice;

                            // Update payment with invoice_id if not set
                            if (!$payment->invoice_id) {
                                $payment->update(['invoice_id' => $invoice->id]);
                            }
                        } else {
                            Log::info('Task not invoiced yet, generating invoice', [
                                'task_id' => $taskModel->id
                            ]);

                            $autoGenerateResponse = app(InvoiceController::class)->autoGenerateInvoice($taskModel, $payment);

                            if ($autoGenerateResponse['success'] ?? false) {
                                $invoiceId = $autoGenerateResponse['invoice_id'] ?? null;
                                if ($invoiceId) {
                                    $invoice = Invoice::find($invoiceId);

                                    // Update payment with invoice_id
                                    if ($invoice) {
                                        $payment->update(['invoice_id' => $invoice->id]);
                                    }

                                    Log::info('Invoice generated successfully for TBO task', [
                                        'invoice_id' => $invoiceId,
                                        'invoice_number' => $invoice->invoice_number ?? null
                                    ]);
                                }
                            } else {
                                Log::warning('Failed to generate invoice for TBO task', [
                                    'response' => $autoGenerateResponse
                                ]);
                            }
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Exception checking/generating invoice for TBO task', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            return [
                'success' => true,
                'task' => $task,
                'invoice' => $invoice
            ];
        } catch (Exception $e) {
            Log::error('Exception creating task from TBO booking', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Task creation exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build request data for TaskController@store from TBO booking
     */
    private function buildTaskRequestFromTBO(Payment $payment, TBO $tboBooking, array $bookingResult, int $supplierId): array
    {
        Log::info('Building task request data from TBO booking', [
            'payment_id' => $payment->id,
            'tbo_id' => $tboBooking->id,
            'booking_result' => $bookingResult,
            'supplier_id' => $supplierId
        ]);

        $hotelBooking = $payment->hotelBooking;
        $bookingDetail = $bookingResult['booking_detail'] ?? null;

        // Use BookingDetail from API response if available
        $checkIn = null;
        $checkOut = null;
        $hotelName = null;
        $city = null;
        $hotelCode = null;

        if ($bookingDetail) {
            $checkIn = $bookingDetail['CheckIn'] ?? null;
            $checkOut = $bookingDetail['CheckOut'] ?? null;
            $hotelName = $bookingDetail['HotelDetails']['HotelName'] ?? null;
            $city = $bookingDetail['HotelDetails']['City'] ?? null;
            $hotelCode = $bookingDetail['HotelDetails']['HotelCode'] ?? null;
        }

        // Fallback to TBO booking model
        if (!$checkIn || !$checkOut) {
            $firstRoom = $tboBooking->rooms->first();
            $checkIn = $checkIn ?? ($firstRoom->check_in ?? null);
            $checkOut = $checkOut ?? ($firstRoom->check_out ?? null);
        }

        $hotelName = $hotelName ?? $tboBooking->hotel_name;
        $city = $city ?? $tboBooking->city_name;
        $hotelCode = $hotelCode ?? $tboBooking->hotel_code;

        $duration = null;
        if ($checkIn && $checkOut) {
            $checkInDate = Carbon::parse($checkIn);
            $checkOutDate = Carbon::parse($checkOut);
            $duration = $checkInDate->diffInDays($checkOutDate);
        }

        $hotelDetails = [];

        // Use rooms from BookingDetail API response if available
        if ($bookingDetail && isset($bookingDetail['Rooms'])) {
            foreach ($bookingDetail['Rooms'] as $index => $room) {
                // Extract room name (room type)
                $roomType = is_array($room['Name']) ? implode(', ', $room['Name']) : ($room['Name'] ?? null);

                // Count adults and children from CustomerDetails
                $adults = 0;
                $children = 0;
                if (isset($room['CustomerDetails'])) {
                    foreach ($room['CustomerDetails'] as $customerDetail) {
                        if (isset($customerDetail['CustomerNames'])) {
                            foreach ($customerDetail['CustomerNames'] as $customer) {
                                if (($customer['Type'] ?? '') === 'Adult') {
                                    $adults++;
                                } elseif (($customer['Type'] ?? '') === 'Child') {
                                    $children++;
                                }
                            }
                        }
                    }
                }

                $hotelDetails[] = [
                    'hotel_name' => $hotelName,
                    'room_type' => $roomType,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'adults' => $adults > 0 ? $adults : 1,
                    'children' => $children,
                    'meal_type' => $room['MealType'] ?? ($tboBooking->meal_type ?? null),
                    'city' => $city,
                    'room_details' => json_encode([
                        'hotel_code' => $hotelCode,
                        'room_index' => $index + 1,
                        'is_refundable' => $room['IsRefundable'] ?? $tboBooking->is_refundable,
                        'inclusion' => $room['Inclusion'] ?? null,
                        'total_fare' => $room['TotalFare'] ?? null,
                    ]),
                ];
            }
        } else {
            // Fallback to TBO booking model
            foreach ($tboBooking->rooms as $index => $room) {
                $hotelDetails[] = [
                    'hotel_name' => $hotelName,
                    'room_type' => $room->room_type,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'adults' => $room->adult_quantity ?? 1,
                    'children' => $room->child_quantity ?? 0,
                    'meal_type' => $tboBooking->meal_type,
                    'city' => $city,
                    'room_details' => json_encode([
                        'hotel_code' => $hotelCode,
                        'room_index' => $index + 1,
                        'is_refundable' => $tboBooking->is_refundable,
                    ]),
                ];
            }
        }

        $passengerName = $payment->client->full_name ?? 'Guest';

        return [
            'type' => 'hotel',
            'status' => 'issued',
            'reference' => $bookingResult['confirmation_no'],
            'supplier_id' => $supplierId,
            'company_id' => $payment->agent->branch->company_id,
            'agent_id' => $payment->agent_id,
            'client_id' => $payment->client_id,

            'original_price' => $tboBooking->original_total_fare,
            'original_total' => $tboBooking->original_total_fare,
            'original_currency' => $tboBooking->original_currency ?? 'USD',
            'original_tax' => $tboBooking->original_total_tax ?? 0,

            'price' => $tboBooking->price_before_markup ?? $tboBooking->total_fare,
            'total' => $tboBooking->price_before_markup ?? $tboBooking->total_fare,
            'exchange_currency' => $tboBooking->currency ?? 'KWD',
            'tax' => $tboBooking->tax_before_markup ?? 0,
            'surcharge' => 0,

            'is_exchanged' => !empty($tboBooking->exchange_rate),
            'exchange_rate' => $tboBooking->exchange_rate ?? 1,

            'duration' => $duration,
            'passenger_name' => $passengerName,
            'client_name' => $payment->client->full_name,

            'booking_reference' => $bookingResult['booking_reference_id'] ?? null,
            'gds_reference' => $tboBooking->prebook_key,
            'supplier_pay_date' => now(),
            'issued_date' => now(),

            'payment_type' => $payment->payment_gateway,
            'payment_method_account_id' => $payment->payment_method_id,

            'notes' => sprintf(
                'TBO Booking - %s | Rooms: %d | Meal: %s | Refundable: %s | Payment: %s',
                $tboBooking->hotel_name,
                $tboBooking->rooms->count(),
                $tboBooking->meal_type ?? 'N/A',
                $tboBooking->is_refundable ? 'Yes' : 'No',
                $payment->voucher_number
            ),

            'task_hotel_details' => $hotelDetails,

            'enabled' => true,
        ];
    }

    /**
     * Register a confirmed TBO booking as a task in the system
     * This can be called independently to handle cases where booking succeeded but task creation failed
     * 
     * @param int $paymentId - The payment ID
     * @return JsonResponse
     */
    public function registerTBOBookingAsTask(Request $request)
    {
        try {
            $request->validate([
                'payment_id' => 'required|integer|exists:payments,id',
            ]);

            $paymentId = $request->input('payment_id');

            $payment = Payment::with(['agent.branch.company', 'client', 'hotelBooking'])
                ->find($paymentId);

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            $hotelBooking = $payment->hotelBooking;
            if (!$hotelBooking) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hotel booking associated with this payment'
                ], 400);
            }

            $tboBooking = TBO::with('rooms')->where('hotel_booking_id', $hotelBooking->id)->first();
            if (!$tboBooking) {
                return response()->json([
                    'success' => false,
                    'message' => 'No TBO booking found for this hotel booking'
                ], 404);
            }

            if (!$tboBooking->confirmation_no) {
                return response()->json([
                    'success' => false,
                    'message' => 'TBO booking is not confirmed yet. Confirmation number missing.'
                ], 400);
            }

            $existingTask = Task::where('reference', $tboBooking->confirmation_no)
                ->where('type', 'hotel')
                ->first();

            if ($existingTask) {
                Log::info('Task already exists, checking for invoice', [
                    'task_id' => $existingTask->id,
                    'invoice_id' => $payment->invoice_id
                ]);

                // Check if task has an invoice
                $invoice = null;
                if ($payment->invoice_id) {
                    $invoiceModel = Invoice::find($payment->invoice_id);
                    if ($invoiceModel) {
                        $invoice = [
                            'id' => $invoiceModel->id,
                            'invoice_number' => $invoiceModel->invoice_number
                        ];
                    }
                }

                // If no invoice exists, generate one
                if (!$invoice) {
                    Log::info('Task exists but no invoice found, auto-generating invoice', [
                        'task_id' => $existingTask->id,
                        'payment_id' => $payment->id
                    ]);

                    try {
                        $invoiceController = app(InvoiceController::class);
                        $generateInvoiceResponse = $invoiceController->autoGenerateInvoice($existingTask, $payment);

                        if ($generateInvoiceResponse['success'] ?? false) {
                            $invoiceId = $generateInvoiceResponse['invoice_id'] ?? null;
                            $invoiceNumber = null;

                            if ($invoiceId) {
                                $invoiceModel = Invoice::find($invoiceId);
                                $invoiceNumber = $invoiceModel->invoice_number ?? null;
                            }

                            $invoice = [
                                'id' => $invoiceId,
                                'invoice_number' => $invoiceNumber
                            ];

                            Log::info('Invoice auto-generated successfully for existing task', [
                                'invoice_id' => $invoice['id'],
                                'invoice_number' => $invoice['invoice_number']
                            ]);
                        } else {
                            Log::warning('Failed to auto-generate invoice for existing task', [
                                'response' => $generateInvoiceResponse
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Exception auto-generating invoice for existing task', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $responseData = [
                    'success' => true,
                    'message' => 'Task already exists for this booking' . ($invoice ? '. Invoice has been sent.' : ''),
                    'task_id' => $existingTask->id,
                    'task' => $existingTask,
                    'invoice' => $invoice,
                    'already_exists' => true,
                    'payment_id' => $payment->id,
                    'confirmation_no' => $tboBooking->confirmation_no,
                ];

                if ($existingTask->id) {
                    $responseData['hotel_voucher_url'] = route('tasks.pdf.hotel', $existingTask->id);
                }

                if ($invoice && isset($invoice['invoice_number'])) {
                    $responseData['invoice_url'] = route('invoice.show', [
                        'companyId' => $payment->agent->branch->company_id,
                        'invoiceNumber' => $invoice['invoice_number']
                    ]);
                }

                return response()->json($responseData, 200);
            }

            $bookingResult = [
                'confirmation_no' => $tboBooking->confirmation_no,
                'booking_reference_id' => $tboBooking->booking_reference_id,
            ];

            $taskResult = $this->createTaskFromTBOBooking($payment, $tboBooking, $bookingResult);

            Log::info('createTaskFromTBOBooking result', [
                'taskResult' => $taskResult,
                'has_success' => isset($taskResult['success']),
                'success_value' => $taskResult['success'] ?? 'not set'
            ]);

            if (!$taskResult || !$taskResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $taskResult['message'] ?? 'Failed to create task from TBO booking',
                    'details' => $taskResult
                ], 500);
            }

            $task = $taskResult['task'] ?? null;
            $invoice = $taskResult['invoice'] ?? null;

            Log::info('Task and Invoice extracted', [
                'task' => $task,
                'invoice' => $invoice,
                'has_task' => !is_null($task),
                'has_invoice' => !is_null($invoice),
                'has_task_id' => $task && isset($task['id'])
            ]);

            // If no invoice was created, auto-generate one
            if (!$invoice && $task && isset($task['id'])) {
                Log::info('No invoice found, auto-generating invoice for TBO task', [
                    'task_id' => $task['id'],
                    'payment_id' => $paymentId
                ]);

                try {
                    $taskModel = Task::find($task['id']);
                    if ($taskModel) {
                        $invoiceController = app(InvoiceController::class);
                        $generateInvoiceResponse = $invoiceController->autoGenerateInvoice($taskModel, $payment);

                        if ($generateInvoiceResponse['success'] ?? false) {
                            $invoiceId = $generateInvoiceResponse['invoice_id'] ?? null;
                            $invoiceNumber = null;

                            if ($invoiceId) {
                                $invoiceModel = Invoice::find($invoiceId);
                                $invoiceNumber = $invoiceModel->invoice_number ?? null;
                            }

                            $invoice = [
                                'id' => $invoiceId,
                                'invoice_number' => $invoiceNumber
                            ];

                            Log::info('Invoice auto-generated successfully', [
                                'invoice_id' => $invoice['id'],
                                'invoice_number' => $invoice['invoice_number']
                            ]);
                        } else {
                            Log::warning('Failed to auto-generate invoice', [
                                'response' => $generateInvoiceResponse
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Exception auto-generating invoice', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $responseData = [
                'success' => true,
                'message' => 'TBO booking registered as task successfully. Invoice and hotel voucher have been sent automatically.',
                'task' => $task,
                'invoice' => $invoice,
                'payment_id' => $paymentId,
                'confirmation_no' => $tboBooking->confirmation_no,
            ];

            if ($task && isset($task['id'])) {
                $responseData['hotel_voucher_url'] = route('tasks.pdf.hotel', $task['id']);
            }

            if ($invoice && isset($invoice['invoice_number'])) {
                $responseData['invoice_url'] = route('invoice.show', [
                    'companyId' => $payment->agent->branch->company_id,
                    'invoiceNumber' => $invoice['invoice_number']
                ]);
            }

            Log::info('TBO booking registered as task successfully', [
                'payment_id' => $paymentId,
                'tbo_id' => $tboBooking->id,
                'task_id' => $task['id'] ?? null,
                'invoice_id' => $invoice['id'] ?? null,
                'invoice_number' => $invoice['invoice_number'] ?? null,
                'hotel_voucher_url' => $responseData['hotel_voucher_url'] ?? null,
                'invoice_url' => $responseData['invoice_url'] ?? null,
                'note' => 'Invoice and hotel voucher sent automatically via N8N webhook from autoGenerateInvoice'
            ]);

            return response()->json($responseData, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Exception in registerTBOBookingAsTask', [
                'payment_id' => $request->input('payment_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Exception occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function initiatePayment($data): JsonResponse
    {
        $invoice = $data['invoice'];
        $company = $invoice->agent->branch->company;

        if (!$company) {
            Log::error('Company not found for the invoice', ['invoice_id' => $invoice->id]);

            return response()->json(['error' => 'Company not found for the invoice.'], 500);
        }

        $invoicePartialId = $data['invoice_partial_id'] ?? null;
        if (!$invoicePartialId) {
            return response()->json(['error' => 'Invoice partial ID is missing.'], 400);
        }

        $companyId = $invoice->agent->branch->company_id;

        $voucherSequence = Sequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
        $currentSequence = $voucherSequence->current_sequence;
        $voucherNumber = $this->generateVoucherNumber($currentSequence);
        $voucherSequence->current_sequence++;
        $voucherSequence->save();

        $finalAmount = $data['total_amount'];

        $existingPayment = Payment::where('invoice_id', $invoice->id)
            ->where('status', 'initiate')
            ->whereNotNull('payment_url')
            ->orderByDesc('created_at')
            ->first();

        if ($existingPayment) {
            if (
                strtolower($existingPayment->payment_gateway) !== strtolower($data['payment_gateway']) ||
                $existingPayment->payment_method_id != $data['payment_method']
            ) {
                Log::info('Payment gateway or method changed, deleting old payment.', [
                    'old_gateway' => $existingPayment->payment_gateway,
                    'new_gateway' => $data['payment_gateway'],
                    'old_method' => $existingPayment->payment_method_id,
                    'new_method' => $data['payment_method'],
                ]);
                $existingPayment->delete();
            } elseif (
                $existingPayment->payment_url &&
                $existingPayment->expiry_date &&
                now()->lt($existingPayment->expiry_date) &&
                !in_array(strtolower($data['payment_gateway']), ['tap', 'hesabe'])
            ) {
                Log::info('Reusing existing payment link.', [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $existingPayment->id,
                    'url' => $existingPayment->payment_url,
                    'expires_at' => $existingPayment->expiry_date,
                ]);

                InvoicePartial::where('id', $invoicePartialId)->update(['payment_id' => $existingPayment->id]);

                return response()->json([
                    'success' => 'Reusing existing payment link.',
                    'url' => $existingPayment->payment_url,
                ]);
            } else {
                Log::info('Existing payment expired, creating new one.', [
                    'payment_id' => $existingPayment->id,
                    'expiry_date' => $existingPayment->expiry_date,
                ]);
                $existingPayment->delete();
            }
        }

        $partial = InvoicePartial::findOrFail($invoicePartialId);
        $originalAmount = $partial->amount;

        $payment = Payment::create([
            'voucher_number' => $voucherNumber,
            'from' => $invoice->client->full_name,
            'pay_to' => $invoice->agent->branch->company->name,
            'created_by' => Auth::id(),
            'currency' => 'KWD',
            'payment_date' => Carbon::now(),
            'service_charge' => $finalAmount - $originalAmount,
            'amount' => $originalAmount,
            'payment_gateway' => $data['payment_gateway'],
            'payment_method_id' => $data['payment_method'],
            'status' => 'pending',
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'agent_id' => $invoice->agent_id
        ]);

        InvoicePartial::where('id', $invoicePartialId)->update(['payment_id' => $payment->id]);

        $paymentReference = null;
        $paymentUrl = null;
        $expiryDate = now()->addDays(2);

        if (strtolower($data['payment_gateway']) === 'tap') {

            $tap = new Tap();

            $requestTap = new Request([
                'finalAmount' => $finalAmount,
                'client_name' => $data['client_name'],
                'client_email' => $data['client_email'],
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'payment_id' => $payment->id,
                'payment_method_id' => $data['payment_method'],
                'payment_gateway' => $payment->payment_gateway,
                'invoice_partial_id' => $data['invoice_partial_id'],
                'description' => 'Payment for invoice: ' . $invoice->id,
            ]);

            Log::info('requestTap', ['requestTap' => $requestTap]);

            $response = $tap->createCharge($requestTap);

            logger('response', ['response' => $response]);

            if (isset($response['errors'])) {
                return response()->json(['error' => $response['errors'][0]['description'] ?? 'Payment failed'], 500);
            }

            if (isset($response['status']) && $response['status'] === 'FAILED') {
                $errorMessage = $response['gateway']['response']['message'] ?? $response['response']['message'] ?? 'Payment failed';
                return response()->json(['error' => $errorMessage], 500);
            }

            $paymentReference = $response['id'];
            $paymentUrl = $response['transaction']['url'];
        } else if (strtolower($data['payment_gateway']) === 'myfatoorah') {

            $myFatoorah = new MyFatoorah();

            $requestFatoorah = new Request([
                'final_amount' => $finalAmount,
                'client_name' => $data['client_name'],
                'client_email' => $data['client_email'],
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'payment_id' => $payment->id,
                'payment_gateway' => $payment->payment_gateway,
                'payment_method_id' => $data['payment_method'],
                'invoice_partial_id' => $data['invoice_partial_id'],
                'client_phone' => $data['client_phone'],
            ]);

            Log::info('requestFatoorah', ['requestFatoorah' => $requestFatoorah]);

            $response = $myFatoorah->createCharge($requestFatoorah);

            Log::info('MyFatoorah: ExecutePayment response', ['response' => $response]);

            if (isset($response['status']) && $response['status'] === 'error') {
                return response()->json(['error' => $response['message'] ?? 'MyFatoorah payment initiation failed'], 500);
            }

            $paymentReference = $response['invoice_id'] ?? null;
            $paymentUrl = $response['payment_url'] ?? null;

            if (isset($response['expiry_date'])) {
                $expiryDate = $response['expiry_date'];
            }

            // Update payment record after successful charge creation
            $payment->payment_reference = $paymentReference;
            $payment->payment_url = $paymentUrl;
            $payment->expiry_date = $expiryDate ? \Carbon\Carbon::parse($expiryDate) : now()->addDays(2);
            $payment->status = 'initiate';
            $payment->save();
        } else if (strtolower($data['payment_gateway']) === 'upayment') {
            $uPayment = new UPayment();

            $requestUPayment = new Request([
                'final_amount' => $finalAmount,
                'client_id' => $data['client_id'],
                'client_name' => $data['client_name'],
                'client_email' => $data['client_email'],
                'client_phone' => $data['client_phone'],
                'company_email' => $company->email,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'payment_id' => $payment->id,
                'payment_number' => $payment->voucher_number,
                'payment_method_id' => (int) $data['payment_method'],
                'invoice_partial_id' => $data['invoice_partial_id'],
                'currency' => $invoice->currency,
            ]);

            $response = $uPayment->makeCharge($requestUPayment);

            if (!$response['status']) {
                return response()->json(['error' => $response['message']], 500);
            }

            $paymentReference = $response['data']['trackId'] ?? null;
            $paymentUrl = $response['data']['link'] ?? null;

            if (isset($response['transaction']['expiryDate'])) {
                $expiryDate = $response['transaction']['expiryDate'];
            }
        } elseif (strtolower($data['payment_gateway']) === 'hesabe') {

            $companyId = $payment->agent->branch->company_id;
            $company = Company::find($companyId);
            $configService = new GatewayConfigService();
            $hesabeConfig = $configService->getHesabeConfig();

            if (!$hesabeConfig['status'] || !$hesabeConfig['data']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hesabe configuration is missing or inactive',
                ]);
            }

            $apiKey = Charge::where('company_id', $companyId)
                ->where('name', 'Hesabe')
                ->pluck('api_key')
                ->first();
            Log::info('API key received from database', ['api_key' => $apiKey]);

            if (!$apiKey) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'API key of ' . ucwords($data['payment_gateway']) .
                        ' gateway for company ' . ($company?->name ?? 'Unknown') .
                        ' does not exist. Contact support team for more detail',
                ], 422);
            }
            $baseUrl = $hesabeConfig['data']['base_url'];
            $accessCode = $hesabeConfig['data']['access_code'];
            $merchantCode = $hesabeConfig['data']['merchant_code'];
            $encryptionKey = $hesabeConfig['data']['iv_key'];

            $payment = Payment::with('agent', 'client')->where('id', $payment->id)->first();
            $paymentMethod = $payment->paymentMethod?->myfatoorah_id;
            $companyId = optional($payment->agent->branch)->company_id;

            $chargeResult = ChargeService::calculate($payment->amount, $companyId, $payment->payment_method_id, 'Hesabe');
            $finalAmount = $chargeResult['finalAmount'] ?? $payment->amount;

            $firstName = $payment->client->first_name;
            $middleName = $payment->client->middle_name;
            $lastName = $payment->client->last_name;
            $customerName = trim("$firstName $middleName $lastName");

            $variable2 = (string) $data['invoice_partial_id'];

            $checkoutPayload = [
                "amount" => $finalAmount,
                "currency" => 'KWD',
                "paymentType" => $paymentMethod,
                "orderReferenceNumber" => $payment->voucher_number,
                "name" => $customerName,
                "version" => '2.0',
                "merchantCode" => $merchantCode,
                "variable1" => 'invoice',
                "variable2" => $variable2,
                "responseUrl" => route('payment.hesabe.response'),
                "failureUrl" => route('payment.hesabe.failure'),
                'webhookUrl' => route('payment.hesabe.webhook'),
            ];

            Log::info('Hesabe RequestData', ['payload' => $checkoutPayload]);

            $requestDataJson = json_encode($checkoutPayload);
            Log::info('RequestData: ', ['json' => $requestDataJson]);

            $encryptedData = HesabeCrypt::encrypt($requestDataJson, $apiKey, $encryptionKey);
            Log::info('EncryptedData: ', ['encrypted_data' => $encryptedData]);

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "$baseUrl/checkout",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => array('data' => $encryptedData),
                CURLOPT_HTTPHEADER => array(
                    "accessCode: $accessCode",
                    "Accept: application/json"
                ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            Log::info('Checkout response: ', ['response', $response]);

            if (!$response) {
                Log::error('Hesabe: cURL error ', ['response' => $response]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hesabe checkout failed due to cURL error',
                ]);
            }

            $decryptedData = HesabeCrypt::decrypt($response, $apiKey, $encryptionKey);
            Log::info('Hesabe decryption: ' . $decryptedData);

            if (!$decryptedData) {
                Log::error('Hesabe: Decryption failed ', ['response' => $decryptedData]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hesabe decryption failed',
                ]);
            }

            $responseData = json_decode($decryptedData, true);
            Log::info('Response data: ', ['response', $responseData]);

            if (!$responseData) {
                Log::error('Hesabe: Checkout failed', ['response' => $responseData]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hesabe checkout failed, no response data',
                ]);
            }

            $responseToken = $responseData['response']['data'];
            $paymentUrl = $baseUrl . '/payment' . '?data=' . $responseToken;
            $paymentReference = $payment->voucher_number;
        } elseif (strtolower($data['payment_gateway']) === 'knet') {

            $knet = new Knet($companyId);

            $requestKnet = new Request([
                'finalAmount' => $finalAmount,
                'payment_id' => $payment->id,
                'voucher_number' => $payment->voucher_number,
                'invoice_number' => $invoice->invoice_number,
                'invoice_partial_id' => $data['invoice_partial_id'],
                'company_id' => $companyId,
            ]);

            Log::info('KNET create charge request', ['request' => $requestKnet->all()]);

            $response = $knet->createCharge($requestKnet);

            Log::info('KNET create charge response', ['response' => $response]);

            if ($response['status'] !== 'success') {
                return response()->json(['error' => $response['message'] ?? 'KNET payment initiation failed'], 500);
            }

            $paymentReference = $response['track_id'];
            $paymentUrl = $response['redirect_url'];
        } else {
            $payment->delete();
            return response()->json(['error' => 'Unsupported payment method'], 400);
        }

        if ($paymentReference && $paymentUrl) {
            $payment->update([
                'payment_reference' => $paymentReference,
                'payment_url' => $paymentUrl,
                'expiry_date' => $expiryDate,
                'status' => 'initiate',
            ]);

            return response()->json([
                'success' => 'Payment initiated successfully',
                'url' => $paymentUrl,
            ]);
        } else {
            Log::error('Failed to initiate payment: Missing payment reference or URL.', [
                'payment_id' => $payment->id,
                'payment_gateway' => $payment->payment_gateway,
                'payment_reference' => $paymentReference,
                'payment_url' => $paymentUrl
            ]);

            $payment->delete();

            return response()->json(['error' => 'Failed to initiate payment.'], 500);
        }
    }

    public function webhook(Request $request)
    {
        Log::info('Tap Payment Webhook received: ' . $request->getContent());
    }

    public function getPaymentStatusMyFatoorah($invoiceId): JsonResponse
    {
        $configService = new GatewayConfigService();
        $myfatoorahConfig = $configService->getMyFatoorahConfig();

        if (!$myfatoorahConfig['status'] || !$myfatoorahConfig['data']) {
            Log::error('MyFatoorah configuration is missing or inactive');
            return response()->json([
                'status' => 'error',
                'message' => $myfatoorahConfig['message'] ?? 'MyFatoorah configuration is missing or inactive'
            ], 500);
        }

        $myfatoorahConfig = $myfatoorahConfig['data'];

        $apiKey  = $myfatoorahConfig['api_key'];
        $baseUrl = $myfatoorahConfig['base_url'];

        Log::info('getPaymentStatusMyFatoorah called with invoice_id: ', [
            'invoice_id' => $invoiceId,
            'apiKey' => $apiKey,
            'baseUrl' => $baseUrl,
        ]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type' => 'application/json',
        ])->post("$baseUrl/getPaymentStatus", [
            "Key" => $invoiceId,
            "KeyType" => "InvoiceId"
        ]);

        Log::info('getPaymentStatusMyFatoorah Response', [
            'response' => $response->json() ?? $response->body()
        ]);

        if (!$response->successful()) {

            $message = $response->json()['Message'] ?? 'Unknown error';

            Log::error('Failed to fetch payment status from MyFatoorah', [
                'invoiceId' => $invoiceId,
                'response' => $response->body()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $message
            ], 500);
        }

        $responseData = $response->json();
        $data = $responseData['Data'] ?? [];

        if (empty($data)) {
            Log::error('No data found in MyFatoorah response', ['response' => $responseData]);
            return response()->json([
                'status' => 'error',
                'message' => 'No data found in MyFatoorah response'
            ], 404);
        }

        $invoiceTransactions = $data['InvoiceTransactions'] ?? '[]';
        $authCode = data_get($invoiceTransactions, '0.AuthorizationId');

        $invoiceStatus = $data['InvoiceStatus'] ?? null;

        if (!$invoiceStatus) {
            Log::error('Invoice status not found in MyFatoorah response', ['response' => $responseData]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice status not found in MyFatoorah response'
            ], 404);
        }

        $invoiceValue = $data['InvoiceValue'] ?? null;

        if (!$invoiceValue) {
            Log::error('Invoice value not found in MyFatoorah response', ['response' => $responseData]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice value not found in MyFatoorah response'
            ], 404);
        }

        if ($invoiceStatus === 'Paid') {
            $invoiceId = $response->json()['Data']['InvoiceId'] ?? null;

            if (!$invoiceId) {
                Log::info('Invoice ID not found in MyFatoorah portal');
                return response()->json([
                    'status' => 'error',
                    'message' => 'No such Invoice ID found in MyFatoorah portal'
                ], 400);
            }

            $existingInvoiceId = Payment::where('payment_reference', $invoiceId)->exists();

            if ($existingInvoiceId) {
                Log::info('Invoice ID has already been imported');
                return response()->json([
                    'status' => 'error',
                    'message' => 'A payment with this Invoice ID has already been imported'
                ], 400);
            }
        } else {
            Log::info('Invoice status is not Paid', ['invoiceStatus' => $invoiceStatus]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice status is not Paid'
            ], 400);
        }

        $userDefined = json_decode($data['UserDefinedField'] ?? '{}', true);
        $paymentMethodId = PaymentMethod::where('english_name')->value('id');

        return response()->json([
            'status' => 'success',
            'message' => 'Payment status fetched successfully',
            'data' => $data,
            'amount' => $invoiceValue,
            'invoice_status' => $invoiceStatus,
            'invoice_id' => $data['InvoiceId'] ?? null,
            'invoice_reference' => $data['InvoiceReference'],
            'customer_name' => $data['CustomerName'] ?? null,
            'created_date' => $data['CreatedDate'] ?? null,
            'payment_gateway' => Arr::get($userDefined, 'payment_gateway', 'MyFatoorah'),
            'payment_method_id' => $paymentMethodId,
            'auth_code' => $authCode,
            'user_defined' => $userDefined,
        ]);
    }

    public function importFromInvoice(Request $request): JsonResponse
    {
        Log::info('Starting to import payment from invoice');
        Log::info('Starting to import payment from invoice');

        $gateway = strtolower($request->input('gateway'));

        $request->validate([
            'gateway' => 'required|in:myfatoorah,hesabe',
            'import_invoice_id' => 'nullable|string',
            'import_order_reference' => 'nullable|string',
            'receiverName' => 'required|string',
            'agentName' => 'required|string',
        ]);

        $importInvoiceId = $request->input('import_invoice_id');
        $importOrderReference = $request->input('import_order_reference');

        $agentId = Agent::where('name', $request->input('agentName'))->value('id');
        $clientId = Client::where('name', $request->input('receiverName'))->value('id');

        if (!$agentId || !$clientId) {

            Log::error('Invoice ID, Client, or Agent is missing', [
                'clientId' => $clientId,
                'agentId' => $agentId,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong, please ensure all fields are filled correctly.'
            ], 400);
        }

        if ($gateway === 'myfatoorah') {

            $response = $this->getPaymentStatusMyFatoorah($importInvoiceId)->getData(true);

            if ($response['status'] === 'error') {
                Log::error('Error fetching payment status from MyFatoorah', [
                    'message' => $response['message']
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => $response['message']
                ], 400);
            }

            $data = [
                'invoice_id' => $importInvoiceId,
                'payment_gateway' => $response['payment_gateway'],
                'payment_method' => $response['payment_method_id'],
                'amount' => $response['amount'],
                'client_id' => $clientId,
                'agent_id' => $agentId,
                'notes' => 'Imported from MyFatoorah Portal with Invoice ID: ' . $response['invoice_id'],
                'source' => 'import',
            ];
        } elseif ($gateway === 'hesabe') {

            $response = $this->getHesabeTransaction($importOrderReference)->getData(true);

            if ($response['status'] === 'error') {
                Log::error('Error fetching payment status from Hesabe', [
                    'message' => $response['message'],
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => $response['message']
                ], 400);
            }

            $data = [
                'invoice_id' => $importOrderReference,
                'payment_gateway' => $response['payment_gateway'],
                'payment_method' => $response['payment_method_id'],
                'amount' => $response['amount'],
                'client_id' => $clientId,
                'agent_id' => $agentId,
                'notes' => 'Imported from Hesabe Portal with Order Reference Number: ' . $response['payment_reference'],
                'source' => 'import',
            ];
        } else {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unsupported payment gateway selected.'
            ], 400);
        }


        $response = $this->paymentStoreLinkProcess(new Request($data));

        if ($response['status'] === 'error') {
            Log::error('Error during payment store link process', ['message' => $response['message']]);
            return response()->json([
                'status' => 'error',
                'message' => $response['message']
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Payment imported successfully',
            'data' => [
                'client_id' => $clientId,
                'agent_id' => $agentId,
            ]
        ]);
    }

    public function importFromPayment(Request $request): RedirectResponse
    {
        $gateway = strtolower($request->input('gateway'));

        $request->validate([
            'gateway' => 'required|string|in:myfatoorah,hesabe',
            'import_invoice_id' => 'required_if:gateway,myfatoorah|string|nullable',
            'import_order_reference' => 'required_if:gateway,hesabe|string|nullable',
        ]);

        if ($gateway === 'myfatoorah') {
            $invoiceId = $request->input('import_invoice_id');

            $response = $this->getPaymentStatusMyFatoorah($invoiceId)->getData(true);
            session(['fatoorah_import' => $response]);

            if ($response['status'] === 'error') {
                Log::error('Error fetching payment status from MyFatoorah', ['message' => $response['message']]);
                return redirect()->back()->with('error', $response['message']);
            }

            return redirect()->route('payment.link.create')->withInput([
                'invoice_id'        => $response['invoice_id'],
                'payment_gateway'   => $response['payment_gateway'],
                'payment_method'    => $response['payment_method_id'],
                'amount'            => $response['amount'],
                'notes'             => 'Imported from MyFatoorah Portal with Invoice ID: ' . $response['invoice_id'],
                'source'            => 'import',
                'invoice_reference' => $response['invoice_reference'],
                'auth_code'         => $response['auth_code'],
            ]);
        } elseif ($gateway === 'hesabe') {
            $orderRef = $request->input('import_order_reference');

            $response = $this->getHesabeTransaction($orderRef)->getData(true);
            session(['hesabe_import' => $response]);

            if ($response['status'] === 'error') {
                return redirect()->back()->with('error', $response['message']);
            }

            return redirect()->route('payment.link.create')->withInput([
                'order_reference'       => $response['data']['reference_number'],
                'payment_gateway'       => 'Hesabe',
                'payment_method'        => $response['data']['payment_type'],
                'amount'                => $response['data']['amount'],
                'notes'                 => 'Imported from Hesabe Portal with Order Reference Number: ' . $response['data']['reference_number'],
                'source'                => 'import',
                'payment_reference'     => $response['data']['TransactionID'],
                'track_id'            => $response['data']['TrackID'],
            ]);
        }

        return redirect()->back()->with('error', 'Unsupported payment gateway selected.');
    }

    public function importPaymentProcess(Request $request)
    {
        Log::info('Starting the process of importing payment from Portal');

        $request->validate([
            'payment_gateway' => 'required',
            'payment_method' => 'nullable',
            'amount' => 'required|numeric',
            'client_id' => 'nullable',
            'agent_id' => 'nullable',
            'invoice_id' => 'nullable',
            'invoice_reference' => 'nullable',
            'auth_code' => 'nullable',
            'paymentReference' => 'nullable',
            'trackId' => 'nullable',
            'notes' => 'nullable|string|max:255'
        ]);

        $invoiceId = $request->input('invoice_id');
        $invoiceReference = $request->input('invoice_reference');
        $authCode = $request->input('auth_code');
        $paymentReference = $request->input('payment_reference');
        $trackId = $request->input('track_id');
        $companyId = null;
        $user = Auth::user();

        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company->id;
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = $user->agent->branch->company->id;
        }

        $voucherSequence = Sequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
        $client = Client::find($request->client_id);
        $agent = Agent::find($request->agent_id);

        if (!$client) {
            return [
                'status' => 'error',
                'message' => 'Client cannot be found'
            ];
        }

        if (!$agent) {
            return [
                'status' => 'error',
                'message' => 'Agent cannot be found'
            ];
        }

        $currentSequence = $voucherSequence->current_sequence;
        $voucherNumber = $this->generateVoucherNumber($currentSequence);

        try {
            $voucherSequence->current_sequence++;
            $voucherSequence->save();
        } catch (Exception $e) {
            logger('Failed to save voucher sequence', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        try {
            $data = [
                'voucher_number' => $voucherNumber,
                'payment_reference' => $invoiceId ?? $paymentReference,
                'invoice_reference' => $invoiceReference ?? $trackId,
                'auth_code' => $authCode,
                'from' => $client->full_name,
                'pay_to' => $agent->branch->company->name,
                'currency' => 'KWD',
                'payment_date' => Carbon::now(),
                'amount' => $request->amount,
                'payment_gateway' => $request->payment_gateway,
                'payment_method_id' => $request->payment_method,
                'status' => 'completed',
                'client_id' => $client->id,
                'agent_id' => $agent->id,
                'notes' => $request->notes,
                'created_by' => Auth::id()

            ];

            $payment = Payment::create($data);
            Log::info('Payment successfully created');

            if (!$payment) {
                Log::error('Payment failed to create');
            }

            if ($payment->payment_gateway === 'MyFatoorah') {
                $fatoorahPayload = $data ?? session()->pull('fatoorah_import');
                Log::info('MyFatoorah Payload', [
                    'fatoorah_payload' => $fatoorahPayload,
                ]);

                $fatoorahData = [
                    'payment_int_id' => $payment->id,
                    'payment_id' => $fatoorahPayload['user_defined']['payment_id'] ?? null,
                    'invoice_id' => $fatoorahPayload['invoice_id'] ?? null,
                    'invoice_reference' => $fatoorahPayload['invoice_reference'] ?? null,
                    'invoice_status' => $fatoorahPayload['invoice_status'] ?? null,
                    'customer_reference' => $fatoorahPayload['customer_name'] ?? null,
                    'payload' => $fatoorahPayload ?? null,
                ];

                $fatoorah = MyFatoorahPayment::create($fatoorahData);
                Log::info('MyFatoorah Payment successfully created');

                if (!$fatoorah) {
                    Log::error('MyFatoorah Payment failed to create');
                }
            } elseif ($payment->payment_gateway === 'Hesabe') {
                $hesabePayload = $data ?? session()->pull('hesabe_import');

                if (is_string($hesabePayload)) {
                    $hesabePayload = json_decode($hesabePayload, true);
                }

                Log::info('Hesabe Payload', ['hesabePayload' => $hesabePayload]);

                if (!$hesabePayload) {
                    Log::error('Hesabe payload not found in session');
                    return [
                        'status' => 'error',
                        'message' => 'Hesabe payload not found in session'
                    ];
                }

                $payload = $hesabePayload['data'] ?? null;

                $hesabeData = [
                    'payment_int_id' => $payment->id,
                    'status' => $payload['status'] ?? null,
                    'payment_token' => $payload['token'] ?? null,
                    'payment_id' => $payload['PaymentID'] ?? null,
                    'order_reference_number' => $payload['reference_number'] ?? null,
                    'auth_code' => $payload['auth'] ?? null,
                    'track_id' => $payload['TrackID'] ?? null,
                    'transaction_id' => $payload['TransactionID'] ?? null,
                    'invoice_id' => $payload['Id'] ?? null,
                    'paid_on' => $payload['datetime'] ?? null,
                    'payload' => $hesabePayload ?? null,
                ];

                $hesabe = HesabePayment::create($hesabeData);
                Log::info('Hesabe Payment successfully created');

                if (!$hesabe) {
                    Log::error('Hesabe Payment failed to create');
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to create payment', [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        try {
            $payment = Payment::findOrFail($payment->id);

            if ($payment->status === 'completed') {
                Log::info('Import payment has already been paid');

                $clientController = new ClientController;
                $addCredit = $clientController->addCredit($payment);

                if (isset($addCredit['error'])) {
                    Log::error('Failed to add credit to client', [
                        'status' => 'error',
                        'message' => $addCredit['error'],
                        'payment_id' => $payment->id,
                    ]);

                    return [
                        'status' => 'error',
                        'message' => 'Client credit cannot be updated',
                    ];
                }

                DB::commit();

                return [
                    'status' => 'success',
                    'message' => 'Successfully importing payment from payment gateway ' . $payment->payment_gateway . ' for payment ID ' . $payment->id,
                    'data' => [
                        'voucher_number' => $payment->voucher_number,
                        'payment_id' => $payment->id,
                    ],
                ];
            } elseif ($payment->status != 'completed') {
                return [
                    'status' => 'error',
                    'message' => 'Failed to add credit and journal entry as the payment is not yet completed'
                ];
            }
        } catch (Exception $e) {
            Log::error('Failed to add credit & journal entry for import payment from payment gateway ' . $payment->payment_gateway);

            return [
                'status' => 'error',
                'message' => 'Failed to add credit & journal entry for import payment',
            ];
        }
    }

    public function paymentLink(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);
        $agents = Agent::with('branch');

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $agents = $agents->whereHas('branch', fn($q) => $q->where('company_id', $companyId))->get();
            } else {
                $agents = $agents->get();
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $branches = Branch::where('company_id', $companyId)->get();
            $agents = Agent::whereIn('branch_id', $branches->pluck('id')->toArray())->get();
        } elseif ($user->role_id == Role::BRANCH) {
            $agents = Agent::where('branch_id', $user->branch->id)->get();
        } elseif ($user->role_id == Role::AGENT) {
            $agents = Agent::where('id', $user->agent->id)->get();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $branches = Branch::where('company_id', $companyId)->get();
            $agents = Agent::whereIn('branch_id', $branches->pluck('id')->toArray())->get();
        } else {
            return redirect()->back()->with('error', 'You are not authorized to view payment links.');
        }

        $agentsId = $agents->pluck('id')->toArray();

        $clients = Client::where(function ($query) use ($agentsId) {
            $query->whereIn('agent_id', $agentsId)
                ->orWhereHas('agents', function ($q) use ($agentsId) {
                    $q->whereIn('agent_id', $agentsId);
                });
        })->get();

        $payments = Payment::with('invoice')
            ->where(function ($query) use ($agentsId) {
                $query->whereHas('invoice', function ($payment) use ($agentsId) {
                    $payment->whereIn('agent_id', $agentsId);
                })->orWhereIn('agent_id', $agentsId);
            });

        if ($request->boolean('clear')) {
            session()->forget('filter');
            return redirect()->route('payment.link.index', array_filter([
                'q' => $request->query('q'),
            ]));
        }

        if ($search = $request->query('q')) {
            $payments = $payments->where(function ($query) use ($search) {
                $query->where('payment_reference', 'like', '%' . $search . '%')
                    ->orWhere('payment_gateway', 'like', '%' . $search . '%')
                    ->orWhere('voucher_number', 'like', '%' . $search . '%')
                    ->orWhereHas('paymentMethod', function ($q) use ($search) {
                        $q->where('english_name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('agent', function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('middle_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('country_code', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('myFatoorahPayment', function ($q) use ($search) {
                        $q->where('invoice_ref', 'like', '%' . $search . '%');
                    });
            });
        }

        $incoming = collect($request->input('filter', []))
            ->filter(fn($v) => is_array($v) ? array_filter($v, fn($x) => $x !== '' && $x !== null) : $v !== '' && $v !== null)
            ->all();

        if ($request->has('filter')) {
            session(['filter' => array_replace(session('filter', []), $incoming)]);
            return redirect()->route('payment.link.index', array_filter([
                'q' => $request->query('q'),
            ]));
        }

        $filters = session('filter', []);

        $payments->when(data_get($filters, 'client_id'), fn($q, $v) => $q->where('client_id', $v));
        $payments->when(data_get($filters, 'agent_id'), fn($q, $v) => $q->where('agent_id', $v));
        $payments->when(data_get($filters, 'payment_method_id'), fn($q, $v) => $q->where('payment_method_id', $v));
        $payments->when(data_get($filters, 'created_by'), fn($q, $v) => $q->where('created_by', $v));
        $payments->when(data_get($filters, 'payment_gateway'), fn($q, $v) => $q->whereIn('payment_gateway', (array)$v));
        $payments->when(data_get($filters, 'status'), fn($q, $v) => $q->whereIn('status', (array)$v));
        $payments->when(data_get($filters, 'date_from'), fn($q, $v) => $q->whereDate('created_at', '>=', $v));
        $payments->when(data_get($filters, 'date_to'), fn($q, $v) => $q->whereDate('created_at', '<=', $v));

        $payments = $payments->orderBy('id', 'desc')->paginate(15)->appends($request->only(['q']));

        $payments->getCollection()->transform(function ($payment) {
            if ($payment->payment_gateway === 'MyFatoorah') {
                $mfPayment = MyFatoorahPayment::where('payment_int_id', $payment->id)->first();
                $payment->invoice_ref = $mfPayment->invoice_ref ?? null;
            } else {
                $payment->invoice_ref = null;
            }
            return $payment;
        });

        $paymentGateways = Charge::where('can_generate_link', true)
            ->where('is_active', true)->get();

        foreach ($payments as $payment) {
            $payment->selected_gateway = $paymentGateways->where('name', $payment->payment_gateway)->first();
            $payment->selected_method = PaymentMethod::where('id', $payment->payment_method_id)->first();
        }

        $users = User::whereIn('id', Payment::select('created_by')->distinct()->pluck('created_by'))->get();
        $status = ['pending', 'initiate', 'completed', 'failed', 'cancelled'];

        $paymentMethodChose = $companyId
            ? PaymentMethodChose::where('company_id', $companyId)->get()
            : collect();

        return view('payment.link.index', compact(
            'payments',
            'clients',
            'agents',
            'paymentGateways',
            'users',
            'status',
            'filters',
            'paymentMethodChose'
        ));
    }

    public function paymentCreateLink(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);
        $agents = collect();
        $agentsId = [];

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $agents = Agent::with('branch.company')
                    ->whereHas('branch', fn($q) => $q->where('company_id', $companyId))
                    ->get();
            } else {
                $agents = Agent::with('branch.company')->get();
            }
            $agentsId = $agents->pluck('id')->toArray();
        } elseif ($user->role_id == Role::COMPANY) {
            $branches = Branch::where('company_id', $companyId)->get();
            $agents = Agent::whereIn('branch_id', $branches->pluck('id')->toArray())->get();
            $agentsId = $agents->pluck('id')->toArray();
        } elseif ($user->role_id == Role::BRANCH) {
            $agents = Agent::where('branch_id', $user->branch->id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        } elseif ($user->role_id == Role::AGENT) {
            $agents = Agent::where('id', $user->agent->id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        } else {
            return redirect()->back()->with('error', 'You are not authorized to create payment links.');
        }

        if ($user->role_id == Role::ADMIN && !$companyId) {
            $clients = Client::all();
        } else {
            $clients = Client::where(function ($query) use ($agentsId) {
                $query->whereIn('agent_id', $agentsId)
                    ->orWhereHas('agents', function ($q) use ($agentsId) {
                        $q->whereIn('agent_id', $agentsId);
                    });
            })->get();
        }

        $invoices = Invoice::all();
        $payments = Payment::all();
        $currencies = Currency::all();

        $paymentGateways = Charge::with('methods')->where('is_active', true)->get();
        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        $gatewayMethods = [];
        foreach ($paymentGateways as $gateway) {
            $methods = PaymentMethod::where('is_active', true)
                ->where('type', $gateway->name);

            if ($companyId) {
                $methods = $methods->where('company_id', $companyId);
            }

            $methods = $methods->get();

            if ($methods->isNotEmpty()) {
                $gatewayMethods[strtolower($gateway->name)] = $methods;
            }
        }

        if ($companyId) {
            $paymentMethodChose = PaymentMethodChose::where('company_id', $companyId)->get();
            $can_import = Charge::where('company_id', $companyId)
                ->where('can_import', true)
                ->get();
        } else {
            $paymentMethodChose = collect();
            $can_import = collect();
        }

        $sendPaymentReceipt = UserSetting::getValue(Auth::id(), 'payment_whatsapp_notification');

        return view('payment.link.create', compact(
            'payments',
            'clients',
            'agents',
            'invoices',
            'currencies',
            'paymentGateways',
            'paymentMethods',
            'gatewayMethods',
            'can_import',
            'paymentMethodChose',
            'sendPaymentReceipt',
        ));
    }

    public function paymentStoreLinkProcess(Request $request)
    {
        $source = $request->input('source');
        $invoiceId = $request->input('invoice_id');
        $invoiceReference = $request->input('invoice_reference');

        if ($source === 'import') {
            return $this->importPaymentProcess($request);
        }

        $request->validate([
            'payment_gateway' => 'required',
            'payment_method' => 'nullable',
            'amount' => 'nullable|numeric',
            'client_id' => 'required|integer|exists:clients,id',
            'agent_id' => 'required|integer|exists:agents,id',
            'invoice_id' => 'nullable',
            'invoice_reference' => 'nullable',
            'auth_code' => 'nullable',
            'paymentReference' => 'nullable',
            'trackId' => 'nullable',
            'notes' => 'nullable|string|max:255',
            'terms_conditions' => 'nullable|string|max:99999',
            'currency' => 'nullable|string|max:3',
            'company_id' => 'nullable|integer|exists:companies,id',
            'language' => 'nullable',
            'items' => 'nullable|array|min:1',
            'items.*.product_name' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|numeric|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.extended_amount' => 'required_with:items|numeric',
            'items.*.currency' => 'required_with:items|string|max:10',
        ]);

        $isAdvancedMode = $request->has('items') && is_array($request->items) && count($request->items) > 0;

        if ($isAdvancedMode && (!$request->items || count($request->items) === 0)) {
            Log::error('[PAYMENT LINK] No items provided in advanced mode');
            return ['status' => 'error', 'message' => 'At least one item is required in Advanced mode'];
        }

        if (!$isAdvancedMode && !$request->amount) {
            Log::error('[PAYMENT LINK] No amount provided in quick mode');
            return ['status' => 'error', 'message' => 'Amount is required in Quick mode'];
        }

        if (!$request->company_id) {
            $companyId = null;
            $user = Auth::user();

            if ($user->role_id == Role::COMPANY) {
                $companyId = $user->company->id;
            } elseif ($user->role_id == Role::BRANCH) {
                $companyId = $user->branch->company->id;
            } elseif ($user->role_id == Role::AGENT) {
                $companyId = $user->agent->branch->company->id;
            }
        } else {
            $companyId = $request->company_id;
        }

        $company = $companyId ? Company::find($companyId) : null;
        $companyEmail = $company?->email ?? 'admin@citytravelers.co';

        $voucherSequence = Sequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
        $client = Client::find($request->client_id);
        $agent = Agent::find($request->agent_id);

        if (!$client) {
            return ['status' => 'error', 'message' => 'Client cannot be found'];
        }

        if (!$agent) {
            return ['status' => 'error', 'message' => 'Agent cannot be found'];
        }

        $currentSequence = $voucherSequence->current_sequence;
        $voucherNumber = $this->generateVoucherNumber($currentSequence);

        try {
            $voucherSequence->current_sequence++;
            $voucherSequence->save();
        } catch (Exception $e) {
            logger('Failed to save voucher sequence', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        $paymentMethodId = (int) $request->payment_method;

        $totalAmountInKWD = 0;
        $convertedItems = [];

        if ($isAdvancedMode) {
            foreach ($request->items as $item) {
                $itemAmountInKWD = $item['extended_amount'];

                if (strtoupper($item['currency']) !== 'KWD') {
                    $conversionResult = $this->convert(
                        $companyId,
                        strtoupper($item['currency']),
                        'KWD',
                        $item['extended_amount']
                    );

                    if ($conversionResult['status'] === 'error') {
                        Log::error('[PAYMENT LINK] Currency conversion failed', [
                            'from' => $item['currency'],
                            'to' => 'KWD',
                            'amount' => $item['extended_amount'],
                            'error' => $conversionResult['message']
                        ]);
                        return ['status' => 'error', 'message' => 'Currency exchange rate not found for ' . $item['currency'] . ' to KWD'];
                    }

                    $itemAmountInKWD = $conversionResult['converted_amount'];
                    Log::info('[PAYMENT LINK] Converted item amount', [
                        'product' => $item['product_name'],
                        'from_currency' => $item['currency'],
                        'original_amount' => $item['extended_amount'],
                        'exchange_rate' => $conversionResult['exchange_rate'],
                        'kwd_amount' => $itemAmountInKWD
                    ]);
                }

                $totalAmountInKWD += $itemAmountInKWD;
                $convertedItems[] = array_merge($item, ['kwd_amount' => $itemAmountInKWD]);
            }
        } else {
            $totalAmountInKWD = $request->amount;
        }

        $totalAmount = $totalAmountInKWD;

        Log::info('[PAYMENT LINK] Mode: ' . ($isAdvancedMode ? 'Advanced' : 'Quick') . ', Total: ' . $totalAmount . ' KWD');

        $chargeResult = ChargeService::calculate($totalAmount, $companyId, $paymentMethodId, $request->payment_gateway);
        $serviceCharge = $chargeResult['gatewayFee'] ?? 0;

        try {
            $data = [
                'voucher_number' => $voucherNumber,
                'payment_reference' => $invoiceId,
                'invoice_reference' => $invoiceReference,
                'from' => $client->full_name,
                'pay_to' => $agent->branch->company->name,
                'currency' => 'KWD',
                'payment_date' => Carbon::now(),
                'amount' => $totalAmount,
                'service_charge' => $serviceCharge,
                'payment_gateway' => $request->payment_gateway,
                'payment_method_id' => $request->payment_method,
                'status' => 'pending',
                'client_id' => $client->id,
                'agent_id' => $agent->id,
                'notes' => $request->notes,
                'terms_conditions' => $request->terms_conditions,
                'language' => $request->language,
                'created_by' => Auth::id()
            ];

            $payment = Payment::create($data);
            Log::info('[PAYMENT LINK] Created payment', ['payment_id' => $payment->id, 'voucher' => $voucherNumber]);

            if ($isAdvancedMode && !empty($request->items)) {
                foreach ($request->items as $item) {
                    $payment->paymentItems()->create([
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'extended_amount' => $item['extended_amount'],
                        'currency' => $item['currency'],
                    ]);
                }
                Log::info('[PAYMENT LINK] Created ' . count($request->items) . ' payment items for payment ID: ' . $payment->id);
            }
        } catch (Exception $e) {
            Log::error('[PAYMENT LINK] Failed to create payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        return [
            'status' => 'success',
            'message' => 'Payment Link Created',
            'clientEmail' => $companyEmail,
            'data' => $payment
        ];
    }

    public function paymentStoreLink(Request $request)
    {
        if ($request->payment_gateway == null) {

            Log::info("multi payment method invoke at paymentStoreLink");

            $request->validate([
                'payment_methods' => 'required'
            ]);

            $response = $this->multiPaymentMethodProcess($request);

            $route = $response['payment_id'] ? route('payment.show', $response['payment_id']) : route('payment.link.index');

            return auth()->check() ? redirect()->to($route)->with($response['success'], $response['message']) : redirect()->back()->with($response['success'] ? 'success' : 'error', $response['message']);
        }

        // old process (backward compatibility)
        $response = $this->paymentStoreLinkProcess($request);
        if ($response['status'] === 'error') {
            return redirect()->back()->with('error', $response['message']);
        }

        $voucherNumber = $response['data']['voucher_number'];
        $paymentUrl = url('/payment/link/show/' . $voucherNumber);
        // Mail::to($response['clientEmail'])->send(new PaymentLinkEmail($paymentUrl));
        return redirect()->route('payment.link.index')->with('success', 'Payment link created successfully!');
    }

    public function paymentShowLink($companyId, $voucherNumber)
    {
        $payment = Payment::with(['agent.branch.company', 'client', 'paymentItems'])
            ->where('voucher_number', $voucherNumber)
            ->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId))
            ->first();

        if (!$payment) {
            return Auth::user() ? redirect()->route('payment.link.index') : abort(404);
        }

        if (!$payment->client) {
            return Auth::user() ? redirect()->route('payment.link.index') : abort(404);
        }

        if (!$payment->agent) {
            return Auth::user() ? redirect()->route('payment.link.index') : abort(404);
        }

        $locale = $payment->language === 'ARB' ? 'ar' : 'en';
        app()->setLocale($locale);

        $payment = Payment::with('agent', 'client', 'paymentItems')->where('id', $payment->id)->first();

        $fatoorahPayment = $payment->myFatoorahPayment;

        $invoiceRef = null;
        $authorizationId = null;

        if ($fatoorahPayment) {
            $invoiceRef = $fatoorahPayment->invoice_ref ?? null;
            $payloadData = $fatoorahPayment->payload;

            if (empty($invoiceRef) && is_array($payloadData) && isset($payloadData['Data'])) {
                $invoiceRef = $payloadData['Data']['InvoiceReference'] ?? null;
            }
            if (is_array($payloadData) && isset($payloadData['Data']['InvoiceTransactions'])) {
                $transactions = $payloadData['Data']['InvoiceTransactions'];
                if (!empty($transactions)) {
                    $authorizationId = $transactions[0]['AuthorizationId'] ?? null;
                }
            }
        }

        $companyId = optional($payment->agent->branch)->company_id;
        $chargeResult = [];
        $gatewayFee = 0;
        $finalAmount = 0;
        $chargeData = [
            'amount'    => $payment->amount,
            'client_id' => $payment->client_id,
            'agent_id'  => $payment->agent_id,
            'currency'  => $payment->currency,
        ];

        if ($payment->status === 'completed' && is_null($payment->service_charge)) {
            if ($payment->invoice) {
                $invoicePartial = InvoicePartial::where('invoice_id', $payment->invoice->id)->first();
                if ($invoicePartial) {
                    $gatewayFee = $invoicePartial->service_charge ?? 0;
                    $finalAmount = $payment->amount;
                } else {
                    $gatewayFee = 0;
                    $finalAmount = $payment->amount;
                }
            } else {

                $tempChargeResult = [
                    'finalAmount' => $payment->amount,
                    'gatewayFee' => 0,
                    'amount' => $payment->amount,
                    'gatewayFee' => 0,
                ];

                try {
                    $tempChargeResult = ChargeService::calculate($payment->amount, $companyId, $payment->payment_method_id, $payment->payment_gateway);
                } catch (Exception $e) {
                    Log::error('getFee exception in paymentShowLink', [
                        'gateway' => $payment->payment_gateway,
                        'message' => $e->getMessage(),
                        'payment_id' => $payment->id,
                    ]);
                }

                $gatewayFee = $tempChargeResult['gatewayFee'] ?? 0;
                $finalAmount = $payment->amount;
            }
        } else if ($payment->status !== 'completed') {
            $chargeData = [
                'amount'     => $payment->amount,
                'currency'   => $payment->currency,
                'client_id'  => $payment->client_id,
                'agent_id'   => $payment->agent_id,
            ];

            $chargeResult = [];

            try {
                $chargeResult = ChargeService::calculate($payment->amount, $companyId, $payment->payment_method_id, $payment->payment_gateway);
            } catch (Exception $e) {
                Log::error('getFee exception in paymentShowLink (unpaid)', [
                    'gateway' => $payment->payment_gateway,
                    'message' => $e->getMessage(),
                    'payment_id' => $payment->id,
                ]);
                $chargeResult = ['gatewayFee' => 0, 'finalAmount' => $payment->amount, 'paid_by' => 'Company'];
            }

            $gatewayFee = $chargeResult['gatewayFee'] ?? 0;
            $finalAmount = $chargeResult['finalAmount'] ?? $payment->amount;

            $payment->service_charge = ($chargeResult['paid_by'] === 'Company') ? 0 : $chargeResult['gatewayFee'];
            $payment->save();
        } else {
            $gatewayFee = $payment->service_charge ?? 0;
            $finalAmount = $payment->amount + $gatewayFee;
        }

        $payment->load(['availablePaymentMethodGroups']);

        if ($payment->availablePaymentMethodGroups->isEmpty()) {
            return view('payment.link.show', compact(
                'payment',
                'chargeResult',
                'gatewayFee',
                'finalAmount',
                'invoiceRef',
                'authorizationId',
            ));
        }

        $availablePaymentMethods = collect();

        foreach ($payment->availablePaymentMethodGroups as $group) {

            $chose = PaymentMethodChose::where('company_id', $companyId)
                ->where('payment_method_group_id', $group->id)
                ->with(['paymentMethod.charge', 'paymentMethod.paymentMethodGroup'])
                ->first();

            $currentMethod = null;

            if ($chose && $chose->paymentMethod && $chose->paymentMethod->is_active) {
                $currentMethod = $chose->paymentMethod;
            } else {
                $currentMethod = PaymentMethod::withoutGlobalScope('company')
                    ->with(['paymentMethodGroup', 'charge'])
                    ->where('company_id', $companyId)
                    ->where('payment_method_group_id', $group->id)
                    ->where('is_active', 1)
                    ->first();
            }

            if ($currentMethod) {
                try {
                    $feeResult = ChargeService::calculate($payment->amount, $companyId, $currentMethod->id, $currentMethod->charge->name ?? null);

                    $currentMethod->calculated_fee = $feeResult['gatewayFee'] ?? 0;
                    $currentMethod->final_amount = $feeResult['finalAmount'] ?? $payment->amount;
                    $currentMethod->paid_by = $feeResult['paid_by'] ?? 'Company';
                } catch (Exception $e) {
                    Log::error('Failed to calculate fee for payment method', [
                        'payment_method_id' => $currentMethod->id,
                        'error' => $e->getMessage(),
                    ]);

                    $currentMethod->calculated_fee = 0;
                    $currentMethod->final_amount = $payment->amount;
                    $currentMethod->paid_by = 'Company';
                }

                $availablePaymentMethods->push($currentMethod);
            }
        }

        if ($availablePaymentMethods->isEmpty()) {
            return view('payment.link.show', compact('payment', 'chargeResult', 'gatewayFee', 'finalAmount', 'invoiceRef', 'authorizationId'));
        }

        $payment->setRelation('availablePaymentMethods', $availablePaymentMethods);

        return view('payment.link.multi-payment', compact(
            'payment',
            'chargeResult',
            'gatewayFee',
            'finalAmount',
            'invoiceRef',
            'authorizationId',
        ));
    }

    public function paymentLinkInitiate(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,id',
        ]);

        // $auth = Auth::user();

        $payment = Payment::with('invoice')->find($request->payment_id);

        if (!$payment) {
            if (Auth::user()) {
                return redirect()->back()->with('error', 'Payment not found.');
            }

            return abort(404);
        }

        $process = 'topup';
        if ($payment->invoice) {
            $process = 'invoice';
        }
        $paymentGateway = $payment->payment_gateway;
        $paymentMethod = $payment->paymentMethod?->myfatoorah_id;

        if (strtolower($paymentGateway) === 'tap') {
            $tap = new Tap();
            $paymentMethod = $payment->paymentMethod ? $payment->paymentMethod->id : null;

            $chargeResult = ChargeService::calculate($payment->amount, $payment->agent->branch->company_id, $paymentMethod, 'Tap');

            $finalAmount = $chargeResult['finalAmount'];

            $requestTap = new Request([
                'finalAmount' => $finalAmount,
                'client_name' => $payment->client->full_name,
                'client_email' => $payment->client->email,
                'voucher_number' => $payment->voucher_number,
                'payment_id' => $payment->id,
                'payment_gateway' => $paymentGateway,
                'payment_method_id' => $paymentMethod,
                'description' => 'Payment for ' . $payment->voucher_number,
                'process' => $process,
            ]);

            Log::info('requestTap', ['requestTap' => $requestTap]);

            $response = $tap->createCharge($requestTap);
            logger('Payment link initiate response', ['response' => $response]);

            if (isset($response['errors'])) {
                return redirect()->back()->with('error', $response['errors'][0]['description']);
            }

            $paymentUrl = $response['transaction']['url'];
            return redirect($paymentUrl);
        } else if (strtolower($paymentGateway) === 'myfatoorah') {
            $configService = new GatewayConfigService();
            $myfatoorahConfig = $configService->getMyFatoorahConfig();

            if (!$myfatoorahConfig['status'] || !$myfatoorahConfig['data']) {
                return redirect()->back()->with('error', $myfatoorahConfig['message'] ?? 'MyFatoorah configuration is missing or inactive');
            }

            $myfatoorahConfig = $myfatoorahConfig['data'];

            $apiKey  = $myfatoorahConfig['api_key'];
            $baseUrl = $myfatoorahConfig['base_url'];

            $payment = Payment::with('agent', 'client')->where('id', $payment->id)->first();
            $companyId = $payment->agent->branch->company_id;

            if (!$companyId) {
                Log::error('Company ID not found for the payment.', ['payment_id' => $payment->id]);
                return Auth::user() ? redirect()->back()->with('error', 'Company ID not found for the payment.') : abort(500);
            }

            if ($payment->status === 'initiate') {
                if ($payment->payment_url && $payment->expiry_date && now()->lt($payment->expiry_date)) {
                    Log::info('Reusing existing payment URL', [
                        'invoice_id' => $payment->payment_reference,
                        'url' => $payment->payment_url,
                        'expires_at' => $payment->expiry_date,
                    ]);

                    return redirect($payment->payment_url);
                }
                Log::info('Old payment URL expired, reinitiating new payment');
                return $this->paymentLinkReinitiate($payment->payment_reference);
            } elseif (in_array(strtolower($payment->status), ['completed', 'paid'])) {
                Log::info('Initiate payment ignored: payment already completed', ['payment_id' => $payment->id]);
                $partialId = $payment->invoice?->invoicePartials()->where('payment_id', $payment->id)->value('id');
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);
                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
            }

            //filter record
            $firstName = $payment->client->first_name;
            $middleName = $payment->client->middle_name ?? '';
            $lastName = $payment->client->last_name ?? '';

            $customerName = trim("$firstName $middleName $lastName");

            $client = $payment->client;
            $clientPhone = $client->phone ?? null;

            if (isset($clientPhone) && strpos($clientPhone, '+') === 0) {
                // Remove country code if present (e.g., +96512345678 -> 12345678)
                $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
                $clientPhone = ltrim($clientPhone, '0'); // Optionally remove leading zero
            }

            $chargeResult = ChargeService::calculate($payment->amount, $companyId, $payment->payment_method_id, 'MyFatoorah');
            $finalAmount = $chargeResult['finalAmount'];

            $company = $companyId ? Company::find($companyId) : null;
            $companyEmail = $company?->email ?? 'admin@citytravelers.co';

            $executePayload = [
                "PaymentMethodId"     => $paymentMethod,
                "InvoiceValue"        => $finalAmount,
                "CustomerName"       => $customerName ?? 'Customer',
                "CustomerEmail"       => $companyEmail,
                "MobileCountryCode"   => $client->country_code ?? '+965',
                "CustomerMobile"      => $clientPhone ?? '50000000',
                "DisplayCurrencyIso"  => $payment->currency ?? 'KWD',
                "CallBackUrl"         => route('payments.callback'),
                "ErrorUrl"            => route('payments.error', ['payment_id' => $payment->id]),
                // "ErrorUrl"            => route('payments.error'),
                "Language"            => "en",
                "UserDefinedField"   => json_encode([
                    'voucher_number' => $payment->voucher_number,
                    'payment_id' => $payment->id,
                    'payment_gateway' => $paymentGateway,
                    'payment_method' => $paymentMethod,
                    'process' => $process,
                ]),
                "InvoiceItems" => [
                    [
                        "ItemName"   => "Voucher " . $payment->voucher_number,
                        "Quantity"   => 1,
                        "UnitPrice"  => $finalAmount,
                    ]
                ],
            ];

            Log::info('MyFatoorah ExecutePayment request', [
                'payload' => $executePayload,
                'api_key' => $apiKey,
                'base_url' => $baseUrl,
            ]);

            $executeResponse = Http::withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Content-Type' => 'application/json',
            ])->post("$baseUrl/ExecutePayment", $executePayload);

            if (!$executeResponse->successful()) {
                Log::error('MyFatoorah: ExecutePayment failed', ['response' => $executeResponse->body()]);
                return redirect()->back()->with('error', 'ExecutePayment failed.');
            }

            $resData = $executeResponse->json();
            $invoiceUrl = $resData['Data']['PaymentURL'] ?? null;
            $mfInvoiceId = $resData['Data']['InvoiceId'] ?? null;
            $expiryDateURL = $resData['Data']['ExpiryDate'] ?? null;

            if ($invoiceUrl && $mfInvoiceId) {
                $payment->payment_reference = $mfInvoiceId;
                $payment->payment_url = $invoiceUrl;
                $payment->expiry_date = $expiryDateURL ? Carbon::parse($expiryDateURL) : now()->addDays(2);
                $payment->status = 'initiate';
                $payment->save();

                Log::info('MyFatoorah payment initiated', [
                    'old_invoice_id' => $mfInvoiceId,
                    'old_url' => $invoiceUrl,
                    'old_expires_at' => $payment->expiry_date,
                ]);
                return redirect($invoiceUrl);
            }

            return redirect()->back()->with('error', 'MyFatoorah response missing PaymentURL or InvoiceId.');
        } elseif (strtolower($paymentGateway) === 'hesabe') {

            $companyId = $payment->agent->branch->company_id;
            $company = Company::find($companyId);
            $configService = new GatewayConfigService();
            $hesabeConfig = $configService->getHesabeConfig();

            if (!$hesabeConfig['status'] || !$hesabeConfig['data']) {
                return redirect()->back()->with('error', $hesabeConfig['message'] ?? 'Hesabe configuration is missing or inactive');
            }

            $apiKey = Charge::where('company_id', $companyId)
                ->where('name', 'Hesabe')
                ->pluck('api_key')
                ->first();
            Log::info('API key received from database', ['api_key' => $apiKey]);

            if (!$apiKey) {
                return redirect()->back()->with('error', 'API key of ' . ucwords($paymentGateway) . ' gateway for company ' . $company->name . ' does not exist. Contact support team for more details');
            }

            /* $apiKey = $hesabeConfig['data']['api_key']; */
            $baseUrl = $hesabeConfig['data']['base_url'];
            $accessCode = $hesabeConfig['data']['access_code'];
            $merchantCode = $hesabeConfig['data']['merchant_code'];
            $encryptionKey = $hesabeConfig['data']['iv_key'];

            $payment = Payment::with('agent', 'client')->where('id', $payment->id)->first();
            $paymentMethod = $payment->paymentMethod?->myfatoorah_id;

            $firstName = $payment->client->first_name;
            $middleName = $payment->client->middle_name;
            $lastName = $payment->client->last_name;
            $customerName = trim("$firstName $middleName $lastName");

            $chargeResult = ChargeService::calculate($payment->amount, $companyId, $payment->payment_method_id, 'Hesabe');
            $finalAmount = $chargeResult['finalAmount'] ?? $payment->amount;

            $checkoutPayload = [
                "amount" => $finalAmount,
                "currency" => 'KWD',
                "paymentType" => $paymentMethod,
                "orderReferenceNumber" => $payment->voucher_number,
                "name" => $customerName,
                "version" => '2.0',
                "merchantCode" => $merchantCode,
                "variable1" => 'topup',
                "responseUrl" => route('payment.hesabe.response'),
                "failureUrl" => route('payment.hesabe.failure'),
                'webhookUrl' => route('payment.hesabe.webhook'),
            ];

            $requestDataJson = json_encode($checkoutPayload);
            Log::info('RequestData: ', ['json' => $requestDataJson]);

            $encryptedData = HesabeCrypt::encrypt($requestDataJson, $apiKey, $encryptionKey);
            Log::info('EncryptedData: ', ['encrypted_data' => $encryptedData]);

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "$baseUrl/checkout",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => array('data' => $encryptedData),
                CURLOPT_HTTPHEADER => array(
                    "accessCode: $accessCode",
                    "Accept: application/json"
                ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            Log::info('Checkout response: ', ['response', $response]);

            if (!$response) {
                Log::error('Hesabe: cURL error ', ['response' => $response]);
                return redirect()->back()->with('error', 'Hesabe checkout failed due to cURL error');
            }

            $decryptedData = HesabeCrypt::decrypt($response, $apiKey, $encryptionKey);
            Log::info('Hesabe decryption: ' . $decryptedData);

            if (!$decryptedData) {
                Log::error('Hesabe: Decryption failed ', ['response' => $decryptedData]);
                return redirect()->back()->with('error', 'Hesabe decryption failed');
            }

            $responseData = json_decode($decryptedData, true);
            Log::info('Response data: ', ['response', $responseData]);

            if (!$responseData) {
                Log::error('Hesabe: Checkout failed', ['response' => $responseData]);
                return redirect()->back()->with('error', 'Hesabe checkout failed, no response data');
            }

            $responseToken = $responseData['response']['data'];
            $paymentUrl = $baseUrl . '/payment' . '?data=' . $responseToken;

            if ($paymentUrl) {
                $payment->payment_url = $paymentUrl;
                $payment->status = 'initiate';
                $payment->save();

                Log::info('Hesabe payment initiated', [
                    'payment_id' => $payment->id,
                    'payment_url' => $paymentUrl,
                    'payment_status' => $payment->status,
                ]);

                return redirect($paymentUrl);
            } else {
                Log::error('Hesabe: Missing token for payment URL', [
                    'response_token' => $responseData['response']['data'],
                    'payment_url' => $paymentUrl,
                ]);
                return redirect()->back()->with('error', 'Hesabe response missing token for PaymentURL');
            }
        } elseif (strtolower($paymentGateway) === 'upayment') {
            if ($payment->status === 'initiate') {
                if ($payment->payment_url && $payment->expiry_date && now()->lt($payment->expiry_date)) {
                    Log::info('Reusing existing payment URL', [
                        'invoice_id' => $payment->payment_reference,
                        'url' => $payment->payment_url,
                        'expires_at' => $payment->expiry_date,
                    ]);

                    return redirect($payment->payment_url);
                }
                Log::info('Old payment URL expired, reinitiating new payment');
                return $this->paymentLinkReinitiate($payment->payment_reference);
            }


            $payment->load(['agent.branch.company', 'client']);
            $company = $payment->agent?->branch?->company;
            $client = $payment->client;

            $clientPhone = $client->phone ?? null;
            if ($clientPhone && str_starts_with($clientPhone, '+')) {
                $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
                $clientPhone = ltrim($clientPhone, '0');
            }

            $chargeResult = ChargeService::calculate($payment->amount, $company->id, $payment->payment_method_id, 'UPayment');
            $finalAmount  = $chargeResult['finalAmount'] ?? $payment->amount;

            $requestUPayment = new Request([
                'final_amount'      => $finalAmount,
                'client_id'         => $client->id,
                'client_name'       => $client->full_name,
                'client_email'      => $client->email ?? $company?->email,
                'client_phone'      => $clientPhone ?? '50000000',
                'company_email'     => $company?->email,
                'payment_id'        => $payment->id,
                'payment_number'    => $payment->voucher_number,
                'payment_method_id' => $payment->payment_method_id,
                'invoice_id'        => optional($payment->invoice)->id,
                'invoice_number'    => optional($payment->invoice)->invoice_number,
                'currency'          => $payment->currency ?? 'KWD',
            ]);

            $uPayment = new UPayment();
            $response = $uPayment->makeCharge($requestUPayment);

            if (!is_array($response)) {
                Log::error('UPayments: Unexpected response', ['raw' => $response]);
                return redirect()->back()->with('error', 'UPayments: unexpected response');
            }

            if (isset($response['status']) && $response['status'] === 'error') {
                return redirect()->back()->with('error', $response['message'] ?? 'UPayments error');
            }

            $paymentReference = $response['data']['trackId'] ?? null;
            $paymentUrl = $response['data']['link'] ?? null;
            $expiryDate = $response['transaction']['expiryDate'] ?? $response['data']['expiryDate'] ?? null;

            if ($paymentUrl && $paymentReference) {
                $payment->payment_reference = $paymentReference;
                $payment->payment_url = $paymentUrl;
                $payment->expiry_date = $expiryDate ? Carbon::parse($expiryDate) : now()->addDays(2);
                $payment->status = 'initiate';
                $payment->save();

                Log::info('UPayments payment initiated', [
                    'payment_id'  => $payment->id,
                    'track_id'    => $paymentReference,
                    'payment_url' => $paymentUrl,
                    'expires_at'  => $payment->expiry_date,
                ]);

                return redirect($paymentUrl);
            }
            Log::error('UPayments: Missing link or trackId', ['response' => $response]);
            return redirect()->back()->with('error', 'UPayments response missing link or trackId.');
        }

        return redirect()->route('payment.link.index')->with('success', 'Payment initiated successfully!');
    }

    public function paymentLinkReinitiate($paymentReference)
    {
        $payment = Payment::with(['client', 'agent.branch.company', 'paymentMethod'])->where('payment_reference', $paymentReference)->first();
        if (!$payment || $payment->status !== 'initiate') {
            return redirect()->back()->with('error', 'Invalid or already processed payment.');
        }

        Log::info('Reinitiating payment link', ['payment_reference' => $paymentReference]);

        $configService = new GatewayConfigService();
        $myfatoorahConfig = $configService->getMyFatoorahConfig();

        if (!$myfatoorahConfig['status'] || !$myfatoorahConfig['data']) {
            return redirect()->back()->with('error', $myfatoorahConfig['message'] ?? 'MyFatoorah configuration is missing or inactive');
        }

        $gateway = strtolower($payment->payment_gateway);
        $company = $payment->agent?->branch?->company;
        $client  = $payment->client;

        $clientPhone = $client->phone ?? '50000000';
        if (str_starts_with($clientPhone, '+')) {
            $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
            $clientPhone = ltrim($clientPhone, '0');
        }

        switch ($gateway) {
            case 'myfatoorah':
                return $this->reinitiateMyFatoorah($payment, $company, $client, $clientPhone);

            case 'upayment':
                return $this->reinitiateUPayment($payment, $company, $client, $clientPhone);

            default:
                return redirect()->back()->with('error', "Reinitiation not supported for gateway: {$payment->payment_gateway}");
        }
    }

    protected function reinitiateMyFatoorah($payment, $company, $client, $clientPhone)
    {
        $configService = new GatewayConfigService();
        $config = $configService->getMyFatoorahConfig();

        $companyId = $payment->agent->branch->company_id;
        if (!$companyId) {
            Log::error('reinitiateMyFatoorah: Company ID not found for the payment.', ['payment_id' => $payment->id]);
            return Auth::user() ? redirect()->back()->with('error', 'Company ID not found for the payment.') : abort(500);
        }

        $company = $companyId ? Company::find($companyId) : null;
        $companyEmail = $company?->email ?? 'admin@citytravelers.co';

        if (!$config['status'] || !$config['data']) {
            return redirect()->back()->with('error', $config['message'] ?? 'MyFatoorah config missing or inactive.');
        }

        $cfg = $config['data'];
        $apiKey = $cfg['api_key'];
        $baseUrl = $cfg['base_url'];

        $chargeResult = ChargeService::calculate($payment->amount, $company->id, $payment->payment_method_id, 'MyFatoorah');
        $finalAmount = $chargeResult['finalAmount'];

        $executePayload = [
            "PaymentMethodId"     => $payment->paymentMethod?->myfatoorah_id,
            "InvoiceValue"        => $finalAmount,
            "CustomerName"        => $client->full_name,
            "CustomerEmail"       => $companyEmail,
            "MobileCountryCode"   => $client->country_code ?? '+965',
            "CustomerMobile"      => $clientPhone,
            "DisplayCurrencyIso"  => $payment->currency ?? 'KWD',
            "CallBackUrl"         => route('payments.callback'),
            "ErrorUrl"            => route('payments.error', ['payment_id' => $payment->id]),
            "Language"            => "en",
            "UserDefinedField"    => json_encode([
                'voucher_number'   => $payment->voucher_number,
                'payment_id'       => $payment->id,
                'payment_gateway'  => $payment->payment_gateway,
                'payment_method'   => $payment->paymentMethod?->myfatoorah_id,
                'process'          => $payment->invoice ? 'invoice' : 'topup',
            ]),
            "InvoiceItems" => [
                [
                    "ItemName"   => "Voucher " . $payment->voucher_number,
                    "Quantity"   => 1,
                    "UnitPrice"  => $finalAmount,
                ]
            ],
        ];

        $executeResponse = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type'  => 'application/json',
        ])->post("$baseUrl/ExecutePayment", $executePayload);

        if (!$executeResponse->successful()) {
            Log::error('MyFatoorah reinitiate failed', ['response' => $executeResponse->body()]);
            return Auth::user() ? redirect()->route('invoices.index')->with('error', 'Failed to reinitiate MyFatoorah payment.') : abort(500);
        }

        $resData = $executeResponse->json() ?? [];
        $invoiceUrl = $resData['Data']['PaymentURL'] ?? null;
        $mfInvoiceId = $resData['Data']['InvoiceId'] ?? null;

        if ($invoiceUrl && $mfInvoiceId) {
            $payment->payment_reference = $mfInvoiceId;
            $payment->status = 'initiate';
            $payment->save();

            return redirect($invoiceUrl);
        }

        return Auth::user() ? redirect()->route('invoices.index')->with('error', 'Failed to retrieve MyFatoorah reinitiation URL.') : abort(500);
    }

    protected function reinitiateUPayment($payment, $company, $client, $clientPhone)
    {
        $charge = ChargeService::calculate($payment->amount, $company->id, $payment->payment_method_id, 'UPayment');
        $finalAmount = $charge['finalAmount'] ?? $payment->amount;

        $request = new Request([
            'final_amount'      => $finalAmount,
            'client_id'         => $client->id,
            'client_name'       => $client->full_name,
            'client_email'      => $client->email ?? $company?->email,
            'client_phone'      => $clientPhone,
            'company_email'     => $company?->email,
            'payment_id'        => $payment->id,
            'payment_number'    => $payment->voucher_number,
            'payment_method_id' => $payment->payment_method_id,
            'invoice_id'        => optional($payment->invoice)->id,
            'invoice_number'    => optional($payment->invoice)->invoice_number,
            'currency'          => $payment->currency ?? 'KWD',
        ]);

        $upayment = new UPayment();
        $response = $upayment->makeCharge($request);

        if (!is_array($response)) {
            Log::error('UPayment reinitiate unexpected response', ['raw' => $response]);
            return redirect()->back()->with('error', 'UPayment: unexpected response.');
        }

        if (isset($response['status']) && $response['status'] === 'error') {
            return redirect()->back()->with('error', $response['message'] ?? 'UPayment error.');
        }

        $trackId = $response['data']['trackId'] ?? null;
        $link = $response['data']['link'] ?? null;

        if ($trackId && $link) {
            $payment->status = 'initiate';
            $payment->save();

            return redirect($link);
        }

        Log::error('UPayment reinitiate missing link/trackId', ['response' => $response]);
        return redirect()->back()->with('error', 'UPayment reinitiate failed: Missing link or trackId.');
    }

    public function paymentLinkWebhook(Request $request)
    {
        Log::info('Tap Payment Webhook received: ' . $request->getContent());
    }

    public function handleMyFatoorahCallback(Request $request)
    {
        try {
            Log::info('MyFatoorah callback received', ['request' => $request->all()]);

            $paymentId = $request->query('paymentId') ?? $request->input('paymentId');

            if (!$paymentId) {
                return redirect()->route('payment.failed')->with('error', 'Invalid payment callback data.');
            }

            $eventKey = 'mf:callback:' . $paymentId;
            $lock = Cache::lock($eventKey, 40);
            if (!$lock->get()) {
                Log::warning('Duplicate MyFatoorah callback suppressed by lock', ['key' => $eventKey]);
                return response('OK', 200);
            }

            try {
                $myfatoorah = new MyFatoorah();

                $statusResponse = $myfatoorah->getPaymentStatus(type: 'payment', key: $paymentId);

                if (!$statusResponse['success']) {
                    return redirect()->route('payment.failed')->with('error', 'Failed to verify payment status.');
                }

                $invoiceStatus = strtolower($statusResponse['data']['InvoiceStatus'] ?? '');
                $invoiceId = $statusResponse['data']['InvoiceId'] ?? null;

                $userDefinedField   = !empty($statusResponse['data']['UserDefinedField']) ? json_decode($statusResponse['data']['UserDefinedField'], true) : [];

                Log::info('[MYFATOORAH CALLBACK] UserDefinedField:', ['user_defined_field' => $userDefinedField]);
                $voucherNumber = $userDefinedField['voucher_number'] ?? null;
                $process = $userDefinedField['process'] ?? 'invoice';
                $partialId = $userDefinedField['invoice_partial_id'] ?? null;

                $payment = Payment::where('payment_reference', $invoiceId)->orWhere('voucher_number', $voucherNumber)->first();

                if (!$invoiceId || $invoiceStatus !== 'paid') {
                    if ($payment) {
                        $receiptInfo = $this->publicReceiptNotice($payment, $process, 'failed', $partialId);

                        $this->storeNotification([
                            'user_id' => $receiptInfo['agent']->user_id,
                            'title'   => $receiptInfo['title'],
                            'message' => $receiptInfo['message'],
                        ]);

                        (new ResayilController())->message(
                            $receiptInfo['agent']->phone_number,
                            $receiptInfo['agent']->country_code,
                            $receiptInfo['message']
                        );

                        return redirect()->to($receiptInfo['url'])->with('error', 'Payment was not completed or was cancelled.');
                    }

                    return redirect()->route('payment.failed')->with('error', 'Payment was not completed.');
                }

                if (!$payment) {
                    Log::error('Payment not found', ['invoiceId' => $invoiceId]);
                    return redirect()->route('payment.failed')->with('error', 'Payment record not found.');
                }

                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

                if ($payment->status === 'completed') {

                    $invoice = $payment->invoice;

                    if ($invoice && $invoice->status !== 'paid') {
                        $invoice->status = 'paid';
                        $invoice->paid_date = now();
                        $invoice->save();

                        Log::info('Invoice status updated to paid for completed payment', ['invoice_id' => $invoice->id]);
                    }

                    Log::info('Callback ignored: payment already completed', ['payment_id' => $payment->id]);
                    return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
                }

                try {
                    $this->processMyFatoorahPaymentCompletion($payment, $statusResponse['data'], $process, $partialId, true);
                } catch (Exception $e) {
                    Log::error('MyFatoorah callback processing failed', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return redirect()->to($receiptInfo['url'])->with('error', 'Error: ' . $e->getMessage());
                }
                return redirect()->to($receiptInfo['url'])->with('success', 'Payment successful!');
            } finally {
                optional($lock)->release();
            }
        } catch (Exception $e) {
            Log::error('MyFatoorah callback exception', ['message' => $e->getMessage()]);
            return redirect()->route('payment.failed')->with('error', 'Something went wrong. Please contact support.');
        }
    }

    public function handleMyFatoorahError(Request $request)
    {
        Log::error('[MYFATOORAH] error callback', [
            'request' => $request->all(),
            'query' => $request->query(),
            'input' => $request->input(),
        ]);

        if ($request->has('invoice_id')) {

            Log::error('[MYFATOORAH] update transaction for failed invoice payment', [
                'invoice_id' => $request->input('invoice_id'),
            ]);

            $invoice = Invoice::with('agent.branch', 'client')->find($request->input('invoice_id'));
            $paymentId = $request->query('paymentId') ?? $request->input('paymentId');
            Transaction::create([
                'branch_id' => $invoice->agent->branch->id,
                'company_id' => $invoice->agent->branch->company->id,
                'entity_id' => $invoice->agent->branch->company->id,
                'entity_type' => 'company',
                'transaction_type' => 'credit',
                'amount' => $invoice->amount,
                'description' => 'MyFatoorah payment failed: ' . $invoice->invoice_number,
                'invoice_id' => $invoice->id,
                'payment_id' => $invoice->payment->id,
                'payment_reference' => $invoice->payment->payment_reference,
                'reference_type' => 'Invoice',
                'transaction_date' => now(),
            ]);
        }

        if ($request->has('payment_id')) {

            Log::error('[MYFATOORAH] update transaction for failed topup payment', [
                'payment_id' => $request->input('payment_id'),
            ]);

            $payment = Payment::with('client', 'agent.branch')->find($request->input('payment_id'));
            Transaction::create([
                'branch_id' => $payment->agent->branch->id,
                'company_id' => $payment->agent->branch->company->id,
                'entity_id' => $payment->agent->branch->company->id,
                'entity_type' => 'company',
                'transaction_type' => 'debit',
                'amount' => $payment->amount,
                'description' => 'Topup failed by ' . $payment->client->full_name,
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'payment_reference' => $payment->payment_reference,
                'reference_type' => 'Payment',
                'transaction_date' => now(),
            ]);
        }

        $process = $payment->invoice ? 'invoice' : 'topup';
        $partialId = $payment->invoice?->invoicePartials()->where('payment_id', $payment->id)->value('id');
        $receiptInfo = $this->publicReceiptNotice($payment, $process, 'failed', $partialId);

        Log::info('[MYFATOORAH] prepare notification for failed payment', [
            'user_id' => $receiptInfo['agent']->user_id,
            'title'   => $receiptInfo['title'],
            'message' => $receiptInfo['message'],
        ]);

        $this->storeNotification([
            'user_id' => $receiptInfo['agent']->user_id,
            'title'   => $receiptInfo['title'],
            'message' => $receiptInfo['message'],
        ]);

        (new ResayilController())->message(
            $receiptInfo['agent']->phone_number,
            $receiptInfo['agent']->country_code,
            $receiptInfo['message']
        );

        return redirect()->to($receiptInfo['url'])->with('error', 'Payment was not completed or was cancelled.');
    }

    public function handleTapCallback(Request $request)
    {
        try {
            Log::info('Tap callback received', ['request' => $request->all()]);

            $tapId = $request->query('tap_id') ?? $request->input('tap_id');
            if (!$tapId) {
                Log::error('Tap callback missing tap_id', ['request' => $request->all()]);
                return redirect()->route('payment.failed')->with('error', 'Invalid callback data.');
            }

            $tap = new Tap();
            $response = $tap->getCharge($tapId);

            if (isset($response['errors'])) {
                Log::error('Tap charge error', ['errors' => $response['errors']]);
                return redirect()->route('payment.failed')->with('error', $response['errors'][0]['description'] ?? 'Payment failed.');
            }

            $paymentId = $response['metadata']['payment_id'] ?? null;
            $process = $response['metadata']['process'] ?? null;
            if (!$paymentId) {
                Log::error('Missing payment_id in Tap metadata', ['response' => $response]);
                return redirect()->route('payment.failed')->with('error', 'Payment reference missing.');
            }

            $payment = Payment::with(['agent.branch.company', 'client', 'invoice'])->find($paymentId);
            if (!$payment) {
                Log::error('Payment not found for Tap callback', ['payment_id' => $paymentId]);
                return redirect()->route('payment.failed')->with('error', 'Payment not found.');
            }

            $paymentTransaction = $payment->paymentTransactions()->where('reference_number', $tapId)->first();

            if ($paymentTransaction) {
                Log::info("[TAP CALLBACK] Update payment transaction status", [
                    'payment_transaction_id' => $paymentTransaction->id,
                    'status' => $response['status'],
                ]);

                $paymentTransaction->status = $response['status'];
                $paymentTransaction->save();
            } else {
                Log::warning('Payment transaction not found for Tap ID', ['tap_id' => $tapId, 'payment_id' => $paymentId]);
            }

            $partialId = $response['metadata']['invoice_partial_id'] ?? null;

            $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

            if ($payment->status === 'completed') {
                $invoice = $payment->invoice;

                if ($invoice && $invoice->status !== 'paid') {
                    $invoice->status = 'paid';
                    $invoice->paid_date = now();
                    $invoice->save();

                    Log::info('Invoice status updated to paid for already completed payment', ['invoice_id' => $invoice->id]);
                }

                Log::info('Callback ignored: already completed', ['payment_id' => $paymentId]);

                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
            }

            if ($response['status'] !== 'CAPTURED') {
                Log::warning('Tap payment failed or cancelled', ['status' => $response['status'], 'tap_id' => $tapId]);

                $transaction = Transaction::create([
                    'branch_id' => $payment->agent->branch->id,
                    'company_id' => $payment->agent->branch->company->id,
                    'entity_id' => $payment->agent->branch->company->id,
                    'entity_type' => 'company',
                    'transaction_type' => 'debit',
                    'amount' => $payment->amount,
                    'description' => 'Tap payment failed for ' . $payment->client->full_name,
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'payment_reference' => $response['id'],
                    'reference_type' => 'Payment',
                    'transaction_date' => now(),
                ]);

                if ($paymentTransaction) {
                    $paymentTransaction->transaction_id = $transaction->id;
                    $paymentTransaction->save();
                }

                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'failed', $partialId);

                $this->storeNotification([
                    'user_id' => $receiptInfo['agent']->user_id,
                    'title'   => $receiptInfo['title'],
                    'message' => $receiptInfo['message'],
                ]);

                (new ResayilController())->message(
                    $receiptInfo['agent']->phone_number,
                    $receiptInfo['agent']->country_code,
                    $receiptInfo['message']
                );

                return redirect()->to($receiptInfo['url'])->with('error', 'Payment failed or cancelled. Please try again or contact support.');
            }

            DB::transaction(function () use ($payment, $response, $process, $partialId, $paymentTransaction) {
                $finalPaidAmount = $response['amount'] ?? $payment->amount;

                $dateCreated = Carbon::createFromTimestampMs($response['transaction']['date']['created'])->format('Y-m-d H:i:s');
                $dateCompleted = isset($response['transaction']['date']['completed'])
                    ? Carbon::createFromTimestampMs($response['transaction']['date']['completed'])->format('Y-m-d H:i:s')
                    : now();
                $dateTransaction = Carbon::createFromTimestampMs($response['transaction']['date']['transaction'])->format('Y-m-d H:i:s');

                TapPayment::create([
                    'payment_id'       => $payment->id,
                    'tap_id'           => $response['id'],
                    'authorization_id' => $response['transaction']['authorization_id'] ?? null,
                    'timezone'         => $response['transaction']['timezone'] ?? null,
                    'expiry_period'    => $response['transaction']['expiry']['period'] ?? null,
                    'expiry_type'      => $response['transaction']['expiry']['type'] ?? null,
                    'amount'           => $finalPaidAmount,
                    'currency'         => $response['currency'] ?? 'KWD',
                    'date_created'     => $dateCreated,
                    'date_completed'   => $dateCompleted,
                    'date_transaction' => $dateTransaction,
                    'receipt_id'       => $response['receipt']['id'] ?? null,
                    'receipt_email'    => $response['receipt']['email'] ?? null,
                    'receipt_sms'      => $response['receipt']['sms'] ?? null,
                ]);

                $payment->status = 'completed';
                $payment->completed = 1;
                $payment->service_charge = $finalPaidAmount - $payment->amount;
                $payment->payment_reference = $response['id'];
                $payment->payment_date = now();
                $payment->save();

                if ($process === 'topup') {
                    $clientController = new ClientController;
                    $addCreditResponse = $clientController->addCredit($payment);

                    if (isset($addCreditResponse['error']) || $addCreditResponse['status'] === 'error') {
                        throw new \RuntimeException('Failed to add credit: ' . ($addCreditResponse['message'] ?? $addCreditResponse['error']));
                    }

                    Log::info('Credit added successfully via addCredit()', [
                        'payment_id' => $payment->id,
                        'response' => $addCreditResponse,
                    ]);


                    if ($paymentTransaction) {
                        $transactionId = $addCreditResponse['data']['transaction_id'] ?? null;

                        Log::info('[MYFATOORAH] Updating payment transaction ID: ' . $paymentTransaction->id, [
                            'payment_id' => $payment->id,
                            'transaction_id' => $transactionId,
                        ]);

                        $paymentTransaction->transaction_id = $transactionId;
                        $paymentTransaction->save();
                    }
                } else {
                    $coaResult = $this->createInvoicePaymentCOA(
                        payment: $payment,
                        finalPaidAmount: $finalPaidAmount,
                        gatewayName: 'Tap',
                        partialIds: !empty($partialId) ? [$partialId] : null,
                        paymentReference: $response['id']
                    );

                    if (!$coaResult['success']) {
                        throw new \RuntimeException($coaResult['message']);
                    }

                    $transaction = Transaction::find($coaResult['transaction_id']);
                }
            });

            $tboResult = $this->processTBOBookingAfterPayment($payment);

            if ($tboResult !== null) {
                if ($tboResult['success']) {
                    Log::info('TBO booking processed successfully via Tap callback', $tboResult);
                } else {
                    Log::error('TBO booking failed via Tap callback', $tboResult);
                }
            }

            $payment->refresh();

            $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

            $storeNotificationData = [
                'user_id' => $receiptInfo['agent']->user_id,
                'title'   => $receiptInfo['title'],
                'message' => $receiptInfo['message'],
            ];

            if ($payment->invoice) {
                $storeNotificationData['type'] = 'invoice';
                $storeNotificationData['invoice'] = $payment->invoice;
            } else {
                $storeNotificationData['type'] = 'payment';
                $storeNotificationData['payment'] = $payment;
            }

            $this->storeNotificationWithSendingPdf($storeNotificationData);

            (new ResayilController())->message(
                $receiptInfo['agent']->phone_number,
                $receiptInfo['agent']->country_code,
                $receiptInfo['message']
            );

            if ($payment['status'] == 'CAPTURED') {
                $checkNotes = $payment->notes;
                if (str_contains($checkNotes, 'Prebook Key')) {
                    preg_match('/PB-[A-Za-z0-9]+/', $checkNotes, $match);
                    $prebookKey = $match[0] ?? null;
                    if ($prebookKey) {
                        try {
                            $wsHotelController = new WhatsAppHotelController;
                            $response = $wsHotelController->hotelBookingDetails($payment);
                            $apiResponse = $response->getData(true);

                            if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                                return redirect()->to($receiptInfo['url'])->with('success', 'Payment successful and booking confirmed!');
                            }

                            Log::warning('Hotel booking API responded with failure', ['response' => $apiResponse]);
                            return redirect()->route('payment.failed')->with('error', $apiResponse['message'] ?? 'Booking API failed.');
                        } catch (Throwable $e) {
                            Log::error('Hotel booking API crashed', ['error' => $e->getMessage()]);
                            return redirect()->route('payment.failed')->with('error', 'Booking process failed: ' . $e->getMessage());
                        }
                    }
                }
            }

            return redirect()->to($receiptInfo['url'])->with('success', 'Payment successful!');
        } catch (Throwable $e) {
            Log::error('Tap callback exception', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('payment.failed')->with('error', 'Something went wrong. Please contact support.');
        }
    }

    /**
     * Handle KNET payment response (success callback)
     * This is called by KNET gateway after payment processing
     */
    public function handleKnetResponse(Request $request)
    {
        try {
            Log::info('KNET Response received', ['request' => $request->all()]);

            // Get encrypted response data
            $encryptedData = $request->input('trandata');

            if (!$encryptedData) {
                Log::error('KNET Response: Missing encrypted data');
                return redirect()->route('payment.failed')->with('error', 'Invalid response data.');
            }

            // Extract company_id from UDF to initialize Knet with correct credentials
            // We need to decrypt first to get company_id, but we need company_id to initialize Knet
            // Solution: Get company_id from a temporary query parameter or use a default/first attempt
            $tempCompanyId = $request->query('company_id');

            if (!$tempCompanyId) {
                Log::error('KNET Response: Missing company_id parameter');
                return redirect()->route('payment.failed')->with('error', 'Missing company identifier.');
            }

            $knet = new \App\Support\PaymentGateway\Knet($tempCompanyId);
            $responseData = $knet->decryptResponse($encryptedData);

            if (!$responseData) {
                Log::error('KNET Response: Decryption failed');
                return redirect()->route('payment.failed')->with('error', 'Failed to process response.');
            }

            Log::info('KNET Response decrypted', $responseData);

            // Extract payment data from UDF fields
            $paymentId = $responseData['udf1'] ?? null;
            $voucherNumber = $responseData['udf2'] ?? null;
            $companyId = $responseData['udf3'] ?? null;
            $invoiceNumber = $responseData['udf4'] ?? null;
            $partialId = $responseData['udf5'] ?? null;

            // Determine process type (invoice or topup)
            $process = $invoiceNumber ? 'invoice' : 'topup';

            if (!$paymentId) {
                Log::error('KNET Response: Missing payment_id in UDF', ['response' => $responseData]);
                return redirect()->route('payment.failed')->with('error', 'Payment reference missing.');
            }

            $payment = Payment::with(['agent.branch.company', 'client', 'invoice'])->find($paymentId);
            if (!$payment) {
                Log::error('KNET Response: Payment not found', ['payment_id' => $paymentId]);
                return redirect()->route('payment.failed')->with('error', 'Payment not found.');
            }

            $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

            // Check if already processed
            if ($payment->status === 'completed') {
                $invoice = $payment->invoice;

                if ($invoice && $invoice->status !== 'paid') {
                    $invoice->status = 'paid';
                    $invoice->paid_date = now();
                    $invoice->save();

                    Log::info('Invoice status updated to paid for already completed KNET payment', ['invoice_id' => $invoice->id]);
                }

                Log::info('KNET callback ignored: already completed', ['payment_id' => $paymentId]);
                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
            }

            // Check payment result
            $resultCode = $responseData['result'] ?? '';
            if ($resultCode !== 'CAPTURED' && $resultCode !== 'SUCCESS') {
                Log::warning('KNET payment failed or cancelled', [
                    'result' => $resultCode,
                    'error' => $responseData['Error'] ?? '',
                    'error_text' => $responseData['ErrorText'] ?? '',
                    'track_id' => $responseData['trackid'] ?? '',
                ]);

                Transaction::create([
                    'branch_id' => $payment->agent->branch->id,
                    'company_id' => $payment->agent->branch->company->id,
                    'entity_id' => $payment->agent->branch->company->id,
                    'entity_type' => 'company',
                    'transaction_type' => 'debit',
                    'amount' => $payment->amount,
                    'description' => 'KNET payment failed for ' . $payment->client->full_name,
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'payment_reference' => $responseData['paymentid'] ?? null,
                    'reference_type' => 'Payment',
                    'transaction_date' => now(),
                ]);

                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'failed', $partialId);

                $storeNotificationData = [
                    'user_id' => $receiptInfo['agent']->user_id,
                    'title'   => $receiptInfo['title'],
                    'message' => $receiptInfo['message'],
                ];

                if ($payment->invoice) {
                    $storeNotificationData['type'] = 'invoice';
                    $storeNotificationData['invoice'] = $payment->invoice;
                } else {
                    $storeNotificationData['type'] = 'payment';
                    $storeNotificationData['payment'] = $payment;
                }

                $this->storeNotificationWithSendingPdf($storeNotificationData);

                (new ResayilController())->message(
                    $receiptInfo['agent']->phone_number,
                    $receiptInfo['agent']->country_code,
                    $receiptInfo['message']
                );

                $errorMessage = $responseData['ErrorText'] ?? 'Payment failed or cancelled.';
                return redirect()->to($receiptInfo['url'])->with('error', $errorMessage . ' Please try again or contact support.');
            }

            // Process successful payment
            DB::transaction(function () use ($payment, $responseData, $process, $partialId) {
                $finalPaidAmount = floatval($responseData['amt'] ?? $payment->amount);

                $paymentTransaction = $payment->paymentTransactions()
                    ->where('reference_number', $responseData['trackid'] ?? null)
                    ->orWhere('track_id', $responseData['trackid'] ?? null)
                    ->first();

                if ($paymentTransaction) {
                    $paymentTransaction->status = $responseData['result'] ?? 'CAPTURED';
                    $paymentTransaction->track_id = $responseData['trackid'] ?? $paymentTransaction->track_id;
                    $paymentTransaction->save();

                    Log::info('[KNET] Payment transaction updated', [
                        'payment_transaction_id' => $paymentTransaction->id,
                        'status' => $paymentTransaction->status,
                    ]);
                }

                $payment->status = 'completed';
                $payment->completed = 1;
                $payment->service_charge = $finalPaidAmount - $payment->amount;
                $payment->payment_reference = $responseData['paymentid'] ?? $responseData['tranid'] ?? null;
                $payment->payment_date = now();
                $payment->save();

                if ($process === 'topup') {
                    $clientController = new ClientController;
                    $addCreditResponse = $clientController->addCredit($payment);

                    if (isset($addCreditResponse['error']) || (isset($addCreditResponse['status']) && $addCreditResponse['status'] === 'error')) {
                        throw new \RuntimeException('Failed to add credit: ' . ($addCreditResponse['message'] ?? $addCreditResponse['error']));
                    }

                    $transactionId = $addCreditResponse['data']['transaction_id'] ?? null;
                    if ($paymentTransaction && $transactionId) {
                        Log::info('[KNET] Updating payment transaction ID: ' . $paymentTransaction->id, [
                            'payment_id' => $payment->id,
                            'transaction_id' => $transactionId,
                        ]);

                        $paymentTransaction->transaction_id = $transactionId;
                        $paymentTransaction->save();
                    } else {
                        Log::warning('[KNET] Payment transaction or transaction ID missing for update', [
                            'payment_transaction_exists' => $paymentTransaction !== null,
                            'transaction_id' => $transactionId,
                        ]);
                    }

                    Log::info('Credit added successfully via addCredit()', [
                        'payment_id' => $payment->id,
                        'response' => $addCreditResponse,
                    ]);
                } else {
                    // Handle invoice payment
                    $invoice = $payment->invoice;

                    if (!$invoice) {
                        throw new \RuntimeException('Invoice not found for payment.');
                    }

                    if (!empty($partialId)) {
                        $partial = InvoicePartial::where('invoice_id', $invoice->id)->where('id', $partialId)->first();

                        if ($partial) {
                            $partial->status = 'paid';
                            $partial->payment_id = $payment->id;
                            $partial->amount = $finalPaidAmount;
                            $partial->save();
                        }

                        Log::info('Updated KNET invoice partials to paid', [
                            'invoice_id' => $invoice->id,
                            'partial_id' => $partialId
                        ]);
                    }

                    $allPartials = InvoicePartial::where('invoice_id', $invoice->id)->get();
                    $paidCount = $allPartials->where('status', 'paid')->count();
                    if ($paidCount === $allPartials->count()) {
                        $invoice->status = 'paid';
                    } elseif ($paidCount > 0) {
                        $invoice->status = 'partial';
                    } else {
                        $invoice->status = 'unpaid';
                    }

                    $invoice->paid_date = now();
                    $invoice->save();

                    $coaResult = $this->createInvoicePaymentCOA(
                        payment: $payment,
                        finalPaidAmount: $finalPaidAmount,
                        gatewayName: 'KNET',
                        partialIds: !empty($partialId) ? [$partialId] : null,
                        paymentReference: $responseData['paymentid'] ?? $responseData['tranid'] ?? null
                    );

                    if (!$coaResult['success']) {
                        throw new \RuntimeException($coaResult['message']);
                    }
                }
            });

            $tboResult = $this->processTBOBookingAfterPayment($payment);
            if ($tboResult !== null) {
                if ($tboResult['success']) {
                    Log::info('TBO booking processed successfully via KNET callback', $tboResult);
                } else {
                    Log::error('TBO booking failed via KNET callback', $tboResult);
                }
            }

            $payment->refresh();

            $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

            $storeNotificationData = [
                'user_id' => $receiptInfo['agent']->user_id,
                'title'   => $receiptInfo['title'],
                'message' => $receiptInfo['message'],
            ];

            if ($payment->invoice) {
                $storeNotificationData['type'] = 'invoice';
                $storeNotificationData['invoice'] = $payment->invoice;
            } else {
                $storeNotificationData['type'] = 'payment';
                $storeNotificationData['payment'] = $payment;
            }

            $this->storeNotificationWithSendingPdf($storeNotificationData);

            (new ResayilController())->message(
                $receiptInfo['agent']->phone_number,
                $receiptInfo['agent']->country_code,
                $receiptInfo['message']
            );

            Log::info('KNET payment processed successfully', ['payment_id' => $payment->id]);

            return redirect()->to($receiptInfo['url'])->with('success', 'Payment successful!');
        } catch (\Throwable $e) {
            Log::error('KNET Response exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('payment.failed')->with('error', 'Something went wrong. Please contact support.');
        }
    }

    /**
     * Handle KNET payment error
     * This is called by KNET gateway when payment fails
     */
    public function handleKnetError(Request $request)
    {
        try {
            Log::info('KNET Error received', ['request' => $request->all()]);

            // Extract error information
            $errorCode = $request->input('Error');
            $errorText = $request->input('ErrorText');
            $trackId = $request->input('trackid');
            $paymentId = $request->input('paymentid');

            Log::error('KNET Payment Error', [
                'error_code' => $errorCode,
                'error_text' => $errorText,
                'track_id' => $trackId,
                'payment_id' => $paymentId,
            ]);

            // Try to get payment from UDF if available
            $encryptedData = $request->input('trandata');
            $companyId = $request->query('company_id');

            if ($encryptedData && $companyId) {
                try {
                    $knet = new \App\Support\PaymentGateway\Knet($companyId);
                    $responseData = $knet->decryptResponse($encryptedData);

                    $paymentIdFromUdf = $responseData['udf1'] ?? null;
                    $voucherNumber = $responseData['udf2'] ?? null;
                    $partialId = $responseData['udf5'] ?? null;

                    if ($paymentIdFromUdf) {
                        $payment = Payment::find($paymentIdFromUdf);

                        if ($payment) {
                            $process = $voucherNumber ? 'topup' : 'invoice';
                            $receiptInfo = $this->publicReceiptNotice($payment, $process, 'failed', $partialId);

                            $this->storeNotification([
                                'user_id' => $receiptInfo['agent']->user_id,
                                'title'   => $receiptInfo['title'],
                                'message' => $receiptInfo['message'],
                            ]);

                            (new ResayilController())->message(
                                $receiptInfo['agent']->phone_number,
                                $receiptInfo['agent']->country_code,
                                $receiptInfo['message']
                            );

                            return redirect()->to($receiptInfo['url'])
                                ->with('error', $errorText ?: 'Payment failed. Please try again.');
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to decrypt KNET error response', ['error' => $e->getMessage()]);
                }
            }

            return redirect()->route('payment.failed')
                ->with('error', $errorText ?: 'Payment failed. Please try again or contact support.');
        } catch (\Throwable $e) {
            Log::error('KNET Error handler exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('payment.failed')
                ->with('error', 'Something went wrong. Please contact support.');
        }
    }

    public function paymentUpdateLink($paymentId, Request $request)
    {
        Log::info("[PAYMENT LINK] Update request received", [
            'payment_id' => $paymentId,
            'request_data' => $request->all(),
        ]);

        $payment = Payment::find($paymentId);

        if (!$payment) {
            return redirect()->back()->with('error', 'Payment not found.');
        }

        if ($clientId = $request->client_id) {
            $client = Client::find($clientId);
            if (!$client) {
                return redirect()->back()->with('error', 'Client not found.');
            }

            $payment->client_id = $clientId;
        } else {
            $client = $payment->client;
            if (!$client) {
                return redirect()->back()->with('error', 'Client not found.');
            }
        }

        if ($request->agent_id) $payment->agent_id = $request->agent_id;
        if ($request->dial_code) $client->country_code = $request->dial_code;
        if ($request->phone) $client->phone = $request->phone;
        if ($request->amount) $payment->amount = $request->amount;
        if ($request->language) $payment->language = $request->language;

        // Handle payment method based on flow
        if ($payment->availablePaymentMethodGroups()->exists()) {
            // New flow: Multi payment method groups
            if ($request->has('payment_method_groups') && is_array($request->payment_method_groups)) {
                // Sync the many-to-many relationship with GROUPS
                $payment->availablePaymentMethodGroups()->sync($request->payment_method_groups);
            }
        } else {
            // Old flow: Single payment gateway and method
            if ($request->payment_gateway) $payment->payment_gateway = $request->payment_gateway;
            if ($request->payment_method_id) $payment->payment_method_id = $request->payment_method_id;
        }

        try {
            $payment->save();
            $client->save();
        } catch (Exception $e) {
            Log::error('Failed to update payment link', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Failed to update payment link.');
        }


        Log::info("[PAYMENT LINK] Updated successfully", [
            'payment' => $payment->toArray(),
        ]);

        return redirect()->route('payment.link.index')->with('success', 'Payment link updated successfully!');
    }

    public function updatePaymentItems($id, Request $request)
    {
        try {
            $payment = Payment::with('paymentItems')->findOrFail($id);

            if ($payment->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit items for completed payments.'
                ], 403);
            }

            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.id' => 'nullable|exists:payment_items,id',
                'items.*.product_name' => 'required|string|max:255',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.currency' => 'required|string|max:10',
                'items.*.extended_amount' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            $existingItemIds = collect($validated['items'])->pluck('id')->filter();
            PaymentItem::where('payment_id', $payment->id)
                ->whereNotIn('id', $existingItemIds)
                ->delete();

            foreach ($validated['items'] as $itemData) {
                if (isset($itemData['id'])) {
                    $item = PaymentItem::find($itemData['id']);
                    if ($item) {
                        $item->update([
                            'product_name' => $itemData['product_name'],
                            'quantity' => $itemData['quantity'],
                            'unit_price' => $itemData['unit_price'],
                            'currency' => $itemData['currency'],
                            'extended_amount' => $itemData['extended_amount'],
                        ]);
                    }
                } else {
                    PaymentItem::create([
                        'payment_id' => $payment->id,
                        'product_name' => $itemData['product_name'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'currency' => $itemData['currency'],
                        'extended_amount' => $itemData['extended_amount'],
                    ]);
                }
            }

            $totalAmount = collect($validated['items'])->sum('extended_amount');
            $payment->update(['amount' => $totalAmount]);

            DB::commit();

            Log::info('[PAYMENT ITEMS] Updated payment items', [
                'payment_id' => $payment->id,
                'items_count' => count($validated['items']),
                'total_amount' => $totalAmount
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment items updated successfully.'
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('[PAYMENT ITEMS] Failed to update payment items', [
                'payment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment items.'
            ], 500);
        }
    }

    public function updateReceipt(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->status === 'completed') {
            return back()->with('error', 'Cannot update receipt settings for completed payments.');
        }

        $payment->update(['send_payment_receipt' => $request->boolean('send_payment_receipt')]);

        return back()->with('success', 'Receipt settings updated successfully.');
    }

    public function paymentDeleteLink($paymentId)
    {
        $payment = Payment::find($paymentId);
        if (!$payment) {
            return redirect()->back()->with('error', 'Payment not found.');
        }

        try {
            $payment->delete();
        } catch (Exception $e) {
            Log::error('Failed to delete payment link', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Failed to delete payment link.');
        }

        return redirect()->route('payment.link.index')->with('success', 'Payment link deleted successfully!');
    }

    public function handleWebhookFatoorah(Request $request)
    {
        $secretKey = config('services.myfatoorah.webhook_secret_key');

        $incomingSignature = $request->header('MyFatoorah-Signature');
        Log::info('Received Signature From MyFatoorah: ' . $incomingSignature);

        $rawBody = $request->getContent();
        if (empty($rawBody)) {
            Log::error('MF Webhook: empty body');
            return response()->json(['error' => 'Empty body received'], 400);
        }
        Log::info('Raw Body: ' . $rawBody);

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            Log::error('MF Webhook: invalid JSON');
            return response()->json(['error' => 'Invalid JSON'], 400);
        }
        Log::info('MyFatoorah Webhook Received', ['body' => $payload]);

        // ============ CHECK IF THIS IS ERP BOOKING PAYMENT ============
        $userDefinedField = json_decode(data_get($payload, 'Data.Invoice.UserDefinedField', '{}'), true) ?? [];
        $project = $userDefinedField['project'] ?? null;
        $customerReference = data_get($payload, 'Data.Invoice.ExternalIdentifier', '');

        if ($project === 'erp_booking' || str_starts_with($customerReference, 'APP')) {
            Log::info('MF Webhook: Routing to ERP Booking system', [
                'project' => $project,
                'customer_reference' => $customerReference,
            ]);

            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'MyFatoorah-Signature' => $incomingSignature,
                    ])
                    ->post(config('services.erp_booking.webhook_url'), $payload);

                Log::info('MF Webhook: Forwarded to ERP Booking', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return response()->json([
                    'status' => 'forwarded',
                    'target' => 'erp_booking',
                    'erp_response' => $response->json(),
                ]);
            } catch (\Exception $e) {
                Log::error('MF Webhook: Failed to forward to ERP Booking', [
                    'error' => $e->getMessage(),
                ]);
                return response()->json(['error' => 'Failed to forward to ERP'], 500);
            }
        }

        $sigString = sprintf(
            'Invoice.Id=%s,Invoice.Status=%s,Transaction.Status=%s,Transaction.PaymentId=%s,Invoice.ExternalIdentifier=%s',
            (string) data_get($payload, 'Data.Invoice.Id', ''),
            (string) data_get($payload, 'Data.Invoice.Status', ''),
            (string) data_get($payload, 'Data.Transaction.Status', ''),
            (string) data_get($payload, 'Data.Transaction.PaymentId', ''),
            (string) data_get($payload, 'Data.Invoice.ExternalIdentifier', '')
        );
        $generatedSignature = base64_encode(hash_hmac('sha256', $sigString, $secretKey, true));

        Log::info('MF Webhook: signature check', [
            'match' => hash_equals($generatedSignature, $incomingSignature),
            'generated_signature' => $generatedSignature,
            'received_signature' => $incomingSignature,
        ]);

        if (!hash_equals($generatedSignature, $incomingSignature)) {
            Log::error('MF Webhook: invalid signature');
            return response()->json(['error' => 'Unauthorized request'], 403);
        }

        $invoiceId = data_get($payload, 'Data.Invoice.Id');
        $invoiceStatus = data_get($payload, 'Data.Invoice.Status');

        $userDefinedField = json_decode(data_get($payload, 'Data.Invoice.UserDefinedField', '{}'), true) ?? [];
        $process = $userDefinedField['process'] ?? 'invoice';
        $partialId = $userDefinedField['invoice_partial_id'] ?? null;

        if (!$invoiceId || !$invoiceStatus) {
            Log::warning('MF Webhook: missing invoice fields', compact('invoiceId', 'invoiceStatus'));
            return response()->json(['message' => 'Ignored (missing fields)'], 200);
        }

        $payment = Payment::where('payment_reference', $invoiceId)->first();
        if ($payment) {
            Log::info('Found the payment record in the system with ID: ' . $payment->id);
            if ($payment->status === 'initiate') {
                if ($invoiceStatus === 'PAID') {
                    try {
                        $statusData = $payload['Data'] ?? $payload;
                        $this->processMyFatoorahPaymentCompletion($payment, $statusData, $process, $partialId, true);

                        Log::info('MF Webhook: payment processed successfully', [
                            'payment_id' => $payment->id,
                            'payment_reference' => $invoiceId,
                            'new_status' => $invoiceStatus
                        ]);
                    } catch (Exception $e) {
                        Log::error('MF Webhook: payment processing failed', [
                            'payment_id' => $payment->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        return response()->json(['error' => 'Payment processing failed'], 500);
                    }
                } else {
                    $paymentType = $payment->invoice ? 'invoice' : 'topup';

                    if ($paymentType === 'invoice') {
                        $receiptInfo = $this->publicReceiptNotice($payment, $payment->invoice ? 'invoice' : 'topup', 'failed', $partialId);

                        $this->storeNotification([
                            'user_id' => $receiptInfo['agent']->user_id,
                            'title'   => $receiptInfo['title'],
                            'message' => $receiptInfo['message'],
                        ]);

                        (new ResayilController())->message(
                            $receiptInfo['agent']->phone_number,
                            $receiptInfo['agent']->country_code,
                            $receiptInfo['message']
                        );
                    }

                    Log::info('MF Webhook: ignoring downgrade from initiate', [
                        'payment_id' => $payment->id,
                        'current_status' => $payment->status,
                        'incoming_status' => $invoiceStatus,
                    ]);
                }
            } else {
                Log::info('MF Webhook: payment already processed', [
                    'payment_id' => $payment->id,
                    'payment_reference' => $invoiceId,
                    'current_status' => $payment->status
                ]);
            }
        } else {
            Log::warning('MF Webhook: no matching payment', ['invoice_id' => $invoiceId]);
        }
        return response()->json(['message' => 'Webhook processed successfully'], 200);
    }

    /**
     * Unified MyFatoorah payment completion logic
     * Used by both callback and webhook to ensure consistent processing
     */
    private function processMyFatoorahPaymentCompletion($payment, $statusData, $process, $partialId, $sendNotification = false)
    {
        DB::beginTransaction();

        try {
            $finalPaidAmount = $statusData['InvoiceValue'];

            $payment->status = 'completed';
            $payment->service_charge = $finalPaidAmount - $payment->amount;
            $payment->payment_date = now();
            $payment->save();

            $transaction = $statusData['InvoiceTransactions'][0] ?? [];
            $existingMF = MyFatoorahPayment::where('payment_int_id', $payment->id)->first();

            if (!$existingMF) {
                MyFatoorahPayment::create([
                    'payment_int_id' => $payment->id,
                    'payment_id' => $transaction['PaymentId'] ?? null,
                    'invoice_id' => $statusData['InvoiceId'],
                    'invoice_ref' => $statusData['InvoiceReference'],
                    'invoice_status' => $statusData['InvoiceStatus'],
                    'customer_reference' => $process === 'invoice' ? $payment->invoice?->invoice_number : $payment->voucher_number,
                    'payload' => $statusData,
                ]);
            } else {
                $existingMF->update([
                    'invoice_status' => $statusData['InvoiceStatus'],
                    'payload' => $statusData,
                ]);
            }

            if ($process === 'topup') {
                $clientController = new ClientController;
                $addCreditResponse = $clientController->addCredit($payment);

                if (isset($addCreditResponse['error']) || $addCreditResponse['status'] === 'error') {
                    throw new \Exception('Failed to add credit: ' . ($addCreditResponse['error'] ?? $addCreditResponse['message']));
                }

                $transactionId = $addCreditResponse['data']['transaction_id'] ?? null;

                $paymentTransaction = $payment->paymentTransactions()
                    ->where('reference_number', $statusData['InvoiceReference'])
                    ->first();

                if ($paymentTransaction) {
                    if ($transactionId) {
                        $paymentTransaction->transaction_id = $transactionId;
                    }
                    $paymentTransaction->status = $statusData['InvoiceStatus'];
                    $paymentTransaction->save();
                } else {
                    Log::warning('[MYFATOORAH] Payment transaction not found for reference: ' . $statusData['InvoiceReference'], [
                        'payment_id' => $payment->id,
                        'status' => $statusData['InvoiceStatus'],
                    ]);
                }
            } else {
                if ($payment->invoice) {
                    $coaResult = $this->createInvoicePaymentCOA(
                        payment: $payment,
                        finalPaidAmount: $finalPaidAmount,
                        gatewayName: 'MyFatoorah',
                        partialIds: !empty($partialId) ? [$partialId] : null,
                        paymentReference: $statusData['InvoiceReference']
                    );

                    if (!$coaResult['success']) {
                        throw new \Exception($coaResult['message']);
                    }
                }
            }

            $tboResult = $this->processTBOBookingAfterPayment($payment);
            if ($tboResult !== null) {
                if ($tboResult['success']) {
                    Log::info('TBO booking processed successfully via MyFatoorah callback', $tboResult);
                } else {
                    Log::error('TBO booking failed via MyFatoorah callback', $tboResult);
                }
            }

            $payment->refresh();

            if ($sendNotification) {
                $this->sendPaymentCompletionNotifications($payment, $process, $partialId);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('MyFatoorah payment processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function generateSignature($data, $secretKey)
    {
        return hash_hmac('sha256', $data, $secretKey);
    }

    public function handleUPaymentCallback(Request $request)
    {
        try {
            Log::info('UPayment callback received', ['request' => $request->all()]);

            $trackId = $request->query('trackId') ?? $request->input('trackId') ?? $request->input('track_id');
            if (!$trackId) {
                Log::error('UPayment callback missing trackId', ['request' => $request->all()]);
                return redirect()->route('payment.failed')->with('error', 'Invalid payment callback data.');
            }

            // Find the payment record by track_id
            $payment = Payment::where('payment_reference', $trackId)->first();
            if (!$payment) {
                Log::error('Payment not found for UPayment track_id', ['track_id' => $trackId]);
                return redirect()->route('payment.failed')->with('error', 'Payment record not found.');
            }

            // Determine if this is a topup or invoice payment
            $process = $payment->invoice ? 'invoice' : 'topup';
            $partialId = $request->input('invoice_partial_id') ?? null;

            if ($payment->status === 'completed') {
                $invoice = $payment->invoice;
                if ($invoice && $invoice->status !== 'paid') {
                    $invoice->status = 'paid';
                    $invoice->paid_date = now();
                    $invoice->save();
                }

                Log::info('[UPAYMENT] Callback ignored: payment already completed', ['payment_id' => $payment->id]);
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);
                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
            }

            $uPayment = new UPayment();
            $statusResponse = $uPayment->getPaymentStatus($trackId);

            Log::info('UPayment status response', ['response' => $statusResponse]);

            if (!$statusResponse['status'] || !isset($statusResponse['data']['transaction'])) {
                Log::error('Failed to get UPayment status', ['response' => $statusResponse]);
                return redirect()->route('payment.failed')->with('error', 'Failed to verify payment status.');
            }

            $transaction = $statusResponse['data']['transaction'];
            $result = strtoupper($transaction['result'] ?? '');
            $status = $transaction['status'] ?? '';
            $totalPaidAmount = floatval($transaction['total_price'] ?? 0);

            if ($result !== 'CAPTURED' || strtolower($status) !== 'done') {
                Log::error('[UPAYMENT] Transaction not successful', [
                    'result' => $result,
                    'status' => $status,
                    'track_id' => $trackId
                ]);

                UpaymentPayment::create([
                    'payment_int_id' => $payment->id,
                    'payment_id' => $transaction['payment_id'] ?? null,
                    'order_id' => $transaction['order_id'] ?? null,
                    'invoice_id' => $transaction['invoice_id'] ?? null,
                    'track_id' => $transaction['track_id'] ?? $trackId,
                    'status' => strtolower($transaction['status'] ?? 'failed'),
                    'payment_type' => $transaction['payment_type'] ?? null,
                    'payment_method' => $transaction['payment_method'] ?? null,
                    'total_price' => $transaction['total_price'] ?? null,
                    'payment_date' => $transaction['payment_date'] ?? $transaction['transaction_date'] ?? now(),
                    'payload' => $statusResponse,
                ]);

                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'failed', $partialId);

                $storeNotificationData = [
                    'user_id' => $receiptInfo['agent']->user_id,
                    'title'   => $receiptInfo['title'],
                    'message' => $receiptInfo['message'],
                    'type' => $process,
                ];

                if ($process === 'invoice' && $payment->invoice) {
                    $storeNotificationData['invoice'] = $payment->invoice;
                } else {
                    $storeNotificationData['payment'] = $payment;
                }

                $this->storeNotificationWithSendingPdf($storeNotificationData);

                (new ResayilController())->message(
                    $receiptInfo['agent']->phone_number,
                    $receiptInfo['agent']->country_code,
                    $receiptInfo['message']
                );

                return redirect()->to($receiptInfo['url'])->with('error', 'Payment was not completed or was cancelled.');
            }

            DB::transaction(function () use ($payment, $process, $totalPaidAmount, $trackId, $statusResponse, $transaction, $partialId) {

                $paymentTransaction = $payment->paymentTransactions()
                    ->where('reference_number', $trackId)
                    ->first();

                if ($paymentTransaction) {
                    $paymentTransaction->status = $transaction['status'] ?? 'done';
                    $paymentTransaction->track_id = $transaction['track_id'] ?? $paymentTransaction->track_id;
                    $paymentTransaction->save();

                    Log::info('[UPAYMENT] Payment transaction updated', [
                        'payment_transaction_id' => $paymentTransaction->id,
                        'status' => $paymentTransaction->status,
                    ]);
                }

                $payment->status = 'completed';
                $payment->completed = 1;
                $payment->service_charge = $totalPaidAmount - $payment->amount;
                $payment->payment_date = now();
                $payment->save();

                UpaymentPayment::create([
                    'payment_int_id' => $payment->id,
                    'payment_id' => $transaction['payment_id'] ?? null,
                    'order_id' => $transaction['order_id'] ?? null,
                    'invoice_id' => $transaction['invoice_id'] ?? null,
                    'track_id' => $transaction['track_id'] ?? $trackId,
                    'status' => strtolower($transaction['status'] ?? ''),
                    'payment_type' => $transaction['payment_type'] ?? null,
                    'payment_method' => $transaction['payment_method'] ?? null,
                    'total_price' => $transaction['total_price'] ?? null,
                    'payment_date' => $transaction['payment_date'] ?? $transaction['transaction_date'] ?? now(),
                    'payload' => $statusResponse,
                ]);

                if ($process === 'topup') {
                    $clientController = new ClientController;
                    $addCreditResponse = $clientController->addCredit($payment);

                    if (isset($addCreditResponse['error']) || (isset($addCreditResponse['status']) && $addCreditResponse['status'] === 'error')) {
                        throw new \RuntimeException('Failed to add credit: ' . ($addCreditResponse['message'] ?? $addCreditResponse['error']));
                    }

                    $transactionId = $addCreditResponse['data']['transaction_id'] ?? null;

                    if ($paymentTransaction && $transactionId) {
                        Log::info('[UPAYMENT] Updating payment transaction ID: ' . $paymentTransaction->id, [
                            'payment_id' => $payment->id,
                            'status' => $transaction['status'] ?? '',
                        ]);

                        $paymentTransaction->transaction_id = $transactionId;
                        $paymentTransaction->save();
                    } else {
                        Log::warning('[UPAYMENT] Payment transaction not found or missing transaction ID for reference: ' . $trackId, [
                            'payment_id' => $payment->id,
                            'transaction_id' => $transactionId,
                        ]);
                    }

                    Log::info('Credit added successfully via addCredit()', [
                        'payment_id' => $payment->id,
                        'response' => $addCreditResponse,
                    ]);
                } else {
                    if (!empty($partialId)) {
                        $partial = InvoicePartial::where('invoice_id', $payment->invoice_id)->where('id', $partialId)->first();
                        if ($partial) {
                            $partial->status = 'paid';
                            $partial->payment_id = $payment->id;
                            $partial->amount = $totalPaidAmount;
                            $partial->save();
                        }
                    }

                    $invoice = $payment->invoice()->with('invoicePartials:id,invoice_id,status')->first();
                    $hasUnpaid = $invoice->invoicePartials()->where('status', '!=', 'paid')->exists();
                    $hasPaid   = $invoice->invoicePartials()->where('status', 'paid')->exists();

                    if (!$hasUnpaid && $hasPaid) {
                        $invoice->status = 'paid';
                    } elseif ($hasUnpaid && $hasPaid) {
                        $invoice->status = 'partial';
                    }
                    $invoice->save();

                    $coaResult = $this->createInvoicePaymentCOA(
                        payment: $payment,
                        finalPaidAmount: $totalPaidAmount,
                        gatewayName: 'UPayment',
                        partialIds: !empty($partialId) ? [$partialId] : null,
                        paymentReference: $trackId
                    );

                    if (!$coaResult['success']) {
                        throw new \RuntimeException($coaResult['message']);
                    }
                }
            });

            // Process TBO booking if applicable (BEFORE sending notification)
            $tboResult = $this->processTBOBookingAfterPayment($payment);
            if ($tboResult !== null) {
                if ($tboResult['success']) {
                    Log::info('TBO booking processed successfully via UPayment callback', $tboResult);
                } else {
                    Log::error('TBO booking failed via UPayment callback', $tboResult);
                }
            }

            $payment->refresh();

            $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

            $storeNotificationData = [
                'user_id' => $receiptInfo['agent']->user_id,
                'title'   => $receiptInfo['title'],
                'message' => $receiptInfo['message'],
            ];

            if ($payment->invoice) {
                $storeNotificationData['type'] = 'invoice';
                $storeNotificationData['invoice'] = $payment->invoice;
            } else {
                $storeNotificationData['type'] = 'payment';
                $storeNotificationData['payment'] = $payment;
            }

            $this->storeNotificationWithSendingPdf($storeNotificationData);

            (new ResayilController())->message(
                $receiptInfo['agent']->phone_number,
                $receiptInfo['agent']->country_code,
                $receiptInfo['message']
            );

            return redirect()->to($receiptInfo['url'])->with('success', 'Payment successful!');
        } catch (\Exception $e) {
            Log::error('UPayment callback exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('payment.failed')->with('error', 'Something went wrong. Please contact support.');
        }
    }

    public function handleUPaymentError(Request $request)
    {
        Log::error('UPayment error callback', [
            'request' => $request->all(),
            'query' => $request->query(),
            'input' => $request->input(),
        ]);

        $trackId   = $request->input('track_id') ?? $request->query('trackId') ?? null;
        $paymentId = $request->input('payment_id') ?? null;
        $orderId   = $request->input('order_id') ?? null;
        $invoiceId = $request->input('invoice_id') ?? null;
        $payment = $trackId ? Payment::where('payment_reference', $trackId)->first() : null;

        UpaymentPayment::create([
            'payment_int_id' => $payment?->id,
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'track_id' => $trackId,
            'status' => 'cancelled',
            'payment_type' => $request->input('payment_type'),
            'payment_method' => $request->input('payment_method'),
            'total_price' => $request->input('total_price'),
            'payment_date' => now(),
            'payload'  => $request->all(),
        ]);

        if ($payment) {
            $process = $payment->invoice ? 'invoice' : 'topup';
            $partialId = $payment->invoice?->invoicePartials()->where('payment_id', $payment->id)->value('id');
            $receiptInfo = $this->publicReceiptNotice($payment, $process, 'failed', $partialId);

            $this->storeNotification([
                'user_id' => $receiptInfo['agent']->user_id,
                'title'   => $receiptInfo['title'],
                'message' => $receiptInfo['message'],
            ]);

            (new ResayilController())->message(
                $receiptInfo['agent']->phone_number,
                $receiptInfo['agent']->country_code,
                $receiptInfo['message']
            );

            return redirect()->to($receiptInfo['url'])->with('error', 'Payment was not completed or was cancelled.');
        }

        return redirect()->route('payment.failed');
    }

    public function handleUPaymentNoti()
    {
        Log::info('UPayment notification received', ['request' => request()->all()]);

        return response()->json(['message' => 'Notification received'], 200);
    }

    public function handleHesabeResponse(Request $request)
    {
        Log::info('Hesabe success response received', [$request->all()]);

        $configService = new GatewayConfigService();
        $hesabeConfig = $configService->getHesabeConfig();

        if (!$hesabeConfig['status'] || !$hesabeConfig['data']) {
            return redirect()->route('payment.failed')->with('error', $hesabeConfig['message'] ?? 'Hesabe configuration is missing or inactive');
        }

        $apiKey = $hesabeConfig['data']['api_key'];
        $encryptionKey = $hesabeConfig['data']['iv_key'];
        $response = $request->input('data');
        $decryptedResponse = HesabeCrypt::decrypt($response, $apiKey, $encryptionKey);

        if ($decryptedResponse === false) {
            Log::error('Hesabe: Response decryption failed ', ['response' => $response]);
            return redirect()->route('payment.failed')->with('error', 'Hesabe response decryption failed');
        }

        $responseData = json_decode($decryptedResponse, true);
        Log::info('Callback response data: ', ['response', $responseData]);
        $partialId = null;

        if ($responseData['status'] == true) {
            $data = $responseData['response'];
            $voucherNumber = $data['orderReferenceNumber'];
            $process = $data['variable1'];

            $paymentToken = $data['paymentToken'] ?? null;

            $raw = $data['variable2'] ?? null;
            $partialId = $raw ? intval($raw) : null;

            Log::info('Extracted Hesabe variable2 (partialId):', ['raw' => $raw, 'parsed' => $partialId]);

            $payment = Payment::where('voucher_number', $voucherNumber)->first();
            if (!$payment) {
                Log::info('Payment record not found', ['voucher_number' => $voucherNumber]);
                return redirect()->route('payment.failed')->with('error', 'Payment record not found');
            }

            if ($payment->status === 'completed') {
                Log::info('Hesabe callback: Payment already processed', [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                ]);
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);
                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
            }

            $payment->payment_reference = $data['transactionId'];
            $payment->invoice_reference = $data['trackID'];
            $payment->payment_date = $data['paidOn'] ?? now();
            $payment->status = 'completed';
            $payment->service_charge = $data['amount'] - $payment->amount;
            $payment->save();

            $paymentTransaction = null;

            if ($paymentToken) {
                Log::info('[HESABE] Payment token found in the response', [
                    'payment_token' => $paymentToken,
                    'status' => $data['resultCode'] ?? null,
                ]);

                $paymentTransaction = $payment->paymentTransactions()->where('reference_number', $paymentToken)->first();

                if ($paymentTransaction) {
                    $hesabe = new Hesabe();
                    $getPaymentStatus = $hesabe->getPaymentStatus($paymentToken);

                    if ($getPaymentStatus['status'] == true) {
                        $paymentTransaction->status = $getPaymentStatus['data']['status'] ?? 'Completed';
                        $paymentTransaction->save();

                        Log::info('[HESABE] Payment transaction updated to completed', [
                            'payment_transaction_id' => $paymentTransaction->id,
                            'status' => $paymentTransaction->status
                        ]);
                    }

                    $paymentTransaction->save();

                    Log::info('[HESABE] Payment transaction updated to completed', [
                        'payment_transaction_id' => $paymentTransaction->id
                    ]);
                } else {
                    Log::warning('[HESABE] Payment transaction not found for the given payment token', [
                        'payment_token' => $paymentToken
                    ]);
                }
            } else {
                Log::warning('[HESABE] Payment token is not found in the response', [
                    'response' => $responseData
                ]);
            }

            $tboResult = $this->processTBOBookingAfterPayment($payment);
            if ($tboResult !== null) {
                if ($tboResult['success']) {
                    Log::info('TBO booking processed successfully via Hesabe callback', $tboResult);
                } else {
                    Log::error('TBO booking failed via Hesabe callback', $tboResult);
                }
            }

            $payment->refresh();

            $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);
        } else {
            Log::error('Response from Hesabe failed', ['response' => $responseData]);

            $voucherNumber = $responseData['response']['orderReferenceNumber'] ?? null;
            $payment = $voucherNumber ? Payment::where('voucher_number', $voucherNumber)->first() : null;

            if ($payment) {
                $process = $payment->invoice ? 'invoice' : 'topup';
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'failed', $partialId);

                $this->storeNotification([
                    'user_id' => $receiptInfo['agent']->user_id,
                    'title'   => $receiptInfo['title'],
                    'message' => $receiptInfo['message'],
                ]);

                (new ResayilController())->message(
                    $receiptInfo['agent']->phone_number,
                    $receiptInfo['agent']->country_code,
                    $receiptInfo['message']
                );

                return redirect()->to($receiptInfo['url'])->with('error', 'Payment failed or cancelled.');
            }

            return redirect()->route('payment.failed')->with('error', 'Payment failed.');
        }

        DB::beginTransaction();

        try {

            HesabePayment::updateOrCreate(
                [
                    'payment_int_id' => $payment->id,
                ],
                [
                    'status' => $data['resultCode'] ?? null,
                    'payment_token' => $data['paymentToken'] ?? null,
                    'payment_id' => $data['paymentId'] ?? null,
                    'order_reference_number' => $data['orderReferenceNumber'] ?? null,
                    'auth_code' => $data['auth'] ?? null,
                    'track_id' => $data['trackID'] ?? null,
                    'transaction_id' => $data['transactionId'] ?? null,
                    'invoice_id' => $data['Id'] ?? null,
                    'paid_on' => $data['paidOn'] ?? null,
                    'payload' => $responseData,
                ]
            );

            if ($process === 'topup') {
                Log::info('Starting to process the credit for successful callback from Hesabe');

                $clientController = new ClientController;
                $addCreditResponse = $clientController->addCredit($payment);

                if (isset($addCreditResponse['error']) || (isset($addCreditResponse['status']) && $addCreditResponse['status'] === 'error')) {
                    Log::error('Failed to add credit to client', [
                        'message' => $addCreditResponse['error'] ?? $addCreditResponse['message'],
                        'payment_reference' => $data['transactionId'],
                    ]);
                    return redirect()->to($receiptInfo['url'])->with('error', $addCreditResponse['error'] ?? $addCreditResponse['message']);
                }

                $transactionId = $addCreditResponse['data']['transaction_id'] ?? null;

                if ($paymentTransaction && $transactionId) {

                    Log::info('[HESABE] Updating payment transaction ID: ' . $paymentTransaction->id, [
                        'payment_id' => $payment->id,
                        'transaction_id' => $transactionId,
                    ]);

                    $paymentTransaction->transaction_id = $transactionId;
                    $paymentTransaction->save();
                } else {

                    Log::warning('[HESABE] Payment transaction not found or transaction ID missing', [
                        'payment_id' => $payment->id,
                        'transaction_id' => $transactionId,
                    ]);
                }

                Log::info('Credit added successfully via addCredit()', [
                    'payment_id' => $payment->id,
                    'response' => $addCreditResponse,
                ]);
            } elseif ($process === 'invoice') {
                Log::info('Starting to process the invoice for successful callback from Hesabe');

                $coaResult = $this->createInvoicePaymentCOA(
                    payment: $payment,
                    finalPaidAmount: (float) $data['amount'],
                    gatewayName: 'Hesabe',
                    partialIds: $partialId ? [$partialId] : null,
                    paymentReference: $data['transactionId'] ?? null
                );

                if (!$coaResult['success']) {
                    Log::error('Failed to create journal entry for invoice payment', ['message' => $coaResult['message']]);
                    return redirect()->to($receiptInfo['url'])->with('error', $coaResult['message']);
                }
            }

            $agent = $payment->agent;

            $storeNotificationData = [
                'user_id' => $agent->user_id,
                'title' => $receiptInfo['title'],
                'message' => $receiptInfo['message'],
            ];

            if ($payment->invoice) {
                $storeNotificationData['type'] = 'invoice';
                $storeNotificationData['invoice'] = $payment->invoice;
            } else {
                $storeNotificationData['type'] = 'payment';
                $storeNotificationData['payment'] = $payment;
            }

            Log::info('[HESABE] Storing notification with PDF for agent ID: ' . $agent->id, $storeNotificationData);

            $this->storeNotificationWithSendingPdf($storeNotificationData);

            (new ResayilController())->message(
                $agent->phone_number,
                $agent->country_code,
                $receiptInfo['message']
            );
        } catch (Exception $e) {
            DB::rollback();
            logger('Failed to process the payment to Hesabe gateway', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->to($receiptInfo['url'])->with('error', 'Payment to Hesabe failed');
        }

        DB::commit();

        return redirect()->to($receiptInfo['url'])->with('success', 'Payment successful!');
    }

    public function handleHesabeFailure(Request $request)
    {
        Log::error('Hesabe failure response received', [
            'request' => $request->all(),
        ]);

        $configService = new GatewayConfigService();
        $hesabeConfig = $configService->getHesabeConfig();

        if (!$hesabeConfig['status'] || !$hesabeConfig['data']) {
            return redirect()->back()->with('error', $hesabeConfig['message'] ?? 'Hesabe configuration is missing or inactive');
        }

        $apiKey = $hesabeConfig['data']['api_key'];
        $encryptionKey = $hesabeConfig['data']['iv_key'];
        $response = $request->input('data');

        $decryptedResponse = HesabeCrypt::decrypt($response, $apiKey, $encryptionKey);
        if ($decryptedResponse === false) {
            Log::error('Hesabe: Response decryption failed ', [
                'response' => $decryptedResponse
            ]);
            return redirect()->back()->with('error', 'Hesabe response decryption failed');
        }

        $responseData = json_decode($decryptedResponse, true);
        Log::info('Failure callback response data: ', [
            'response',
            $responseData
        ]);

        if (!isset($responseData['status']) || $responseData['status'] !== false) {
            return redirect()->route('payment.failed')->with('error', 'Invalid failure response format.');
        }

        DB::beginTransaction();
        try {
            $data = $responseData['response'];
            $voucherNumber = $data['orderReferenceNumber'];
            $partialId = null;

            $raw = $data['variable2'] ?? null;
            $partialId = $raw ? intval($raw) : null;

            Log::info('Extracted Hesabe failure variable2 (partialId):', [
                'raw' => $raw,
                'parsed' => $partialId,
            ]);

            if (!$voucherNumber) {
                Log::error('Missing voucher number in failure response', ['data' => $data]);
                return redirect()->route('payment.failed')->with('error', 'Invalid failure response — missing reference number.');
            }

            $payment = Payment::where('voucher_number', $voucherNumber)->first();
            if ($payment) {
                $payment->payment_reference = $data['transactionId'];
                $payment->payment_date = $data['paidOn'] ?? now();
                $payment->status = 'failed';
                $payment->save();
            }

            HesabePayment::updateOrCreate(
                [
                    'payment_int_id' => $payment->id,
                ],
                [
                    'status' => $data['resultCode'] ?? null,
                    'payment_token' => $data['paymentToken'] ?? null,
                    'payment_id' => $data['paymentId'] ?? null,
                    'order_reference_number' => $data['orderReferenceNumber'] ?? null,
                    'auth_code' => $data['auth'] ?? null,
                    'track_id' => $data['trackID'] ?? null,
                    'transaction_id' => $data['transactionId'] ?? null,
                    'invoice_id' => $data['Id'] ?? null,
                    'paid_on' => $data['paidOn'] ?? null,
                    'payload' => $responseData,
                ]
            );

            DB::commit();

            if ($payment) {
                $process = $payment && $payment->invoice_id ? 'invoice' : 'topup';
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'failed', $partialId);

                $this->storeNotification([
                    'user_id' => $receiptInfo['agent']->user_id,
                    'title'   => $receiptInfo['title'],
                    'message' => $receiptInfo['message'],
                ]);

                (new ResayilController())->message(
                    $receiptInfo['agent']->phone_number,
                    $receiptInfo['agent']->country_code,
                    $receiptInfo['message']
                );

                return redirect()->to($receiptInfo['url'])->with('error', 'Payment failed — Transaction declined.');
            }

            return redirect()->route('payment.failed')->with('error', 'Payment failed.');
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Failed to process Hesabe failure', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('payment.failed')->with('error', 'Payment failed! Something went wrong while processing failure.');
        }
    }

    public function handleHesabeWebhook(Request $request)
    {
        Log::info('Hesabe webhook received', ['request' => $request->all()]);

        // Extract webhook data - Hesabe sends unencrypted JSON directly
        $voucherNumber = $request->input('reference_number');
        $paymentToken = $request->input('token');
        $status = $request->input('status');
        $statusCode = $request->input('status_code');
        $amount = $request->input('amount');
        $paymentType = $request->input('payment_type');
        $serviceType = $request->input('service_type');
        $datetime = $request->input('datetime');

        if (!$voucherNumber || !$status) {
            Log::error('Hesabe webhook: Missing required fields', [
                'reference_number' => $voucherNumber,
                'status' => $status,
            ]);
            return response()->json(['error' => 'Invalid request - missing required fields'], 400);
        }

        Log::info('Hesabe webhook data extracted:', [
            'voucher_number' => $voucherNumber,
            'payment_token' => $paymentToken,
            'status' => $status,
            'status_code' => $statusCode,
            'amount' => $amount,
            'payment_type' => $paymentType,
        ]);

        DB::beginTransaction();
        try {
            $payment = Payment::where('voucher_number', $voucherNumber)->first();

            if (!$payment) {
                Log::error('Hesabe webhook: Payment record not found', ['voucher_number' => $voucherNumber]);
                DB::rollback();
                return response()->json(['error' => 'Payment not found'], 404);
            }

            // Check if already processed
            if ($payment->status === 'completed') {
                Log::info('Hesabe webhook: Payment already processed', [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                ]);
                DB::rollback();
                return response()->json([
                    'message' => 'Payment already processed',
                    'status' => 'success',
                ], 200);
            }

            // Determine process type from payment record
            $process = $payment->invoice ? 'invoice' : 'topup';
            $partialId = $payment->invoice ? $payment->invoice->invoicePartials()->where('payment_id', $payment->id)->value('id') : null;

            Log::info('Hesabe webhook: Processing payment', [
                'payment_id' => $payment->id,
                'process' => $process,
                'partial_id' => $partialId,
            ]);

            // Check if payment was successful
            if (strtoupper($status) === 'SUCCESSFUL' && $statusCode == 1) {

                $paymentStatusData = null;
                $fullPaymentResponse = null;
                $paymentTransaction = null;

                if ($paymentToken) {
                    Log::info('[HESABE WEBHOOK] Payment token found in the response', [
                        'payment_token' => $paymentToken,
                        'status' => $status,
                    ]);

                    $hesabe = new Hesabe();
                    $getPaymentStatus = $hesabe->getPaymentStatus($paymentToken);

                    if ($getPaymentStatus['status'] == true) {
                        $paymentStatusData = $getPaymentStatus['data'];
                        $fullPaymentResponse = $getPaymentStatus;

                        Log::info('[HESABE WEBHOOK] Full payment status retrieved', [
                            'payment_token' => $paymentToken,
                            'data' => $paymentStatusData,
                        ]);

                        $paymentTransaction = $payment->paymentTransactions()->where('reference_number', $paymentToken)->first();

                        if ($paymentTransaction) {
                            $paymentTransaction->status = $paymentStatusData['status'] ?? 'Completed';
                            $paymentTransaction->track_id = $paymentStatusData['TrackID'] ?? $paymentTransaction->track_id;
                            $paymentTransaction->save();

                            Log::info('[HESABE WEBHOOK] Payment transaction updated to completed', [
                                'payment_transaction_id' => $paymentTransaction->id,
                                'status' => $paymentTransaction->status
                            ]);
                        } else {
                            Log::warning('[HESABE WEBHOOK] Payment transaction not found for the given payment token', [
                                'payment_token' => $paymentToken
                            ]);
                        }
                    } else {
                        Log::warning('[HESABE WEBHOOK] Failed to get payment status from Hesabe API', [
                            'payment_token' => $paymentToken,
                            'response' => $getPaymentStatus,
                        ]);
                    }
                }

                $payment->payment_reference = $paymentStatusData['TransactionID'] ?? $paymentToken;
                $payment->invoice_reference = $paymentStatusData['TrackID'] ?? $voucherNumber;
                $payment->payment_date = $datetime ? \Carbon\Carbon::parse($datetime) : now();
                $payment->status = 'completed';
                $payment->service_charge = isset($paymentStatusData['amount']) ? $paymentStatusData['amount'] - $payment->amount : 0;
                $payment->save();

                // Process TBO booking if applicable
                $tboResult = $this->processTBOBookingAfterPayment($payment);
                if ($tboResult !== null) {
                    if ($tboResult['success']) {
                        Log::info('TBO booking processed successfully via Hesabe webhook', $tboResult);
                    } else {
                        Log::error('TBO booking failed via Hesabe webhook', $tboResult);
                    }
                }

                $payment->refresh();

                // Update Hesabe payment record
                HesabePayment::updateOrCreate(
                    ['payment_int_id' => $payment->id],
                    [
                        'status' => $paymentStatusData['status'] ?? $status,
                        'payment_token' => $paymentStatusData['token'] ?? $paymentToken,
                        'payment_id' => $paymentStatusData['PaymentID'] ?? null,
                        'order_reference_number' => $paymentStatusData['reference_number'] ?? $voucherNumber,
                        'auth_code' => $paymentStatusData['auth'] ?? null,
                        'track_id' => $paymentStatusData['TrackID'] ?? null,
                        'transaction_id' => $paymentStatusData['TransactionID'] ?? null,
                        'invoice_id' => $paymentStatusData['Id'] ?? null,
                        'paid_on' => $paymentStatusData['datetime'] ?? $datetime,
                        'payload' => $fullPaymentResponse ?? $request->all(),
                    ]
                );

                // Process based on payment type
                if ($process === 'topup') {
                    Log::info('Hesabe webhook: Processing credit for topup');
                    $clientController = new ClientController;
                    $addCreditResponse = $clientController->addCredit($payment);

                    if (isset($addCreditResponse['error'])) {
                        Log::error('Hesabe webhook: Failed to add credit to client', [
                            'message' => $addCreditResponse['error'],
                            'payment_reference' => $paymentToken,
                        ]);
                        DB::rollback();
                        return response()->json(['error' => $addCreditResponse['error']], 500);
                    }

                    $transactionId = $addCreditResponse['data']['transaction_id'] ?? null;

                    if ($paymentTransaction && $transactionId) {

                        Log::info('[HESABE WEBHOOK] Updating payment transaction ID: ' . $paymentTransaction->id, [
                            'payment_id' => $payment->id,
                            'transaction_id' => $transactionId,
                        ]);

                        $paymentTransaction->transaction_id = $transactionId;
                        $paymentTransaction->save();
                    } else {

                        Log::warning('[HESABE WEBHOOK] Payment transaction not found or transaction ID missing', [
                            'payment_id' => $payment->id,
                            'transaction_id' => $transactionId,
                        ]);
                    }

                    Log::info('Credit added successfully via addCredit()', [
                        'payment_id' => $payment->id,
                        'response' => $addCreditResponse,
                    ]);
                } else {
                    // Process invoice payment
                    Log::info('Hesabe webhook: Processing invoice payment');

                    $coaResult = $this->createInvoicePaymentCOA(
                        payment: $payment,
                        finalPaidAmount: floatval($amount),
                        gatewayName: 'Hesabe',
                        partialIds: $partialId ? [$partialId] : null,
                        paymentReference: $paymentToken
                    );

                    if (!$coaResult['success']) {
                        Log::error('Hesabe webhook: Failed to create invoice journal entry', [
                            'message' => $coaResult['message'],
                        ]);
                    }
                }

                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

                $storeNotificationData = [
                    'user_id' => $receiptInfo['agent']->user_id,
                    'title' => $receiptInfo['title'],
                    'message' => $receiptInfo['message'],
                ];

                if ($payment->invoice) {
                    $storeNotificationData['type'] = 'invoice';
                    $storeNotificationData['invoice'] = $payment->invoice;
                } else {
                    $storeNotificationData['type'] = 'payment';
                    $storeNotificationData['payment'] = $payment;
                }

                Log::info('Hesabe webhook: Storing notification', $storeNotificationData);

                $this->storeNotificationWithSendingPdf($storeNotificationData);

                (new ResayilController())->message(
                    $receiptInfo['agent']->phone_number,
                    $receiptInfo['agent']->country_code,
                    $receiptInfo['message']
                );

                DB::commit();

                Log::info('Hesabe webhook: Payment processed successfully', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $paymentToken,
                ]);

                return response()->json([
                    'message' => 'Payment processed successfully',
                    'status' => 'success',
                ], 200);
            } else {
                // Payment failed
                $paymentStatusData = null;
                $fullPaymentResponse = null;

                if ($paymentToken) {
                    $hesabe = new Hesabe();
                    $getPaymentStatus = $hesabe->getPaymentStatus($paymentToken);

                    if ($getPaymentStatus['status'] == true) {
                        $paymentStatusData = $getPaymentStatus['data'];
                        $fullPaymentResponse = $getPaymentStatus;
                    }
                }

                Log::error('Hesabe webhook: Payment failed', [
                    'status' => $status,
                    'status_code' => $statusCode,
                    'voucher_number' => $voucherNumber,
                ]);

                $payment->payment_reference = $paymentStatusData['TransactionID'] ?? $paymentToken;
                $payment->invoice_reference = $paymentStatusData['TrackID'] ?? $voucherNumber;
                $payment->payment_date = $datetime ? \Carbon\Carbon::parse($datetime) : now();
                $payment->status = 'failed';
                $payment->save();

                HesabePayment::updateOrCreate(
                    ['payment_int_id' => $payment->id],
                    [
                        'status' => $paymentStatusData['status'] ?? $status,
                        'payment_token' => $paymentStatusData['token'] ?? $paymentToken,
                        'payment_id' => $paymentStatusData['PaymentID'] ?? null,
                        'order_reference_number' => $paymentStatusData['reference_number'] ?? $voucherNumber,
                        'auth_code' => $paymentStatusData['auth'] ?? null,
                        'track_id' => $paymentStatusData['TrackID'] ?? null,
                        'transaction_id' => $paymentStatusData['TransactionID'] ?? null,
                        'invoice_id' => $paymentStatusData['Id'] ?? null,
                        'paid_on' => $paymentStatusData['datetime'] ?? $datetime,
                        'payload' => $fullPaymentResponse ?? $request->all(),
                    ]
                );

                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'failed', $partialId);

                $this->storeNotification([
                    'user_id' => $receiptInfo['agent']->user_id,
                    'title'   => $receiptInfo['title'],
                    'message' => $receiptInfo['message'],
                ]);

                (new ResayilController())->message(
                    $receiptInfo['agent']->phone_number,
                    $receiptInfo['agent']->country_code,
                    $receiptInfo['message']
                );

                DB::commit();

                return response()->json(['message' => 'Payment failed processed'], 200);
            }
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Hesabe webhook: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Create COA entries for invoice payment via payment gateway
     * 
     * This unified method handles:
     * - Updating invoice partials to paid
     * - Updating invoice status (paid/partial)
     * - Completing refund if applicable
     * - Creating transaction record
     * - Creating all journal entries (receivable, gateway asset, gateway fee)
     * - Updating account balances
     * 
     * @param Payment $payment - The payment record
     * @param float $finalPaidAmount - What client actually paid (including service charge if client pays)
     * @param string $gatewayName - Gateway name for charge lookup (MyFatoorah, Tap, Hesabe, UPayment, KNET)
     * @param array|null $partialIds - Array of partial IDs to mark as paid
     * @param string|null $paymentReference - Payment reference from gateway
     * @return array ['success' => bool, 'message' => string, 'transaction_id' => int|null]
     */
    private function createInvoicePaymentCOA(
        Payment $payment,
        float $finalPaidAmount,
        string $gatewayName,
        ?array $partialIds = null,
        ?string $paymentReference = null
    ): array {
        try {
            return DB::transaction(function () use ($payment, $finalPaidAmount, $gatewayName, $partialIds, $paymentReference) {
                $invoice = $payment->invoice;

                if (!$invoice) {
                    throw new \Exception('Invoice not found for payment');
                }

                $companyId = $payment->agent->branch->company_id;

                if (!empty($partialIds)) {
                    InvoicePartial::where('invoice_id', $invoice->id)
                        ->whereIn('id', $partialIds)
                        ->update([
                            'status' => 'paid',
                            'payment_id' => $payment->id,
                        ]);

                    Log::info('[INVOICE COA] Updated invoice partials to paid', [
                        'invoice_id' => $invoice->id,
                        'partial_ids' => $partialIds,
                    ]);
                }

                $allPartials = InvoicePartial::where('invoice_id', $invoice->id)->get();
                $paidCount = $allPartials->where('status', 'paid')->count();
                $totalCount = $allPartials->count();

                if ($totalCount > 0) {
                    if ($paidCount === $totalCount) {
                        $invoice->status = 'paid';
                    } elseif ($paidCount > 0) {
                        $invoice->status = 'partial';
                    }
                } else {
                    $invoice->status = 'paid';
                }

                $invoice->paid_date = now();
                $invoice->save();

                Log::info('[INVOICE COA] Updated invoice status', [
                    'invoice_id' => $invoice->id,
                    'status' => $invoice->status,
                    'paid_count' => $paidCount,
                    'total_count' => $totalCount,
                ]);

                if ($invoice->status === 'paid') {
                    $this->completeRefundIfApplicable($payment);
                }

                $chargeRecord = Charge::where('name', 'LIKE', "%{$gatewayName}%")
                    ->where('company_id', $companyId)
                    ->first();

                if (!$chargeRecord) {
                    throw new \Exception("Charge record not found for gateway: {$gatewayName}");
                }

                $gatewayAssetAccount = Account::find($chargeRecord->acc_fee_bank_id);
                $gatewayExpenseAccount = Account::find($chargeRecord->acc_fee_id);
                $receivableAccount = Account::where('name', 'Clients')->first();

                if (!$gatewayAssetAccount || !$gatewayExpenseAccount || !$receivableAccount) {
                    throw new \Exception('One or more required financial accounts not found');
                }

                $chargeResult = ChargeService::calculate($payment->amount, $companyId, $payment->payment_method_id, $gatewayName);
                $accountingFee = $chargeResult['accountingFee'] ?? 0;
                $paidBy = $chargeResult['paidBy'] ?? 'Company';

                $payment->gateway_fee = $accountingFee;
                $payment->save();

                $netAmount = $finalPaidAmount - $accountingFee;

                Log::info('[INVOICE COA] Amount calculations', [
                    'payment_id' => $payment->id,
                    'final_paid_amount' => $finalPaidAmount,
                    'gateway_fee' => $accountingFee,
                    'net_amount' => $netAmount,
                    'gateway' => $gatewayName,
                ]);

                $transaction = Transaction::create([
                    'branch_id' => $invoice->agent->branch->id,
                    'company_id' => $companyId,
                    'entity_id' => $companyId,
                    'entity_type' => 'company',
                    'transaction_type' => 'debit',
                    'amount' => $finalPaidAmount,
                    'description' => "{$gatewayName} payment success: {$invoice->invoice_number}",
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'payment_reference' => $paymentReference ?? $payment->payment_reference,
                    'reference_type' => 'Invoice',
                    'transaction_date' => now(),
                ]);

                $invoiceDetail = InvoiceDetail::where('invoice_number', $invoice->invoice_number)->first();
                $client = $invoice->client;

                if (!$invoiceDetail || !$client) {
                    throw new \Exception('Invoice detail or client not found');
                }

                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $invoice->agent->branch->id,
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    'account_id' => $receivableAccount->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'transaction_date' => now(),
                    'description' => "Client payment received via {$gatewayName}",
                    'debit' => 0,
                    'credit' => $finalPaidAmount,
                    'balance' => $invoiceDetail->task_price - $finalPaidAmount,
                    'name' => $client->full_name,
                    'type' => 'receivable',
                    'voucher_number' => $payment->voucher_number,
                    'type_reference_id' => $receivableAccount->id,
                ]);

                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $invoice->agent->branch->id,
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    'account_id' => $gatewayAssetAccount->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'transaction_date' => now(),
                    'description' => 'Net payment received',
                    'debit' => $netAmount,
                    'credit' => 0,
                    'balance' => $gatewayAssetAccount->actual_balance + $netAmount,
                    'name' => $gatewayAssetAccount->name,
                    'type' => 'bank',
                    'voucher_number' => $payment->voucher_number,
                    'type_reference_id' => $gatewayAssetAccount->id,
                ]);

                $gatewayAssetAccount->actual_balance += $netAmount;
                $gatewayAssetAccount->save();

                $feeDescription = ($paidBy === 'Company' ? 'Company Pays Gateway Fee: ' : 'Client Pays Gateway Fee: ') . $gatewayExpenseAccount->name;

                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $invoice->agent->branch->id,
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    'account_id' => $gatewayExpenseAccount->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'transaction_date' => now(),
                    'description' => $feeDescription,
                    'debit' => $accountingFee,
                    'credit' => 0,
                    'balance' => $gatewayExpenseAccount->actual_balance + $accountingFee,
                    'name' => $gatewayExpenseAccount->name,
                    'type' => 'charges',
                    'voucher_number' => $payment->voucher_number,
                    'type_reference_id' => $gatewayExpenseAccount->id,
                ]);

                $gatewayExpenseAccount->actual_balance += $accountingFee;
                $gatewayExpenseAccount->save();

                Log::info('[INVOICE COA] Journal entries created successfully', [
                    'transaction_id' => $transaction->id,
                    'payment_id' => $payment->id,
                    'invoice_number' => $invoice->invoice_number,
                    'credit_receivable' => $finalPaidAmount,
                    'debit_gateway_asset' => $netAmount,
                    'debit_gateway_fee' => $accountingFee,
                    'balanced' => ($finalPaidAmount == ($netAmount + $accountingFee)) ? 'YES' : 'NO',
                ]);

                // Recalculate profit after each payment (deduct gateway fees progressively)
                $invoiceController = app(InvoiceController::class);
                $invoiceController->recalculateInvoiceCOA($invoice);

                return [
                    'success' => true,
                    'message' => 'Invoice payment COA created successfully',
                    'transaction_id' => $transaction->id,
                ];
            });
        } catch (\Exception $e) {
            Log::error('[INVOICE COA] Failed to create COA entries', [
                'payment_id' => $payment->id,
                'gateway' => $gatewayName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error creating COA: ' . $e->getMessage(),
                'transaction_id' => null,
            ];
        }
    }

    public function success()
    {
        return view('payment.success');
    }

    public function failed()
    {
        return view('payment.failed');
    }

    public function hesabeTransactionEnquiry(Request $request): JsonResponse
    {
        $request->validate([
            'data' => 'required|string',
            'accessCode' => 'required|string',
            'isOrderReference' => 'sometimes|boolean',
        ]);

        $dataValue   = $request->input('data');
        $accessCode  = $request->input('accessCode');
        $useOrderRef = $request->boolean('isOrderReference', false);

        $configService = new GatewayConfigService();
        $hesabeConfig = $configService->getHesabeConfig();
        $baseUrl = $hesabeConfig['data']['base_url'];

        $url = rtrim($baseUrl, '/') . '/api/transaction/' . urlencode($dataValue);

        if ($useOrderRef) {
            $url .= '?isOrderReference=1';
        }

        try {
            $response = Http::withHeaders([
                'accessCode' => $accessCode,
                'Accept'     => 'application/json',
            ])->get($url);
        } catch (Exception $e) {
            Log::error('Hesabe Transaction Enquiry HTTP error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to call Hesabe Transaction Enquiry: ' . $e->getMessage(),
            ], 500);
        }

        $statusCode = $response->status();
        $body = $response->json();

        Log::info('Hesabe Transaction Enquiry response', [
            'url' => $url,
            'response_status' => $statusCode,
            'body' => $body,
        ]);

        if ($statusCode >= 200 && $statusCode < 300) {
            return response()->json($body);
        }

        return response()->json([
            'status' => 'error',
            'message' => $body['message'] ?? 'Hesabe Transaction Enquiry failed',
            'code' => $statusCode,
        ], $statusCode);
    }

    public function getHesabeTransaction(string $orderRef): JsonResponse
    {
        $user = Auth::user();

        if ($user->role_id == Role::AGENT) {
            $companyId = $user->agent->branch->company_id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company_id;
        } elseif ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
        } else {
            $companyId = null;
        }

        $charge = Charge::where('company_id', $companyId)
            ->where('name', 'Hesabe')
            ->first();

        if (!$charge) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Hesabe configuration not found for this company.'
            ]);
        }
        $configService = new GatewayConfigService();
        $hesabeConfig = $configService->getHesabeConfig();
        $baseUrl = $hesabeConfig['data']['base_url'];
        $accessCode = $hesabeConfig['data']['access_code'];

        $url = $baseUrl . '/api/transaction/' . urlencode($orderRef) . '?isOrderReference=1';

        try {
            $response = Http::withHeaders([
                'accessCode' => $accessCode,
                'Accept'     => 'application/json',
            ])->get($url);
        } catch (\Exception $e) {
            Log::error('Import Hesabe Transaction error', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to call Hesabe Transaction Enquiry: ' . $e->getMessage(),
            ]);
        }
        Log::info('Response: ', ['data' => $response]);

        $responseData = $response->json();

        if (empty($responseData) || empty($responseData['data'])) {
            Log::error('No data found in Hesabe response', ['response' => $responseData]);

            return response()->json([
                'status' => 'error',
                'message' => 'No data found in Hesabe response'
            ], 404);
        }

        $referenceNumber = $responseData['data']['reference_number'] ?? null;

        if (!$referenceNumber) {
            Log::info('Reference Number not found in Hesabe portal', ['response' => $responseData]);

            return response()->json([
                'status' => 'error',
                'message' => 'No such transaction found in Hesabe portal'
            ], 400);
        }

        $transactionStatus = $responseData['data']['status'];

        if (!$transactionStatus) {
            Log::error('Transaction status not found in Hesabe response', [
                'response' => $responseData
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Transaction status not found in Hesabe response'
            ], 400);
        }

        $paymentMethodId = null;

        if ($transactionStatus === 'SUCCESSFUL') {

            $referenceNumber   = $responseData['data']['reference_number'] ?? null;
            $transactionId     = $responseData['data']['TransactionID'] ?? null;
            $trackId           = $responseData['data']['TrackID'] ?? null;

            if (Payment::where('voucher_number', $referenceNumber)->exists()) {
                Log::info('Duplicate payment found by voucher_number', [
                    'voucher_number' => $referenceNumber,
                ]);

                return response()->json([
                    'status'  => 'error',
                    'message' => 'A payment with this Order Reference Number has already been imported.'
                ], 400);
            }

            if (Payment::where('payment_reference', $transactionId)->exists()) {
                Log::info('Duplicate payment found by TransactionID', [
                    'payment_reference' => $transactionId,
                ]);

                return response()->json([
                    'status'  => 'error',
                    'message' => 'A payment with this Transaction ID has already been imported.'
                ], 400);
            }

            if (Payment::where('invoice_reference', $trackId)->exists()) {
                Log::info('Duplicate payment found by TrackID', [
                    'invoice_reference' => $trackId,
                ]);

                return response()->json([
                    'status'  => 'error',
                    'message' => 'A payment with this Track ID has already been imported.'
                ], 400);
            }

            $paymentMethod = $responseData['data']['payment_type'];
            $paymentMethodId = PaymentMethod::whereRaw('LOWER(english_name) = ?', [strtolower($paymentMethod)])->value('id');
        } elseif ($transactionStatus === 'FAILED') {
            Log::info('Transaction status is not paid', [
                'transaction_status' => $transactionStatus
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Transaction status is not paid'
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Transaction status fetched successfully',
            'data' => $responseData['data'],
            'amount' => $responseData['data']['amount'],
            'payment_reference' => $responseData['data']['TransactionID'],
            'transaction_status' => $transactionStatus,
            'invoice_reference' => $responseData['data']['TrackID'],
            'customer_name' => $responseData['data']['customerName'] ?? null,
            'created_date' => $responseData['data']['datetime'],
            'payment_gateway' => 'Hesabe',
            'payment_method_id' => $paymentMethodId,
        ]);
    }

    protected function completeRefundIfApplicable(Payment $payment)
    {
        $invoice = $payment->invoice;

        if ($invoice) {
            $refund = Refund::where('refund_invoice_id', $invoice->id)->first();

            if ($refund && $refund->status !== 'completed') {
                $refund->update(['status' => 'completed']);

                Log::info('Refund automatically marked as completed (by invoice link)', [
                    'refund_id' => $refund->id,
                    'refund_invoice_id' => $invoice->id,
                ]);
            }
        }
    }

    private function publicReceiptNotice(
        Payment $payment,
        ?string $process = null,
        string $status = 'success',
        ?int $partialId = null
    ): array {
        $isInvoice = $process === 'invoice' || (!empty($payment->invoice_id) && $process !== 'topup');

        $hotelBooking = $payment->hotelBooking()->with('tbo')->first();
        $isHotelBooking = !empty($hotelBooking) && !$isInvoice;

        $invoicePartialType = $payment->invoice?->invoicePartials()->where('payment_id', $payment->id)->value('type');
        $isPartial = in_array(strtolower($invoicePartialType ?? ''), ['split', 'partial']);

        if ($isPartial) {
            $route = [
                'name' => 'invoice.split',
                'params' => [
                    'invoiceNumber' => $payment->invoice->invoice_number,
                    'clientId' => $payment->client_id,
                    'partialId' => $partialId,
                ],
            ];
        } else {
            $route = $isInvoice
                ? [
                    'name' => 'invoice.show',
                    'params' => [
                        'companyId' => $payment->agent->branch->company_id,
                        'invoiceNumber' => $payment->invoice->invoice_number,
                    ],
                ]
                : [
                    'name' => 'payment.link.show',
                    'params' => [
                        'companyId' => $payment->agent->branch->company_id,
                        'voucherNumber' => $payment->voucher_number,
                    ],
                ];
        }

        $url = route($route['name'], $route['params']);

        if ($status === 'success') {
            if ($isPartial) {
                return [
                    'agent'  => $payment->invoice->agent,
                    'title'   => $payment->invoice->invoice_number . ' partial payment paid successfully',
                    'message' => 'Your client ' . $payment->client->full_name . ' successfully paid part of invoice ' . $payment->invoice->invoice_number . ".\n\nCheck the link : " . $url,
                    'url' => $url,
                    'route' => $route,
                ];
            } elseif ($isInvoice) {
                return [
                    'agent'  => $payment->invoice->agent,
                    'title'   => $payment->invoice->invoice_number . ' paid successfully',
                    'message' => 'Your client ' . $payment->client->full_name . ' has paid invoice ' . $payment->invoice->invoice_number .
                        ".\n\nCheck the link : " . $url,
                    'url' => $url,
                    'route' => $route,
                ];
            } elseif ($isHotelBooking) {

                $tbo = $hotelBooking->tbo;
                $confirmationInfo = '';

                if ($tbo && $tbo->confirmation_no) {
                    $confirmationInfo = " (Confirmation: {$tbo->confirmation_no})";
                }

                return [
                    'agent'  => $payment->agent,
                    'title'   => 'Hotel Booking Payment Successful',
                    'message' => 'Your client ' . $payment->client->full_name . ' has successfully paid for hotel booking' . $confirmationInfo .
                        ' with amount ' . number_format($payment->amount, 3) . ' ' . $payment->currency .
                        ' using voucher ' . $payment->voucher_number . ".\n\nCheck the link : " . $url,
                    'url' => $url,
                    'route' => $route,
                ];
            } else {
                return [
                    'agent'  => $payment->agent,
                    'title'   => 'Client ' . $payment->client->full_name . ' Topup Successful',
                    'message' => 'Your client ' . $payment->client->full_name . ' has successfully topped up ' . number_format($payment->amount, 3) .
                        ' ' . $payment->currency . ' using voucher ' . $payment->voucher_number . ".\n\nCheck the link : " . $url,
                    'url' => $url,
                    'route' => $route,
                ];
            }
        }

        if ($isPartial) {
            return [
                'agent' => $payment->invoice->agent,
                'title' => 'Client ' . $payment->client->full_name . "'s Partial Payment Failed",
                'message' => 'Your client ' . $payment->client->full_name . ' attempted to pay a part of invoice ' . $payment->invoice->invoice_number . ' but the payment failed or was cancelled. Please follow up with your client to resolve the issue.' . "\n\nCheck the link : " . $url,
                'url' => $url,
                'route' => $route,
            ];
        } elseif ($isInvoice) {
            return [
                'agent' => $payment->invoice->agent,
                'title' => 'Client ' . $payment->client->full_name . "'s Payment Failed",
                'message' => 'Your client ' . $payment->client->full_name . ' attempted to pay invoice ' . $payment->invoice->invoice_number .
                    ' but the payment failed or was cancelled. Please follow up with your client to resolve the issue.' . "\n\nCheck the link : " . $url,
                'url' => $url,
                'route' => $route,
            ];
        } elseif ($isHotelBooking) {

            return [
                'agent' => $payment->agent,
                'title' => 'Hotel Booking Payment Failed',
                'message' => 'Your client ' . $payment->client->full_name . ' attempted to pay for hotel booking using payment link ' . $payment->voucher_number .
                    ' but the payment failed or was cancelled. Please follow up with your client to resolve the issue.' . "\n\nCheck the link : " . $url,
                'url' => $url,
                'route' => $route,
            ];
        }

        return [
            'agent' => $payment->agent,
            'title' => 'Client ' . $payment->client->full_name . "'s Topup Failed",
            'message' => 'Your client ' . $payment->client->full_name . ' attempted to top up their account using payment link ' . $payment->voucher_number .
                ' but the payment failed or was cancelled. Please follow up with your client to resolve the issue.' . "\n\nCheck the link : " . $url,
            'url' => $url,
            'route' => $route,
        ];
    }

    public function paymentLinkActivation($paymentId)
    {
        $payment = Payment::find($paymentId);

        if (!$payment) {
            Log::info('Payment not found for ID: ' . $paymentId . ' to proceed with disabling payment link');
            return redirect()->back()->with('error', 'Payment not found for ID: ' . $paymentId);
        }

        try {
            $payment->is_disabled = !$payment->is_disabled;
            $payment->save();

            $message = $payment->is_disabled ? 'Payment link successfully disabled' : 'Payment link successfully enabled';
            Log::info($message . ' for payment ID: ' . $paymentId);

            return redirect()->back()->with('success', $message);
        } catch (Exception $e) {
            Log::error('Error disabling payment link for payment ID: ' . $paymentId, [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Error disabling payment link: ' . $e->getMessage());
        }
    }

    public function multiPaymentMethodProcess(Request $request): array
    {
        Log::info('[MULTI PAYMENT METHOD] Initiating multi payment method process', $request->all());

        $request->validate([
            'payment_methods' => 'required|array|min:1',
            'amount' => 'nullable|numeric',
            'currency' => 'required|string',
            'client_id' => 'required|integer|exists:clients,id',
            'agent_id' => 'required|integer|exists:agents,id',
            'send_payment_receipt' => 'required|boolean',
            'items' => 'nullable|array|min:1',
            'items.*.product_name' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|numeric|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.extended_amount' => 'required_with:items|numeric',
            'items.*.currency' => 'required_with:items|string|max:10',
        ]);

        $agent = Agent::find($request->input('agent_id'));
        $client = Client::find($request->input('client_id'));

        $company = $agent->branch->company;

        if (!$company) {
            Log::error('[MULTI PAYMENT METHOD] Company not found for agent ID: ' . $agent->id);
            return [
                'success' => false,
                'message' => 'Company not found for the specified agent'
            ];
        }

        $voucherSequence = Sequence::firstOrCreate(['company_id' => $company->id], ['current_sequence' => 1]);

        $currentSequence = $voucherSequence->current_sequence;
        $voucherNumber = $this->generateVoucherNumber($currentSequence);

        $response = DB::transaction(function () use (
            $request,
            $voucherNumber,
            $company,
            $client,
            $agent,
            $voucherSequence,
        ) {
            try {
                $isAdvancedMode = $request->has('items') && is_array($request->items) && count($request->items) > 0;

                $totalAmountInKWD = 0;
                $convertedItems = [];

                if ($isAdvancedMode) {
                    foreach ($request->items as $item) {
                        $itemAmountInKWD = $item['extended_amount'];

                        if (strtoupper($item['currency']) !== 'KWD') {
                            $conversionResult = $this->convert(
                                $company->id,
                                strtoupper($item['currency']),
                                'KWD',
                                $item['extended_amount']
                            );

                            if ($conversionResult['status'] === 'error') {
                                Log::error('[MULTI PAYMENT METHOD] Currency conversion failed', [
                                    'from' => $item['currency'],
                                    'to' => 'KWD',
                                    'amount' => $item['extended_amount'],
                                    'error' => $conversionResult['message']
                                ]);
                                throw new Exception('Currency exchange rate not found for ' . $item['currency'] . ' to KWD');
                            }

                            $itemAmountInKWD = $conversionResult['converted_amount'];
                            Log::info('[MULTI PAYMENT METHOD] Converted item amount', [
                                'product' => $item['product_name'],
                                'from_currency' => $item['currency'],
                                'original_amount' => $item['extended_amount'],
                                'exchange_rate' => $conversionResult['exchange_rate'],
                                'kwd_amount' => $itemAmountInKWD
                            ]);
                        }

                        $totalAmountInKWD += $itemAmountInKWD;
                        $convertedItems[] = array_merge($item, ['kwd_amount' => $itemAmountInKWD]);
                    }
                } else {
                    $totalAmountInKWD = $request->amount;
                }

                $totalAmount = $totalAmountInKWD;

                Log::info('[MULTI PAYMENT METHOD] Mode: ' . ($isAdvancedMode ? 'Advanced' : 'Quick') . ', Total: ' . $totalAmount . ' KWD');

                $payment = Payment::create([
                    'voucher_number' => $voucherNumber,
                    'amount' => $totalAmount,
                    'from' => $client->full_name,
                    'pay_to' => $company->name,
                    'currency' => 'KWD',
                    'payment_gateway' => 'Multi',
                    'status' => 'pending',
                    'client_id' => $client->id,
                    'agent_id' => $agent->id,
                    'notes' => $request->notes,
                    'terms_conditions' => $request->terms_conditions,
                    'send_payment_receipt' => $request->send_payment_receipt,
                    'language' => $request->language,
                    'created_by' => Auth::id(),
                ]);

                if ($isAdvancedMode && !empty($request->items)) {
                    foreach ($request->items as $item) {
                        $payment->paymentItems()->create([
                            'product_name' => $item['product_name'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'extended_amount' => $item['extended_amount'],
                            'currency' => $item['currency'],
                        ]);
                    }
                    Log::info('[MULTI PAYMENT METHOD] Created ' . count($request->items) . ' payment items for payment ID: ' . $payment->id);
                }

                $paymentMethods = PaymentMethod::whereIn('id', $request->payment_methods)->get();
                $groupIds = $paymentMethods->pluck('payment_method_group_id')->unique()->filter();

                $payment->availablePaymentMethodGroups()->attach($groupIds);

                Log::info('[MULTI PAYMENT METHOD] Attached payment method groups to payment ID: ' . $payment->id, [
                    'payment_methods_selected' => $request->payment_methods,
                    'payment_method_groups' => $groupIds->toArray(),
                ]);

                $voucherSequence->current_sequence++;
                $voucherSequence->save();

                Log::info('[MULTI PAYMENT METHOD] Payment created with voucher number: ' . $voucherNumber, [
                    'payment_id' => $payment->id,
                ]);

                return [
                    'success' => true,
                    'payment_id' => $payment->id,
                    'message' => 'Multi payment method payment created successfully'
                ];
            } catch (Exception $e) {
                Log::error('[MULTI PAYMENT METHOD] Failed to create payment', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return [
                    'success' => false,
                    'message' => 'Error creating payment'
                ];
            }
        });

        return $response;
    }

    public function multiPaymentLinkInitiate(Request $request)
    {
        Log::info('[MULTI PAYMENT] Initiating multi payment link process', [
            'request_data' => $request->all(),
        ]);

        $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
        ]);

        $payment = Payment::with('invoice', 'agent.branch', 'availablePaymentMethodGroups')->find($request->payment_id);

        $paymentMethod = PaymentMethod::withoutGlobalScope('company')
            ->with(['charge', 'paymentMethodGroup'])
            ->find($request->payment_method_id);

        if (!$paymentMethod) {
            Log::error('[MULTI PAYMENT] Payment method not found', ['payment_method_id' => $request->payment_method_id]);
            return redirect()->back()->with('error', 'Selected payment method not found');
        }

        if (!$paymentMethod->is_active) {
            Log::warning('[MULTI PAYMENT] Inactive payment method selected', [
                'payment_method_id' => $paymentMethod->id,
                'payment_id' => $payment->id
            ]);
            return redirect()->back()->with('error', 'Selected payment method is no longer active. Please choose another payment method.');
        }

        $companyId = optional($payment->agent->branch)->company_id;
        if ($paymentMethod->company_id !== $companyId) {
            Log::error('[MULTI PAYMENT] Payment method company mismatch', [
                'payment_method_id' => $paymentMethod->id,
                'payment_method_company_id' => $paymentMethod->company_id,
                'payment_company_id' => $companyId
            ]);
            return redirect()->back()->with('error', 'Invalid payment method selected');
        }

        $allowedGroupIds = $payment->availablePaymentMethodGroups->pluck('id');
        if (!$allowedGroupIds->contains($paymentMethod->payment_method_group_id)) {
            Log::error('[MULTI PAYMENT] Payment method group not allowed', [
                'payment_method_id' => $paymentMethod->id,
                'payment_method_group_id' => $paymentMethod->payment_method_group_id,
                'allowed_group_ids' => $allowedGroupIds->toArray()
            ]);
            return redirect()->back()->with('error', 'This payment method is not available for this payment link');
        }

        $paymentGateway = $paymentMethod->charge->name;

        if (!$paymentGateway) {
            Log::error('[MULTI PAYMENT] Payment gateway not found for payment method ID: ' . $paymentMethod->id);
            return redirect()->back()->with('error', 'Payment gateway configuration is missing. Please contact support.');
        }

        $paymentTransaction = $payment->paymentTransactions()->latest()->first();

        if ($paymentTransaction) {
            Log::info('[MULTI PAYMENT] Existing payment transaction found, comparing the payment method', [
                'existing_payment_method_id' => $paymentTransaction->payment_method_id,
                'selected_payment_method_id' => $paymentMethod->id,
            ]);

            if ($paymentTransaction->payment_method_id == $paymentMethod->id) {

                Log::info('[MULTI PAYMENT] Payment method matches the existing transaction, redirecting to existing payment URL', [
                    'payment_id' => $payment->id,
                    'payment_url' => $paymentTransaction->url,
                ]);

                if ($paymentTransaction->expiry_date && now()->gt($paymentTransaction->expiry_date)) {
                    Log::info('[MULTI PAYMENT] Existing payment URL has expired, reinitiating new payment', [
                        'payment_id' => $payment->id,
                        'payment_url' => $paymentTransaction->url,
                        'expiry_date' => $paymentTransaction->expiry_date,
                    ]);
                } else {
                    return redirect($paymentTransaction->url);
                }
            }
        }

        $payment->payment_gateway = $paymentGateway;
        $payment->payment_method_id = $paymentMethod->id;
        $payment->save();

        Log::info('[MULTI PAYMENT] Payment initiated', [
            'payment_id' => $payment->id,
            'voucher' => $payment->voucher_number,
            'method' => $paymentMethod->english_name,
            'gateway' => $paymentGateway,
        ]);

        $process = 'topup';
        if ($payment->invoice) {
            $process = 'invoice';
        }

        $paymentGatewayStatus = null;
        $paymentGatewayUrl = null;
        $paymentGatewayTrackId = null;
        $paymentGatewayReferenceNumber = null;
        $paymentGatewayExpiryDate = null;

        if (strtolower($paymentGateway) === 'tap') {
            $tap = new Tap();
            $paymentMethodId = $payment->paymentMethod ? $payment->paymentMethod->id : null;

            $chargeResult = ChargeService::calculate($payment->amount, $payment->agent->branch->company_id, $paymentMethodId, 'Tap');
            $finalAmount = $chargeResult['finalAmount'];

            $requestTap = new Request([
                'finalAmount' => $finalAmount,
                'client_name' => $payment->client->full_name,
                'client_email' => $payment->client->email,
                'voucher_number' => $payment->voucher_number,
                'payment_id' => $payment->id,
                'payment_gateway' => $paymentGateway,
                'payment_method_id' => $paymentMethodId,
                'description' => 'Payment for ' . $payment->voucher_number,
                'process' => $process,
            ]);

            Log::info('requestTap', ['requestTap' => $requestTap]);

            $response = $tap->createCharge($requestTap);
            logger('Payment link initiate response', ['response' => $response]);

            if (isset($response['errors'])) {
                return redirect()->back()->with('error', $response['errors'][0]['description']);
            }

            $paymentGatewayStatus = $response['status'];
            $paymentGatewayUrl = $response['transaction']['url'];
            $paymentGatewayTrackId = $response['id'];
            $paymentGatewayReferenceNumber = $response['id'];

            $periodExpiry = $response['transaction']['expiry']['period'];
            $typeExpiry = $response['transaction']['expiry']['type'];

            $expiryDate = $tap->calculateExpiryDate($periodExpiry, $typeExpiry);

            $paymentGatewayExpiryDate = $expiryDate;

            // return redirect($paymentUrl);
        } else if (strtolower($paymentGateway) === 'myfatoorah') {
            $payment = Payment::with('agent', 'client')->where('id', $payment->id)->first();
            $companyId = $payment->agent->branch->company_id;

            if (!$companyId) {
                Log::error('[MULTI PAYMENT] Company ID not found for the payment.', ['payment_id' => $payment->id]);
                return Auth::user() ? redirect()->back()->with('error', 'Company ID not found for the payment.') : abort(500);
            }

            $client = $payment->client;
            $clientPhone = $client->phone ?? '50000000';

            if (isset($clientPhone) && strpos($clientPhone, '+') === 0) {
                $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
                $clientPhone = ltrim($clientPhone, '0');
            }

            $chargeResult = ChargeService::calculate($payment->amount, $companyId, $payment->payment_method_id, 'MyFatoorah');
            $finalAmount = $chargeResult['finalAmount'];

            $firstName = $payment->client->first_name;
            $middleName = $payment->client->middle_name ?? '';
            $lastName = $payment->client->last_name ?? '';
            $customerName = trim("$firstName $middleName $lastName");

            // Create request object for MyFatoorah
            $company = $companyId ? Company::find($companyId) : null;
            $companyEmail = $company?->email ?? 'admin@citytravelers.co';

            $requestMyFatoorah = new Request([
                'final_amount' => $finalAmount,
                'client_name' => $customerName,
                'client_email' => $companyEmail,
                'client_phone' => $clientPhone,
                'invoice_id' => optional($payment->invoice)->id,
                'invoice_number' => $payment->voucher_number,
                'payment_id' => $payment->id,
                'payment_gateway' => $paymentGateway,
                'payment_method_id' => $paymentMethod->id,
                'invoice_partial_id' => null,
            ]);

            Log::info('[MULTI PAYMENT] Creating MyFatoorah charge', [
                'payment_id' => $payment->id,
                'request' => $requestMyFatoorah->all()
            ]);

            $myFatoorah = new MyFatoorah();
            $response = $myFatoorah->createCharge($requestMyFatoorah);

            if ($response['status'] === 'error') {
                Log::error('[MULTI PAYMENT] MyFatoorah charge creation failed', [
                    'payment_id' => $payment->id,
                    'response' => $response
                ]);
                return redirect()->back()->with('error', $response['message'] ?? 'MyFatoorah payment initiation failed.');
            }

            // Update payment record after successful charge creation
            $payment->payment_reference = $response['invoice_id'];
            $payment->payment_url = $response['payment_url'];
            $payment->expiry_date = isset($response['expiry_date'])
                ? Carbon::parse($response['expiry_date'])
                : now()->addDays(2);
            $payment->status = 'initiate';
            $payment->save();

            $paymentGatewayStatus = 'initiate';
            $paymentGatewayUrl = $response['payment_url'];
            $paymentGatewayTrackId = $response['invoice_id'];
            $paymentGatewayReferenceNumber = $response['invoice_id'];
            $paymentGatewayExpiryDate = isset($response['expiry_date'])
                ? Carbon::parse($response['expiry_date'])
                : now()->addDays(2);

            if ($response['invoice_id']) {

                Log::info('[MULTI PAYMENT] Fetching MyFatoorah payment status', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $response['invoice_id'],
                ]);

                $getPaymentStatus = $myFatoorah->getPaymentStatus(
                    type: 'invoice',
                    key: $response['invoice_id'],
                );

                if ($getPaymentStatus['success']) {

                    $invoiceReference = $getPaymentStatus['data']['InvoiceReference'] ?? null;
                    $trackId = $getPaymentStatus['data']['InvoiceTransactoins'][0]['TrackId'] ?? null;
                    $invoiceStatus = $getPaymentStatus['data']['InvoiceStatus'] ?? null;
                    $expiryDateStr = $getPaymentStatus['data']['ExpiryDate'] ?? null;
                    $expiryTimeStr = $getPaymentStatus['data']['ExpiryTime'] ?? null;

                    Log::info('[MULTI PAYMENT] MyFatoorah payment status fetched successfully', [
                        'payment_id' => $payment->id,
                        'reference_number' => $invoiceReference,
                        'track_id' => $trackId,
                        'status' => $invoiceStatus,
                        'expiry_date' => $expiryDateStr,
                        'expiry_time' => $expiryTimeStr,
                    ]);

                    $paymentGatewayStatus = $invoiceStatus ?? 'initiate';
                    $paymentGatewayReferenceNumber = $invoiceReference ?? $paymentGatewayReferenceNumber;
                    $paymentGatewayTrackId =   $trackId ?? $paymentGatewayTrackId;
                    $paymentGatewayExpiryDate =  $myFatoorah->convertExpiryDate(expiryDate: $expiryDateStr, expiryTime: $expiryTimeStr)
                        ?? $paymentGatewayExpiryDate;
                } else {
                    Log::warning('[MULTI PAYMENT] Failed to fetch MyFatoorah payment status', [
                        'payment_id' => $payment->id,
                        'response' => $getPaymentStatus,
                    ]);
                }
            }

            Log::info('[MULTI PAYMENT] MyFatoorah payment initiated successfully', [
                'payment_id' => $payment->id,
                'invoice_id' => $response['invoice_id'],
                'payment_url' => $response['payment_url'],
                'expiry_date' => $paymentGatewayExpiryDate
            ]);
        } elseif (strtolower($paymentGateway) === 'hesabe') {

            $payment = Payment::with('agent', 'client')->where('id', $payment->id)->first();
            $companyId = $payment->agent->branch->company_id;

            $chargeResult = ChargeService::calculate($payment->amount, $companyId, $payment->payment_method_id, 'Hesabe');
            $finalAmount = $chargeResult['finalAmount'] ?? $payment->amount;

            $client = $payment->client;
            $clientPhone = $client->phone ?? '50000000';

            if (isset($clientPhone) && strpos($clientPhone, '+') === 0) {
                $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
                $clientPhone = ltrim($clientPhone, '0');
            }

            $firstName = $payment->client->first_name;
            $middleName = $payment->client->middle_name;
            $lastName = $payment->client->last_name;
            $customerName = trim("$firstName $middleName $lastName");

            $requestHesabe = new Request([
                'final_amount' => $finalAmount,
                'client_name' => $customerName,
                'client_email' => $payment->agent->branch->company->email ?? 'admin@citytravelers.co',
                'invoice_id' => optional($payment->invoice)->id,
                'invoice_number' => $payment->voucher_number,
                'payment_id' => $payment->id,
                'payment_gateway' => $paymentGateway,
                'payment_method_id' => $payment->payment_method_id,
                'invoice_partial_id' => null,
                'client_phone' => $clientPhone,
                'type' => 'topup',
            ]);

            Log::info('[HESABE] Creating charge via Hesabe helper', ['request' => $requestHesabe->all()]);

            $hesabe = new Hesabe();
            $response = $hesabe->createCharge($requestHesabe);

            if (!$response['success']) {
                Log::error('[HESABE] Payment initiation failed', ['response' => $response]);
                return redirect()->back()->with('error', 'Hesabe payment initiation failed: ' . ($response['message'] ?? 'Something went wrong'));
            }

            $paymentUrl = $response['payment_url'] ?? null;

            if (!$paymentUrl) {
                Log::error('[HESABE] Payment URL missing in response', ['response' => $response]);
                return redirect()->back()->with('error', 'Hesabe response missing payment URL.');
            }

            $payment->payment_url = $paymentUrl;
            $payment->status = 'initiate';
            $payment->save();

            Log::info('[HESABE] Payment initiated successfully', [
                'payment_id' => $payment->id,
                'payment_url' => $paymentUrl,
                'payment_status' => $payment->status,
            ]);

            if (!$response['token']) {
                Log::error('[HESABE] Token missing in response', ['response' => $response]);
                return redirect()->back()->with('error', 'Hesabe response missing token.');
            }

            $paymentGatewayStatus = 'initiate';
            $paymentGatewayUrl = $paymentUrl;
            $paymentGatewayTrackId = null;
            $paymentGatewayReferenceNumber = $response['token'];
            $paymentGatewayExpiryDate = now()->addDays(2);

            // return redirect($paymentUrl);

        } elseif (strtolower($paymentGateway) === 'upayment') {
            if ($payment->status === 'initiate') {
                if ($payment->payment_url && $payment->expiry_date && now()->lt($payment->expiry_date)) {
                    Log::info('Reusing existing payment URL', [
                        'invoice_id' => $payment->payment_reference,
                        'url' => $payment->payment_url,
                        'expires_at' => $payment->expiry_date,
                    ]);

                    return redirect($payment->payment_url);
                }
                Log::info('Old payment URL expired, reinitiating new payment');
                return $this->paymentLinkReinitiate($payment->payment_reference);
            }


            $payment->load(['agent.branch.company', 'client']);
            $company = $payment->agent?->branch?->company;
            $client = $payment->client;

            $clientPhone = $client->phone ?? null;
            if ($clientPhone && str_starts_with($clientPhone, '+')) {
                $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
                $clientPhone = ltrim($clientPhone, '0');
            }

            $chargeResult = ChargeService::calculate($payment->amount, $company->id, $payment->payment_method_id, 'UPayment');
            $finalAmount  = $chargeResult['finalAmount'] ?? $payment->amount;

            $requestUPayment = new Request([
                'final_amount'      => $finalAmount,
                'client_id'         => $client->id,
                'client_name'       => $client->full_name,
                'client_email'      => $client->email ?? $company?->email,
                'client_phone'      => $clientPhone ?? '50000000',
                'company_email'     => $company?->email,
                'payment_id'        => $payment->id,
                'payment_number'    => $payment->voucher_number,
                'payment_method_id' => $payment->payment_method_id,
                'invoice_id'        => optional($payment->invoice)->id,
                'invoice_number'    => optional($payment->invoice)->invoice_number,
                'currency'          => $payment->currency ?? 'KWD',
            ]);

            $uPayment = new UPayment();
            $response = $uPayment->makeCharge($requestUPayment);

            if (!is_array($response)) {
                Log::error('UPayments: Unexpected response', ['raw' => $response]);
                return redirect()->back()->with('error', 'UPayments: unexpected response');
            }

            if (isset($response['status']) && $response['status'] === 'error') {
                return redirect()->back()->with('error', $response['message'] ?? 'UPayments error');
            }

            $paymentReference = $response['data']['trackId'] ?? null;
            $paymentUrl = $response['data']['link'] ?? null;
            $expiryDate = $response['transaction']['expiryDate'] ?? $response['data']['expiryDate'] ?? null;

            if ($paymentUrl && $paymentReference) {
                $payment->payment_reference = $paymentReference;
                $payment->payment_url = $paymentUrl;
                $payment->expiry_date = $expiryDate ? Carbon::parse($expiryDate) : now()->addDays(2);
                $payment->status = 'initiate';
                $payment->save();

                Log::info('UPayments payment initiated', [
                    'payment_id'  => $payment->id,
                    'track_id'    => $paymentReference,
                    'payment_url' => $paymentUrl,
                    'expires_at'  => $payment->expiry_date,
                ]);

                $paymentGatewayStatus = 'initiate';
                $paymentGatewayUrl = $paymentUrl;
                $paymentGatewayTrackId = $paymentReference;
                $paymentGatewayReferenceNumber = $paymentReference;
                $paymentGatewayExpiryDate = $payment->expiry_date;

                // return redirect($paymentUrl);
            } else {
                Log::error('UPayments: Missing link or trackId', ['response' => $response]);
                return redirect()->back()->with('error', 'UPayments response missing link or trackId.');
            }
        }

        $paymentTransaction = PaymentTransaction::updateOrCreate(
            [
                'payment_id' => $payment->id,
                'payment_gateway_id' => $paymentMethod->charge->id,
                'payment_method_id' => $paymentMethod->id,
                'reference_number' => $paymentGatewayReferenceNumber,
            ],
            [
                'status' => $paymentGatewayStatus,
                'url' => $paymentGatewayUrl,
                'track_id' => $paymentGatewayTrackId,
                'expiry_date' => $paymentGatewayExpiryDate,
            ]
        );

        if ($paymentGatewayUrl) {

            Log::info('[MULTI PAYMENT] Redirecting to payment gateway URL', [
                'payment_id' => $payment->id,
                'payment_url' => $paymentGatewayUrl,
            ]);

            return redirect($paymentGatewayUrl);
        } else {
            Log::error('[MULTI PAYMENT] Payment gateway URL is missing, cannot redirect', [
                'payment_id' => $payment->id,
            ]);
            return redirect()->back()->with('error', 'Payment gateway URL is missing. Please contact support.');
        }
    }

    public function getHesabePayment(string $token)
    {
        $hesabe = new Hesabe();

        $response = $hesabe->getPaymentStatus(
            token: $token,
        );

        return $response->body();
    }

    public function outstanding(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        $plSort = in_array($request->input('ps', 'created_at'), ['voucher_number', 'client_name', 'created_at']) ? $request->input('ps', 'created_at') : 'created_at';
        $plDirection = in_array($request->input('pd', 'desc'), ['asc', 'desc']) ? $request->input('pd', 'desc') : 'desc';
        $invSort = in_array($request->input('is', 'created_at'), ['invoice_number', 'created_at', 'invoice_date']) ? $request->input('is', 'created_at') : 'created_at';
        $invDirection = in_array($request->input('id', 'desc'), ['asc', 'desc']) ? $request->input('id', 'desc') : 'desc';
        $search = $request->input('search', '');

        $agentsQuery = Agent::query();
        switch ($user->role_id) {
            case Role::ADMIN:
                if ($companyId) $agentsQuery->whereHas('branch', fn($q) => $q->where('company_id', $companyId));
                break;
            case Role::COMPANY:
            case Role::ACCOUNTANT:
                $agentsQuery->whereIn('branch_id', Branch::where('company_id', $companyId)->pluck('id'));
                break;
            case Role::BRANCH:
                $agentsQuery->where('branch_id', $user->branch->id);
                break;
            case Role::AGENT:
                $agentsQuery->where('id', $user->agent->id);
                break;
            default:
                return redirect()->back()->with('error', 'You are not authorized to view this page.');
        }
        $agentIds = $agentsQuery->pluck('id')->toArray();

        $paymentLinksQuery = Payment::with(['client', 'agent', 'paymentMethod', 'createdBy', 'myFatoorahPayment', 'hesabePayment'])
            ->where(fn($q) => $q->whereHas('invoice', fn($sub) => $sub->whereIn('invoices.agent_id', $agentIds))
                ->orWhereIn('payments.agent_id', $agentIds))
            ->where('payments.status', '!=', 'completed');

        if ($search) {
            $paymentLinksQuery->where(function ($q) use ($search) {
                $q->where('payments.voucher_number', 'like', "%{$search}%")
                    ->orWhereHas('client', fn($sub) => $sub->where(fn($s) => $s
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(middle_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$search}%"])))
                    ->orWhereHas('agent', fn($sub) => $sub->where('name', 'like', "%{$search}%"));
            });
        }

        if ($plSort === 'client_name') {
            $paymentLinksQuery->leftJoin('clients', 'payments.client_id', '=', 'clients.id')
                ->orderByRaw("CONCAT(COALESCE(clients.first_name, ''), ' ', COALESCE(clients.middle_name, ''), ' ', COALESCE(clients.last_name, '')) $plDirection")
                ->select('payments.*');
        } else {
            $paymentLinksQuery->orderBy("payments.$plSort", $plDirection);
        }

        $paymentLinks = $paymentLinksQuery->paginate(20, ['*'], 'pp');
        $totalPaymentLinks = Payment::where(fn($q) => $q->whereHas('invoice', fn($sub) => $sub->whereIn('invoices.agent_id', $agentIds))
            ->orWhereIn('payments.agent_id', $agentIds))
            ->where('payments.status', '!=', 'completed')
            ->count();

        $companiesId = ($user->role_id == Role::ADMIN && !$companyId) ? Company::pluck('id')->toArray() : [$companyId];

        $invoicesQuery = Invoice::with(['agent.branch', 'invoiceDetails.task.supplier', 'client', 'invoicePartials'])
            ->whereIn('agent_id', $agentIds)
            ->whereHas('agent.branch', fn($q) => $q->whereIn('company_id', $companiesId))
            ->whereIn('status', ['unpaid', 'partial']);

        if ($search) {
            $invoicesQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('client', fn($sub) => $sub->where(fn($s) => $s
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(middle_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$search}%"])))
                    ->orWhereHas('agent', fn($sub) => $sub->where('name', 'like', "%{$search}%"));
            });
        }

        $invoices = $invoicesQuery->orderBy($invSort, $invDirection)->paginate(20, ['*'], 'ip');

        $invoices->each(fn($invoice) => $invoice->client_pay = $invoice->amount + $invoice->invoicePartials->sum('service_charge'));

        $totalInvoices = Invoice::whereIn('agent_id', $agentIds)
            ->whereHas('agent.branch', fn($q) => $q->whereIn('company_id', $companiesId))
            ->whereIn('status', ['unpaid', 'partial'])
            ->count();

        return view('payment.outstanding', compact(
            'paymentLinks',
            'totalPaymentLinks',
            'invoices',
            'totalInvoices',
            'plSort',
            'plDirection',
            'invSort',
            'invDirection',
            'search'
        ));
    }

    public function checkTransactionStatus($transactionId)
    {
        try {
            $paymentTransaction = PaymentTransaction::with(['payment.invoice', 'payment.agent.branch.company', 'payment.client', 'paymentGateway'])->findOrFail($transactionId);
            $payment = $paymentTransaction->payment;

            if (!$payment) {
                return redirect()->back()->with('error', 'Payment not found for this transaction.');
            }

            if ($payment->status === 'completed') {
                return redirect()->back()->with('error', 'Payment is already completed.');
            }

            if (in_array(strtolower($paymentTransaction->status), ['paid', 'captured', 'successful'])) {
                return redirect()->back()->with('error', 'Transaction is already completed. Current status: ' . $paymentTransaction->status);
            }

            $gateway = $paymentTransaction->paymentGateway;
            if (!$gateway) {
                return redirect()->back()->with('error', 'Payment gateway not found.');
            }

            $gatewayName = $gateway->name;
            $statusResult = null;
            $newStatus = null;
            $isCompleted = false;

            switch ($gatewayName) {
                case 'Tap':
                    $tap = new Tap();
                    $response = $tap->getCharge($paymentTransaction->reference_number);

                    Log::info('[CHECK_STATUS] Tap response', ['response' => $response]);

                    if (isset($response['status'])) {
                        $newStatus = $response['status'];
                        $isCompleted = strtoupper($newStatus) === 'CAPTURED';
                        $statusResult = $response;
                    }
                    break;

                case 'MyFatoorah':
                    $myFatoorah = new MyFatoorah();
                    $response = $myFatoorah->getPaymentStatus('invoice', $paymentTransaction->track_id);

                    Log::info('[CHECK_STATUS] MyFatoorah response', ['response' => $response]);

                    if ($response['success'] && isset($response['data'])) {
                        $invoiceStatus = $response['data']['InvoiceStatus'] ?? null;
                        $newStatus = $invoiceStatus;
                        $isCompleted = strtoupper($invoiceStatus) === 'PAID';
                        $statusResult = $response['data'];
                    }
                    break;

                case 'Hesabe':
                    $hesabe = new Hesabe();
                    $response = $hesabe->getPaymentStatus($paymentTransaction->reference_number);

                    Log::info('[CHECK_STATUS] Hesabe response', ['response' => $response->json()]);

                    $responseData = $response->json();
                    if (isset($responseData['status']) && $responseData['status'] === true) {
                        $newStatus = $responseData['data']['status'] ?? null;
                        $isCompleted = in_array(strtolower($newStatus), ['captured', 'completed', 'successful', 'paid']);
                        $statusResult = $responseData['data'];
                    }
                    break;

                case 'UPayment':
                    $uPayment = new UPayment();
                    $response = $uPayment->getPaymentStatus($paymentTransaction->track_id);

                    Log::info('[CHECK_STATUS] UPayment response', ['response' => $response]);

                    if (isset($response['status']) && $response['status'] === true && isset($response['data']['transaction'])) {
                        $transaction = $response['data']['transaction'];
                        $newStatus = $transaction['result'] ?? $transaction['status'] ?? null;
                        $isCompleted = strtoupper($newStatus) === 'CAPTURED' || strtoupper($newStatus) === 'SUCCESS';
                        $statusResult = $transaction;
                    }
                    break;

                default:
                    return redirect()->back()->with('error', "Unsupported payment gateway: {$gatewayName}");
            }

            if ($newStatus) {
                $paymentTransaction->status = $newStatus;
                $paymentTransaction->save();

                Log::info('[CHECK_STATUS] Payment transaction updated', [
                    'transaction_id' => $transactionId,
                    'new_status' => $newStatus,
                    'is_completed' => $isCompleted,
                ]);
            }

            if ($isCompleted && $payment->status !== 'completed') {
                $process = $payment->invoice ? 'invoice' : 'topup';
                $partialId = $payment->invoice?->invoicePartials()->where('payment_id', $payment->id)->value('id');

                Log::info('[CHECK_STATUS] Processing completed payment', [
                    'payment_id' => $payment->id,
                    'gateway' => $gatewayName,
                    'process' => $process,
                ]);

                try {
                    switch ($gatewayName) {
                        case 'Tap':
                            $this->processCompletedTapPayment($payment, $statusResult, $process, $partialId, $paymentTransaction, false);
                            break;

                        case 'MyFatoorah':
                            $this->processMyFatoorahPaymentCompletion($payment, $statusResult, $process, $partialId, false);
                            break;

                        case 'Hesabe':
                            $this->processCompletedHesabePayment($payment, $statusResult, $process, $partialId, $paymentTransaction, false);
                            break;

                        case 'UPayment':
                            $this->processCompletedUPaymentPayment($payment, $statusResult, $process, $partialId, $paymentTransaction, false);
                            break;
                    }

                    return redirect()->back()->with('success', 'Payment completed successfully and processed.');
                } catch (\Exception $e) {
                    Log::error('[CHECK_STATUS] Error processing completed payment', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);

                    return redirect()->back()->with('error', 'Payment is completed on gateway but failed to process: ' . $e->getMessage());
                }
            }

            return redirect()->back()->with('error', "Payment has not been completed yet. Please ask the client to complete the payment before the expiry date. Current status: {$newStatus}");
        } catch (\Exception $e) {
            Log::error('[CHECK_STATUS] Error checking transaction status', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Error checking payment status: ' . $e->getMessage());
        }
    }

    private function processCompletedTapPayment($payment, $response, $process, $partialId, $paymentTransaction, $sendNotification = false)
    {
        DB::beginTransaction();

        try {
            $finalPaidAmount = $response['amount'] ?? $payment->amount;

            $dateCreated = isset($response['transaction']['date']['created'])
                ? Carbon::createFromTimestampMs($response['transaction']['date']['created'])->format('Y-m-d H:i:s')
                : now();
            $dateCompleted = isset($response['transaction']['date']['completed'])
                ? Carbon::createFromTimestampMs($response['transaction']['date']['completed'])->format('Y-m-d H:i:s')
                : now();
            $dateTransaction = isset($response['transaction']['date']['transaction'])
                ? Carbon::createFromTimestampMs($response['transaction']['date']['transaction'])->format('Y-m-d H:i:s')
                : now();

            TapPayment::updateOrCreate(
                ['payment_id' => $payment->id],
                [
                    'tap_id' => $response['id'],
                    'authorization_id' => $response['transaction']['authorization_id'] ?? null,
                    'timezone' => $response['transaction']['timezone'] ?? null,
                    'expiry_period' => $response['transaction']['expiry']['period'] ?? null,
                    'expiry_type' => $response['transaction']['expiry']['type'] ?? null,
                    'amount' => $finalPaidAmount,
                    'currency' => $response['currency'] ?? 'KWD',
                    'date_created' => $dateCreated,
                    'date_completed' => $dateCompleted,
                    'date_transaction' => $dateTransaction,
                    'receipt_id' => $response['receipt']['id'] ?? null,
                    'receipt_email' => $response['receipt']['email'] ?? null,
                    'receipt_sms' => $response['receipt']['sms'] ?? null,
                ]
            );

            $payment->status = 'completed';
            $payment->completed = 1;
            $payment->service_charge = $finalPaidAmount - $payment->amount;
            $payment->payment_reference = $response['id'];
            $payment->payment_date = now();
            $payment->save();

            if ($process === 'topup') {
                $clientController = new ClientController;
                $addCreditResponse = $clientController->addCredit($payment);

                if (isset($addCreditResponse['error']) || $addCreditResponse['status'] === 'error') {
                    throw new \RuntimeException('Failed to add credit: ' . ($addCreditResponse['message'] ?? $addCreditResponse['error']));
                }

                if ($paymentTransaction) {
                    $transactionId = $addCreditResponse['data']['transaction_id'] ?? null;
                    if ($transactionId) {
                        $paymentTransaction->transaction_id = $transactionId;
                    }
                    $paymentTransaction->save();
                }
            } else {
                $coaResult = $this->createInvoicePaymentCOA(
                    payment: $payment,
                    finalPaidAmount: $finalPaidAmount,
                    gatewayName: 'Tap',
                    partialIds: !empty($partialId) ? [$partialId] : null,
                    paymentReference: $response['id']
                );

                if (!$coaResult['success']) {
                    throw new \RuntimeException($coaResult['message']);
                }
            }

            $tboResult = $this->processTBOBookingAfterPayment($payment);
            if ($tboResult !== null && !$tboResult['success']) {
                Log::error('TBO booking failed via manual status check', $tboResult);
            }

            $payment->refresh();

            if ($sendNotification) {
                $this->sendPaymentCompletionNotifications($payment, $process, $partialId);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function processCompletedHesabePayment($payment, $data, $process, $partialId, $paymentTransaction, $sendNotification = false)
    {
        DB::beginTransaction();

        try {
            $finalPaidAmount = $data['amount'] ?? $payment->amount;

            $payment->status = 'completed';
            $payment->service_charge = $finalPaidAmount - $payment->amount;
            $payment->payment_date = now();
            $payment->save();

            HesabePayment::updateOrCreate(
                ['payment_int_id' => $payment->id],
                [
                    'status' => $data['resultCode'] ?? $data['status'] ?? null,
                    'payment_token' => $data['paymentToken'] ?? null,
                    'payment_id' => $data['paymentId'] ?? null,
                    'order_reference_number' => $data['orderReferenceNumber'] ?? null,
                    'auth_code' => $data['auth'] ?? null,
                    'track_id' => $data['trackID'] ?? null,
                    'transaction_id' => $data['transactionId'] ?? null,
                    'invoice_id' => $data['Id'] ?? null,
                    'paid_on' => $data['paidOn'] ?? null,
                    'payload' => $data,
                ]
            );

            if ($process === 'topup') {
                $clientController = new ClientController;
                $addCreditResponse = $clientController->addCredit($payment);

                if (isset($addCreditResponse['error']) || (isset($addCreditResponse['status']) && $addCreditResponse['status'] === 'error')) {
                    throw new \RuntimeException('Failed to add credit: ' . ($addCreditResponse['error'] ?? $addCreditResponse['message']));
                }

                if ($paymentTransaction) {
                    $transactionId = $addCreditResponse['data']['transaction_id'] ?? null;
                    if ($transactionId) {
                        $paymentTransaction->transaction_id = $transactionId;
                    }
                    $paymentTransaction->save();
                }
            } else {
                $coaResult = $this->createInvoicePaymentCOA(
                    payment: $payment,
                    finalPaidAmount: (float) $finalPaidAmount,
                    gatewayName: 'Hesabe',
                    partialIds: !empty($partialId) ? [$partialId] : null,
                    paymentReference: $data['transactionId'] ?? null
                );

                if (!$coaResult['success']) {
                    throw new \RuntimeException($coaResult['message']);
                }
            }

            $tboResult = $this->processTBOBookingAfterPayment($payment);
            if ($tboResult !== null && !$tboResult['success']) {
                Log::error('TBO booking failed via manual status check', $tboResult);
            }

            $payment->refresh();

            if ($sendNotification) {
                $this->sendPaymentCompletionNotifications($payment, $process, $partialId);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function processCompletedUPaymentPayment($payment, $transaction, $process, $partialId, $paymentTransaction, $sendNotification = false)
    {
        DB::beginTransaction();

        try {
            $finalPaidAmount = $transaction['amount'] ?? $payment->amount;

            $payment->status = 'completed';
            $payment->completed = 1;
            $payment->service_charge = $finalPaidAmount - $payment->amount;
            $payment->payment_reference = $transaction['trackId'] ?? $transaction['paymentId'] ?? null;
            $payment->payment_date = now();
            $payment->save();

            if ($paymentTransaction) {
                $paymentTransaction->status = $transaction['result'] ?? $transaction['status'] ?? 'CAPTURED';
                $paymentTransaction->track_id = $transaction['trackId'] ?? $paymentTransaction->track_id;
                $paymentTransaction->save();
            }

            if ($process === 'topup') {
                $clientController = new ClientController;
                $addCreditResponse = $clientController->addCredit($payment);

                if (isset($addCreditResponse['error']) || (isset($addCreditResponse['status']) && $addCreditResponse['status'] === 'error')) {
                    throw new \RuntimeException('Failed to add credit: ' . ($addCreditResponse['error'] ?? $addCreditResponse['message']));
                }

                if ($paymentTransaction) {
                    $transactionId = $addCreditResponse['data']['transaction_id'] ?? null;
                    if ($transactionId) {
                        $paymentTransaction->transaction_id = $transactionId;
                    }
                    $paymentTransaction->save();
                }
            } else {
                $coaResult = $this->createInvoicePaymentCOA(
                    payment: $payment,
                    finalPaidAmount: $finalPaidAmount,
                    gatewayName: 'UPayment',
                    partialIds: !empty($partialId) ? [$partialId] : null,
                    paymentReference: $transaction['paymentId'] ?? $transaction['trackId'] ?? null
                );

                if (!$coaResult['success']) {
                    throw new \RuntimeException($coaResult['message']);
                }
            }

            $tboResult = $this->processTBOBookingAfterPayment($payment);
            if ($tboResult !== null && !$tboResult['success']) {
                Log::error('TBO booking failed via manual status check', $tboResult);
            }

            $payment->refresh();

            if ($sendNotification) {
                $this->sendPaymentCompletionNotifications($payment, $process, $partialId);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function sendPaymentCompletionNotifications($payment, $process, $partialId)
    {
        $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);
        $agent = $receiptInfo['agent'];

        $storeNotificationData = [
            'user_id' => $agent->user_id,
            'title' => $receiptInfo['title'],
            'message' => $receiptInfo['message'],
        ];

        if ($payment->invoice) {
            $storeNotificationData['type'] = 'invoice';
            $storeNotificationData['invoice'] = $payment->invoice;
        } else {
            $storeNotificationData['type'] = 'payment';
            $storeNotificationData['payment'] = $payment;
        }

        $this->storeNotificationWithSendingPdf($storeNotificationData);

        (new ResayilController())->message(
            $agent->phone_number,
            $agent->country_code,
            $receiptInfo['message']
        );
    }
}
