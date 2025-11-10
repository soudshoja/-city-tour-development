<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\IncomingMedia;
use App\Models\InvoicePartial;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResayilController extends Controller
{

    protected $url;
    protected $token;

    public function __construct()
    {
        $this->url = config('services.resayil.base_url') . config('services.resayil.version') . '/';
        $this->token = config('services.whatsapp.token');
    }

    public function message($phone, $country_code ,$message, $header = null, $footer = null, $buttons = null)
    {
        $url = $this->url . 'messages';
      
        if (str_starts_with($phone, '+')) {
            $phoneNumber = $phone;
        } else {   
            $phoneNumber = $country_code . $phone;
        }

        if(app()->environment('local')){
            $phoneNumber = env('PHONE_LOCAL', '+60193058463');
            $message = "This is a test message from local environment.\n\n" . $message;
        }

        $payload = [
            'phone' => $phoneNumber,
            'message' => $message,
        ];

        if ($header) {
            $payload['header'] = $header;
        }

        if ($footer) {
            $payload['footer'] = $footer;
        }

        if ($buttons && is_array($buttons)) {
            $payload['buttons'] = $buttons;
        }

        Log::debug('Sending to Resayil:', $payload);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Token' => $this->token,
        ])->post($url, $payload);

        if ($response->failed()) {
            Log::error("Error in sending Resayil: {$response->body()}");
            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status()
            ];
        } else {
            $data = json_decode($response, true);
            Log::debug('Resayil API Response:', $data ?? []);

            if (!empty($data['status']) && in_array($data['status'], ['queued', 'sent', 'delivered'])) {
                return ['success' => true];
            }

            return [
                'success' => false,
                'response' => $data
            ];
        }
    }

    public function handleWebhook(Request $request)
    {
        Log::debug('Resayil Webhook Received:', $request->all());

        $phone = $request->input('phone') ?? $request->input('messages.0.from');

        // Check if this is a media (image) message
        $message = $request->input('messages.0');
        
        if ($message && $message['type'] === 'image') {
            $mediaId = $message['image']['id'] ?? null;
            $mimeType = $message['image']['mimeType'] ?? null;
            $caption = $message['image']['caption'] ?? null;

            if ($mediaId) {
                // Save to DB
                IncomingMedia::create([
                    'phone' => $phone,
                    'media_id' => $mediaId,
                    'mime_type' => $mimeType,
                    'caption' => $caption,
                    'received_at' => now(),
                ]);

                Log::info("Saved incoming image from {$phone} with media ID {$mediaId}");
            }
        }

        // Your existing message + button reply handling here ...

        return response()->json(['message' => 'Webhook received successfully']);
    }

    public function shareInvoiceLink(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'invoiceNumber' => 'required|string',
        ]);
        Log::debug('Share Invoice:', $request->all());
        $client = Client::findOrFail($request->client_id);
        $invoiceNumber = $request->invoiceNumber;
        $companyName = $client->agent->branch->company->name;

        $invoiceLink = route('invoice.show', ['companyId' => $client->agent->branch->company_id, 'invoiceNumber' => $invoiceNumber]);

        $message = "Dear {$client->full_name},\n\nYour invoice #{$invoiceNumber} has been generated and is now available for your review.\n\nPlease click the following link to view your invoice:\n{$invoiceLink}\n\nIf you have any questions or require assistance, please don't hesitate to contact us.\n\nBest regards,\n{$companyName}";

        $response = $this->message($client->phone, $client->country_code, $message);

        Log::debug('Resayil API Response:', $response);

        if ($response['success'] ?? false) {
            return back()->with('success', 'Invoice link successfully shared via WhatsApp message through Resayil!');
        } else {

            Log::error('Failed to send WhatsApp message via Resayil', [
                'response' => $response
            ]);

            if($response['status'] == 400){
                return back()->withErrors(['error' => 'Invalid phone number format. Please check the client\'s phone number.']);
            } elseif ($response['status'] == 401) {
                return back()->withErrors(['error' => 'Unauthorized access. Please check your Resayil API token.']);
            } elseif ($response['status'] == 500) {
                return back()->withErrors(['error' => 'Internal server error. Please try again later.']);
            }

            return back()->withErrors(['error' => 'Something went wrong while sending the message.']);
        }
    }

    public function shareInvoicePartialLink(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'invoiceNumber' => 'required|string',
        ]);
        
        $client = Client::findOrFail($request->client_id);
        $invoiceNumber = $request->invoiceNumber;
        $companyName = $client->agent->branch->company->name;

        $invoicePartial = InvoicePartial::where('invoice_number', $invoiceNumber)
            ->where('client_id', $client->id)
            ->first();

        // Assuming you have a method to generate the partial invoice link
        $partialInvoiceLink = route('invoice.split', ['invoiceNumber' => $invoiceNumber, 'clientId' => $client->id, 'partialId' => $invoicePartial->id]);

        $message = "Dear {$client->full_name},\n\nYour partial invoice #{$invoiceNumber} has been generated and is now available for your review.\n\nPlease click the following link to view your partial invoice:\n{$partialInvoiceLink}\n\nIf you have any questions or require assistance, please don't hesitate to contact us.\n\nBest regards,\n{$companyName}";

        $response = $this->message($client->phone, $client->country_code, $message);

        Log::debug('Resayil API Response:', $response);

        if ($response['success'] ?? false) {
            return back()->with('success', 'Partial invoice link successfully shared via WhatsApp message through Resayil!');
        } else {
            Log::error('Failed to send WhatsApp message via Resayil', [
                'response' => $response
            ]);
            return back()->withErrors(['error' => 'Failed to send message.']);
        }
    }

    public function sharePaymentLink(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'payment_id' => 'required|exists:payments,id',
        ]);

        Log::debug('Share Payment Link:', $request->all());
        $client = Client::findOrFail($request->client_id);
        $payment = Payment::findOrFail($request->payment_id);
        $companyName = $payment->agent->branch->company->name;

        // Assuming you have a method to generate the payment link
        $paymentLink = route('payment.link.show', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number ]);
       
        $message = "Dear {$client->full_name},\n\nYour payment link for voucher #{$payment->voucher_number} is now ready.\n\nPlease click the following link to complete your payment:\n{$paymentLink}\n\nIf you have any questions or require assistance, please don't hesitate to contact us.\n\nBest regards,\n{$companyName}";

        $response = $this->message($client->phone, $client->country_code, $message);

        if ($response['success'] ?? false) {
            return back()->with('success', 'Payment link successfully shared via WhatsApp message through Resayil!');
        } else {
            Log::error('Failed to send WhatsApp message via Resayil', [
                'response' => $response
            ]);
            return back()->withErrors(['error' => 'Failed to send message.']);
        }
    }
}
