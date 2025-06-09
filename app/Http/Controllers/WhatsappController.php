<?php

namespace App\Http\Controllers;

use App\Http\Traits\HttpRequestTrait;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;


class WhatsappController extends Controller
{
    use HttpRequestTrait;
    public function sendMessage(Request $request)
    {
        $client = json_decode($request->client);
        $agent = Agent::find($client->agent_id);
        $invoiceNumber = $request->invoiceNumber;
        
        $header = "Your Invoice Is Ready!";

        $link = 'invoice/' . $invoiceNumber;

        $reqBody = [
            "messaging_product" => "whatsapp",
            "to" => $client->phone,
            "type" => "template",
            "template" => [
                "name" => "alphia_number",
                "language" => [
                    "code" => "en_US"
                ],
                "components" => [
                    [
                        "type" => "header",
                        "parameters" => [
                            [
                                "type" => "text",
                                "text" => $client->name,
                            ]
                        ]
                    ],
                    [
                        "type" => "body",
                        "parameters" => []
                    ],
                    [
                        "type" => "button",
                        "sub_type" => "url",
                        "index" => 0,
                        "parameters" => [
                            [
                                "type" => "text",
                                "text" => $link
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $bodies = [
            $invoiceNumber,
            $agent->name,
            $agent->company->name ?? 'N/A', // Use a fallback value if company is null
        ];
        
        foreach ($bodies as $body) {
            $reqBody['template']['components'][1]['parameters'][] = [
                "type" => "text",
                "text" => $body
            ];
        }


        logger($reqBody);
        $response = $this->postRequest(
            config('services.whatsapp.url') . '/' . config('services.whatsapp.phone-number-id') . '/messages',
            array(
                'Authorization: Bearer ' . config('services.whatsapp.token'),
                'Content-Type: application/json'
            ),
            json_encode($reqBody),
        );

        logger($response);
        
        if(!isset($response['messages'][0]['message_status'])){
            return Redirect::back()->with('error', 'Failed to send message');
        }

        return Redirect::back()->with('success', 'Message sent successfully');

        $pdf = Pdf::loadView('pdf', compact('client', 'agent', 'invoiceNumber'));
        $pdfPath = storage_path('app/public/invoice_' . $invoiceNumber . '.pdf');
        $pdf->save($pdfPath);

        // Upload PDF to a public URL
        $pdfUrl = Storage::url('invoice_' . $invoiceNumber . '.pdf');

        // Update the link to the PDF
        $link = $pdfUrl;


        $reqBody['template']['components'][2]['parameters'][0]['text'] = $link;

 
    }

    public function sendMessage1(Request $request)
    {
        $client = Client::findOrFail($request->clientid); // Fetch client using ID
        $agent = Agent::find($client->agent_id);
        $invoiceNumber = $request->invoiceNumber;

        $header = "Your Invoice Is Ready!";
        $link = 'invoice/' . $invoiceNumber;

        $reqBody = [
            "messaging_product" => "whatsapp",
            "to" => $client->phone,
            "type" => "template",
            "template" => [
                "name" => "alphia_number",
                "language" => ["code" => "en_US"],
                "components" => [
                    [
                        "type" => "header",
                        "parameters" => [["type" => "text", "text" => $client->name]]
                    ],
                    ["type" => "body", "parameters" => []],
                    [
                        "type" => "button",
                        "sub_type" => "url",
                        "index" => 0,
                        "parameters" => [["type" => "text", "text" => $link]]
                    ]
                ]
            ]
        ];

        $bodies = [$invoiceNumber, $agent->name, $agent->branch->company->name];
        foreach ($bodies as $body) {
            $reqBody['template']['components'][1]['parameters'][] = ["type" => "text", "text" => $body];
        }

        logger($reqBody);
        $response = $this->postRequest(
            config('services.whatsapp.url') . '/' . config('services.whatsapp.phone-number-id') . '/messages',
            ['Authorization: Bearer ' . config('services.whatsapp.token'), 'Content-Type: application/json'],
            json_encode($reqBody),
        );

        logger($response);

        if (!isset($response['messages'][0]['message_status'])) {
            return Redirect::back()->with('error', 'Failed to send message');
        }

        return redirect()->route('invoices.index')->with('success', 'Message sent successfully');
    }

    public function sendMessagepdf(Request $request)
{      
    $client = json_decode($request->client);
    $agent = Agent::find($client->agent_id);
    $invoiceNumber = $request->invoiceNumber;
    $client = Client::findOrFail($client->id); 
    $agent = Agent::find($client->agent_id);

    $header = "Your Invoice Is Ready!";
    $link1 = "$invoiceNumber/pdf";
    $link2 = "$invoiceNumber/pdf";

    $reqBody = [
        "messaging_product" => "whatsapp",
        "to" => preg_replace('/[^0-9]/', '', $client->phone), 
        "type" => "template",
        "template" => [
            "name" => "payment_complete",
            "language" => ["code" => "en_US"],
            "components" => [
                [
                    "type" => "body",
                    "parameters" => [
                        ["type" => "text", "text" => $client->name],
                        ["type" => "text", "text" => $client->name]
                    ]
                ],
                [
                    "type" => "button",
                    "sub_type" => "url",
                    "index" => 0,
                    "parameters" => [["type" => "text", "text" => $link1]]
                ],
                [
                    "type" => "button",
                    "sub_type" => "url",
                    "index" => 1,
                    "parameters" => [["type" => "text", "text" => $link2]]
                ]
            ]
        ]
    ];

    // ✅ Append extra body parameters correctly
    $bodies = [$invoiceNumber, $agent->name, $agent->company->name];
    foreach ($bodies as $body) {
        $reqBody['template']['components'][0]['parameters'][] = ["type" => "text", "text" => $body];
    }

    // ✅ Log final request body & URL
    logger("Final Request Body: " . json_encode($reqBody));
    logger("Sending to URL: " . config('services.whatsapp.url') . '/' . config('services.whatsapp.phone-number-id') . '/messages');

    // ✅ Make the API request
    $response = $this->postRequest(
        config('services.whatsapp.url') . '/' . config('services.whatsapp.phone-number-id') . '/messages',
        ['Authorization: Bearer ' . config('services.whatsapp.token'), 'Content-Type: application/json'],
        json_encode($reqBody),
    );

    logger("WhatsApp API Response: " . json_encode($response));

    // ✅ Handle API response correctly
    if (!isset($response['messages'][0]['message_status'])) {
        return Redirect::back()->with('error', 'Failed to send message');
    }

    return response()->json(['message' => 'PDF sent successfully']);
}

    // $$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$

    private $VERIFY_TOKEN = 'd41d8cd98f00b204e9800998ecf8427e';


    public function handleWebhook(Request $request)
    {
        // Handle Verification Request
        if ($request->isMethod('get') && $request->has('hub_mode') && $request->input('hub_mode') === 'subscribe') {
            return $this->verifyWebhook($request);
        }

        // Handle Incoming Message
        if ($request->isMethod('post')) {
            return $this->processIncomingMessage($request);
        }

        return response()->json(['message' => 'Not Found'], 404);
    }

    private function verifyWebhook(Request $request)
    {
        if ($request->input('hub_verify_token') === $this->VERIFY_TOKEN) {
            return response($request->input('hub_challenge'), 200);
        }

        return response()->json(['error' => 'Verification token mismatch'], 403);
    }

    private function processIncomingMessage(Request $request)
    {
        $requestBody = $request->getContent();
        Log::info("Incoming WhatsApp Webhook: " . $requestBody);
        
        $data = json_decode($requestBody, true);

        if (isset($data['entry'])) {
            foreach ($data['entry'] as $entry) {
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $change) {
                        if (isset($change['value']['messages'])) {
                            foreach ($change['value']['messages'] as $message) {
                                $phoneNumber = $message['from'];
                                $messageType = $message['type'];

                                if ($messageType === 'text') {
                                    $text = $message['text']['body'] ?? '';
                                    Log::info("Received Text from $phoneNumber: $text");
                                    Storage::append('received_messages.txt', "From: $phoneNumber - Message: $text");
                                } elseif ($messageType === 'document') {
                                    $docName = $message['document']['filename'];
                                    $mimeType = $message['document']['mime_type'];
                                    $mediaId = $message['document']['id'];

                                    Log::info("Received Document from $phoneNumber: $docName ($mimeType) - Media ID: $mediaId");
                                    Storage::append('received_messages.txt', "From: $phoneNumber - Document: $docName ($mimeType) - Media ID: $mediaId");

                                    // Trigger document download script
                                    $this->downloadMedia($mediaId);
                                }
                            }
                        }
                    }
                }
            }
        }

        return response()->json(['message' => 'OK'], 200);
    }

    private function downloadMedia($mediaId)
    {
        $downloadScriptUrl = "https://tour.citycommerce.group/whatsapp_webhook/download_media.php?media_id=" . urlencode($mediaId);
        
        $response = Http::timeout(20)->get($downloadScriptUrl);

        if ($response->successful()) {
            Log::info("Download script response: " . $response->body());
        } else {
            Log::error("Failed to download media: " . $response->body());
        }
    }

    public function sendToResayilSimple(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'message' => 'required|string',
        ]);

        $client = Client::findOrFail($request->client_id);

        // Prepare the payload
        $payload = [
            'phone' => $client->phone, 
            'message' => $request->message,
        ];

        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Token' => config('services.whatsapp.token'),
        ])->post(config('services.whatsapp.url') . '/messages', $payload);

        // Log the response
        logger('Resayil API Response: ' . $response->body());

        // Return result to frontend
        if ($response->successful()) {
            return back()->with('success', 'Message sent successfully via Resayil');
        } else {
            return back()->with('error', 'Failed to send message via Resayil: ' . $response->body());
        }
    }


