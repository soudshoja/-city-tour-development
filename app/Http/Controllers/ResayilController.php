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

    public function message($phone, $message, $header = null, $footer = null, $buttons = null)
    {
        $url = $this->url . 'messages';
      
        // Build the payload according to Resayil spec
        $payload = [
            'phone' => $phone,
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

        // Log payload for debugging
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

        $invoiceLink = route('invoice.show', ['invoiceNumber' => $invoiceNumber]);

        $message = "👋 Hello {$client->name},\n\n🧾 Your invoice is ready!\n\nYou can view it here:\n🔗 $invoiceLink\n\nThank you for choosing us! 😊";

        $response = $this->message($client->phone, $message);

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

        $invoicePartial = InvoicePartial::where('invoice_number', $invoiceNumber)
            ->where('client_id', $client->id)
            ->first();

        // Assuming you have a method to generate the partial invoice link
        $partialInvoiceLink = route('invoice.split', ['invoiceNumber' => $invoiceNumber, 'clientId' => $client->id, 'partialId' => $invoicePartial->id]);

        $message = "👋 Hello {$client->name},\n\n🧾 Your partial invoice is ready!\n\nYou can view it here:\n🔗 $partialInvoiceLink\n\nThank you for choosing us! 😊";

        $response = $this->message($client->phone, $message);

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

        // Assuming you have a method to generate the payment link
        $paymentLink = route('payment.link.show', ['voucherNumber' => $payment->voucher_number ]);
       
        $message = "👋 Hello {$client->name},\n\n💳 Your payment link is ready!\n\nYou can complete your payment here:\n🔗 $paymentLink\n\nThank you for choosing us! 😊";

        $response = $this->message($client->phone, $message);

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
