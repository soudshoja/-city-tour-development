<?php

namespace App\Http\Controllers;

use App\Http\Traits\HttpRequestTrait;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class WhatsappController extends Controller
{
    use HttpRequestTrait;
    public function sendMessage(Request $request)
    {
        $client = json_decode($request->client);
        $agent = Agent::find($client->agent_id);
        $invoiceNumber = $request->invoiceNumber;
        
        $header = "Your Invoice Is Ready!";
        $link = 'payment-clients/'. $invoiceNumber;

        $reqBody = [
            "messaging_product" => "whatsapp",
            "to" => '+60' . $client->phone,
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
            $agent->company->name,
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
    }
}
