<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Agent;
use App\Models\Client;
use App\Http\Controllers\ResayilController;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $sortField = $request->get('sort', 'due_date');
        $sortDirection = $request->get('direction', 'asc');

        // Validate sort field
        $allowedSorts = ['invoice_number', 'due_date', 'client_name', 'agent_name'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'due_date';
        }

        // Validate direction
        $sortDirection = $sortDirection === 'desc' ? 'desc' : 'asc';

        $query = Invoice::where('invoices.status', 'unpaid')  // ← Add table prefix
            ->with(['client', 'agent']);

        // Handle sorting by relationship fields
        if ($sortField === 'client_name') {
            $query->join('clients', 'invoices.client_id', '=', 'clients.id')
                ->orderBy('clients.name', $sortDirection)
                ->select('invoices.*');
        } elseif ($sortField === 'agent_name') {
            $query->join('users', 'invoices.agent_id', '=', 'users.id')
                ->orderBy('users.name', $sortDirection)
                ->select('invoices.*');
        } else {
            $query->orderBy('invoices.' . $sortField, $sortDirection);  // ← Add table prefix
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

        return view('reminder.index', compact('invoices', 'sortField', 'sortDirection', 'search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'agent_id' => 'nullable|exists:agents,id',
            'client_id' => 'nullable|exists:clients,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'payment_id' => 'nullable|exists:payments,id',
            'target_type' => 'required|in:invoice,client,agent',
            'message' => 'required|string|max:500',
            'send_to_client' => 'nullable',
            'send_to_agent' => 'nullable',
            'frequency' => 'required|in:once,auto',
            'value' => 'required_if:frequency,auto|nullable|integer|min:1',
            'unit' => 'required_if:frequency,auto|nullable|in:hours,days',
        ]);

        $invoice = Invoice::with(['client', 'agent'])->findOrFail($request->invoice_id);
        $agent = Agent::findOrFail($invoice->agent_id);
        $client = Client::findOrFail($invoice->client_id);

        $formattedDueDate = \Carbon\Carbon::parse($invoice->due_date)->format('jS F Y');

        $invoiceLink = route('invoice.show', [
            'companyId' => $client->agent->branch->company_id,
            'invoiceNumber' => $invoice->invoice_number,
        ]);

        $messageText = "Please be reminded that you have an outstanding payment to invoice {$invoice->invoice_number} of {$invoice->currency} {$invoice->amount} that was past due on {$formattedDueDate}\n\nAdditional information regarding this invoice can be find below:\n{$request->message}\n\nPlease click the following link to make the payment to the invoice:\n{$invoiceLink}\n\nShould you require further assistance, feel free to reach out our support team.";

        // Test number
        $dummyPhone = '126103085';
        $country_code = '+60';

        // Generate ONE group_id for all reminders in this batch
        $groupId = Str::uuid()->toString();

        // Determine how many reminders to create based on frequency
        // once = 1 reminder
        // auto = value number of reminders (e.g., value=3 means 3 reminders)
        $reminderCount = $request->frequency === 'once' ? 1 : (int) ($request->value ?? 1);
        $intervalUnit = $request->unit ?? 'days';

        Log::info('=== REMINDER CREATION ===');
        Log::info('Invoice: ' . $invoice->invoice_number);
        Log::info('Frequency: ' . $request->frequency);
        Log::info('Total Reminders to create: ' . $reminderCount);
        Log::info('Interval: every 1 ' . $intervalUnit);

        $resayil = new ResayilController();
        $createdReminders = [];
        $firstReminderSent = false;

        for ($i = 0; $i < $reminderCount; $i++) {
            $reminderNumber = $i + 1;
            $sentAt = null;

            if ($i === 0) {
                // First reminder - send immediately
                $scheduledAt = now();
                $status = 'pending';

                $result = $resayil->shareReminder(
                    $dummyPhone,
                    $country_code,
                    $messageText,
                    $invoice->client_id,
                    $invoice->agent_id,
                    $invoice->id
                );

                Log::info("Reminder #" . $reminderNumber . " - Sending now", $result);

                if ($result['success']) {
                    $status = 'sent';
                    $sentAt = now();
                    $firstReminderSent = true;
                } else {
                    $status = 'failed';
                    $sentAt = null;
                    Log::error("Failed to send reminder #" . $reminderNumber . ": " . ($result['error'] ?? 'Unknown error'));
                }
            } else {
                // Subsequent reminders - schedule 1 unit apart each
                $scheduledAt = $intervalUnit === 'hours'
                    ? now()->addHours($i)      // +1h, +2h, +3h...
                    : now()->addDays($i);       // +1d, +2d, +3d...

                $status = 'pending';
                $sentAt = null;

                Log::info("Reminder #" . $reminderNumber . " - Scheduled for: " . $scheduledAt->format('Y-m-d H:i:s'));
            }

            // Create reminder record
            $reminder = Reminder::create([
                'client_id' => $invoice->client_id,
                'agent_id' => $invoice->agent_id,
                'target_type' => $request->target_type,
                'invoice_id' => $invoice->id,
                'payment_id' => $request->payment_id,
                'message' => $request->message,
                'group_id' => $groupId,
                'send_to_client' => $request->has('send_to_client'),
                'send_to_agent' => $request->has('send_to_agent'),
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
        ]);

        if ($firstReminderSent) {
            $message = $reminderCount > 1
                ? "First reminder sent! " . $reminderCount . " reminders scheduled in total."
                : "Reminder sent to " . $country_code . $dummyPhone;

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
