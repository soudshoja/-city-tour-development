<?php

namespace App\Http\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Task;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierSurcharge;
use App\Models\SupplierSurchargeReference;
use App\Models\AutoBilling;
use App\Models\Account;
use App\Models\HotelBooking;
use App\Models\TBO;
use App\Models\Payment;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TaskController;
use App\Http\Traits\NotificationTrait;
use App\Models\Company;
use App\Models\Wallet;
use Carbon\Carbon;
use Exception;
use Illuminate\Validation\ValidationException;

class TaskWebhook
{
    use NotificationTrait;

    public function webhook(Request $request): JsonResponse
    {
        Log::info('[WEBHOOK] Task request received', ['request' => $request->all()]);

        try {
            $this->validateWebhookRequest($request);
            $this->prepareRequestData($request);

            $existingTask = $this->checkForExistingTask($request);
            if ($existingTask) {
                return $this->handleExistingTask($existingTask, $request);
            }

            $this->applySupplierSpecificRules($request);
            $this->linkOriginalTask($request);

            DB::beginTransaction();

            $task = $this->createTaskWithDetails($request);
            $this->processSupplierSurcharge($task);
            $this->applyAutoBillingRules($task);
            $this->saveTaskTypeDetails($task, $request);
            $this->processSpecialSupplierIntegrations($task);
            $this->setTaskEnabledStatus($task);
            $this->processFinancials($task);
            $this->processIataWallet($task);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Task created successfully via webhook',
                'data' => [
                    'task_id' => $task->id,
                    'reference' => $task->reference,
                    'type' => $task->type,
                    'enabled' => $task->enabled,
                ]
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            Log::error('[WEBHOOK] Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('[WEBHOOK] Task creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Task creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function validateWebhookRequest(Request $request): void
    {
        $request->validate([
            'reference' => 'required|string',
            'status' => 'required|string',
            'company_id' => 'required|exists:companies,id',
            'type' => 'required|string|in:flight,hotel,insurance,visa',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'original_reference' => 'nullable|string',
            'gds_reference' => 'nullable|string',
            'airline_reference' => 'nullable|string',
            'created_by' => 'nullable|string',
            'issued_by' => 'nullable|string',
            'iata_number' => 'nullable|string',
            'supplier_status' => 'nullable|string',
            'price' => 'nullable|numeric',
            'exchange_currency' => 'nullable|string',
            'original_price' => 'nullable|numeric',
            'original_currency' => 'nullable|string',
            'total' => 'nullable|numeric',
            'original_tax' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'original_total' => 'nullable|numeric',
            'original_surcharge' => 'nullable|numeric',
            'surcharge' => 'nullable|numeric',
            'penalty_fee' => 'nullable|numeric',
            'client_name' => 'nullable|string',
            'agent_id' => 'nullable|exists:agents,id',
            'client_id' => 'nullable|exists:clients,id',
            'additional_info' => 'nullable|string',
            'taxes_record' => 'nullable|string',
            'enabled' => 'nullable|boolean',
            'refund_date' => 'nullable|date',
            'ticket_number' => 'nullable|string',
            'original_ticket_number' => 'nullable|string',
            'refund_charge' => 'nullable|numeric',
            'file_name' => 'nullable|string',
            'issued_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:now',
            'supplier_pay_date' => 'nullable|date',
            'booking_reference' => 'nullable|string',
            'client_ref' => 'nullable|string',
            'cancellation_deadline' => 'nullable|date',
        ]);

        match ($request->input('type')) {
            'flight' => $this->validateFlightDetails($request),
            'hotel' => $this->validateHotelDetails($request),
            'insurance' => $this->validateInsuranceDetails($request),
            'visa' => $this->validateVisaDetails($request),
            default => null,
        };
    }

    private function prepareRequestData(Request $request): void
    {
        if ($request->exchange_currency !== 'KWD') {
            $request->merge([
                'exchange_currency' => 'KWD',
                'original_currency' => $request->exchange_currency,
            ]);
        }

        $request->merge([
            'penalty_fee' => $request->penalty_fee ?? 0,
            'passenger_name' => $request->client_name ?? null,
            'tax' => $request->tax ?? 0,
            'enabled' => $request->enabled ?? false
        ]);
    }

    private function checkForExistingTask(Request $request): ?Task
    {
        $query = Task::query()
            ->where('reference', $request->reference)
            ->where('company_id', $request->company_id)
            ->when($request->filled('supplier_status'), fn($q) => $q->where('supplier_status', $request->supplier_status))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('client_name'), fn($q) => $q->where('passenger_name', trim($request->client_name)))
            ->when($request->filled('supplier_id'), fn($q) => $q->where('supplier_id', $request->supplier_id));

        if ($request->type === 'hotel') {
            return $this->checkExistingHotelTask($query, $request);
        }

        $existingTask = $query->first();
        Log::info('[WEBHOOK] Existing task check', ['existing_task_id' => optional($existingTask)->id]);

        return $existingTask;
    }

    private function checkExistingHotelTask($query, Request $request): ?Task
    {
        $hotelName = data_get($request->task_hotel_details, '0.hotel_name');
        $roomType = data_get($request->task_hotel_details, '0.room_type');
        $checkIn = data_get($request->task_hotel_details, '0.check_in');
        $checkOut = data_get($request->task_hotel_details, '0.check_out');

        $checkIn = $checkIn ? Carbon::parse($checkIn)->toDateString() : null;
        $checkOut = $checkOut ? Carbon::parse($checkOut)->toDateString() : null;

        $existingTask = $query->whereHas('hotelDetails', function ($q) use ($checkIn, $checkOut, $hotelName, $roomType) {
            if ($hotelName) $q->whereHas('hotel', fn($qh) => $qh->where('name', 'LIKE', $hotelName));
            if ($checkIn) $q->whereDate('check_in', $checkIn);
            if ($checkOut) $q->whereDate('check_out', $checkOut);
            if ($roomType) $q->where('room_type', $roomType);
        })->first();

        Log::info('[WEBHOOK] Hotel task check', [
            'existing_task_id' => optional($existingTask)->id,
            'hotel_name' => $hotelName,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);

        return $existingTask;
    }

    private function handleExistingTask(Task $existingTask, Request $request): JsonResponse
    {
        if (is_null($existingTask->gds_reference) || is_null($existingTask->airline_reference)) {
            $existingTask->fill([
                'gds_reference' => $request->gds_reference,
                'airline_reference' => $request->airline_reference,
            ])->save();

            Log::info('[WEBHOOK] Updated existing task with GDS/Airline ref', ['task_id' => $existingTask->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Existing task updated with GDS and Airline reference',
                'data' => [
                    'task_id' => $existingTask->id,
                    'reference' => $existingTask->reference,
                ]
            ], 200);
        }

        $existingTask->issued_date = $request->issued_date;
        $existingTask->save();

        Log::info('[WEBHOOK] Updated existing task', ['task_id' => $existingTask->id]);

        return response()->json([
            'status' => 'success',
            'message' => 'Existing task updated',
            'data' => [
                'task_id' => $existingTask->id,
                'reference' => $existingTask->reference,
            ]
        ], 200);
    }

    private function applySupplierSpecificRules(Request $request): void
    {
        $amadeusId = Supplier::where('name', 'Amadeus')->value('id');
        $supplierName = Supplier::where('id', $request->supplier_id)->value('name');

        if ($request->supplier_id !== $amadeusId) {
            $request->merge([
                'gds_reference' => null,
                'airline_reference' => null
            ]);
        }

        if (strtolower($supplierName) !== 'amadeus') {
            $request->merge([
                'created_by' => null,
                'issued_by' => null,
            ]);
        }

        $this->applyStatusMapping($request, $supplierName);
        $this->setExpiryForConfirmed($request);
    }

    private function applyStatusMapping(Request $request, ?string $supplierName): void
    {
        $supplierNameLower = strtolower($supplierName ?? '');

        if (in_array($supplierNameLower, ['jazeera airways', 'fly dubai', 'vfs'])) {
            $status = match ($request->status) {
                'confirmed' => 'issued',
                'on hold' => 'confirmed',
                default => $request->status,
            };
            $request->merge(['status' => $status]);
        }

        if (strtolower($request->input('status')) === 'emd') {
            $request->merge(['status' => 'issued']);
        }
    }

    private function setExpiryForConfirmed(Request $request): void
    {
        if ($request->status === 'confirmed' && !$request->expiry_date) {
            $request->merge(['expiry_date' => Carbon::now()->addHours(48)]);
            Log::info('[WEBHOOK] Auto-set expiry date', ['expiry_date' => $request->expiry_date]);
        }
    }

    private function linkOriginalTask(Request $request): void
    {
        if (in_array($request->status, ['reissued', 'refund', 'void', 'emd'])) {
            $originalTask = Task::where('reference', $request->original_reference)
                ->orWhere('reference', $request->reference)
                ->where('passenger_name', $request->passenger_name)
                ->where('company_id', $request->company_id)
                ->whereIn('status', ['issued', 'reissued'])
                ->first();

            if ($originalTask) {
                $request->merge(['original_task_id' => $originalTask->id]);
                Log::info('[WEBHOOK] Linked original task', ['original_task_id' => $originalTask->id]);
            }
        }

        if ($request->status === 'issued') {
            $passengerName = $request->client_name ?? $request->passenger_name;
            $confirmedTask = Task::where('reference', $request->reference)
                ->where('company_id', $request->company_id)
                ->where('status', 'confirmed')
                ->where('passenger_name', $passengerName)
                ->first();

            if ($confirmedTask) {
                $request->merge(['original_task_id' => $confirmedTask->id]);
                Log::info('[WEBHOOK] Linked to confirmed task', ['confirmed_task_id' => $confirmedTask->id]);
            }
        }
    }

    private function createTaskWithDetails(Request $request): Task
    {
        $issuedDate = $request->input('issued_date');
        $cancellationDeadline = $request->input('cancellation_deadline');
        $taskType = $request->input('type');

        $supplierPayDate = $issuedDate;
        if ($taskType === 'hotel' && $cancellationDeadline) {
            $supplierPayDate = $cancellationDeadline > $issuedDate ? $cancellationDeadline : $issuedDate;
        }

        $data = $request->all();
        $data['supplier_pay_date'] = $supplierPayDate;

        $task = Task::create($data);
        Log::info('[WEBHOOK] Task created', ['task_id' => $task->id, 'reference' => $task->reference]);

        return $task;
    }

    private function processSupplierSurcharge(Task $task): void
    {
        if (!$task->supplier_id) {
            return;
        }

        $supplierCompany = SupplierCompany::where('supplier_id', $task->supplier_id)
            ->where('company_id', $task->company_id)
            ->first();

        if (!$supplierCompany) {
            return;
        }

        $totalSurcharge = 0;
        $surcharges = SupplierSurcharge::with('references')
            ->where('supplier_company_id', $supplierCompany->id)
            ->get();

        foreach ($surcharges as $surcharge) {
            if ($surcharge->charge_mode === 'task' && $surcharge->canChargeForStatus($task->status)) {
                $totalSurcharge += $surcharge->amount;
                Log::info('[WEBHOOK] Adding task surcharge', ['amount' => $surcharge->amount]);
            }

            if ($surcharge->charge_mode === 'reference') {
                $totalSurcharge += $this->processReferenceSurcharge($task, $surcharge);
            }
        }

        if ($totalSurcharge > 0) {
            $task->update(['supplier_surcharge' => $totalSurcharge]);
            Log::info('[WEBHOOK] Updated supplier surcharge', [
                'task_id' => $task->id,
                'surcharge' => $totalSurcharge,
            ]);
        }
    }

    private function processReferenceSurcharge(Task $task, $surcharge): float
    {
        $response = SupplierSurchargeReference::createSurchargeRecord($task, $surcharge);

        if (!$response || !$response->canBeCharged($surcharge->charge_behavior)) {
            return 0;
        }

        if ($surcharge->charge_behavior === 'single') {
            $response->markAsCharged();
        }

        Log::info('[WEBHOOK] Adding reference surcharge', ['amount' => $surcharge->amount]);
        return $surcharge->amount;
    }

    private function applyAutoBillingRules(Task $task): void
    {
        $matchedRule = AutoBilling::where('company_id', $task->company_id)
            ->where('is_active', true)
            ->where(function ($q) use ($task) {
                $q->where('created_by', $task->created_by)
                    ->orWhere('issued_by', $task->issued_by)
                    ->orWhere('agent_id', $task->agent_id);
            })
            ->get()
            ->first(
                fn($r) => (!$r->created_by || $r->created_by === $task->created_by) &&
                    (!$r->issued_by || $r->issued_by === $task->issued_by) &&
                    (!$r->agent_id || $r->agent_id === $task->agent_id)
            );

        if ($matchedRule) {
            $task->update(['client_id' => $matchedRule->client_id]);
            Log::info("[WEBHOOK] Auto-linked with AutoBilling rule #{$matchedRule->id}", [
                'task_id' => $task->id,
                'client_id' => $matchedRule->client_id
            ]);
        }
    }

    private function saveTaskTypeDetails(Task $task, Request $request): void
    {
        $detailsMap = [
            'hotel' => 'task_hotel_details',
            'flight' => 'task_flight_details',
            'insurance' => 'task_insurance_details',
            'visa' => 'task_visa_details',
        ];

        $detailsKey = $detailsMap[$task->type] ?? null;
        if (!$detailsKey || !$request->has($detailsKey) || empty($request->$detailsKey)) {
            return;
        }

        $taskController = app(TaskController::class);

        match ($task->type) {
            'hotel' => $taskController->saveHotelDetails($request->$detailsKey, $task->id),
            'flight' => $taskController->saveFlightDetails($request->$detailsKey, $task->id),
            'insurance' => $taskController->saveInsuranceDetails($request->$detailsKey, $task->id),
            'visa' => $taskController->saveVisaDetails($request->$detailsKey, $task->id),
            default => null,
        };
    }

    private function processSpecialSupplierIntegrations(Task $task): void
    {
        $this->processMagicHolidayBooking($task);
        $this->processTBOBooking($task);
    }

    private function processMagicHolidayBooking(Task $task): void
    {
        $supplierMagicHoliday = Supplier::where('name', 'Magic Holiday')->first();

        if (!$task->client_ref || !$supplierMagicHoliday || $task->supplier_id != $supplierMagicHoliday->id) {
            return;
        }

        $hotelBooking = HotelBooking::where('client_ref', $task->client_ref)->first();
        if (!$hotelBooking) {
            Log::warning('[WEBHOOK] No HotelBooking found for Magic Holiday', ['client_ref' => $task->client_ref]);
            return;
        }

        $payment = $hotelBooking->payment;
        $task->is_n8n_booking = true;

        if ($payment) {
            $task->enabled = true;
            $task->client_id = $payment->client_id;
            $task->client_name = $payment->client->full_name;
            $task->agent_id = $payment->agent_id;

            $response = app(InvoiceController::class)->autoGenerateInvoice($task, $payment);
            Log::info('[WEBHOOK] Auto-generated invoice for Magic Holiday', $response);
        } else {
            Log::warning("[WEBHOOK] No payment found for Magic Holiday", ['client_ref' => $task->client_ref]);
            $task->enabled = false;
        }

        $task->save();
    }

    private function processTBOBooking(Task $task): void
    {
        $supplierTBO = Supplier::where('name', 'LIKE', '%TBO%')->orWhere('name', 'TBO Holiday')->first();

        if (!$task->booking_reference || !$supplierTBO || $task->supplier_id != $supplierTBO->id) {
            return;
        }

        $tboBooking = TBO::where('booking_reference_id', $task->booking_reference)
            ->orWhere('confirmation_no', $task->reference)
            ->first();

        if (!$tboBooking || !$tboBooking->hotel_booking_id) {
            Log::warning('[WEBHOOK] No TBO booking found', ['reference' => $task->reference]);
            return;
        }

        $hotelBooking = HotelBooking::find($tboBooking->hotel_booking_id);
        if (!$hotelBooking || !$hotelBooking->payment_id) {
            Log::warning("[WEBHOOK] No hotel booking/payment for TBO", ['tbo_id' => $tboBooking->id]);
            return;
        }

        $payment = Payment::find($hotelBooking->payment_id);
        if (!$payment) {
            Log::warning("[WEBHOOK] No payment found for TBO", ['tbo_id' => $tboBooking->id]);
            return;
        }

        $task->is_n8n_booking = true;
        $task->enabled = true;

        $response = app(InvoiceController::class)->autoGenerateInvoice($task, $payment);
        Log::info('[WEBHOOK] Auto-generated invoice for TBO', $response);

        $task->save();
    }

    private function setTaskEnabledStatus(Task $task): void
    {
        $task->enabled = $task->is_complete && $task->agent_id && $task->client;
        $task->save();

        $status = $task->enabled ? 'enabled' : 'disabled';
        $reason = !$task->is_complete ? 'incomplete' : (!$task->agent_id ? 'no agent' : 'no client');

        Log::info("[WEBHOOK] Task {$status}", [
            'task_id' => $task->id,
            'reason' => $reason
        ]);
    }

    private function processFinancials(Task $task): void
    {
        $task->loadMissing('supplier');

        $offline = ($task->type === 'hotel' && $task->supplier_id)
            ? !(bool) data_get($task, 'supplier.is_online', true)
            : false;

        $supplierNameLower = strtolower(optional($task->supplier)->name ?? '');
        $isZeroTotalSupplier = (str_contains($supplierNameLower, 'trendy travel') ||
            str_contains($supplierNameLower, 'alam al raya travel')) && empty((float) $task->total);

        $shouldProcess = ($offline && $task->is_complete ||
            $task->status !== 'confirmed' ||
            ($task->status == 'void' && $task->original_task_id)) &&
            !$isZeroTotalSupplier;

        if ($shouldProcess) {
            $reason = $task->is_complete ? 'complete task' : 'void task with original';
            Log::info("[WEBHOOK] Processing financials for {$reason}", [
                'task_id' => $task->id,
                'agent_id' => $task->agent_id ?? 'none'
            ]);
            // $this->processTaskFinancial($task);
            app(TaskController::class)->processTaskFinancial($task);
        } else {
            $reason = $offline ? 'incomplete' : 'not offline supplier';
            Log::warning('[WEBHOOK] Financial processing skipped', [
                'task_id' => $task->id,
                'reason' => $reason,
                'status' => $task->status
            ]);
        }
    }

    private function processIataWallet(Task $task): void
    {
        if (!$task->iata_number) {
            Log::info('[WEBHOOK] No IATA wallet detected');
            return;
        }

        Log::info('[WEBHOOK] Processing IATA wallet', ['iata_number' => $task->iata_number]);

        $accounts = $this->getIataAccounts();

        if ($task->supplier_id == '2') {
            $this->processAmadeusIataWallet($task, $accounts);
        } elseif (in_array($task->supplier_id, ['29', '38', '39'])) {
            $this->processNdcIataWallet($task, $accounts);
        }
    }

    private function getIataAccounts(): array
    {
        $liabilities = Account::where('name', 'like', '%Liabilities%')->value('id');
        $accountPayable = Account::where('name', 'like', '%Accounts Payable%')
            ->where('parent_id', $liabilities)
            ->value('id');
        $creditors = Account::where('name', 'like', '%Creditors%')
            ->where('root_id', $liabilities)
            ->where('parent_id', $accountPayable)
            ->value('id');

        return compact('liabilities', 'creditors');
    }

    private function processAmadeusIataWallet(Task $task, array $accounts): void
    {
        $issuedBy = $task->issued_by;
        $iataNumber = $task->iata_number;

        if ($issuedBy == 'KWIKT211N' && $iataNumber == '42230215') {
            $this->processCityTravelersWallet($task, $accounts);
        } elseif ($issuedBy == 'KWIKT2843') {
            $this->processComoTravelWallet($task);
        }
    }

    private function processCityTravelersWallet(Task $task, array $accounts): void
    {
        Log::info('[WEBHOOK] Processing City Travelers wallet', [
            'issued_by' => $task->issued_by,
            'iata_number' => $task->iata_number,
        ]);

        $paymentMethodAccountId = Account::where('name', 'like', '%City Travelers (EasyPay)%')
            ->where('root_id', $accounts['liabilities'])
            ->where('parent_id', $accounts['creditors'])
            ->value('id');

        $this->updateTaskPaymentMethod($task, $paymentMethodAccountId);
        $this->createWalletRecord($task);
        $this->sendWalletNotification($task);
    }

    private function processComoTravelWallet(Task $task): void
    {
        Log::info('[WEBHOOK] Processing Como Travel wallet', [
            'issued_by' => $task->issued_by,
            'iata_number' => $task->iata_number,
        ]);

        $paymentMethodAccountId = Account::where('name', 'like', '%Como Travel & Tourism%')->value('id');
        $this->updateTaskPaymentMethod($task, $paymentMethodAccountId);
    }

    private function processNdcIataWallet(Task $task, array $accounts): void
    {
        Log::info('[WEBHOOK] Processing NDC supplier wallet');

        $paymentMethodAccountId = Account::where('name', 'like', '%City Travelers (EasyPay)%')
            ->where('root_id', $accounts['liabilities'])
            ->where('parent_id', $accounts['creditors'])
            ->value('id');

        $this->updateTaskPaymentMethod($task, $paymentMethodAccountId);
        $this->createWalletRecord($task);
        $this->sendWalletNotification($task);
    }

    private function updateTaskPaymentMethod(Task $task, ?int $paymentMethodAccountId): void
    {
        $task->update(['payment_method_account_id' => $paymentMethodAccountId]);

        $response = app(TaskController::class)->updateJournalPaymentMethod($task, $paymentMethodAccountId);

        if (!$response instanceof JsonResponse || $response->getData(true)['status'] !== 'success') {
            throw new Exception('Failed to update payment method journal entries');
        }
    }

    private function createWalletRecord(Task $task): void
    {
        $wallet = Wallet::where('iata_number', $task->iata_number)->latest('created_at')->first();
        $openingBalance = $wallet ? ($wallet->closing_balance ?? $wallet->wallet_balance) : 0;
        $closingBalance = $openingBalance - $task->total;

        Wallet::create([
            'iata_number' => $task->iata_number,
            'currency' => $task->exchange_currency ?? 'KWD',
            'opening_balance' => $openingBalance,
            'task_amount' => $task->total,
            'closing_balance' => $closingBalance,
        ]);

        Log::info("[WEBHOOK] Wallet record created", [
            'task_id' => $task->id,
            'opening_balance' => $openingBalance,
            'task_amount' => $task->total,
            'closing_balance' => $closingBalance
        ]);
    }

    private function sendWalletNotification(Task $task): void
    {
        $company = Company::find($task->company_id);

        $this->storeNotification([
            'user_id' => $company->user_id,
            'title' => 'IATA City Travelers (EasyPay) successfully deducted',
            'message' => 'IATA balance deducted KWD ' . $task->total . ' for task ID: ' . $task->id,
        ]);
    }

    private function validateFlightDetails(Request $request)
    {
        $request->validate([
            'task_flight_details' => 'required|array',
            'task_flight_details.*.is_ancillary' => 'required|boolean',
            'task_flight_details.*.farebase' => 'required|numeric',
            'task_flight_details.*.departure_time' => 'required|date',
            'task_flight_details.*.country_id_from' => 'required|integer|exists:countries,id',
            'task_flight_details.*.airport_from' => 'required|string',
            'task_flight_details.*.terminal_from' => 'required|string',
            'task_flight_details.*.arrival_time' => 'required|date',
            'task_flight_details.*.duration_time' => 'required|string',
            'task_flight_details.*.country_id_to' => 'required|integer|exists:countries,id',
            'task_flight_details.*.airport_to' => 'required|string',
            'task_flight_details.*.terminal_to' => 'required|string',
            'task_flight_details.*.airline_id' => 'required|integer',
            'task_flight_details.*.flight_number' => 'required|string',
            'task_flight_details.*.ticket_number' => 'required|string',
            'task_flight_details.*.class_type' => 'required|string',
            'task_flight_details.*.baggage_allowed' => 'required|string',
            'task_flight_details.*.equipment' => 'required|string',
            'task_flight_details.*.flight_meal' => 'required|string',
            'task_flight_details.*.seat_no' => 'required|string',
        ]);
    }

    private function validateHotelDetails(Request $request)
    {
        $request->validate([
            'task_hotel_details' => 'required|array',
            'task_hotel_details.*.hotel_name' => 'required|string',
            'task_hotel_details.*.booking_time' => 'required|date',
            'task_hotel_details.*.check_in' => 'required|date',
            'task_hotel_details.*.check_out' => 'required|date|after:task_hotel_details.*.check_in',
            'task_hotel_details.*.room_reference' => 'required|string',
            'task_hotel_details.*.room_number' => 'required|string',
            'task_hotel_details.*.room_type' => 'required|string',
            'task_hotel_details.*.room_amount' => 'required|integer|min:1',
            'task_hotel_details.*.room_details' => 'required|string',
            'task_hotel_details.*.room_promotion' => 'required|string',
            'task_hotel_details.*.rate' => 'required|numeric',
            'task_hotel_details.*.meal_type' => 'required|string',
            'task_hotel_details.*.is_refundable' => 'required|boolean',
            'task_hotel_details.*.supplements' => 'required|string',
        ]);
    }

    private function validateInsuranceDetails(Request $request)
    {
        $request->validate([
            'task_insurance_details' => 'required|array',
            'task_insurance_details.*.date' => 'required|string',
            'task_insurance_details.*.paid_leaves' => 'required|integer',
            'task_insurance_details.*.document_reference' => 'required|string',
            'task_insurance_details.*.insurance_type' => 'required|string',
            'task_insurance_details.*.destination' => 'required|string',
            'task_insurance_details.*.plan_type' => 'required|string',
            'task_insurance_details.*.duration' => 'required|string',
            'task_insurance_details.*.package' => 'required|string',
        ]);
    }

    private function validateVisaDetails(Request $request)
    {
        $request->validate([
            'task_visa_details' => 'required|array',
            'task_visa_details.*.visa_type' => 'required|string',
            'task_visa_details.*.application_number' => 'required|string',
            'task_visa_details.*.expiry_date' => 'required|date|after:now',
            'task_visa_details.*.number_of_entries' => 'required|string|in:single,double,multiple',
            'task_visa_details.*.stay_duration' => 'required|integer',
            'task_visa_details.*.issuing_country' => 'required|string',
        ]);
    }
}