    public function shareInvoice(Request $request)
    {
        //dd($request);
        $request->validate([
            'clientid' => 'required|exists:clients,id',
            'invoiceNumber' => 'required|string',
        ]);
        Log::debug('Share Invoice:', $request->all());
        $client = Client::findOrFail($request->clientid);
        $invoiceNumber = $request->invoiceNumber;

        $invoiceLink = url("/invoice/{$invoiceNumber}");

        $message = "Hello {$client->name}, here is your invoice link: $invoiceLink";

        $response = $this->sendToResayil($client->phone, $message);

        if ($response['success'] ?? false) {
            return back()->with('success', 'Invoice link shared in WhatsApp message via Resayil successfully!');
        } else {
            
            Log::error('Failed to send WhatsApp message via Resayil', [
                'response' => $response
            ]);

            return back()->withErrors(['error' => 'Failed to send message.']);
        }

    }

    public function handleResayilWebhook(Request $request)
    {
        // Log incoming webhook data for debugging
        Log::debug('Resayil Webhook Received:', $request->all());

        $messageId = $request->input('id');
        $phone = $request->input('phone');
        $status = $request->input('status');
        $deliveryStatus = $request->input('deliveryStatus');
        $webhookStatus = $request->input('webhookStatus');

        return response()->json(['message' => 'Webhook received successfully']);
    }

    protected function sendToResayil($phone, $message)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => config('services.whatsapp.url'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                'phone' => $phone,
                'message' => $message,
            ]),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Token: " . config('services.whatsapp.token'), 
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return [
                'success' => false,
                'error' => $err
            ];
        } else {
            $data = json_decode($response, true);

            // Log the full API response for debugging
            Log::debug('Resayil API Response:', $data ?? []);

            // Success condition — normally Resayil returns "status": "queued"
            if (!empty($data['status']) && in_array($data['status'], ['queued', 'sent', 'delivered'])) {
                return ['success' => true];
            }

            return [
                'success' => false,
                'response' => $data
            ];
        }
    }

    
}