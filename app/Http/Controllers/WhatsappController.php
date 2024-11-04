<?php

namespace App\Http\Controllers;

use App\Http\Traits\HttpRequestTrait;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class WhatsappController extends Controller
{
    use HttpRequestTrait;
    public function sendMessage(Request $request)
    {
        $client = json_decode($request->client);
        // dd(json_decode($client));
        // dd($client->phone);
        $invoiceNumber = $request->invoiceNumber;

        $header = "Your Invoice Is Ready!";
        $link = route('payment.create', ['invoiceNumber' => $invoiceNumber]);
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
                                "text" => $header
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
            "Dear " . $client->name . ",",
            "We are pleased to inform you that your account has been successfully created.",
            "Please click the link below to activate your account.",
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

        return Redirect::back()->with('success', 'Message sent successfully');
    }
}
