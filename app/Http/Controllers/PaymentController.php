<?php

namespace App\Http\Controllers;

use App\Exports\PaymentLinkExport;
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
use App\Exceptions\Accounting\LegacyInvoiceCoaFailureException;
use App\Exceptions\Accounting\PaymentUnattributedException;
use App\Exceptions\Accounting\PostingException;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PaymentIdempotencyKey;
use App\Services\Accounting\PostingSeam;
use App\Services\TBOHolidayService;
use App\Services\TrialBalanceService;
use App\Support\PaymentGateway\Knet;
use Carbon\Carbon;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class PaymentController extends Controller
{
    use NotificationTrait, CurrencyExchangeTrait;

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
     * Atomically claim the next voucher sequence number for a company.
     *
     * The previous read-then-increment-then-save pattern (Sequence::firstOrCreate()
     * followed by ++/save() with no lock) let two concurrent requests read the same
     * current_sequence and both save the same incremented value, so two payments
     * could be handed the identical voucher number (VOU-YYYY-NNNNN). Locking the
     * sequence row for update inside a dedicated transaction, mirroring
     * CreateBulkInvoicesJob::generateInvoiceNumber(), serializes concurrent callers
     * so each one gets a distinct current_sequence.
     */
    private function nextVoucherNumber(int $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $voucherSequence = Sequence::where('company_id', $companyId)->lockForUpdate()->first();

            if (! $voucherSequence) {
                $voucherSequence = Sequence::create(['company_id' => $companyId, 'current_sequence' => 1]);
            }

            $voucherNumber = $this->generateVoucherNumber($voucherSequence->current_sequence);
            $voucherSequence->increment('current_sequence');

            return $voucherNumber;
        });
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

        $voucherNumber = $this->nextVoucherNumber($companyId);

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
            'company_id' => $invoice->agent->branch->company_id,
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
            Log::info('API key received from database', [
                'api_key' => $apiKey ? '...'.substr($apiKey, -4) : null,
            ]);

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
                "amount" => number_format((float) $finalAmount, 3, '.', ''),
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
            'apiKey' => $apiKey ? '...'.substr($apiKey, -4) : null,
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

            $existingInvoiceId = MyFatoorahPayment::where('invoice_id', $invoiceId)->exists();

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

        $paymentGatewayName = data_get($invoiceTransactions, '0.PaymentGateway');
        $gateway = strtolower(Arr::get($userDefined, 'payment_gateway', 'MyFatoorah'));
        $paymentMethodId = $paymentGatewayName
            ? PaymentMethod::where('type', $gateway)->where('english_name', $paymentGatewayName)->value('id')
            : null;

        $totalServiceCharge = (float) data_get($invoiceTransactions, '0.TotalServiceCharge', 0);
        $vatAmount = (float) data_get($invoiceTransactions, '0.VatAmount', 0);
        $gatewayFee = $totalServiceCharge + $vatAmount;

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
            'actual_gateway_fee' => $gatewayFee,
        ]);
    }

    public function importFromInvoice(Request $request): JsonResponse
    {
        Log::info('Starting to import payment from invoice');

        $gateway = strtolower($request->input('gateway'));

        $request->validate([
            'gateway' => 'required|in:myfatoorah,hesabe,tap',
            'import_invoice_id' => 'nullable|string',
            'import_order_reference' => 'nullable|string',
            'import_charge_id' => 'required_if:gateway,tap|string',
            'client_id' => 'required|integer|exists:clients,id',
            'agent_id' => 'required|integer|exists:agents,id',
        ]);

        $importInvoiceId = $request->input('import_invoice_id');
        $importOrderReference = $request->input('import_order_reference');
        $importChargeId = $request->input('import_charge_id');

        $clientId = $request->input('client_id');
        $agentId = $request->input('agent_id');

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

            session(['fatoorah_import' => $response]);

            $data = [
                'invoice_id' => $importInvoiceId,
                'payment_gateway' => $response['payment_gateway'],
                'payment_method_name' => data_get($response, 'data.InvoiceTransactions.0.PaymentGateway'),
                'amount' => $response['amount'],
                'client_id' => $clientId,
                'agent_id' => $agentId,
                'notes' => 'Imported from MyFatoorah Portal with Invoice ID: ' . $response['invoice_id'],
                'source' => 'import',
                'invoice_reference' => $response['invoice_reference'],
                'auth_code' => $response['auth_code'],
                'actual_gateway_fee' => $response['actual_gateway_fee'] ?? 0,
                'transaction_date' => data_get($response, 'data.InvoiceTransactions.0.TransactionDate'),
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

            session(['hesabe_import' => $response]);

            $data = [
                'invoice_id' => $importOrderReference,
                'payment_gateway' => $response['payment_gateway'],
                'payment_method_name' => $response['data']['payment_type'] ?? null,
                'amount' => $response['amount'],
                'client_id' => $clientId,
                'agent_id' => $agentId,
                'notes' => 'Imported from Hesabe Portal with Order Reference Number: ' . $response['payment_reference'],
                'source' => 'import',
                'payment_reference' => $response['data']['TransactionID'] ?? null,
                'track_id' => $response['data']['TrackID'] ?? null,
                'transaction_date' => $response['data']['datetime'] ?? null,
            ];
        } elseif ($gateway === 'tap') {

            try {
                $response = (new Tap())->getCharge($importChargeId);
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to fetch TAP charge: ' . $e->getMessage()
                ], 400);
            }

            if (!is_array($response) || ($response['status'] ?? '') === 'error') {
                return response()->json([
                    'status' => 'error',
                    'message' => $response['message'] ?? 'TAP charge not found.'
                ], 400);
            }

            if ($response['status'] !== 'CAPTURED') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'TAP charge is not captured (status: ' . ($response['status'] ?? 'unknown') . ')'
                ], 400);
            }

            if (TapPayment::where('tap_id', $importChargeId)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A payment with this Charge ID has already been imported.'
                ], 400);
            }

            $sourceMethod = strtoupper($response['source']['payment_method'] ?? '');
            $dbName = in_array($sourceMethod, ['VISA', 'MASTERCARD', 'MASTER']) ? 'Credit/Debit Cards' : $sourceMethod;
            $tapMethod = PaymentMethod::where('type', 'tap')->where('english_name', $dbName)->with('paymentMethodGroup')->first();
            $paymentMethodName = $tapMethod?->paymentMethodGroup?->name ?? $sourceMethod;
            $amount = $response['amount'] ?? 0;
            $fee = isset($response['payouts']['amount']) ? (float) ($amount - $response['payouts']['amount']) : 0;

            session(['tap_import' => $response]);

            $data = [
                'invoice_id' => $importChargeId,
                'payment_gateway' => 'Tap',
                'payment_method_name' => $paymentMethodName,
                'amount' => $amount,
                'client_id' => $clientId,
                'agent_id' => $agentId,
                'notes' => 'Imported from TAP Portal with Charge ID: ' . $importChargeId,
                'source' => 'import',
                'invoice_reference' => $response['reference']['payment'] ?? null,
                'auth_code' => $response['transaction']['authorization_id'] ?? null,
                'actual_gateway_fee' => $fee > 0 ? $fee : '',
                'transaction_date' => isset($response['transaction']['date']['created'])
                    ? Carbon::createFromTimestampMs($response['transaction']['date']['created'])->toDateTimeString()
                    : null,
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
            'gateway' => 'required|string|in:myfatoorah,hesabe,tap',
            'import_invoice_id' => 'required_if:gateway,myfatoorah|string|nullable',
            'import_order_reference' => 'required_if:gateway,hesabe|string|nullable',
            'import_charge_id' => 'required_if:gateway,tap|string|nullable',
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
                'invoice_id'          => $response['invoice_id'],
                'payment_gateway'     => $response['payment_gateway'],
                'payment_method_name' => data_get($response, 'data.InvoiceTransactions.0.PaymentGateway'),
                'amount'              => $response['amount'],
                'notes'               => 'Imported from MyFatoorah Portal with Invoice ID: ' . $response['invoice_id'],
                'source'              => 'import',
                'invoice_reference'   => $response['invoice_reference'],
                'auth_code'           => $response['auth_code'],
                'actual_gateway_fee'  => $response['actual_gateway_fee'] ?? 0,
                'transaction_date'    => data_get($response, 'data.InvoiceTransactions.0.TransactionDate'),
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
                'payment_method_name'   => $response['data']['payment_type'],
                'amount'                => $response['data']['amount'],
                'notes'                 => 'Imported from Hesabe Portal with Order Reference Number: ' . $response['data']['reference_number'],
                'source'                => 'import',
                'payment_reference'     => $response['data']['TransactionID'],
                'track_id'              => $response['data']['TrackID'],
                'transaction_date'      => $response['data']['datetime'],
            ]);
        } elseif ($gateway === 'tap') {
            $chargeId = $request->input('import_charge_id');

            try {
                $response = (new Tap())->getCharge($chargeId);
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', 'Failed to fetch TAP charge: ' . $e->getMessage());
            }

            if (!is_array($response) || ($response['status'] ?? '') === 'error') {
                return redirect()->back()->with('error', $response['message'] ?? 'TAP charge not found.');
            }

            if ($response['status'] !== 'CAPTURED') {
                return redirect()->back()->with('error', 'TAP charge is not captured (status: ' . ($response['status'] ?? 'unknown') . ')');
            }

            if (TapPayment::where('tap_id', $chargeId)->exists()) {
                return redirect()->back()->with('error', 'A payment with this Charge ID has already been imported.');
            }

            $sourceMethod = strtoupper($response['source']['payment_method'] ?? '');
            $dbName = in_array($sourceMethod, ['VISA', 'MASTERCARD', 'MASTER']) ? 'Credit/Debit Cards' : $sourceMethod;
            $tapMethod = PaymentMethod::where('type', 'tap')->where('english_name', $dbName)->with('paymentMethodGroup')->first();
            $paymentMethodName = $tapMethod?->paymentMethodGroup?->name ?? $sourceMethod;
            $amount = $response['amount'] ?? 0;
            $fee = isset($response['payouts']['amount']) ? (float) ($amount - $response['payouts']['amount']) : 0;

            session(['tap_import' => $response]);

            return redirect()->route('payment.link.create')->withInput([
                'invoice_id'          => $chargeId,
                'payment_gateway'     => 'Tap',
                'payment_method_name' => $paymentMethodName,
                'amount'              => $amount,
                'notes'               => 'Imported from TAP Portal with Charge ID: ' . $chargeId,
                'source'              => 'import',
                'invoice_reference'   => $response['reference']['payment'] ?? null,
                'auth_code'           => $response['transaction']['authorization_id'] ?? null,
                'actual_gateway_fee'  => $fee > 0 ? $fee : '',
                'transaction_date'    => isset($response['transaction']['date']['created'])
                    ? Carbon::createFromTimestampMs($response['transaction']['date']['created'])->toDateTimeString()
                    : null,
            ]);
        }

        return redirect()->back()->with('error', 'Unsupported payment gateway selected.');
    }

    public function importPaymentProcess(Request $request)
    {
        Log::info('Starting the process of importing payment from Portal');

        $request->validate([
            'payment_gateway' => 'required',
            'payment_methods' => 'nullable|array',
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
        $companyId = getCompanyId(Auth::user());

        $client = Client::with('agent.branch.company')->findOrFail($request->client_id);
        $agent = Agent::with('branch.company')->findOrFail($request->agent_id);

        // Tenant isolation (security fix): $companyId above is already correctly the CALLER's
        // own company, but client_id/agent_id themselves were never checked against it -- an
        // authenticated user could otherwise import a gateway payment (written straight to
        // status: 'completed' below, no approval step) against another company's client/agent,
        // landing real money in the wrong tenant's books. Mirrors the client/agent-company
        // check used by paymentStoreLinkProcess()/multiPaymentMethodProcess() elsewhere in this
        // class, checked directly against $companyId since that value is already the verified
        // caller company here (no separate "unscoped admin" branch needed).
        $clientCompanyId = $client->agent?->branch?->company?->id;
        $agentCompanyId = $agent->branch?->company?->id;

        abort_unless(
            $clientCompanyId && $agentCompanyId
                && (int) $clientCompanyId === (int) $companyId
                && (int) $agentCompanyId === (int) $companyId,
            403,
            'Unauthorized action.'
        );

        // Resolve payment method
        $methodName = $request->input('payment_method_name');
        $gatewayType = strtolower($request->input('payment_gateway', ''));
        $paymentMethodId = $methodName
            ? PaymentMethod::where('type', $gatewayType)->whereHas('paymentMethodGroup', fn($q) => $q->where('name', $methodName))->value('id')
            : ($request->payment_methods[0] ?? $request->payment_method);

        // Resolve gateway fee
        $actualGatewayFee = $request->input('actual_gateway_fee');
        if ($actualGatewayFee !== null && $actualGatewayFee !== '') {
            $gatewayFee = (float) $actualGatewayFee;
        } else {
            $chargeResult = ChargeService::calculate($request->amount, $companyId, $paymentMethodId, $request->payment_gateway);
            $gatewayFee = $chargeResult['gatewayFee'] ?? 0;
        }

        // Pull session data before transaction
        $gatewaySession = match ($request->payment_gateway) {
            'MyFatoorah' => session()->pull('fatoorah_import'),
            'Hesabe' => session()->pull('hesabe_import'),
            'Tap' => session()->pull('tap_import'),
            default => null,
        };

        DB::beginTransaction();

        try {
            $voucherNumber = $this->nextVoucherNumber($companyId);

            $data = [
                'company_id' => $companyId,
                'voucher_number' => $voucherNumber,
                'payment_reference' => $invoiceId ?? $paymentReference,
                'invoice_reference' => $invoiceReference ?? $trackId,
                'auth_code' => $authCode,
                'from' => $client->full_name,
                'pay_to' => $agent->branch->company->name,
                'currency' => 'KWD',
                'payment_date' => $request->input('transaction_date') ? Carbon::parse($request->input('transaction_date')) : Carbon::now(),
                'amount' => $request->amount,
                'service_charge' => 0,
                'gateway_fee' => $gatewayFee,
                'payment_gateway' => $request->payment_gateway,
                'payment_method_id' => $paymentMethodId,
                'status' => 'completed',
                'client_id' => $client->id,
                'agent_id' => $agent->id,
                'notes' => $request->notes,
                'created_by' => Auth::id()
            ];

            $payment = Payment::create($data);
            Log::info('Payment successfully created', ['payment_id' => $payment->id]);

            // Create gateway-specific payment record
            if ($payment->payment_gateway === 'MyFatoorah') {
                $apiData = data_get($gatewaySession, 'data', []);
                $transaction = data_get($apiData, 'InvoiceTransactions.0', []);

                MyFatoorahPayment::create([
                    'payment_int_id' => $payment->id,
                    'payment_id' => data_get($transaction, 'PaymentId'),
                    'invoice_id' => data_get($apiData, 'InvoiceId', $invoiceId),
                    'invoice_ref' => data_get($apiData, 'InvoiceReference', $invoiceReference),
                    'invoice_status' => data_get($apiData, 'InvoiceStatus'),
                    'customer_reference' => $payment->voucher_number,
                    'payload' => $apiData,
                ]);

                Log::info('MyFatoorah Payment record created');
            } elseif ($payment->payment_gateway === 'Hesabe') {
                if (is_string($gatewaySession)) {
                    $gatewaySession = json_decode($gatewaySession, true);
                }

                if (!$gatewaySession) {
                    throw new Exception('Hesabe payload not found in session');
                }

                $payload = $gatewaySession['data'] ?? null;

                HesabePayment::create([
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
                    'payload' => $gatewaySession,
                ]);

                Log::info('Hesabe Payment record created');
            } elseif ($payment->payment_gateway === 'Tap') {
                if (!$gatewaySession) {
                    throw new Exception('TAP payload not found in session');
                }

                TapPayment::create([
                    'payment_id' => $payment->id,
                    'tap_id' => $gatewaySession['id'] ?? $invoiceId,
                    'authorization_id' => $gatewaySession['transaction']['authorization_id'] ?? null,
                    'timezone' => $gatewaySession['transaction']['timezone'] ?? null,
                    'expiry_period' => $gatewaySession['transaction']['expiry']['period'] ?? null,
                    'expiry_type' => $gatewaySession['transaction']['expiry']['type'] ?? null,
                    'amount' => $payment->amount,
                    'currency' => $gatewaySession['currency'] ?? 'KWD',
                    'date_created' => isset($gatewaySession['transaction']['date']['created'])
                        ? Carbon::createFromTimestampMs($gatewaySession['transaction']['date']['created']) : now(),
                    'date_completed' => isset($gatewaySession['transaction']['date']['completed'])
                        ? Carbon::createFromTimestampMs($gatewaySession['transaction']['date']['completed']) : null,
                    'date_transaction' => $payment->payment_date,
                    'receipt_id' => $gatewaySession['receipt']['id'] ?? null,
                    'receipt_email' => $gatewaySession['receipt']['email'] ?? false,
                    'receipt_sms' => $gatewaySession['receipt']['sms'] ?? false,
                    'customer_reference' => $payment->voucher_number,
                    'payload' => $gatewaySession,
                ]);

                Log::info('TAP Payment record created');
            }

            $addCredit = (new ClientController)->addCredit($payment);
            if (isset($addCredit['error'])) {
                throw new Exception('Failed to add credit: ' . $addCredit['error']);
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
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to import payment', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function importPaymentFile(Request $request): RedirectResponse
    {
        set_time_limit(0);

        $request->validate([
            'gateway' => 'required|string',
            'file' => 'required|file|mimes:xlsx,csv,xls',
        ]);

        $companyId = getCompanyId(Auth::user());

        $import = new \App\Imports\PaymentImport();
        \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

        $rows = $import->rows;
        $gatewayName = strtolower($request->input('gateway'));

        if ($rows->isEmpty()) {
            return redirect()->back()->with('error', 'The uploaded file is empty.');
        }

        if (str_contains($gatewayName, 'tap')) {
            return $this->importTapPaymentFile($rows, $request->input('gateway'), $companyId);
        }

        $imported = 0;
        $skipped = 0;
        $skippedIds = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $invoiceId = $row['invoice_id'] ?? null;

            if (strtolower(trim($row['type'] ?? '')) === 'refund') {
                $skippedIds[] = ['row' => $index + 2, 'invoice_id' => $invoiceId, 'reason' => 'refund_skipped'];
                $skipped++;
                continue;
            }

            if (!$invoiceId) {
                $skippedIds[] = ['row' => $index + 2, 'reason' => 'no_invoice_id'];
                $skipped++;
                continue;
            }

            if (MyFatoorahPayment::where('invoice_id', $invoiceId)->exists()) {
                $skippedIds[] = ['row' => $index + 2, 'invoice_id' => $invoiceId, 'reason' => 'already_imported'];
                $skipped++;
                continue;
            }

            $agentName = $row['created_by'] ?? null;
            $agent = $agentName ? Agent::where('name', 'like', '%' . trim($agentName) . '%')
                ->whereHas('branch', fn($q) => $q->where('company_id', $companyId))
                ->first() : null;

            if (!$agent) {
                $errors[] = "Row " . ($index + 2) . ": Agent '{$agentName}' not found";
                $skippedIds[] = ['row' => $index + 2, 'invoice_id' => $invoiceId, 'reason' => 'agent_not_found', 'agent' => $agentName];
                $skipped++;
                continue;
            }

            // Read Excel columns for fallback values
            $excelPaymentId = $row['payment_id'] ?? null;
            $excelVendorServiceCharge = (float) ($row['vendor_service_charge'] ?? 0);
            $excelAuthorizationId = $row['authorization_id'] ?? null;
            $excelPaymentMethod = trim($row['payment_method'] ?? '');

            $paymentMethod = null;
            if ($excelPaymentMethod) {
                $paymentMethod = PaymentMethod::where('type', $gatewayName)->where('english_name', $excelPaymentMethod)->first();
            }

            // Call gateway API to get fee & payment details
            $apiData = null;
            $gatewayFee = $excelVendorServiceCharge;
            $paymentMethodId = $paymentMethod?->id;
            $invoiceReference = $row['invoice_reference'] ?? null;
            $authCode = $excelAuthorizationId;

            try {
                if (str_contains($gatewayName, 'myfatoorah')) {
                    // Always use InvoiceId to fetch payment status
                    $response = $this->getPaymentStatusMyFatoorah($invoiceId)->getData(true);

                    if ($response['status'] === 'success') {
                        $apiData = $response['data'] ?? [];
                        $gatewayFee = (float) ($response['actual_gateway_fee'] ?? 0) ?: $excelVendorServiceCharge;
                        $paymentMethodId = $response['payment_method_id'] ?? $paymentMethod?->id;
                        $invoiceReference = $response['invoice_reference'] ?? $invoiceReference;
                        $authCode = $response['auth_code'] ?? $excelAuthorizationId;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('API call failed for import row', [
                    'invoice_id' => $invoiceId,
                    'payment_id' => $excelPaymentId,
                    'error' => $e->getMessage(),
                ]);
            }

            $paidDate = now();
            $raw = trim((string) ($row['paid_date'] ?? $row['paid date'] ?? $row['payment_date'] ?? $row['payment date'] ?? ''));
            if ($raw && is_numeric($raw) && (float) $raw > 10000) {
                try {
                    $paidDate = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw));
                } catch (\Throwable) {
                }
            } elseif ($raw) {
                $y = preg_match('/\/\d{4}\b/', $raw) ? 'Y' : 'y';
                $t = preg_match('/[AP]M/i', $raw) ? 'h:i A' : (str_contains($raw, ':') ? 'H:i' : '');
                try {
                    $paidDate = Carbon::createFromFormat("d/m/{$y}" . ($t ? " {$t}" : ''), $raw);
                } catch (\Throwable) {
                }
                if ($paidDate->year < 100) $paidDate->year += 2000;
            }

            DB::beginTransaction();
            try {
                $payment = Payment::create([
                    'company_id' => $companyId,
                    'voucher_number' => null,
                    'payment_reference' => $invoiceId,
                    'invoice_reference' => $invoiceReference,
                    'auth_code' => $authCode,
                    'from' => $row['customer_name'] ?? 'Unknown',
                    'pay_to' => $agent->branch->company->name,
                    'currency' => 'KWD',
                    'payment_date' => $paidDate,
                    'amount' => $row['invoice_value'] ?? $row['customer_service'] ?? 0,
                    'service_charge' => 0,
                    'gateway_fee' => $gatewayFee,
                    'payment_gateway' => $request->input('gateway'),
                    'payment_method_id' => $paymentMethodId,
                    'status' => 'completed',
                    'completed' => true,
                    'client_id' => null,
                    'agent_id' => $agent->id,
                    'notes' => 'Imported from ' . $request->input('gateway') . ' file with Invoice ID: ' . $invoiceId,
                    'created_by' => $agent->user_id ?? Auth::id(),
                    'is_imported' => true,
                ]);

                // Store in gateway-specific table
                if (str_contains($gatewayName, 'myfatoorah')) {
                    $transaction = data_get($apiData, 'InvoiceTransactions.0', []);

                    // Build Excel payload as fallback when API data is unavailable (PascalCase keys)
                    $excelPayload = collect($row)->filter(fn($v) => $v !== null && $v !== '')
                        ->mapWithKeys(fn($v, $k) => [Str::studly($k) => is_string($v) ? trim($v) : $v])
                        ->toArray();

                    MyFatoorahPayment::create([
                        'payment_int_id' => $payment->id,
                        'payment_id' => data_get($transaction, 'PaymentId', $excelPaymentId),
                        'invoice_id' => data_get($apiData, 'InvoiceId', $invoiceId),
                        'invoice_ref' => data_get($apiData, 'InvoiceReference', $invoiceReference),
                        'invoice_status' => data_get($apiData, 'InvoiceStatus', $row['invoice_status'] ?? null),
                        'customer_reference' => null,
                        'payload' => $apiData ?? $excelPayload,
                    ]);
                } elseif (str_contains($gatewayName, 'hesabe')) {
                    HesabePayment::create([
                        'payment_int_id' => $payment->id,
                        'invoice_id' => $invoiceId,
                        'payload' => $apiData ?? [],
                    ]);
                } elseif (str_contains($gatewayName, 'upayment')) {
                    UpaymentPayment::create([
                        'payment_int_id' => $payment->id,
                        'invoice_id' => $invoiceId,
                        'total_price' => $row['invoice_value'] ?? $row['customer_service'] ?? 0,
                        'payload' => $apiData ?? [],
                    ]);
                }

                DB::commit();
                $imported++;
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Failed to import payment row', [
                    'row' => $index + 2,
                    'invoice_id' => $invoiceId,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                $skipped++;
            }
        }

        $message = "{$imported} payments imported successfully.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped.";
            Log::info('Import skipped details', ['skipped' => $skippedIds]);
        }

        return redirect()->route('payment.link.index')->with($imported > 0 ? 'success' : 'error', $message);
    }

    private function importTapPaymentFile($rows, string $gatewayName, int $companyId): RedirectResponse
    {
        $imported = 0;
        $skipped = 0;
        $skippedIds = [];
        $errors = [];

        // Re-group: assign fee/transfer rows to the last seen charge ID
        $grouped = collect();
        $lastChargeId = null;

        foreach ($rows as $row) {
            $refOrder = trim($row['reference_order'] ?? '');
            $desc = strtolower(trim($row['description'] ?? ''));

            if (str_starts_with($desc, 'sale') && $refOrder) {
                $lastChargeId = $refOrder;
            }

            if ($lastChargeId) {
                if (!$grouped->has($lastChargeId)) {
                    $grouped[$lastChargeId] = collect();
                }
                $grouped[$lastChargeId]->push($row);
            }

            // Reset after transfer row
            if (str_starts_with($desc, 'transfer')) {
                $lastChargeId = null;
            }
        }

        foreach ($grouped as $chargeId => $group) {
            $chargeId = trim($chargeId);

            if (!$chargeId) {
                $skipped++;
                continue;
            }

            if (TapPayment::where('tap_id', $chargeId)->exists()) {
                $skippedIds[] = ['charge_id' => $chargeId, 'reason' => 'already_imported'];
                $skipped++;
                continue;
            }

            $saleRow = $group->first(fn($r) => str_starts_with(strtolower(trim($r['description'] ?? '')), 'sale'));
            $feeRow  = $group->first(fn($r) => str_starts_with(strtolower(trim($r['description'] ?? '')), 'fee'));

            if (!$saleRow) {
                $skippedIds[] = ['charge_id' => $chargeId, 'reason' => 'no_sale_row'];
                $skipped++;
                continue;
            }

            $netAmount = (float) ($saleRow['credit'] ?? 0);
            $gatewayFee = (float) ($feeRow['debit'] ?? 0);

            $excelMethod = trim($saleRow['payment_method'] ?? '');
            $dbName = in_array(strtoupper($excelMethod), ['VISA', 'MASTERCARD', 'MASTER']) ? 'Credit/Debit Cards' : $excelMethod;
            $paymentMethod = $excelMethod ? PaymentMethod::where('type', 'tap')->where('english_name', $dbName)->first() : null;

            $paidDate = now();
            $raw = trim((string) ($saleRow['txndate'] ?? ''));
            if ($raw && is_numeric($raw) && (float) $raw > 10000) {
                try {
                    $paidDate = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw));
                } catch (\Throwable) {
                }
            } elseif ($raw) {
                $y = preg_match('/\/\d{4}\b/', $raw) ? 'Y' : 'y';
                $t = preg_match('/[AP]M/i', $raw) ? 'h:i A' : (str_contains($raw, ':') ? 'H:i' : '');
                try {
                    $paidDate = Carbon::createFromFormat("d/m/{$y}" . ($t ? " {$t}" : ''), $raw);
                } catch (\Throwable) {
                }
                if ($paidDate->year < 100) $paidDate->year += 2000;
            }

            $excelPayload = $saleRow->toArray();
            $apiData = null;
            try {
                $response = (new Tap())->getCharge($chargeId);
                if (is_array($response) && ($response['status'] ?? '') !== 'error') {
                    $apiData = $response;
                }
            } catch (\Throwable $e) {
                Log::warning('TAP API call failed for import', ['charge_id' => $chargeId, 'error' => $e->getMessage()]);
            }

            DB::beginTransaction();
            try {
                $payment = Payment::create([
                    'company_id' => $companyId,
                    'voucher_number' => null,
                    'payment_reference' => $chargeId,
                    'invoice_reference' => $saleRow['receipt'] ?? null,
                    'auth_code' => $saleRow['authid'] ?? null,
                    'from' => null,
                    'pay_to' => null,
                    'currency' => trim($saleRow['currency'] ?? 'KWD'),
                    'payment_date' => $paidDate,
                    'amount' => $netAmount,
                    'service_charge' => 0,
                    'gateway_fee' => $gatewayFee,
                    'payment_gateway' => $gatewayName,
                    'payment_method_id' => $paymentMethod?->id,
                    'status' => 'completed',
                    'completed' => true,
                    'client_id' => null,
                    'agent_id' => null,
                    'notes' => 'Imported from ' . $gatewayName . ' file with Charge ID: ' . $chargeId,
                    'created_by' => null,
                    'is_imported' => true,
                ]);

                // Build Excel payload as fallback
                $excelPayload = collect($saleRow)->filter(fn($v) => $v !== null && $v !== '')
                    ->mapWithKeys(fn($v, $k) => [Str::studly($k) => is_string($v) ? trim($v) : $v])
                    ->toArray();

                TapPayment::create([
                    'payment_id' => $payment->id,
                    'tap_id' => $chargeId,
                    'authorization_id' => $apiData['authorize_id'] ?? $saleRow['authid'] ?? null,
                    'timezone' => $apiData['transaction']['timezone'] ?? null,
                    'expiry_period' => $apiData['transaction']['expiry']['period'] ?? null,
                    'expiry_type' => $apiData['transaction']['expiry']['type'] ?? null,
                    'amount' => $netAmount,
                    'currency' => $apiData['currency'] ?? trim($saleRow['currency'] ?? 'KWD'),
                    'date_created' => isset($apiData['transaction']['date']['created'])
                        ? Carbon::createFromTimestampMs($apiData['transaction']['date']['created']) : now(),
                    'date_completed' => isset($apiData['transaction']['date']['completed'])
                        ? Carbon::createFromTimestampMs($apiData['transaction']['date']['completed']) : null,
                    'date_transaction' => $paidDate,
                    'receipt_id' => $apiData['receipt']['id'] ?? $saleRow['receipt'] ?? null,
                    'receipt_email' => $apiData['receipt']['email'] ?? false,
                    'receipt_sms' => $apiData['receipt']['sms'] ?? false,
                    'customer_reference' => null,
                    'payload' => $apiData ?? $excelPayload,
                ]);

                DB::commit();
                $imported++;
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Failed to import TAP payment', [
                    'charge_id' => $chargeId,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "Charge {$chargeId}: " . $e->getMessage();
                $skipped++;
            }
        }

        $message = "{$imported} payments imported successfully.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped.";
            Log::info('TAP import skipped details', ['skipped' => $skippedIds]);
        }

        return redirect()->route('payment.link.index')
            ->with($imported > 0 ? 'success' : 'error', $message);
    }

    public function assignClientToImport(Request $request, $paymentId): RedirectResponse
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        // Tenant isolation (security fix): scope the base row lookup to the caller's own
        // company -- mirrors paymentUpdateLink()'s identical fix. $paymentId is otherwise a
        // guessable integer with no company check at all.
        $unscopedAdmin = $user->role_id == Role::ADMIN && ! $companyId;

        $paymentQuery = Payment::where('is_imported', true)->whereNull('client_id');
        if (! $unscopedAdmin) {
            $paymentQuery->where('company_id', $companyId);
        }
        $payment = $paymentQuery->where('id', $paymentId)->firstOrFail();

        $rules = ['client_id' => 'required|integer|exists:clients,id'];
        $messages = ['client_id.in' => 'The client you assigned does not assigned to this agent.'];

        $agentId = null;

        if (!$payment->agent_id) {
            $rules['agent_id'] = 'required|integer|exists:agents,id';
            $agentId = $request->agent_id;
        } else {
            $agentId = $payment->agent_id;
        }

        // Tenant isolation (security fix): agent_id was previously validated only
        // exists:agents,id -- proving the id is a real row, never that it belongs to the
        // caller's own company. Without this, a caller could supply another company's
        // agent_id for a still-unassigned payment (or exploit one already carrying a
        // foreign agent_id) and the client allow-list below would be built from -- and the
        // payment ultimately reassigned into -- that other company's roster.
        $agentForCheck = Agent::with('branch.company')->find($agentId);
        abort_unless($agentForCheck, 404, 'Agent not found.');
        $this->assertSameCompanyOrUnscopedAdmin($user, (int) $agentForCheck->branch?->company?->id);

        $clients = Client::where('agent_id', $agentId)
            ->orWhereHas('agents', fn($q) => $q->where('agent_id', $agentId))
            ->pluck('id')
            ->toArray();

        $rules['client_id'] .= '|in:' . implode(',', $clients);

        $request->validate($rules, $messages);

        $client = Client::findOrFail($request->client_id);

        if (! $payment->agent_id && $request->agent_id) {
            $agent = $agentForCheck;
            $payment->agent_id = $agent->id;
            $payment->created_by = $agent->user_id ?? Auth::id();
            $payment->pay_to = $agent->branch?->company?->name;
            $payment->save();
        }

        $voucherNumber = $this->nextVoucherNumber($companyId);

        $payment->update([
            'voucher_number' => $voucherNumber,
            'client_id' => $client->id,
            'from' => $client->full_name,
            'is_imported' => false,
        ]);

        if ($payment->myFatoorahPayment) {
            $payment->myFatoorahPayment->update(['customer_reference' => $voucherNumber]);
        }
        if ($payment->tapPayment) {
            $payment->tapPayment->update(['customer_reference' => $voucherNumber]);
        }

        $payment->refresh();

        try {
            $clientController = new ClientController();
            $addCredit = $clientController->addCredit($payment);

            if (isset($addCredit['error']) || (isset($addCredit['status']) && $addCredit['status'] === 'error')) {
                return redirect()->back()->with('error', 'Client assigned but failed to create COA: ' . ($addCredit['message'] ?? $addCredit['error']));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to create COA for imported payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Client assigned but COA creation failed: ' . $e->getMessage());
        }

        return redirect()->route('payment.link.index')
            ->with('success', 'Client assigned and journal entries created for ' . $payment->voucher_number);
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

        $baseQuery = Payment::with([
            'invoice',
            'client',
            'agent.branch',
            'createdBy',
            'paymentMethod',
            'myFatoorahPayment',
            'tapPayment',
            'availablePaymentMethodGroups',
            'paymentItems',
            'appliedToInvoices.agent.branch',
        ])->where(function ($query) use ($agentsId) {
            $query->whereHas('invoice', function ($payment) use ($agentsId) {
                $payment->whereIn('agent_id', $agentsId);
            })->orWhereIn('agent_id', $agentsId)
                ->orWhere(fn($q) => $q->where('is_imported', true)->whereNull('agent_id'));
        });

        if ($request->boolean('clear')) {
            session()->forget('filter');
            return redirect()->route('payment.link.index', array_filter([
                'search' => $request->query('search'),
            ]));
        }

        $search = $request->query('search');
        $searchQuery = function ($query) use ($search) {
            $query->where('payment_reference', 'like', '%' . $search . '%')
                ->orWhere('payment_gateway', 'like', '%' . $search . '%')
                ->orWhere('voucher_number', 'like', '%' . $search . '%')
                ->orWhereHas('paymentMethod', fn($q) => $q->where('english_name', 'like', '%' . $search . '%'))
                ->orWhereHas('agent', fn($q) => $q->where('name', 'like', '%' . $search . '%'))
                ->orWhereHas(
                    'client',
                    fn($q) => $q
                        ->whereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(middle_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ['%' . $search . '%'])
                        ->orWhereRaw("CONCAT(COALESCE(country_code, ''), COALESCE(phone, '')) LIKE ?", ['%' . $search . '%'])
                )
                ->orWhereHas('myFatoorahPayment', fn($q) => $q->where('invoice_ref', 'like', '%' . $search . '%'))
                ->orWhereHas('tapPayment', fn($q) => $q->where('tap_id', 'like', '%' . $search . '%'));
        };

        // --- Tab 1: Regular payments (not imported) ---
        $payments = clone $baseQuery;
        $payments = $payments->where(fn($q) => $q->where('is_imported', false)->orWhereNull('is_imported'));

        if ($search) {
            $payments->where($searchQuery);
        }

        $incoming = collect($request->input('filter', []))
            ->filter(fn($v) => is_array($v) ? array_filter($v, fn($x) => $x !== '' && $x !== null) : $v !== '' && $v !== null)
            ->all();

        if ($request->has('filter')) {
            session(['filter' => array_replace(session('filter', []), $incoming)]);
            return redirect()->route('payment.link.index', array_filter([
                'search' => $request->query('search'),
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

        $payments = $payments->orderBy('id', 'desc')->paginate(15, ['*'], 'page')->appends($request->only(['search', 'ipage']));

        // --- Tab 2: Imported payments ---
        $importedPayments = clone $baseQuery;
        $importedPayments = $importedPayments->where('is_imported', true);

        if ($search) {
            $importedPayments->where($searchQuery);
        }

        $importedPayments->when(data_get($filters, 'date_from'), fn($q, $v) => $q->whereDate('payment_date', '>=', $v));
        $importedPayments->when(data_get($filters, 'date_to'), fn($q, $v) => $q->whereDate('payment_date', '<=', $v));

        $importedPayments = $importedPayments->orderBy('id', 'desc')
            ->paginate(15, ['*'], 'ipage')
            ->appends($request->only(['search', 'page']));

        $paymentGateways = Charge::with('methods')->where('can_generate_link', true)->where('is_active', true)->get();
        $can_import = Charge::where('can_import', true)->get();
        $users = User::whereIn('id', Payment::select('created_by')->distinct()->pluck('created_by'))->get();
        $status = Payment::distinct()->pluck('status')->filter()->values()->toArray();
        $paymentMethodChose = $companyId ? PaymentMethodChose::where('company_id', $companyId)->get() : collect();

        return view('payment.link.index', compact(
            'payments',
            'importedPayments',
            'clients',
            'agents',
            'paymentGateways',
            'can_import',
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

        // Tenant isolation (security fix, W41): this is a second front door into payment
        // creation reachable whenever `payment_gateway` is non-null on POST
        // payment/link/store (the `payment_gateway == null` branch already routes through the
        // hardened multiPaymentMethodProcess() instead -- see paymentStoreLink() above).
        // Previously `company_id` was taken straight from request input with only
        // 'exists:companies,id', and Client::find()/Agent::find() had no company check at all,
        // so any authenticated user could inject a real Payment row into another company's
        // books by supplying that company's client_id/agent_id (and/or company_id) pair.
        // Resolve client/agent WITH their company relations, require them to belong to the
        // SAME company, then require the caller to match that company (or be an unscoped
        // admin) -- mirrors multiPaymentMethodProcess()'s identical fix elsewhere in this same
        // class. company_id is now always DERIVED from the verified agent, never trusted from
        // request input, closing the request->company_id bypass entirely.
        $user = Auth::user();
        $client = Client::with('agent.branch.company')->find($request->client_id);
        $agent = Agent::with('branch.company')->find($request->agent_id);

        if (!$client) {
            return ['status' => 'error', 'message' => 'Client cannot be found'];
        }

        if (!$agent) {
            return ['status' => 'error', 'message' => 'Agent cannot be found'];
        }

        $clientCompanyId = $client->agent?->branch?->company?->id;
        $agentCompanyId = $agent->branch?->company?->id;

        abort_unless(
            $clientCompanyId && $agentCompanyId && (int) $clientCompanyId === (int) $agentCompanyId,
            403,
            'Unauthorized action.'
        );

        $this->assertSameCompanyOrUnscopedAdmin($user, (int) $agentCompanyId);

        $companyId = $agentCompanyId;

        $company = $companyId ? Company::find($companyId) : null;
        $companyEmail = $company?->email ?? 'admin@citytravelers.co';

        try {
            $voucherNumber = $this->nextVoucherNumber($companyId);
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
                'company_id' => $companyId,
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
        if ($request->input('source') === 'import') {
            $response = $this->paymentStoreLinkProcess($request);

            if ($response['status'] === 'error') {
                return redirect()->back()->with('error', $response['message']);
            }

            return redirect()->route('payment.link.index')
                ->with('success', 'Payment imported successfully!');
        }

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

        // Self-heal on page load: if MyFatoorah already has this invoice as PAID but a missed
        // webhook/callback left us on `initiate`, complete it now so the page renders the paid
        // receipt immediately instead of showing "not paid" until the 30-min reconciler runs.
        if ($payment->status === 'initiate' && $this->completeIfAlreadyPaid($payment)) {
            $payment = Payment::with('agent', 'client', 'paymentItems')->where('id', $payment->id)->first();
        }

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

            // If the current MF invoice was already paid (e.g. missed webhook), complete it
            // instead of reusing/reinitiating the link — never overwrite a paid invoice reference.
            if ($payment->status === 'initiate' && $this->completeIfAlreadyPaid($payment)) {
                $partialId = $payment->invoice?->invoicePartials()->where('payment_id', $payment->id)->value('id');
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);
                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
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
                "InvoiceValue"        => number_format((float) $finalAmount, 3, '.', ''),
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
                        "UnitPrice"  => number_format((float) $finalAmount, 3, '.', ''),
                    ]
                ],
            ];

            Log::info('MyFatoorah ExecutePayment request', [
                'payload' => $executePayload,
                'api_key' => $apiKey ? '...'.substr($apiKey, -4) : null,
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
            Log::info('API key received from database', [
                'api_key' => $apiKey ? '...'.substr($apiKey, -4) : null,
            ]);

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
                "amount" => number_format((float) $finalAmount, 3, '.', ''),
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
        // Never reinitiate over an invoice the client already paid (missed webhook):
        // complete it and short-circuit to the receipt instead of overwriting payment_reference.
        if ($this->completeIfAlreadyPaid($payment)) {
            $process = $payment->invoice ? 'invoice' : 'topup';
            $partialId = $payment->invoice?->invoicePartials()->where('payment_id', $payment->id)->value('id');
            $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);
            return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
        }

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
            "InvoiceValue"        => number_format((float) $finalAmount, 3, '.', ''),
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
                    "UnitPrice"  => number_format((float) $finalAmount, 3, '.', ''),
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
                $paymentId = $userDefinedField['payment_id'] ?? null;

                // Resolve by our own internal payment_id whenever MyFatoorah echoed it
                // back (it always does; we send it in UserDefinedField on initiate).
                // A bare orWhere('voucher_number', ...) across ALL companies' payments
                // is a cross-tenant hazard: voucher numbers are sequential PER company,
                // so two different companies can legitimately share the same
                // voucher_number, letting this webhook resolve to and complete the
                // wrong tenant's payment. Only fall back to payment_reference (the
                // MyFatoorah-assigned invoice id, unique platform-wide) when payment_id
                // is unavailable, and never fall back to voucher_number alone.
                $payment = $paymentId
                    ? Payment::find($paymentId)
                    : ($invoiceId ? Payment::where('payment_reference', $invoiceId)->first() : null);

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
                } catch (PostingException $e) {
                    // D4 (W2 orchestrator decision): this is the user's own browser landing
                    // on MyFatoorah's CallBackUrl (route('payments.callback')) — the separate
                    // handleWebhookFatoorah is the true server-to-server path — so a genuine
                    // engine failure must never be downgraded to the generic message below.
                    // processMyFatoorahPaymentCompletion()'s own DB::rollBack() has already
                    // rolled the payment completion back whole.
                    $idempotencyKey = PaymentIdempotencyKey::forGatewayPayment(
                        'MyFatoorah',
                        $payment->id,
                        ! empty($partialId) ? [$partialId] : null
                    );

                    Log::error('MyFatoorah callback accounting posting exception', [
                        'payment_id' => $payment->id,
                        'exception_class' => get_class($e),
                        'idempotency_key' => $idempotencyKey,
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    return redirect()->route('payment.failed')->with('error', sprintf(
                        'Accounting posting failed (%s). Reference: %s. Payment not completed.',
                        class_basename($e),
                        $idempotencyKey
                    ));
                } catch (Exception $e) {
                    // If completion failed because another path (the signed webhook or
                    // the reconciler) already completed this payment a moment earlier,
                    // the payer DID pay successfully — show the receipt, not an error.
                    // (Webhook-vs-callback race, VOU-2026-04073.)
                    $payment->refresh();
                    if ($payment->status === 'completed' || str_contains($e->getMessage(), 'already been added')) {
                        Log::info('MF callback: completion raced by another path, payment already completed', [
                            'payment_id' => $payment->id,
                            'error' => $e->getMessage(),
                        ]);
                        return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
                    }

                    // R-1 fix (W2.2): payments.callback carries no auth middleware -- this is
                    // a public route. $e here is whatever processMyFatoorahPaymentCompletion()
                    // raised that was NOT a PostingException (including a legacy COA failure's
                    // \RuntimeException, whose message is now the safe sentence
                    // createInvoicePaymentCOA()'s own catch produces, but also any other
                    // \Exception that method can throw) -- never assume its ->getMessage() is
                    // safe to print. Full detail stays in the log; the flash carries a fixed
                    // sentence plus the payment's own voucher_number as the correlation id.
                    Log::error('MyFatoorah callback processing failed', [
                        'payment_id' => $payment->id,
                        'exception_class' => get_class($e),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return redirect()->to($receiptInfo['url'])->with('error', sprintf(
                        'Payment could not be completed. Reference: %s. Please contact support.',
                        $payment->voucher_number ?: $payment->id
                    ));
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

    /**
     * D6 (W2/mf-error): three sites in this controller record a gateway *failure* as a
     * Transaction row keyed (payment_id, 'Payment') -- handleMyFatoorahError's topup branch,
     * handleTapCallback, handleKnetResponse. `transactions_payment_id_reference_type_unique`
     * (P0 hotfix migration 2026_08_24_000001) allows at most one row per (payment_id,
     * reference_type) pair, and at HEAD every one of the three writes a plain
     * `Transaction::create()`. Residual 20 fix (W2.1) -- corrected: this does NOT 1062 raw and
     * uncaught for all three at HEAD. Only `handleMyFatoorahError`'s topup branch has no
     * surrounding try/catch, so a redelivered failure notification for the SAME payment
     * (gateway retry, or a user revisiting a failed return-url) genuinely does 1062 there with a
     * raw, uncaught UniqueConstraintViolationException. `handleTapCallback` and
     * `handleKnetResponse` wrap this same write in their own outer `catch (Throwable $e)`, so at
     * HEAD the identical collision is swallowed into their generic "Something went wrong. Please
     * contact support." response instead of propagating raw -- silent rather than uncaught, but
     * still wrong: the failure Transaction that redelivery was trying to record never gets
     * written, with no indication to the caller that anything failed to persist.
     * These are LEGACY-side audit/log writes, not double-entry documents: confirmed by reading
     * -- none of the three sites (nor anything hooked to Transaction::created/booted, nor any
     * registered observer; `grep -rn "Transaction::observe"` returns zero hits) ever creates a
     * JournalEntry row for it, and Transaction's own `type`/`type_reference_id`/`posting_status`
     * columns (the ones the engine and ledger-balance queries key on) are left unset/null on
     * all three writes. So they carry no accounting impact and are correctly NOT routed through
     * PostingSeam -- there is no draft to build.
     *
     * withTrashed(): the same (payment_id, reference_type) slot is not freed by a soft delete
     * (no deleted_at in the unique key), and several unrelated cleanup paths DO soft-delete
     * Transaction rows by invoice_id (e.g. InvoiceController's `Transaction::where('invoice_id',
     * ...)->delete()|Transaction::where('id', $receipt->transaction_id)->delete()`), which these
     * three writes are all reachable from (each sets 'invoice_id' => $payment->invoice_id). A
     * plain (non-trashed) lookup would miss such a row and still 1062 on the create.
     *
     * firstOrCreate() itself is safe under genuine concurrent redelivery: Laravel's
     * `Builder::createOrFirst()` (which `firstOrCreate()` calls when its own first `first()`
     * lookup misses) wraps the INSERT in a savepoint-if-needed and, on ANY
     * UniqueConstraintViolationException, re-queries by the exact same $searchAttributes and
     * returns that row -- or rethrows the original exception if nothing matches, so an unrelated
     * unique-constraint collision is never silently misreported as this one. $searchAttributes
     * here is always exactly {payment_id, reference_type: 'Payment'}, the only unique index any
     * of these three creates can ever collide on (none of them sets idempotency_key).
     *
     * @param  array<string, mixed>  $searchAttributes  Exactly ['payment_id' => .., 'reference_type' => 'Payment'].
     * @param  array<string, mixed>  $createAttributes  Every other column HEAD's Transaction::create() call wrote.
     */
    private function firstOrCreateFailureTransaction(array $searchAttributes, array $createAttributes): Transaction
    {
        $transaction = Transaction::withTrashed()->firstOrCreate($searchAttributes, $createAttributes);

        if (! $transaction->wasRecentlyCreated) {
            // Residual 10 fix (W2.1): withTrashed() means a soft-deleted row satisfies
            // this lookup with zero visibility into that fact, and the caller then
            // treats the returned Transaction as if it were live. A fresh row is not
            // an option here -- $searchAttributes is exactly the unique index this
            // method exists to respect, so a second INSERT would just 1062. restore()
            // is the only safe choice when the hit is trashed; log every reuse
            // (trashed or not) so a redelivery landing on an existing row is never
            // silent.
            Log::warning('firstOrCreateFailureTransaction reused an existing row instead of creating one', [
                'transaction_id' => $transaction->id,
                'search' => $searchAttributes,
                'was_trashed' => $transaction->trashed(),
            ]);

            if ($transaction->trashed()) {
                $transaction->restore();
            }
        }

        return $transaction;
    }

    public function handleMyFatoorahError(Request $request)
    {
        Log::error('[MYFATOORAH] error callback', [
            'request' => $request->all(),
            'query' => $request->query(),
            'input' => $request->input(),
        ]);

        // R-2 fix (W2.2): a redelivered payments.error for a payment whose failure was
        // already recorded (firstOrCreateFailureTransaction() below returned an existing
        // row, not a fresh one) must not re-fire the notification/WhatsApp/flash below --
        // that residual-9 early return only covered a completed TOPUP (`! $payment->invoice_id`);
        // a completed INVOICE payment fell through to here on every redelivery. This flag
        // covers both shapes because it keys off whether the write actually happened, not
        // off invoice_id.
        $failureAlreadyRecorded = false;

        if ($request->has('invoice_id')) {

            Log::error('[MYFATOORAH] update transaction for failed invoice payment', [
                'invoice_id' => $request->input('invoice_id'),
            ]);

            $invoice = Invoice::with('agent.branch', 'client')->find($request->input('invoice_id'));
            $paymentId = $request->query('paymentId') ?? $request->input('paymentId');

            // Residual 13 fix (W2.1): mirror the D5 guard already used by
            // handleTapCallback/handleKnetResponse -- payments.agent_id is
            // nullable()->nullOnDelete(), so never let an unattributed chain crash this
            // Transaction::create() uncaught; alert and skip the write instead of
            // guessing a company_id.
            $unattributedCompanyId = $invoice?->agent?->branch?->company?->id;

            if ($unattributedCompanyId === null) {
                Log::critical('accounting.payment_unattributed', [
                    'invoice_id' => $invoice?->id,
                    'payment_id' => $invoice?->payment?->id,
                    'gateway' => 'MyFatoorah',
                    'reason' => 'invoice->agent->branch->company chain unresolved (agent likely deleted/unlinked)',
                ]);
            } else {
                Transaction::create([
                    'branch_id' => $invoice->agent->branch->id,
                    'company_id' => $unattributedCompanyId,
                    'entity_id' => $unattributedCompanyId,
                    'entity_type' => 'company',
                    'transaction_type' => 'credit',
                    'amount' => $invoice->amount,
                    'description' => 'MyFatoorah payment failed: '.$invoice->invoice_number,
                    'invoice_id' => $invoice->id,
                    'payment_id' => $invoice->payment->id,
                    'payment_reference' => $invoice->payment->payment_reference,
                    'reference_type' => 'Invoice',
                    'transaction_date' => now(),
                ]);
            }
        }

        if ($request->has('payment_id')) {

            Log::error('[MYFATOORAH] update transaction for failed topup payment', [
                'payment_id' => $request->input('payment_id'),
            ]);

            $payment = Payment::with('client', 'agent.branch')->find($request->input('payment_id'));

            // Residual 9 fix (W2.1): a replayed payments.error notification for a TOPUP
            // payment that has ALREADY completed via ClientController::addCredit()'s own
            // success write -- which shares this exact (payment_id, 'Payment') slot, see
            // firstOrCreateFailureTransaction()'s own docblock -- must not fire a false
            // "payment failed" notification over a payment that actually succeeded.
            // Scoped to `! $payment->invoice_id` (a genuine topup, addCredit()'s own domain)
            // deliberately: an INVOICE payment already 'completed' via the engine's OWN
            // 'Receipt' document (a different reference_type entirely, no collision, no wrong
            // document) is the mf-error lane's own cross-flip coexistence scenario
            // (PaymentControllerTransactionDedupTest) and must still write its legacy
            // 'Payment' row here exactly as before.
            if ($payment && $payment->status === 'completed' && ! $payment->invoice_id) {
                Log::info('[MYFATOORAH] error callback ignored: payment already completed', [
                    'payment_id' => $payment->id,
                ]);

                $process = $payment->invoice ? 'invoice' : 'topup';
                $partialId = $payment->invoice?->invoicePartials()->where('payment_id', $payment->id)->value('id');
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
            }

            // Residual 13 fix (W2.1): mirror the D5 guard already used by
            // handleTapCallback/handleKnetResponse -- payments.agent_id is
            // nullable()->nullOnDelete(), so never let an unattributed chain crash this
            // write uncaught; alert and skip it instead of guessing a company_id.
            $unattributedCompanyId = $payment->agent?->branch?->company?->id;

            if ($unattributedCompanyId === null) {
                Log::critical('accounting.payment_unattributed', [
                    'payment_id' => $payment->id,
                    'voucher_number' => $payment->voucher_number,
                    'amount' => $payment->amount,
                    'gateway' => 'MyFatoorah',
                    'reason' => 'payment->agent->branch->company chain unresolved (agent likely deleted/unlinked)',
                ]);
            } else {
                // D6 (W2/mf-error): made idempotent on (payment_id, reference_type) -- see
                // firstOrCreateFailureTransaction()'s own docblock just above this method.
                $failureTransaction = $this->firstOrCreateFailureTransaction(
                    ['payment_id' => $payment->id, 'reference_type' => 'Payment'],
                    [
                        'branch_id' => $payment->agent->branch->id,
                        'company_id' => $unattributedCompanyId,
                        'entity_id' => $unattributedCompanyId,
                        'entity_type' => 'company',
                        'transaction_type' => 'debit',
                        'amount' => $payment->amount,
                        'description' => 'Topup failed by '.$payment->client->full_name,
                        'invoice_id' => $payment->invoice_id,
                        'payment_reference' => $payment->payment_reference,
                        'transaction_date' => now(),
                    ]
                );

                // R-b fix (W2b): wasRecentlyCreated only means "a (payment_id,'Payment') row
                // already existed" -- not "we already notified about THIS failure". A genuine
                // second failure after paymentLinkReinitiate() resets $payment->status to
                // 'initiate' hits this same row (firstOrCreateFailureTransaction() is keyed on
                // payment_id+reference_type only) and must still notify; so must a genuine
                // first-ever failure that happens to land on a soft-deleted row restore()'d by
                // firstOrCreateFailureTransaction(). 'completed' is the only status that means
                // this failure report is stale/redelivered -- a superset of residual 9's own
                // topup-only early return above, but here covering invoice payments too.
                $failureAlreadyRecorded = $payment->status === 'completed';
            }
        }

        $process = $payment->invoice ? 'invoice' : 'topup';
        $partialId = $payment->invoice?->invoicePartials()->where('payment_id', $payment->id)->value('id');
        $receiptInfo = $this->publicReceiptNotice($payment, $process, 'failed', $partialId);

        if ($failureAlreadyRecorded) {
            Log::info('[MYFATOORAH] error callback ignored: failure already recorded for this payment', [
                'payment_id' => $payment->id,
            ]);

            // R-b fix (W2b): keep HEAD's flash on this path too -- payment.link.show
            // (the target for a topup) renders session('error'); the invoice details view
            // does not, so this is a no-op for invoices and restores the message for topups.
            return redirect()->to($receiptInfo['url'])->with('error', 'Payment was not completed or was cancelled.');
        }

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

                // D5: payments.agent_id is nullable()->nullOnDelete(); deleting the Agent
                // NULLs it on every live payment. Unlike the unguarded chain this replaces,
                // never let that turn into an ErrorException swallowed by this method's own
                // outer catch — post nothing and alert instead of guessing a company_id.
                $unattributedCompanyId = $payment->agent?->branch?->company?->id;
                $transaction = null;

                if ($unattributedCompanyId === null) {
                    Log::critical('accounting.payment_unattributed', [
                        'payment_id' => $payment->id,
                        'voucher_number' => $payment->voucher_number,
                        'amount' => $payment->amount,
                        'gateway' => 'Tap',
                        'reason' => 'payment->agent->branch->company chain unresolved (agent likely deleted/unlinked)',
                    ]);
                } else {
                    // D6 (W2/mf-error): made idempotent on (payment_id, reference_type) -- see
                    // firstOrCreateFailureTransaction()'s own docblock, just above
                    // handleMyFatoorahError.
                    $transaction = $this->firstOrCreateFailureTransaction(
                        ['payment_id' => $payment->id, 'reference_type' => 'Payment'],
                        [
                            'branch_id' => $payment->agent->branch->id,
                            'company_id' => $unattributedCompanyId,
                            'entity_id' => $unattributedCompanyId,
                            'entity_type' => 'company',
                            'transaction_type' => 'debit',
                            'amount' => $payment->amount,
                            'description' => 'Tap payment failed for '.$payment->client->full_name,
                            'invoice_id' => $payment->invoice_id,
                            'payment_reference' => $response['id'],
                            'transaction_date' => now(),
                        ]
                    );
                }

                if ($paymentTransaction && $transaction) {
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

            $alreadyCompleted = false;

            DB::transaction(function () use ($payment, $response, $process, $partialId, $paymentTransaction, &$alreadyCompleted) {
                // The status check above is only a fast-path snapshot: Tap can deliver
                // the browser return AND the webhook for the same charge within
                // milliseconds of each other. Re-check under a row lock, inside this
                // transaction, so only one concurrent delivery can ever transition the
                // payment to 'completed' and post money/journal entries; the other
                // becomes a no-op.
                $lockedPayment = Payment::lockForUpdate()->find($payment->id);
                if (! $lockedPayment || $lockedPayment->status === 'completed') {
                    $alreadyCompleted = true;

                    return;
                }

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
                    'customer_reference' => $payment->voucher_number,
                    'payload' => $response,
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

            if ($alreadyCompleted) {
                $payment->refresh();
                $invoice = $payment->invoice;

                if ($invoice && $invoice->status !== 'paid') {
                    $invoice->status = 'paid';
                    $invoice->paid_date = now();
                    $invoice->save();
                }

                Log::info('Tap callback ignored: already completed by a concurrent delivery', ['payment_id' => $paymentId]);
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
            }

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
                            // R-1 fix (W2.2): payment.failed is public/unauthenticated (Tap's
                            // own return URL). $e is whatever WhatsAppHotelController::
                            // hotelBookingDetails() crashed with -- never a PostingException,
                            // so its ->getMessage() must never reach the flash. Full detail
                            // stays in the log; the flash carries only a fixed sentence plus
                            // the payment's own voucher_number as the correlation id.
                            Log::error('Hotel booking API crashed', [
                                'payment_id' => $payment->id,
                                'exception_class' => get_class($e),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);

                            return redirect()->route('payment.failed')->with('error', sprintf(
                                'Booking process failed. Reference: %s. Please contact support.',
                                $payment->voucher_number ?: $payment->id
                            ));
                        }
                    }
                }
            }

            return redirect()->to($receiptInfo['url'])->with('success', 'Payment successful!');
        } catch (PostingException $e) {
            // D4 (W2 orchestrator decision): tap-callback is the user's own browser landing
            // on the gateway's return URL (route('tap.callback'), no separate webhook exists
            // for Tap), so a genuine engine failure must never be downgraded to the generic
            // message below — surface the exception's own class and the idempotency key that
            // makes a retry of this same event safe, and never claim success. The
            // DB::transaction() above has already rolled the payment completion back whole.
            $idempotencyKey = PaymentIdempotencyKey::forGatewayPayment(
                'Tap',
                $payment->id,
                ! empty($partialId) ? [$partialId] : null
            );

            Log::error('Tap callback accounting posting exception', [
                'payment_id' => $payment->id,
                'exception_class' => get_class($e),
                'idempotency_key' => $idempotencyKey,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('payment.failed')->with('error', sprintf(
                'Accounting posting failed (%s). Reference: %s. Payment not completed.',
                class_basename($e),
                $idempotencyKey
            ));
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

                // D5: payments.agent_id is nullable()->nullOnDelete(); deleting the Agent
                // NULLs it on every live payment. Unlike the unguarded chain this replaces,
                // never let that turn into an ErrorException swallowed by this method's own
                // outer catch — post nothing and alert instead of guessing a company_id.
                $unattributedCompanyId = $payment->agent?->branch?->company?->id;

                if ($unattributedCompanyId === null) {
                    Log::critical('accounting.payment_unattributed', [
                        'payment_id' => $payment->id,
                        'voucher_number' => $payment->voucher_number,
                        'amount' => $payment->amount,
                        'gateway' => 'KNET',
                        'reason' => 'payment->agent->branch->company chain unresolved (agent likely deleted/unlinked)',
                    ]);
                } else {
                    // D6 (W2/mf-error): made idempotent on (payment_id, reference_type) -- see
                    // firstOrCreateFailureTransaction()'s own docblock, just above
                    // handleMyFatoorahError.
                    $this->firstOrCreateFailureTransaction(
                        ['payment_id' => $payment->id, 'reference_type' => 'Payment'],
                        [
                            'branch_id' => $payment->agent->branch->id,
                            'company_id' => $unattributedCompanyId,
                            'entity_id' => $unattributedCompanyId,
                            'entity_type' => 'company',
                            'transaction_type' => 'debit',
                            'amount' => $payment->amount,
                            'description' => 'KNET payment failed for '.$payment->client->full_name,
                            'invoice_id' => $payment->invoice_id,
                            'payment_reference' => $responseData['paymentid'] ?? null,
                            'transaction_date' => now(),
                        ]
                    );
                }

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
            $alreadyCompleted = false;

            DB::transaction(function () use ($payment, $responseData, $process, $partialId, &$alreadyCompleted) {
                // The status check above is only a fast-path snapshot: KNET can deliver
                // the browser return and a resend within milliseconds of each other.
                // Re-check under a row lock, inside this transaction, so only one
                // concurrent delivery can ever transition the payment to 'completed'
                // and post money/journal entries; the other becomes a no-op.
                $lockedPayment = Payment::lockForUpdate()->find($payment->id);
                if (! $lockedPayment || $lockedPayment->status === 'completed') {
                    $alreadyCompleted = true;

                    return;
                }

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

            if ($alreadyCompleted) {
                $payment->refresh();
                $invoice = $payment->invoice;

                if ($invoice && $invoice->status !== 'paid') {
                    $invoice->status = 'paid';
                    $invoice->paid_date = now();
                    $invoice->save();
                }

                Log::info('KNET callback ignored: already completed by a concurrent delivery', ['payment_id' => $paymentId]);
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
            }

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
        } catch (PostingException $e) {
            // D4 (W2 orchestrator decision): knet-response is the user's own browser landing
            // on KNET's single responseURL (route('knet.response') — Knet.php builds no
            // separate notification URL), so a genuine engine failure must never be
            // downgraded to the generic message below — surface the exception's own class
            // and the idempotency key that makes a retry of this same event safe, and never
            // claim success. The DB::transaction() above has already rolled the payment
            // completion back whole.
            $idempotencyKey = PaymentIdempotencyKey::forGatewayPayment(
                'KNET',
                $payment->id,
                ! empty($partialId) ? [$partialId] : null
            );

            Log::error('KNET Response accounting posting exception', [
                'payment_id' => $payment->id,
                'exception_class' => get_class($e),
                'idempotency_key' => $idempotencyKey,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('payment.failed')->with('error', sprintf(
                'Accounting posting failed (%s). Reference: %s. Payment not completed.',
                class_basename($e),
                $idempotencyKey
            ));
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

        // Tenant isolation (security fix, WORST of this pass): Payment::find($paymentId) had NO
        // company scoping at all -- any authenticated user could load and mutate ANY company's
        // payment row by guessing an integer id, then overwrite client_id/agent_id/amount
        // straight from request input with no exists: validation and no company check at all.
        // (a) Scope the base row lookup itself to the caller's own company -- mirrors
        // assertSameCompanyOrUnscopedAdmin()'s own "unscoped admin" carve-out, applied at the
        // query level so a cross-company id 404s exactly like a genuinely nonexistent one
        // instead of confirming the row exists via a 403.
        $user = Auth::user();
        $companyId = getCompanyId($user);
        $unscopedAdmin = $user->role_id == Role::ADMIN && ! $companyId;

        $paymentQuery = Payment::query();
        if (! $unscopedAdmin) {
            $paymentQuery->where('company_id', $companyId);
        }
        $payment = $paymentQuery->find($paymentId);

        if (!$payment) {
            return redirect()->back()->with('error', 'Payment not found.');
        }

        // (b) Validate the fields this action overwrites -- previously client_id/agent_id/amount
        // were written straight from request input with no exists: validation whatsoever.
        $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'amount' => 'nullable|numeric|min:0',
        ]);

        if ($clientId = $request->client_id) {
            $client = Client::with('agent.branch.company')->find($clientId);
            if (! $client) {
                return redirect()->back()->with('error', 'Client not found.');
            }

            // (c) The standard tenant assertion: the client being attached must belong to the
            // SAME company as the payment row already scoped above. Mirrors
            // CreditController::creditTopup()'s / multiPaymentMethodProcess()'s identical
            // client-company check.
            $clientCompanyId = $client->agent?->branch?->company?->id;
            abort_unless(
                $clientCompanyId && (int) $clientCompanyId === (int) $payment->company_id,
                403,
                'Unauthorized action.'
            );

            $payment->client_id = $clientId;
        } else {
            $client = $payment->client;
            if (!$client) {
                return redirect()->back()->with('error', 'Client not found.');
            }
        }

        if ($request->agent_id) {
            $agent = Agent::with('branch.company')->find($request->agent_id);
            if (! $agent) {
                return redirect()->back()->with('error', 'Agent not found.');
            }

            // (c) Same tenant assertion for the agent being attached.
            $agentCompanyId = $agent->branch?->company?->id;
            abort_unless(
                $agentCompanyId && (int) $agentCompanyId === (int) $payment->company_id,
                403,
                'Unauthorized action.'
            );

            $payment->agent_id = $request->agent_id;
        }
        if ($request->dial_code) {
            $client->country_code = $request->dial_code;
        }
        if ($request->phone) {
            $client->phone = $request->phone;
        }
        if ($request->amount) {
            $payment->amount = $request->amount;
        }
        if ($request->language) {
            $payment->language = $request->language;
        }

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

        $voucherNumber = $userDefinedField['voucher_number'] ?? null;

        $payment = Payment::where('payment_reference', $invoiceId)
            ->when(!empty($voucherNumber), fn ($query) => $query->orWhere('voucher_number', $voucherNumber))
            ->first();
        if ($payment) {
            Log::info('Found the payment record in the system with ID: ' . $payment->id);
            if ($payment->status === 'initiate') {
                if ($invoiceStatus === 'PAID') {
                    try {
                        // Complete straight from the SIGNED webhook body. MyFatoorah HMAC-signs
                        // the webhook (verified above) and Data.Amount/Transaction carry
                        // everything processMyFatoorahPaymentCompletion needs, so we do NOT
                        // re-call the rate-limited GetPaymentStatus endpoint. That second call
                        // was returning HTTP 429 bursts right after payment, making this handler
                        // 500 and wait for MyFatoorah's ~3-min retries (payer stuck on "NOT PAID"
                        // for 6-13 min, e.g. VOU-2026-04037/04038). GetPaymentStatus stays only as
                        // a fallback if the body ever lacks a usable amount.
                        $amountData = data_get($payload, 'Data.Amount', []);
                        $txnData = data_get($payload, 'Data.Transaction', []);
                        $invoiceValue = $amountData['ValueInBaseCurrency']
                            ?? $amountData['ValueInDisplayCurrency']
                            ?? null;

                        if ($invoiceValue !== null && is_numeric($invoiceValue)) {
                            $statusData = [
                                'InvoiceId' => $invoiceId,
                                'InvoiceStatus' => 'Paid',
                                'InvoiceReference' => data_get($payload, 'Data.Invoice.Reference'),
                                'InvoiceValue' => (float) $invoiceValue,
                                'CustomerName' => data_get($payload, 'Data.Customer.Name'),
                                'CustomerMobile' => data_get($payload, 'Data.Customer.Mobile'),
                                'CustomerEmail' => data_get($payload, 'Data.Customer.Email'),
                                'UserDefinedField' => data_get($payload, 'Data.Invoice.UserDefinedField'),
                                'InvoiceTransactions' => [[
                                    'PaymentId' => $txnData['PaymentId'] ?? null,
                                    'AuthorizationId' => $txnData['AuthorizationId'] ?? null,
                                    'TransactionId' => $txnData['Id'] ?? null,
                                    'PaymentGateway' => $txnData['PaymentMethod'] ?? null,
                                    'TransactionStatus' => $txnData['Status'] ?? null,
                                ]],
                            ];

                            Log::info('MF Webhook: completing from signed webhook body (no GetPaymentStatus call)', [
                                'payment_id' => $payment->id,
                                'invoice_id' => $invoiceId,
                                'invoice_value' => $statusData['InvoiceValue'],
                            ]);
                        } else {
                            // Fallback: signed body lacked a usable amount. Re-verify via
                            // GetPaymentStatus (now retried in-request); 500 only if that also fails.
                            $myfatoorah = new MyFatoorah();
                            $statusResponse = $myfatoorah->getPaymentStatus(type: 'invoice', key: (string) $invoiceId);

                            if (empty($statusResponse['success']) || empty($statusResponse['data'])) {
                                Log::error('MF Webhook: body had no amount and GetPaymentStatus fetch failed, returning 500 so MyFatoorah retries', [
                                    'payment_id' => $payment->id,
                                    'invoice_id' => $invoiceId,
                                    'response_message' => $statusResponse['message'] ?? null,
                                ]);
                                return response()->json(['error' => 'Failed to verify payment status'], 500);
                            }

                            $statusData = $statusResponse['data'];

                            if (strtolower($statusData['InvoiceStatus'] ?? '') !== 'paid') {
                                Log::error('MF Webhook: webhook says PAID but GetPaymentStatus disagrees, returning 500 so MyFatoorah retries', [
                                    'payment_id' => $payment->id,
                                    'invoice_id' => $invoiceId,
                                    'invoice_status' => $statusData['InvoiceStatus'] ?? null,
                                ]);
                                return response()->json(['error' => 'Payment status mismatch'], 500);
                            }
                        }

                        $statusUdf = !empty($statusData['UserDefinedField'])
                            ? (json_decode($statusData['UserDefinedField'], true) ?: [])
                            : [];
                        $process = $statusUdf['process'] ?? $process;
                        $partialId = $statusUdf['invoice_partial_id'] ?? $partialId;

                        $this->processMyFatoorahPaymentCompletion($payment, $statusData, $process, $partialId, true);

                        Log::info('MF Webhook: payment processed successfully', [
                            'payment_id' => $payment->id,
                            'payment_reference' => $invoiceId,
                            'new_status' => $invoiceStatus
                        ]);
                    } catch (PostingException $e) {
                        // D4 (W2 orchestrator decision): this is MyFatoorah's genuine
                        // server-to-server webhook (routes/api.php, POST, HMAC-verified, no
                        // auth middleware) — a genuine engine failure must be let to
                        // propagate so it reaches Laravel's own exception handler as an HTTP
                        // 500, prompting MyFatoorah to retry the same notification; the
                        // idempotency key on the draft this failed to post makes that retry
                        // safe. processMyFatoorahPaymentCompletion()'s own DB::rollBack() has
                        // already rolled the payment completion back whole.
                        Log::critical('accounting.payment_posting_failed_webhook', [
                            'gateway' => 'MyFatoorah',
                            'payment_id' => $payment->id,
                            'exception_class' => get_class($e),
                            'message' => $e->getMessage(),
                        ]);

                        throw $e;
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
     * Used by the callback, the webhook, the reinitiate guard (completeIfAlreadyPaid)
     * and the app:myfatoorah-check-status reconciler to ensure consistent processing.
     * $statusData MUST be the GetPaymentStatus response Data (not a webhook payload).
     */
    public function processMyFatoorahPaymentCompletion($payment, $statusData, $process, $partialId, $sendNotification = false)
    {
        DB::beginTransaction();

        try {
            // Webhook, browser callback and reconciler can all reach here for the
            // same payment within seconds; the loser of that race must exit as a
            // no-op instead of re-writing the row and double-posting money/journal
            // entries. The lockForUpdate read serializes concurrent completions.
            $lockedPayment = Payment::lockForUpdate()->find($payment->id);
            if (! $lockedPayment || $lockedPayment->status === 'completed') {
                DB::commit();
                Log::info('MyFatoorah completion skipped: payment already completed', [
                    'payment_id' => $payment->id,
                ]);

                return;
            }

            $finalPaidAmount = $statusData['InvoiceValue'];
            $transaction = $statusData['InvoiceTransactions'][0] ?? [];

            $payment->status = 'completed';
            $payment->service_charge = $finalPaidAmount - $payment->amount;
            $payment->payment_date = now();
            $payment->invoice_reference = $statusData['InvoiceReference'] ?? null;
            $payment->auth_code = $transaction['AuthorizationId'] ?? $transaction['PaymentId'] ?? null;
            $payment->save();

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
        } catch (PostingException $e) {
            // D4: explicit ahead of the generic catch below for clarity, though today it is
            // functionally a no-op — the generic catch already rolls back and rethrows the
            // exact same exception unconditionally for every case, PostingException
            // included. Kept explicit so a future edit to the generic branch (e.g. adding a
            // non-rethrowing fallback) cannot silently start swallowing engine failures here.
            DB::rollBack();
            Log::error('MyFatoorah payment processing failed', [
                'payment_id' => $payment->id,
                'exception_class' => get_class($e),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
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

    /**
     * Guard against reinitiating (and overwriting the payment_reference of) a MyFatoorah
     * invoice the client has ALREADY PAID. VOU-2026-03954 lost its paid invoice exactly
     * this way: the paid MF invoice id was replaced by a fresh invoice on page view after
     * expiry, orphaning the client's money. If the current payment_reference is already
     * Paid on MyFatoorah's side, run the normal completion flow and return true so the
     * caller can short-circuit to the paid response instead of creating a new MF invoice.
     *
     * Returns false when there is nothing to guard (no reference, not MyFatoorah, not
     * initiate) or when the MyFatoorah status could not be fetched / is not Paid.
     * Completion failures are NOT swallowed — better to error than to overwrite.
     */
    private function completeIfAlreadyPaid($payment): bool
    {
        if (!$payment || empty($payment->payment_reference) || $payment->status !== 'initiate') {
            return false;
        }

        if (strtolower($payment->payment_gateway ?? '') !== 'myfatoorah') {
            return false;
        }

        try {
            $myfatoorah = new MyFatoorah();
            $statusResponse = $myfatoorah->getPaymentStatus(type: 'invoice', key: (string) $payment->payment_reference);
        } catch (\Throwable $e) {
            Log::error('[MYFATOORAH GUARD] Failed to check existing invoice before reinitiate', [
                'payment_id' => $payment->id,
                'payment_reference' => $payment->payment_reference,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        if (empty($statusResponse['success']) || empty($statusResponse['data'])) {
            return false;
        }

        $statusData = $statusResponse['data'];

        if (strtolower($statusData['InvoiceStatus'] ?? '') !== 'paid') {
            return false;
        }

        $statusUdf = !empty($statusData['UserDefinedField'])
            ? (json_decode($statusData['UserDefinedField'], true) ?: [])
            : [];
        $process = $statusUdf['process'] ?? ($payment->invoice ? 'invoice' : 'topup');
        $partialId = $statusUdf['invoice_partial_id'] ?? null;

        Log::warning('[MYFATOORAH GUARD] Existing invoice is already PAID; completing instead of reinitiating', [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'voucher_number' => $payment->voucher_number,
        ]);

        $this->processMyFatoorahPaymentCompletion($payment, $statusData, $process, $partialId, true);

        return true;
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

            $alreadyCompleted = false;

            DB::transaction(function () use ($payment, $process, $totalPaidAmount, $trackId, $statusResponse, $transaction, $partialId, &$alreadyCompleted) {
                // The status check above is only a fast-path snapshot: UPayment can
                // deliver the browser return and a resend within milliseconds of each
                // other. Re-check under a row lock, inside this transaction, so only
                // one concurrent delivery can ever transition the payment to
                // 'completed' and post money/journal entries; the other becomes a no-op.
                $lockedPayment = Payment::lockForUpdate()->find($payment->id);
                if (! $lockedPayment || $lockedPayment->status === 'completed') {
                    $alreadyCompleted = true;

                    return;
                }

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

            if ($alreadyCompleted) {
                $payment->refresh();
                $invoice = $payment->invoice;

                if ($invoice && $invoice->status !== 'paid') {
                    $invoice->status = 'paid';
                    $invoice->paid_date = now();
                    $invoice->save();
                }

                Log::info('[UPAYMENT] Callback ignored: already completed by a concurrent delivery', ['payment_id' => $payment->id]);
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
            }

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
        } catch (PostingException $e) {
            // D4 (W2 orchestrator decision): uPayment-callback is the user's own browser
            // landing on the gateway's returnUrl (route('uPayment.callback')) — the separate
            // notificationUrl (handleUPaymentNoti) is a no-op stub that never reaches
            // createInvoicePaymentCOA — so a genuine engine failure must never be downgraded
            // to the generic message below. The DB::transaction() above has already rolled
            // the payment completion back whole.
            $idempotencyKey = PaymentIdempotencyKey::forGatewayPayment(
                'UPayment',
                $payment->id,
                ! empty($partialId) ? [$partialId] : null
            );

            Log::error('UPayment callback accounting posting exception', [
                'payment_id' => $payment->id,
                'exception_class' => get_class($e),
                'idempotency_key' => $idempotencyKey,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('payment.failed')->with('error', sprintf(
                'Accounting posting failed (%s). Reference: %s. Payment not completed.',
                class_basename($e),
                $idempotencyKey
            ));
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

            // R-c fix (W2b): restored at its TRUE HEAD position -- before the payment lookup
            // and its own "Payment record not found" early return, not after. The W2.2 fix
            // below it (relocated here after the lookup) meant an unresolvable
            // orderReferenceNumber logged nothing, the exact diagnostic-loss class
            // residual-5/residual-14 exist to prevent. $partialId is no longer derived from
            // this value (see the residual-5 fix just below), so only the raw gateway-echoed
            // value is logged here; the derived value is logged separately once $payment and
            // $partialId are both known.
            Log::info('Extracted Hesabe variable2 (partialId):', [
                'raw' => $data['variable2'] ?? null,
            ]);

            $payment = Payment::where('voucher_number', $voucherNumber)->first();
            if (!$payment) {
                Log::info('Payment record not found', ['voucher_number' => $voucherNumber]);
                return redirect()->route('payment.failed')->with('error', 'Payment record not found');
            }

            // Residual 5 fix (W2.1): derive $partialId from the SAME source
            // handleHesabeWebhook() uses — invoice_partials by payment id — instead of
            // the gateway-echoed $data['variable2']. Return-URL and webhook are two
            // deliveries of the same event; deriving from two different sources gave
            // them two different PaymentIdempotencyKey values for it.
            $partialId = $payment->invoice ? $payment->invoice->invoicePartials()->where('payment_id', $payment->id)->value('id') : null;

            Log::info('Hesabe callback: derived partialId from invoice_partials', [
                'payment_id' => $payment->id,
                'partial_id' => $partialId,
            ]);

            if ($payment->status === 'completed') {
                Log::info('Hesabe callback: Payment already processed', [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                ]);
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);
                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
            }

            // The status check above is only a fast-path snapshot: Hesabe can deliver
            // the browser return AND the webhook for the same order within
            // milliseconds of each other. Claim the payment atomically under a row
            // lock so only one concurrent delivery can transition it to 'completed';
            // the other becomes a no-op instead of double-crediting/double-posting.
            $alreadyCompleted = false;

            try {
                DB::transaction(function () use ($payment, $data, $process, $partialId, &$alreadyCompleted) {
                    $lockedPayment = Payment::lockForUpdate()->find($payment->id);
                    if (! $lockedPayment || $lockedPayment->status === 'completed') {
                        $alreadyCompleted = true;

                        return;
                    }

                    $payment->payment_reference = $data['transactionId'];
                    $payment->invoice_reference = $data['trackID'];
                    $payment->payment_date = $data['paidOn'] ?? now();
                    $payment->status = 'completed';
                    $payment->service_charge = $data['amount'] - $payment->amount;
                    $payment->save();

                    // D1 (W2 orchestrator decision): post inside the SAME transaction that
                    // holds Payment::lockForUpdate(), atomic with payment completion. HEAD
                    // instead ran this call in an independent DB::beginTransaction() further
                    // down, started only AFTER this transaction had already committed — so a
                    // COA failure there left the payment 'completed' with zero accounting
                    // rows behind it. Moving the call in here closes that gap.
                    if ($process === 'invoice') {
                        $coaResult = $this->createInvoicePaymentCOA(
                            payment: $payment,
                            finalPaidAmount: (float) $data['amount'],
                            gatewayName: 'Hesabe',
                            partialIds: $partialId ? [$partialId] : null,
                            paymentReference: $data['transactionId'] ?? null
                        );

                        if (! $coaResult['success']) {
                            throw new LegacyInvoiceCoaFailureException($coaResult['message']);
                        }
                    }
                });
            } catch (PostingException $e) {
                // D4: hesabe-callback is the user's own browser landing on the gateway's
                // return URL (route('hesabe.response') — handleHesabeWebhook is the separate
                // server-to-server path), so a genuine engine failure must never be
                // downgraded to a generic message. The DB::transaction() above has already
                // rolled the payment completion back whole.
                $idempotencyKey = PaymentIdempotencyKey::forGatewayPayment(
                    'Hesabe',
                    $payment->id,
                    $partialId ? [$partialId] : null
                );

                Log::error('Hesabe callback accounting posting exception', [
                    'payment_id' => $payment->id,
                    'exception_class' => get_class($e),
                    'idempotency_key' => $idempotencyKey,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return redirect()->route('payment.failed')->with('error', sprintf(
                    'Accounting posting failed (%s). Reference: %s. Payment not completed.',
                    class_basename($e),
                    $idempotencyKey
                ));
            } catch (LegacyInvoiceCoaFailureException $e) {
                // Legacy-path COA failure (createInvoicePaymentCOA's own catch(\Exception)
                // branch, not an engine PostingException) — same message HEAD's own
                // $coaResult['message'] branch surfaced, just from its new location. Narrowed
                // from a bare \RuntimeException catch (residual 4): PDOException/QueryException
                // also extend \RuntimeException, so that wider catch silently turned a transient
                // DB failure into this same terminal "Payment Failed" redirect with raw SQL in
                // the session flash. Those now propagate uncaught (-> HTTP 500) instead.
                // R-1 fix (W2.2): $e->getMessage() here is safe to flash BY CONSTRUCTION, not
                // by luck -- createInvoicePaymentCOA()'s own catch(\Exception) is the ONLY
                // place this exception's message is built, and that catch now always returns
                // a fixed sentence + voucher correlation id, never $e->getMessage() of whatever
                // it caught. See that catch for the full reasoning.
                Log::error('Failed to create journal entry for invoice payment', ['message' => $e->getMessage()]);

                return redirect()->route('payment.failed')->with('error', $e->getMessage());
            }

            if ($alreadyCompleted) {
                Log::info('Hesabe callback ignored: already completed by a concurrent delivery', [
                    'payment_id' => $payment->id,
                ]);
                $payment->refresh();
                $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);

                return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
            }

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
                // Residual 14 fix (W2.1): restores the operator-log line HEAD carried on
                // this path (any alert/runbook grepping it went silent after the D1 move).
                Log::info('Starting to process the invoice for successful callback from Hesabe');

                // D1: the createInvoicePaymentCOA() call that used to live here has moved
                // into the lockForUpdate transaction above (see the comment there) so it
                // posts atomically with the payment completion instead of in this separate,
                // unlocked transaction. Nothing left to do for the invoice case in this block.
                Log::info('Hesabe callback: invoice payment COA already posted atomically with completion');
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

        // Extract webhook data - Hesabe sends unencrypted JSON directly. NONE of
        // this is trusted for the pay/no-pay decision below (see SECURITY block) --
        // it is only used to look up which payment the webhook claims to be about.
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

        // SECURITY (.planning/PLAN-GATEWAY-TENANT-ISOLATION-2026-09-02.md §2, Hesabe
        // section): this endpoint is unauthenticated and Hesabe's webhook body is
        // unsigned, so nothing in it (reference_number/status/status_code/amount) can
        // drive a write. Voucher numbers are per-company sequential and collide across
        // tenants, so a bare `voucher_number` lookup is ambiguous. Resolve the paying
        // company FIRST from data we control, then confirm the transaction with
        // Hesabe's own transaction-enquiry endpoint (GET /api/transaction/{token})
        // using that company's credentials before anything is written.
        $companyId = $this->resolveHesabeWebhookCompanyId($voucherNumber, $paymentToken);

        if (!$companyId) {
            Log::error('Hesabe webhook: could not resolve a single owning company for this voucher', [
                'voucher_number' => $voucherNumber,
                'payment_token' => $paymentToken,
            ]);
            return response()->json(['error' => 'Payment not found'], 404);
        }

        $enquiry = $this->hesabeWebhookEnquiry($companyId, $paymentToken, $voucherNumber);

        if (!$enquiry) {
            Log::error('Hesabe webhook: transaction enquiry failed or returned no data, refusing to write', [
                'company_id' => $companyId,
                'voucher_number' => $voucherNumber,
                'payment_token' => $paymentToken,
            ]);
            // 200 so Hesabe does not endlessly retry an enquiry that will never
            // succeed (e.g. a forged token); nothing has been written.
            return response()->json(['message' => 'Unable to verify transaction'], 200);
        }

        DB::beginTransaction();
        try {
            // lockForUpdate() serializes this against any other delivery (retry,
            // and against handleHesabeResponse's own claim) racing on the same
            // payment row, so the "already processed" check right below is
            // authoritative rather than a TOCTOU snapshot. Scoped to the company
            // resolved above -- never a bare voucher_number lookup.
            $payment = Payment::where('company_id', $companyId)
                ->where('voucher_number', $voucherNumber)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                Log::error('Hesabe webhook: Payment record not found for resolved company', [
                    'voucher_number' => $voucherNumber,
                    'company_id' => $companyId,
                ]);
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

            // Assert the enquiry response actually describes THIS payment. Any
            // mismatch means the webhook body cannot be trusted to drive a write:
            // log both sides and stop here (nothing written, no status change).
            $enquiryRef = trim((string) ($enquiry['reference_number'] ?? ''));
            $enquiryToken = trim((string) ($enquiry['token'] ?? ''));
            $enquiryAmountRaw = $enquiry['amount'] ?? null;
            $enquiryAmount = is_numeric($enquiryAmountRaw) ? (float) $enquiryAmountRaw : null;
            $enquiryStatus = strtoupper(trim((string) ($enquiry['status'] ?? '')));

            $refMatches = $enquiryRef !== '' && $enquiryRef === (string) $voucherNumber;
            $tokenMatches = !$paymentToken || ($enquiryToken !== '' && $enquiryToken === (string) $paymentToken);

            if (!$refMatches || !$tokenMatches) {
                Log::error('Hesabe webhook: enquiry identity does not match the resolved payment, refusing to write', [
                    'payment_id' => $payment->id,
                    'voucher_number' => $voucherNumber,
                    'payment_token' => $paymentToken,
                    'enquiry_reference_number' => $enquiryRef,
                    'enquiry_token' => $enquiryToken,
                ]);
                DB::rollback();
                return response()->json(['message' => 'Verification failed'], 200);
            }

            // Determine process type from payment record
            $process = $payment->invoice ? 'invoice' : 'topup';
            $partialId = $payment->invoice ? $payment->invoice->invoicePartials()->where('payment_id', $payment->id)->value('id') : null;

            Log::info('Hesabe webhook: Processing payment', [
                'payment_id' => $payment->id,
                'process' => $process,
                'partial_id' => $partialId,
            ]);

            $amountMatches = $enquiryAmount !== null && abs($enquiryAmount - (float) $payment->amount) <= 0.001;
            $enquiryIsSuccess = in_array($enquiryStatus, ['SUCCESSFUL', 'SUCCESS', 'CAPTURED', 'ACCEPT'], true);

            // Check if payment was successful -- per Hesabe's own enquiry response,
            // never per the request body's status/status_code/amount.
            if ($enquiryIsSuccess && $amountMatches) {

                // Source of truth for this transaction is the enquiry call already
                // made above (hesabeWebhookEnquiry) -- do not re-fetch from Hesabe
                // using unscoped/global credentials as the previous implementation did.
                $paymentStatusData = $enquiry;
                $fullPaymentResponse = ['status' => true, 'data' => $enquiry];
                $paymentTransaction = null;

                if ($paymentToken) {
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
                        finalPaidAmount: $enquiryAmount,
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
            } elseif ($enquiryIsSuccess && !$amountMatches) {
                // Enquiry confirms the transaction is genuinely SUCCESSFUL at Hesabe,
                // but the confirmed amount does not match what this payment is for.
                // This does not happen on a legitimate webhook -- refuse to write
                // anything (do not mark paid, do not mark failed) and stop retries.
                Log::error('Hesabe webhook: enquiry amount does not match payment amount, refusing to write', [
                    'payment_id' => $payment->id,
                    'voucher_number' => $voucherNumber,
                    'payment_amount' => $payment->amount,
                    'enquiry_amount' => $enquiryAmount,
                ]);
                DB::rollback();
                return response()->json(['message' => 'Verification failed'], 200);
            } else {
                // Payment failed -- per Hesabe's own enquiry response, not the
                // unsigned/unverified request body.
                $paymentStatusData = $enquiry;
                $fullPaymentResponse = ['status' => true, 'data' => $enquiry];

                Log::error('Hesabe webhook: Payment failed', [
                    'status' => $status,
                    'status_code' => $statusCode,
                    'enquiry_status' => $enquiryStatus,
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
        } catch (PostingException $e) {
            // D4 (W2 orchestrator decision): this is Hesabe's genuine server-to-server
            // webhook (route registered in routes/api.php, POST, no auth middleware) — a
            // genuine engine failure must be let to propagate so it reaches Laravel's own
            // exception handler as an HTTP 500, prompting Hesabe to retry the same
            // notification; the idempotency key on the draft this failed to post makes that
            // retry safe. Never downgrade it to the generic 500 below.
            DB::rollback();
            Log::critical('accounting.payment_posting_failed_webhook', [
                'gateway' => 'Hesabe',
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw $e;
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
     * Resolve the single company that owns the Hesabe payment a webhook claims to
     * be about, from data this application controls -- never from the request
     * body. Voucher numbers are per-company sequential and can collide across
     * tenants, so a bare `voucher_number` match against >1 company is treated as
     * unresolvable rather than guessed.
     *
     * @return int|null Company id, or null if it cannot be resolved unambiguously.
     */
    private function resolveHesabeWebhookCompanyId(string $voucherNumber, ?string $paymentToken): ?int
    {
        $candidates = Payment::where('voucher_number', $voucherNumber)->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $companyIdFor = function (Payment $payment): ?int {
            return $payment->company_id
                ?? optional(optional($payment->agent)->branch)->company_id;
        };

        if ($candidates->count() === 1) {
            return $companyIdFor($candidates->first());
        }

        // Voucher collision across companies: this is exactly the scenario the
        // request body cannot be trusted to resolve. Disambiguate using Hesabe's
        // own transaction-enquiry response (matched by amount) instead of guessing.
        Log::warning('Hesabe webhook: voucher number collision across companies', [
            'voucher_number' => $voucherNumber,
            'candidate_payment_ids' => $candidates->pluck('id'),
        ]);

        // The enquiry credentials that matter for this GET call (accessCode,
        // base_url) are structurally global in this system today (see plan doc
        // §0.2) -- api_key/secret is the only per-company field and is not used
        // here, so an unscoped probe cannot leak or misuse another company's
        // secret. This probe is only used to pick which candidate to trust; the
        // real, authoritative enquiry is re-run scoped to the resolved company.
        $probe = $this->hesabeWebhookEnquiry(null, $paymentToken, $voucherNumber);

        if (!$probe || !isset($probe['amount']) || !is_numeric($probe['amount'])) {
            return null;
        }

        $probeAmount = (float) $probe['amount'];
        $matches = $candidates->filter(
            fn (Payment $candidate) => abs((float) $candidate->amount - $probeAmount) <= 0.001
        );

        if ($matches->count() !== 1) {
            Log::error('Hesabe webhook: voucher collision could not be resolved to a single payment', [
                'voucher_number' => $voucherNumber,
                'probe_amount' => $probeAmount,
                'match_count' => $matches->count(),
            ]);
            return null;
        }

        return $companyIdFor($matches->first());
    }

    /**
     * Confirm a Hesabe transaction server-side via GET /api/transaction/{token}
     * (or by orderReferenceNumber when no token was supplied), using the given
     * company's Hesabe credentials. Returns the `data` object from a successful,
     * confirmed response, or null if the transaction could not be confirmed.
     *
     * @return array<string,mixed>|null
     */
    private function hesabeWebhookEnquiry(?int $companyId, ?string $paymentToken, string $voucherNumber): ?array
    {
        try {
            $hesabe = new Hesabe($companyId);
        } catch (\Throwable $e) {
            Log::error('Hesabe webhook: unable to build Hesabe client for enquiry', [
                'company_id' => $companyId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }

        try {
            if ($paymentToken) {
                $response = $hesabe->getPaymentStatus($paymentToken);
                $body = $response instanceof \Illuminate\Http\Client\Response ? $response->json() : $response;
            } else {
                $body = $hesabe->getTransaction($voucherNumber);
            }
        } catch (\Throwable $e) {
            Log::error('Hesabe webhook: enquiry HTTP call failed', [
                'company_id' => $companyId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }

        if (!is_array($body) || !($body['status'] ?? false) || !isset($body['data']) || !is_array($body['data'])) {
            Log::warning('Hesabe webhook: enquiry returned no confirmed transaction', [
                'company_id' => $companyId,
                'body' => $body,
            ]);
            return null;
        }

        return $body['data'];
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

                // R3 seam cutover (KEY: coa-seam / B1). $accountingFee/$paidBy/$netAmount are
                // computed here, in this shared pre-branch section, because BOTH the legacy
                // closure below and the ON-path engine draft need the identical fee/net-amount
                // arithmetic — and ChargeService::calculate() has no throw path at all (a
                // missing/zero charge config simply returns a zero-fee result; see that
                // method's own "no charge configuration found" branch), so computing it here
                // instead of after the account checks changes no observable success/failure
                // outcome versus HEAD.
                //
                // What HEAD ran at this exact point instead — `Charge::where('name','LIKE',...)`,
                // `Account::find($chargeRecord->acc_fee_bank_id/acc_fee_id)`, and HF-6's
                // `Account::where('name','Clients')->where('company_id',...)` — is LEGACY-ONLY
                // account resolution by name/FK, exactly what the ON path must never do
                // (ACCOUNT RESOLUTION on ON paths: AccountResolver / purpose codes only). All
                // three lookups, and the "Charge record not found" / "One or more required
                // financial accounts not found" guards that depend on them, have moved
                // verbatim into $legacy below — including HF-6's own line, untouched — so they
                // run ONLY when the legacy closure actually executes, never as a side effect of
                // building the ON-path draft. Reordering them after this arithmetic can't
                // change which exception fires or the final rolled-back state on failure either
                // way (this whole method body runs inside ONE DB::transaction — any exception
                // anywhere rolls every prior write in this closure back to nothing), for the
                // same "no throw path here" reason as above.
                $chargeResult = ChargeService::calculate($payment->amount, $companyId, $payment->payment_method_id, $gatewayName);
                $accountingFee = $chargeResult['accountingFee'] ?? 0;
                // W4.D fix round 2: this read the wrong key ('paidBy', camelCase — never present in
                // ChargeService::calculate()'s return array, which only has 'paid_by', snake_case;
                // see that method's own return statement, and this file's OWN correct read of the
                // same key at line ~3174) and so ALWAYS fell through to the 'Company' default
                // regardless of the gateway's actual configured bearer. That silently broke the
                // $feeDescription label below (always "Company Pays Gateway Fee", even when the
                // client was configured to bear it) and would have made the new
                // createGatewayFeeRecoveryEntries() gross-up call below equally dead — gated on
                // $paidBy === 'Client', which this bug made unreachable. Corrected to the real key.
                $paidBy = $chargeResult['paid_by'] ?? 'Company';
                $markupProfit = (float) ($chargeResult['markup_profit'] ?? 0);
                $roundingProfit = (float) ($chargeResult['rounding_profit'] ?? 0);

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

                // The invoiceDetail/client existence check HEAD ran AFTER Transaction::create()
                // runs here instead, in this shared pre-branch section — the ON-path draft
                // needs this same data (invoiceDetailId, the client's own id/name) to build its
                // lines, and there is no way to know "does this document even have a valid
                // invoice detail/client" without resolving it before deciding which path to
                // take. DOCUMENTED DEVIATION: on the OFF path this means a failing check here
                // no longer happens after Transaction::create() has already run (and would have
                // been rolled back) — the only observable difference is that HEAD's ordering
                // would have consumed one MySQL AUTO_INCREMENT value on `transactions.id`
                // before rolling back (InnoDB auto_increment counters are NOT transactional),
                // which this ordering does not. The return value, the exception message, and
                // the final persisted state (nothing — full rollback either way) are identical.
                $invoiceDetail = InvoiceDetail::where('invoice_number', $invoice->invoice_number)->first();
                $client = $invoice->client;

                if (!$invoiceDetail || !$client) {
                    throw new \Exception('Invoice detail or client not found');
                }

                // ── Legacy closure: VERBATIM HEAD behaviour — the Charge/Account lookups moved
                // in from the shared section above (per the comment there) are the ONLY
                // structural change; every value, order, and failure this closure can produce
                // is otherwise byte-identical to HEAD, including HF-6's own tenant-scoped
                // "Clients" lookup below, untouched. ─────────────────────────────────────────
                $legacy = function () use (
                    $payment, $invoice, $companyId, $gatewayName, $finalPaidAmount, $paymentReference,
                    $invoiceDetail, $client, $accountingFee, $paidBy, $netAmount
                ) {
                    $chargeRecord = Charge::where('name', 'LIKE', "%{$gatewayName}%")
                        ->where('company_id', $companyId)
                        ->first();

                    if (! $chargeRecord) {
                        throw new \Exception("Charge record not found for gateway: {$gatewayName}");
                    }

                    $gatewayAssetAccount = Account::find($chargeRecord->acc_fee_bank_id);
                    $gatewayExpenseAccount = Account::find($chargeRecord->acc_fee_id);
                    // Company-scoped: this runs from unauthenticated gateway webhook/
                    // callback paths (BelongsToCompany's global scope is a no-op there),
                    // so an unscoped lookup would resolve whichever company's "Clients"
                    // account has the lowest id and post another tenant's receivable.
                    $receivableAccount = Account::where('name', 'Clients')->where('company_id', $companyId)->first(); // HF-2: tenant-scoped lookup (2026-08-27)

                    if (! $gatewayAssetAccount || ! $gatewayExpenseAccount || ! $receivableAccount) {
                        if (! $receivableAccount) {
                            // HF-2: tenant-scoped lookup (2026-08-27) — never fall back to an
                            // unscoped query and never auto-create the account; log the exact
                            // (company, name, feeder) so support can fix the company's chart
                            // of accounts, then fail the same way as any other missing account.
                            Log::error('accounting.legacy_account_unresolved', [ // HF-2: tenant-scoped lookup (2026-08-27)
                                'company_id' => $companyId,
                                'name' => 'Clients',
                                'feeder' => $gatewayName,
                            ]);
                        }

                        throw new \Exception('One or more required financial accounts not found');
                    }

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

                    // Ledger-derived balances (accounts.actual_balance is a hand-maintained
                    // decimal(10,2) column that has drifted from the journal entries — up to
                    // 41.5% of accounts disagree with the ledger, and the column truncates
                    // 3-decimal-place fils amounts it can't even represent). These reads
                    // happen before their respective JournalEntry rows below are inserted, so
                    // they reflect the balance *prior to* this transaction, matching the
                    // original actual_balance reads they replace.
                    $trialBalanceService = app(TrialBalanceService::class);
                    $gatewayAssetLedgerBalance = $trialBalanceService->getCurrentAccountBalance($companyId, $gatewayAssetAccount->id);

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
                        'balance' => $gatewayAssetLedgerBalance + $netAmount,
                        'name' => $gatewayAssetAccount->name,
                        'type' => 'bank',
                        'voucher_number' => $payment->voucher_number,
                        'type_reference_id' => $gatewayAssetAccount->id,
                    ]);

                    // Legacy actual_balance write kept in place — W1 (cutting this feeder over
                    // to PostingService, which does not maintain actual_balance) has not
                    // happened yet, so this column must keep working for any code that still
                    // reads it until that cutover ships.
                    $gatewayAssetAccount->actual_balance += $netAmount;
                    $gatewayAssetAccount->save();

                    $feeDescription = ($paidBy === 'Company' ? 'Company Pays Gateway Fee: ' : 'Client Pays Gateway Fee: ').$gatewayExpenseAccount->name;

                    $gatewayExpenseLedgerBalance = $trialBalanceService->getCurrentAccountBalance($companyId, $gatewayExpenseAccount->id);

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
                        'balance' => $gatewayExpenseLedgerBalance + $accountingFee,
                        'name' => $gatewayExpenseAccount->name,
                        'type' => 'charges',
                        'voucher_number' => $payment->voucher_number,
                        'type_reference_id' => $gatewayExpenseAccount->id,
                    ]);

                    // Legacy actual_balance write kept in place — same reasoning as above.
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

                    return $transaction;
                };

                // ── Engine draft (ON path): a BALANCED document — Dr GATEWAY_CLEARING_{gateway}
                // (+ Dr GATEWAY_FEE_EXPENSE_{gateway} when the gateway actually charges a fee —
                // PostingService rejects a zero-amount line, and ChargeService::calculate() can
                // genuinely return accountingFee = 0 for a company with no fee configured for
                // this gateway, so that third leg is OMITTED rather than posted at 0, keeping
                // the document a real balanced 2-line receipt in that case) / Cr
                // RECEIVABLE_CONTROL (this document always settles a real invoice —
                // $invoice->id is always set here — so, matching the CheckMyFatoorahPayments
                // precedent's own invoice_id-based policy split, this is always
                // RECEIVABLE_CONTROL, never CLIENT_ADVANCE). GATEWAY_CLEARING_{gateway} is
                // already seeded by SystemAccountsSeeder::resolveGatewayClearing() for every
                // gateway config('accounting.purpose_codes.gateways') lists — no missing code
                // there. GATEWAY_FEE_EXPENSE_{gateway} is a NEW purpose code this lane adds
                // (PROPOSED — see report), mapped by the new
                // SystemAccountsSeeder::resolveGatewayFeeExpense() to the same per-gateway
                // "<Gateway> Charges" leaves under "Payment Gateway Charges" (Expenses) that
                // CoaSeeder already seeds for MyFatoorah/Tap/Hesabe (a company with no
                // per-gateway split there, e.g. Knet/uPayment today, reports a gap instead of
                // guessing — same conservative philosophy as every other SystemAccountsSeeder
                // resolver; an UnmappedPurposeException there is a genuine engine-path failure,
                // propagated per the catch(PostingException) below, never silently skipped).
                //
                // type_reference_id (LineDraft::$partyAccountRef): the legacy receivable
                // JournalEntry above writes `type_reference_id => $receivableAccount->id` — the
                // ACCOUNT's own id, not a party id — which never matched the client/supplier/
                // agent id shape AccountingController::filterLedgers()'s per-client filter
                // actually filters on (the exact same dead/wrong value the W1.1 MyFatoorah
                // cutover already documented and chose not to reproduce, for the same reason —
                // see CheckMyFatoorahPayments's own engine-draft comment). Not reproduced here
                // either: partyAccountRef below is the client's REAL id, so the per-client
                // ledger filter actually finds this line once the engine is ON.
                $gatewayKey = strtoupper($gatewayName);

                $lines = [
                    new LineDraft(
                        purposeCode: 'RECEIVABLE_CONTROL',
                        accountId: null,
                        side: 'credit',
                        amount: (float) $finalPaidAmount,
                        currency: config('accounting.engine.base_currency'),
                        originalAmount: (float) $finalPaidAmount,
                        exchangeRate: 1.0,
                        transactionType: 'CUSTOMERCREDITED',
                        partyAccountRef: $client->id,
                        description: "Client payment received via {$gatewayName}",
                        invoiceId: $invoice->id,
                        invoiceDetailId: $invoiceDetail->id,
                        ledgerType: 'receivable',
                        partyName: $client->full_name,
                        voucherNumber: $payment->voucher_number,
                    ),
                    new LineDraft(
                        purposeCode: "GATEWAY_CLEARING_{$gatewayKey}",
                        accountId: null,
                        side: 'debit',
                        amount: (float) $netAmount,
                        currency: config('accounting.engine.base_currency'),
                        originalAmount: (float) $netAmount,
                        exchangeRate: 1.0,
                        transactionType: 'GATEWAYDEBITED',
                        description: 'Net payment received',
                        invoiceId: $invoice->id,
                        invoiceDetailId: $invoiceDetail->id,
                        ledgerType: 'bank',
                        voucherNumber: $payment->voucher_number,
                    ),
                ];

                if ($accountingFee > 0.0) {
                    $lines[] = new LineDraft(
                        purposeCode: "GATEWAY_FEE_EXPENSE_{$gatewayKey}",
                        accountId: null,
                        side: 'debit',
                        amount: $accountingFee,
                        currency: config('accounting.engine.base_currency'),
                        originalAmount: $accountingFee,
                        exchangeRate: 1.0,
                        transactionType: 'CCCHARGES',
                        // Deliberately does NOT read $gatewayExpenseAccount->name — that
                        // account is resolved by the legacy closure above, from
                        // Charge::acc_fee_id, an ad hoc FK lookup the ON path must never
                        // depend on (see this method's shared-section comment above). Same
                        // paidBy semantics as legacy's $feeDescription, a synthetic
                        // gateway-name suffix instead of the legacy-resolved account's own
                        // name.
                        description: ($paidBy === 'Company' ? 'Company Pays Gateway Fee: ' : 'Client Pays Gateway Fee: ').$gatewayName,
                        invoiceId: $invoice->id,
                        invoiceDetailId: $invoiceDetail->id,
                        ledgerType: 'charges',
                        voucherNumber: $payment->voucher_number,
                    );
                }

                $idempotencyKey = PaymentIdempotencyKey::forGatewayPayment($gatewayName, $payment->id, $partialIds);

                $draft = new DocumentDraft(
                    companyId: $companyId,
                    branchId: $invoice->agent->branch->id,
                    docType: 'RV', // Receipt Voucher — money received from the client via gateway
                    subType: $gatewayKey,
                    docDate: now(),
                    narration: "{$gatewayName} payment success: {$invoice->invoice_number}",
                    lines: $lines,
                    idempotencyKey: $idempotencyKey,
                    sourceType: 'Receipt',
                    sourceId: $payment->id,
                    invoiceId: $invoice->id,
                    userId: null, // gateway webhook/callback — no authenticated actor; never Auth::user()
                    paymentReference: $paymentReference ?? $payment->payment_reference,
                    // D3 (W2 orchestrator decision): payment_id is carried ONLY by the
                    // original receipt document — PostingService::repost()/reverse() keep it
                    // off any replacement/reversal header, so the (payment_id, reference_type)
                    // unique index never collides on a correction of this document.
                    paymentId: $payment->id,
                );

                $result = app(PostingSeam::class)->post($draft, $legacy, 'payment.invoice_coa');

                // PostingSeam::post() returns: the legacy closure's own return value (a
                // Transaction model) on the OFF path; a PostedDocument on the ON path; or a
                // bare `null` — ONLY on the OFF path, and only when the engine had already
                // posted this exact (company_id, idempotency_key) pair before a kill-switch
                // flip (see PostingSeam::post()'s own docblock, W1.1 FIX ROUND / S1) — every
                // feeder must tolerate it. This method's own return shape
                // (['success','message','transaction_id']) is unchanged for every one of this
                // method's nine call sites, so the null case is resolved to the SAME
                // already-posted transaction's id here, rather than leaking the null upward.
                if ($result === null) {
                    // Residual 15 fix (W2.1): withoutGlobalScopes() also lifts Transaction's
                    // own SoftDeletes scope, so without this the resolution could hand back a
                    // soft-deleted row's id as "the already-posted transaction" for this key.
                    $alreadyPosted = Transaction::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('idempotency_key', $idempotencyKey)
                        ->whereNull('deleted_at')
                        ->first(['id']);

                    $transactionId = $alreadyPosted?->id;
                } elseif ($result instanceof \App\Services\Accounting\PostedDocument) {
                    $transactionId = $result->transaction->id;
                } else {
                    // Legacy path: $legacy() returned the Transaction model it created.
                    $transactionId = $result->id;
                }

                // W4.D fix round 2: gross up the client-borne gateway fee HERE — the one place
                // $paidBy is known from the payment's REAL PaymentMethod/Charge configuration
                // (ChargeService::calculate() above), dated THIS payment. See
                // InvoiceController::createGatewayFeeRecoveryEntries()'s own docblock for the full
                // rationale (Accounting Gap/22-plan-amendments.md rev 3 §4.1 gateway_fee row,
                // ruling B10 — the fee "cannot post with the invoice"). The method itself is a
                // no-op when $paidBy !== 'Client' or the gross-up amount is <= 0, matching every
                // other feeder's "the method decides whether there's anything to post" convention.
                $invoiceController = app(InvoiceController::class);
                $invoiceController->createGatewayFeeRecoveryEntries(
                    payment: $payment,
                    invoice: $invoice,
                    companyId: $companyId,
                    branchId: $invoice->agent->branch->id,
                    gatewayName: $gatewayName,
                    paidBy: $paidBy,
                    accountingFee: $accountingFee,
                    markupProfit: $markupProfit,
                    roundingProfit: $roundingProfit,
                    postingDate: now(),
                    invoiceDetail: $invoiceDetail,
                    partialIds: $partialIds,
                    paymentReference: $paymentReference ?? $payment->payment_reference,
                );

                // Recalculate profit after each payment (deduct gateway fees progressively)
                $invoiceController->recalculateInvoiceCOA($invoice);

                return [
                    'success' => true,
                    'message' => 'Invoice payment COA created successfully',
                    'transaction_id' => $transactionId,
                ];
            });
        } catch (PostingException $e) {
            // D4 (W2 orchestrator decision): a genuine engine correctness failure must never be
            // downgraded to this method's own generic ['success' => false] shape — PostingSeam
            // has already Log::critical'd it with its concrete class. Propagate untouched so
            // the caller (one of this method's nine call sites) can surface the exception's own
            // class and the idempotency key, never a generic string.
            throw $e;
        } catch (\Exception $e) {
            // R-1 fix (W2.2): this catch is the ONLY place a legacy-path COA failure's
            // message is born. $coaResult['message'] is what every one of this method's
            // nine call sites (several of which throw it straight into a public,
            // unauthenticated redirect -- e.g. handleHesabeResponse's
            // LegacyInvoiceCoaFailureException) can end up flashing verbatim to a
            // customer's browser via payment.failed. $e can be ANY \Exception the legacy
            // closure raised, including a QueryException whose ->getMessage() is the raw
            // SQL statement plus every bound value. Never let that reach the caller: keep
            // the full exception (class, message, trace) in Log::error only, and hand back
            // a fixed, safe sentence plus a correlation id support can grep this same log
            // entry by. voucher_number is the customer-facing reference already printed on
            // receipts; fall back to the internal id on the rare row that has none.
            Log::error('[INVOICE COA] Failed to create COA entries', [
                'payment_id' => $payment->id,
                'gateway' => $gatewayName,
                'exception_class' => get_class($e),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => sprintf(
                    'Error creating COA. Reference: %s.',
                    $payment->voucher_number ?: $payment->id
                ),
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
        try {
            $responseData = (new Hesabe())->getTransaction($orderRef);
        } catch (\Exception $e) {
            Log::error('Import Hesabe Transaction error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to call Hesabe Transaction Enquiry: ' . $e->getMessage(),
            ]);
        }

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

        $transactionStatus = $responseData['data']['status'] ?? null;

        if ($transactionStatus !== 'SUCCESSFUL') {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction is not paid (status: ' . ($transactionStatus ?? 'unknown') . ')'
            ], 400);
        }

        $transactionId = $responseData['data']['TransactionID'] ?? null;
        $trackId = $responseData['data']['TrackID'] ?? null;

        if (Payment::where('voucher_number', $referenceNumber)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'A payment with this Order Reference Number has already been imported.'
            ], 400);
        }

        if (Payment::where('payment_reference', $transactionId)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'A payment with this Transaction ID has already been imported.'
            ], 400);
        }

        if (Payment::where('invoice_reference', $trackId)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'A payment with this Track ID has already been imported.'
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Transaction status fetched successfully',
            'data' => $responseData['data'],
            'amount' => $responseData['data']['amount'],
            'payment_reference' => $transactionId,
            'transaction_status' => $transactionStatus,
            'invoice_reference' => $trackId,
            'customer_name' => $responseData['data']['customerName'] ?? null,
            'created_date' => $responseData['data']['datetime'],
            'payment_gateway' => 'Hesabe',
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
            // M1 null guard (residual 13, W2.1): payments.agent_id is
            // nullable()->nullOnDelete() (see the D5 guard's own comment in
            // handleTapCallback/handleKnetResponse), and this method runs unconditionally,
            // BEFORE either D5 guard, on every success and failure path that shares it.
            // An unattributed chain used to crash on the bare deref below -- swallowed
            // generically by the caller's own outer catch(Throwable) -- so
            // accounting.payment_unattributed never fired for the case it was built for.
            $companyId = $payment->agent?->branch?->company_id;

            if ($companyId === null) {
                Log::critical('accounting.payment_unattributed', [
                    'payment_id' => $payment->id,
                    'voucher_number' => $payment->voucher_number,
                    'amount' => $payment->amount,
                    'reason' => 'payment->agent->branch->company_id chain unresolved (agent likely deleted/unlinked)',
                ]);

                throw new PaymentUnattributedException($payment->id);
            }

            $route = $isInvoice
                ? [
                    'name' => 'invoice.show',
                    'params' => [
                        'companyId' => $companyId,
                        'invoiceNumber' => $payment->invoice->invoice_number,
                    ],
                ]
                : [
                    'name' => 'payment.link.show',
                    'params' => [
                        'companyId' => $companyId,
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

        $agent = Agent::with('branch.company')->find($request->input('agent_id'));
        $client = Client::with('agent.branch.company')->find($request->input('client_id'));

        $company = $agent->branch->company;

        if (!$company) {
            Log::error('[MULTI PAYMENT METHOD] Company not found for agent ID: ' . $agent->id);
            return [
                'success' => false,
                'message' => 'Company not found for the specified agent'
            ];
        }

        // Tenant isolation (security fix, W41): same shape as CreditController::creditTopup()'s
        // W40 fix -- 'exists:clients,id' / 'exists:agents,id' only prove the ids are *real* rows,
        // not that they belong to the caller's own company, and the company_id written onto the
        // resulting Payment row (below) is taken from $agent->branch->company->id, i.e. from the
        // ATTACKER-SUPPLIED agent. Without this check, any authenticated user could post a
        // client_id/agent_id pair belonging to a different company and inject a real Payment row
        // into that company's books. Resolve the client's company via its agent -> branch ->
        // company chain (not the denormalized/nullable clients.company_id column), require the
        // client to resolve to the SAME company as the agent, then require the caller to match
        // that company too (or be an unscoped admin) -- see assertSameCompanyOrUnscopedAdmin()
        // below, mirrored from CreditController/ReceiptVoucherController/BankPaymentController.
        $user = Auth::user();
        $clientCompanyId = $client->agent?->branch?->company?->id;
        $agentCompanyId = $company->id;

        abort_unless(
            $clientCompanyId && (int) $clientCompanyId === (int) $agentCompanyId,
            403,
            'Unauthorized action.'
        );

        $this->assertSameCompanyOrUnscopedAdmin($user, (int) $agentCompanyId);

        $voucherNumber = $this->nextVoucherNumber($company->id);

        $response = DB::transaction(function () use (
            $request,
            $voucherNumber,
            $company,
            $client,
            $agent,
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
                    'company_id' => $company->id,
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

                Log::info('[MULTI PAYMENT METHOD] Payment created with voucher number: '.$voucherNumber, [
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

        // If this payment's current MyFatoorah invoice was already paid (missed webhook),
        // complete it and short-circuit — never reinitiate over a paid invoice.
        if ($payment && $payment->status === 'initiate' && $this->completeIfAlreadyPaid($payment)) {
            $process = $payment->invoice ? 'invoice' : 'topup';
            $partialId = $payment->invoice?->invoicePartials()->where('payment_id', $payment->id)->value('id');
            $receiptInfo = $this->publicReceiptNotice($payment, $process, 'success', $partialId);
            return redirect()->to($receiptInfo['url'])->with('success', 'Payment already completed.');
        }

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
                        ->whereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(middle_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("CONCAT(COALESCE(country_code, ''), COALESCE(phone, '')) LIKE ?", ["%{$search}%"])))
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
                        ->whereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(middle_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("CONCAT(COALESCE(country_code, ''), COALESCE(phone, '')) LIKE ?", ["%{$search}%"])))
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

    public function exportPaymentLinks(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $agents = Agent::with('branch')->whereHas('branch', fn ($q) => $q->where('company_id', $companyId))->get();
            } else {
                $agents = Agent::with('branch')->get();
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
            return redirect()->back()->with('error', 'You are not authorized to export payment links.');
        }

        $agentsId = $agents->pluck('id')->toArray();

        $query = Payment::with([
            'invoice', 'client', 'agent.branch', 'createdBy',
            'paymentMethod', 'myFatoorahPayment',
        ])->where(function ($query) use ($agentsId) {
            $query->whereHas('invoice', function ($payment) use ($agentsId) {
                $payment->whereIn('agent_id', $agentsId);
            })->orWhereIn('agent_id', $agentsId);
        })->where(fn ($q) => $q->where('is_imported', false)->orWhereNull('is_imported'));

        $query->when($request->input('client_id'), fn ($q, $v) => $q->where('client_id', $v));
        $query->when($request->input('agent_id'), fn ($q, $v) => $q->where('agent_id', $v));
        $query->when($request->input('payment_method_id'), fn ($q, $v) => $q->where('payment_method_id', $v));
        $query->when($request->input('created_by'), fn ($q, $v) => $q->where('created_by', $v));
        $query->when($request->input('payment_gateway'), fn ($q, $v) => $q->whereIn('payment_gateway', (array) $v));
        $query->when($request->input('status'), fn ($q, $v) => $q->whereIn('status', (array) $v));
        $query->when($request->input('date_from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v));
        $query->when($request->input('date_to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v));

        $search = $request->input('search');
        if ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('payment_reference', 'like', '%'.$search.'%')
                    ->orWhere('payment_gateway', 'like', '%'.$search.'%')
                    ->orWhere('voucher_number', 'like', '%'.$search.'%')
                    ->orWhereHas('paymentMethod', fn ($q) => $q->where('english_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('agent', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('client', fn ($q) => $q
                        ->whereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(middle_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ['%'.$search.'%'])
                        ->orWhereRaw("CONCAT(COALESCE(country_code, ''), COALESCE(phone, '')) LIKE ?", ['%'.$search.'%'])
                    );
            });
        }

        $payments = (clone $query)->orderBy('id', 'desc');

        $filename = 'payment-links-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(
            new PaymentLinkExport($payments),
            $filename
        );
    }

    /**
     * Mirrors CreditController::assertSameCompanyOrUnscopedAdmin() /
     * ReceiptVoucherController's / BankPaymentController's identical copies: an admin acting
     * with no company selected (getCompanyId() falsy) is the app's established "unscoped" admin
     * case and may act across companies; every other caller -- including an admin who HAS a
     * company selected via session -- must match exactly. Aborts 403 rather than returning a
     * bool, same as the other three copies.
     */
    private function assertSameCompanyOrUnscopedAdmin($user, int $recordCompanyId): void
    {
        $companyId = getCompanyId($user);

        if ($user->role_id == Role::ADMIN) {
            if ($companyId && (int) $companyId !== $recordCompanyId) {
                abort(403, 'Unauthorized action.');
            }

            return;
        }

        if ((int) $companyId !== $recordCompanyId) {
            abort(403, 'Unauthorized action.');
        }
    }
}
