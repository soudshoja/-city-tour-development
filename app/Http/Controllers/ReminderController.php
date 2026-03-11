<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Agent;
use App\Models\Client;
use App\Http\Controllers\ResayilController;
use Illuminate\Support\Facades\Log;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);
        $agentIds = collect();

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->pluck('id');
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $agentIds = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->pluck('id');
        } elseif ($user->role_id == Role::BRANCH) {
            $agentIds = Agent::where('branch_id', $user->branch->id)->pluck('id');
        } elseif ($user->role_id == Role::AGENT) {
            $agentIds = collect([$user->agent->id]);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $agentIds = Agent::where('branch_id', $user->accountant->branch_id)->pluck('id');
        }

        $search = $request->get('search');
        $sortField = $request->get('sort', 'due_date');
        $sortDirection = $request->get('direction', 'asc');

        $allowedSorts = ['invoice_number', 'due_date', 'client_name', 'agent_name'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'due_date';
        }
        $sortDirection = $sortDirection === 'desc' ? 'desc' : 'asc';

        $query = Invoice::where('invoices.status', 'unpaid')
            ->with([
                'client',
                'agent',
                'reminders' => function ($query) {
                    $query->orderBy('scheduled_at', 'asc');
                }
            ]);

        if (!$user->role_id == Role::ADMIN || $companyId) {
            $query->whereIn('invoices.agent_id', $agentIds);
        }

        // Handle sorting by relationship fields
        if ($sortField === 'client_name') {
            $query->join('clients', 'invoices.client_id', '=', 'clients.id')
                ->orderBy('clients.name', $sortDirection)
                ->select('invoices.*');
        } elseif ($sortField === 'agent_name') {
            $query->join('agents', 'invoices.agent_id', '=', 'agents.id')
                ->orderBy('agents.name', $sortDirection)
                ->select('invoices.*');
        } else {
            $query->orderBy('invoices.' . $sortField, $sortDirection);
        }

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoices.invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('agent', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $invoices = $query->paginate(20)->withQueryString();

        // Payment Reminders (grouped by group_id)
        $paymentRemindersQuery = Reminder::where('target_type', 'payment')
            ->with(['payment', 'client', 'agent'])
            ->orderBy('group_id')
            ->orderBy('scheduled_at');

        if (!$user->role_id == Role::ADMIN || $companyId) {
            $paymentRemindersQuery->whereIn('agent_id', $agentIds);
        }

        $paymentReminders = $paymentRemindersQuery->get()->groupBy('group_id');

        // All Reminders for History tab (grouped by group_id)
        $allRemindersQuery = Reminder::with(['client', 'agent', 'invoice', 'payment'])
            ->orderBy('created_at', 'desc')
            ->orderBy('scheduled_at', 'asc');

        if (!$user->role_id == Role::ADMIN || $companyId) {
            $allRemindersQuery->whereIn('agent_id', $agentIds);
        }

        $allReminders = $allRemindersQuery->get()->groupBy('group_id');

        return view('reminder.index', compact(
            'invoices',
            'sortField',
            'sortDirection',
            'search',
            'paymentReminders',
            'allReminders',
        ));
    }

    public function store(Request $request)
    {
        Log::info('Starting to create a reminder');
        $request->validate([
            'agent_id' => 'nullable|exists:agents,id',
            'client_id' => 'nullable|exists:clients,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'payment_id' => 'nullable|exists:payments,id',
            'target_type' => 'required|in:invoice,payment,client,agent',
            'message' => 'nullable|string|max:500',
            'send_to_client' => 'nullable',
            'send_to_agent' => 'nullable',
            'frequency' => 'required|in:once,auto',
            'value' => 'required_if:frequency,auto|nullable|integer|min:1',
            'unit' => 'required_if:frequency,auto|nullable|in:hours,days',
            'number_of_reminder' => 'required|integer|min:1',
        ]);

        $targetType = $request->target_type;
        Log::info("Target type selected: {$request->target_type}");

        $agent = Agent::findOrFail($request->agent_id);
        $client = Client::findOrFail($request->client_id);

        $additionalInfo = $request->message ? "\n\nAdditional information regarding this {$targetType} can be find below:\n{$request->message}" : '';

        // Client phone: combine country_code + phone
        $clientPhone = $client->phone;
        $clientCountryCode = $client->country_code ?? '';

        // Agent phone: use phone_number column (already includes country code)
        $agentPhoneFull = $agent->phone_number ?? '';

        // Parse agent phone to separate country code and number
        // Assuming format like "+60123456789" or "60123456789"
        $agentCountryCode = '';
        $agentPhone = $agentPhoneFull;

        if (preg_match('/^(\+?\d{1,3})(\d{6,})$/', $agentPhoneFull, $matches)) {
            $agentCountryCode = $matches[1];
            $agentPhone = $matches[2];
        }

        // Validate phone numbers before proceeding
        $sendToClient = $request->has('send_to_client');
        $sendToAgent = $request->has('send_to_agent');

        if ($sendToClient && (empty($clientPhone) || strlen($clientPhone) < 6)) {
            return redirect()->back()->with('error', 'Client does not have a valid phone number.');
        }
        if ($sendToAgent && (empty($agentPhoneFull) || strlen($agentPhoneFull) < 6)) {
            return redirect()->back()->with('error', 'Agent does not have a valid phone number.');
        }

        Log::info('Phone numbers to be used', [
            'client_country_code' => $clientCountryCode,
            'client_phone' => $clientPhone,
            'agent_country_code' => $agentCountryCode,
            'agent_phone' => $agentPhone,
            'agent_phone_full' => $agentPhoneFull,
        ]);

        $invoice = null;
        $payment = null;
        $clientId = null;
        $agentId = null;
        $invoiceId = null;
        $paymentId = null;

        if ($targetType === 'invoice') {
            Log::info('Target type is Invoice. Searching the target invoice');
            $invoice = Invoice::with(['client', 'agent'])->findOrFail($request->invoice_id);

            $clientId = $invoice->client_id;
            $agentId = $invoice->agent_id;
            $invoiceId = $invoice->id;
            $paymentId = null;

            $formattedDueDate = \Carbon\Carbon::parse($invoice->due_date)->format('jS F Y');
            $invoiceLink = route('invoice.show', [
                'companyId' => $client->agent->branch->company_id,
                'invoiceNumber' => $invoice->invoice_number,
            ]);

            $clientMessageText = "Please be reminded that you have an outstanding payment to invoice {$invoice->invoice_number} of {$invoice->currency} {$invoice->amount} that was past due on {$formattedDueDate}.{$additionalInfo}\n\nPlease click the following link to make the payment to the invoice:\n{$invoiceLink}\n\nShould you require further assistance, feel free to reach out our support team.";

            $agentMessageText = "This is a reminder that your client has an outstanding payment to invoice {$invoice->invoice_number} of {$invoice->currency} {$invoice->amount} that was past due on {$formattedDueDate}.{$additionalInfo}\n\nInvoice link:\n{$invoiceLink}\n\nPlease follow up with your client regarding this payment.";
        } elseif ($targetType === 'payment') {
            Log::info('Target type is Payment. Searching the target payment');
            $payment = Payment::with(['client', 'agent'])->findOrFail($request->payment_id);

            $clientId = $payment->client_id;
            $agentId = $payment->agent_id;
            $invoiceId = null;
            $paymentId = $payment->id;

            $paymentLink = route('payment.link.show', [
                'companyId' => $client->agent->branch->company->id,
                'voucherNumber' => $payment->voucher_number,
            ]);

            $clientMessageText = "Please be reminded that you have an outstanding payment to voucher {$payment->voucher_number} of {$payment->currency} {$payment->amount}.{$additionalInfo}\n\nPlease click the following link to make the payment:\n{$paymentLink}\n\nShould you require further assistance, feel free to reach out our support team.";

            $agentMessageText = "This is a reminder that your client has an outstanding payment to voucher {$payment->voucher_number} of {$payment->currency} {$payment->amount}.{$additionalInfo}\n\nPayment link:\n{$paymentLink}\n\nPlease follow up with your client regarding this payment.";
        } else {
            return redirect()->back()->with('error', 'Unsupported target type for reminder.');
        }

        // Generate ONE group_id for all reminders in this batch
        $groupId = Str::random(10);

        // Determine how many reminders to create based on frequency
        $reminderCount = $request->frequency === 'once' ? 1 : (int) ($request->number_of_reminder ?? 1);
        $intervalValue = (int) ($request->value ?? 1);
        $intervalUnit = $request->unit ?? 'days';

        $resayil = new ResayilController();
        $createdReminders = [];
        $firstReminderSent = false;

        for ($i = 0; $i < $reminderCount; $i++) {
            $reminderNumber = $i + 1;
            $sentAt = null;
            $status = 'pending';

            if ($i === 0) {
                $scheduledAt = now();
                $clientResult = ['success' => false];
                $agentResult = ['success' => false];

                // Send to Client
                if ($sendToClient) {
                    $clientResult = $resayil->shareReminder(
                        $clientPhone,
                        $clientCountryCode,
                        $clientMessageText,
                        $clientId,
                        $agentId,
                        $invoiceId ?? $paymentId,
                    );
                    Log::info("Reminder #{$reminderNumber} - Sent to CLIENT ({$clientCountryCode}{$clientPhone})", $clientResult);
                }

                // Send to Agent
                if ($sendToAgent) {
                    $agentResult = $resayil->shareReminder(
                        $agentPhone,
                        $agentCountryCode,
                        $agentMessageText,
                        $clientId,
                        $agentId,
                        $invoiceId ?? $paymentId,
                    );
                    Log::info("Reminder #{$reminderNumber} - Sent to AGENT ({$agentCountryCode}{$agentPhone})", $agentResult);
                }

                // Determine status based on results
                $clientSuccess = !$sendToClient || $clientResult['success'];
                $agentSuccess = !$sendToAgent || $agentResult['success'];

                if ($clientSuccess && $agentSuccess) {
                    $status = 'sent';
                    $sentAt = now();
                    $firstReminderSent = true;
                } else {
                    $status = 'failed';
                    Log::error("Failed to send reminder #{$reminderNumber}", [
                        'client_result' => $sendToClient ? $clientResult : 'not sent',
                        'agent_result' => $sendToAgent ? $agentResult : 'not sent',
                    ]);
                }
            } else {
                $scheduledAt = $intervalUnit === 'hours'
                    ? now()->addHours($intervalValue * $i)
                    : now()->addDays($intervalValue * $i);

                Log::info("Reminder #{$reminderNumber} - Scheduled for: " . $scheduledAt->format('Y-m-d H:i:s'));
            }

            $reminder = Reminder::create([
                'client_id' => $clientId,
                'agent_id' => $agentId,
                'target_type' => $targetType,
                'invoice_id' => $invoiceId,
                'payment_id' => $paymentId,
                'message' => $request->message,
                'group_id' => $groupId,
                'send_to_client' => $sendToClient,
                'send_to_agent' => $sendToAgent,
                'frequency' => $request->frequency,
                'value' => $request->value,
                'unit' => $request->unit,
                'scheduled_at' => $scheduledAt,
                'sent_at' => $sentAt,
                'status' => $status,
                'is_active' => true,
            ]);

            $createdReminders[] = $reminder;
        }

        Log::info('=== REMINDERS CREATED ===', [
            'group_id' => $groupId,
            'count' => count($createdReminders),
            'sent_to_client' => $sendToClient,
            'sent_to_agent' => $sendToAgent,
        ]);

        if ($firstReminderSent) {
            $recipients = [];
            if ($sendToClient) $recipients[] = "Client ({$clientCountryCode}{$clientPhone})";
            if ($sendToAgent) $recipients[] = "Agent ({$agentCountryCode}{$agentPhone})";

            $message = $reminderCount > 1
                ? "First reminder sent to " . implode(' & ', $recipients) . "! {$reminderCount} reminders scheduled in total."
                : "Reminder sent to " . implode(' & ', $recipients);

            return redirect()->back()->with('success', $message);
        } else {
            return redirect()->back()->with('error', 'Failed to send the first reminder.');
        }
    }

    public function bulk(Request $request)
    {
        $invoices = Invoice::where('status', 'unpaid')->get();

        foreach ($invoices as $invoice) {
            Reminder::create([
                'target_type' => 'invoice',
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'agent_id' => $invoice->agent_id,
                'invoice_number' => $invoice->invoice_number,
                'send_to_client' => $request->has('send_to_client'),
                'send_to_agent' => $request->has('send_to_agent'),
                'frequency' => $request->frequency,
                'value' => $request->value,
                'unit' => $request->unit,
                'max_reminder' => $request->max_reminder ?? 1,
                'sent_reminder' => 0,
                'next_reminder_at' => now(),
                'is_active' => true,
            ]);
        }

        return redirect()->back()->with('success', $invoices->count() . ' reminders created!');
    }
}
