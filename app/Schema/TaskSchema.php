<?php

namespace App\Schema;

use App\Enums\TaskType;
use App\Models\Agent;
use App\Models\Supplier;
use App\Models\Task;

class TaskSchema
{
    public static function getSchema()
    {
        $supplierList = json_encode(Supplier::all()->toArray());

        $taskTypes = Task::where('type', [TaskType::hotel, TaskType::flight])->get();

        $agentsAmadeusId = Agent::pluck('amadeus_id')->toArray();

        $agentAmadeusIdList = json_encode($agentsAmadeusId);

        return [
            'additional_info' => [
                'type' => 'string',
                'desc' => "Include summarized, relevant details from the airfile in fewer than 10 words, ensuring all information directly corresponds to the airfile's content",
                'example' => 'Non-refundable ticket',
                'default' => '',
            ],
            'ticket_number' => [
                'type' => 'string',
                'desc' => "Ticket number. Usually like this: T-K229-2833133219, and it is usually preceded by a 3-digit airline code, so you can just take the last 10 digits as the ticket number. For example, if the ticket number is T-K229-2833133219, you can just use '2833133219' as the ticket number.",
                'example' => '2833133219',
                'default' => '',
            ],
            'gds_reference' => [
                'type' => 'string',
                'desc' => "GDS reference that provided by Amadeus, Sabre, Travelport or any other GDS. It always 6 characters, combination of digits and letters, like '8DROXL , 7648J5'. It usually located on line before line A, and it is usually after 'MUC1A' space and then the GDS reference. But the characters after 'MUC1A' is 9 characters long, so you can just take the first 6 characters after 'MUC1A'. For example, if the line is 'MUC1A 8DROXL0101', you can just take '8DROXL' as the GDS reference. Multiple passengers/client may have the same GDS reference, it means they are in the same booking, so you can just use the same GDS reference for all of them. But they will have different ticket number.",
                'example' => '80DROXL',
                'default' => '',
            ],
            'airline_reference' => [
                'type' => 'string',
                'desc' => "Airline reference that provided by the airline. It is on the same line as the GDS reference, but it is on the end of the line. It is usually 6 characters long, combination of digits and letters, like '8DROXL', '7648J5'. Sometimes the reference is same as the GDS reference, but most of the time it is different. You can just take the last 6 characters of the line where the GDS reference is located. For example, if the line is 'MUC1A 8DROXL0101', and at the end of the line is '8DROXL' or 'NK2B7Y', you can just take '8DROXL' or 'NK2B7Y' as the airline reference. Multiple passengers/client may have the same airline reference, it means they are in the same booking, so you can just use the same airline reference for all of them. But they will have different ticket number.",
                'example' => 'NK2B7Y',
                'default' => '',
            ],
            'status' => [
                'type' => 'string',
                'desc' => "Current status of the task. It can be: 'refund' (if the file contains refund indicator such as `RF`). Make sure to set the status to 'refund' if you detect `RF` keyword. Other status are 'issued', 'reissued','void' or 'emd'. If the files has 'FO' and original ticket number, set the status to 'reissued'. If the file is an 'EMD' ticket which usually means penalty fee, set the status to 'emd'. EMD means 'Electronic Miscellaneous Document', which is a document issued by airlines for various purposes, such as penalty fees, service charges, or other non-flight-related transactions.",
                'example' => 'refund',
                'default' => '',
            ],
            'supplier_status' => [
                'type' => 'string',
                'desc' => 'same data as status, no difference, just replicate the status field to this field',
                'example' => 'refund',
                'default' => '',
            ],
            'refund_date' => [
                'type' => 'datetime',
                'desc' => "Date of refund if applicable.",
                'example' => '2025-06-01 10:00:00',
                'default' => null,
            ],
            'price' => [
                'type' => 'float',
                'desc' => "Price of the task in float type. You may found files with different currency, but the air file already provide the exchange price beside the original price, so just use the exchanged price as the price. usually our default currency is KWD, so if the file has KWD as the currency, you can just use the price as is. If the file has different currency, you can use the exchanged price, which is usually stated in the file like 'EGP5197.00    ;KWD32.000' or 'USD 100.00 ; KWD 30.000'. In this case, you can just use the exchanged price, which is the next or first value after the semicolon, so in this case, you can just use '30.000' as the price.",
                'example' => 100.00,
                'default' => 0.0,
            ],
            'exchange_currency' => [
                'type' => 'string',
                'desc' => "Currency used after exchange, if the file has different currency, which is usually stated in the file like 'EGP5197.00    ;KWD32.000' or 'USD 100.00 ; KWD 30.000'. In this case, you can just use 'KWD' as the exchange currency.",
                'example' => 'KWD',
                'default' => '',
            ],
            'original_price' => [
                'type' => 'float',
                'desc' => "Original price of the task before exchange currency, if the file has different currency, which is usually stated in the file like 'EGP5197.00    ;KWD32.000' or 'USD 100.00 ; KWD 30.000'. In this case, you can just use '32.000' as the original price. if this field is not available, you can set it to null.",
                'example' => 100.00,
                'default' => null,
            ],
            'original_currency' => [
                'type' => 'string',
                'desc' => "Original currency of the task before exchange currency, if the file has different currency, which is usually stated in the file like 'EGP5197.00    ;KWD32.000' or 'USD 100.00 ; KWD 30.000'. In this case, you can just use 'EGP' or 'USD' as the original currency. if this field is not available, you can set it to null.",
                'example' => 'USD',
                'default' => null,
            ],
            'total' => [
                'type' => 'float',
                'desc' => "Total amount for the task in float type. This is usually more than the price, because it includes the price, tax and any other fees. You don't need to calculate the total, just use the total amount stated in the file, which is usually stated at then end of line where the price is stated. if you see the total amount is same as the price, it usually means that there is no tax or any other fees.",
                'example' => 115.00,
                'default' => 0.0,
            ],
            'surcharge' => [
                'type' => 'float',
                'desc' => "Any surcharge applied in float type.",
                'example' => 10.00,
                'default' => 0.0,
            ],
            'penalty_fee' => [
                'type' => 'float',
                'desc' => "Penalty fee if applicable especially for reissued tickets.",
                'example' => 10.00,
                'default' => 0.0,
            ],
            'tax' => [
                'type' => 'float',
                'desc' => "Total tax amount in float type.",
                'example' => 5.00,
                'default' => 0.0,
            ],
            'taxes_record' => [
                'type' => 'string',
                'desc' => "Parsed from the long line starting with KRF. All tax codes with their respective amounts are extracted.",
                'example' => 'KRF:7.500,CJ:7.600,F6:1.000,GZ:2.000,KW:5.000,N4:10.650,RN:9.900,VV:80.300,YQ:0.250,YX:0.900',
                'default' => '',
            ],
            'refund_charge' => [
                'type' => 'float',
                'desc' => "Total tax amount of YQ, YR, YX and other which non-refundable in float type. make sure to result in only one value of float type. it is float data type, so it can be 0.0 if there is no refund charge. You can just take the value of YQ, YR, YX and other which non-refundable from the taxes_record field, and sum them up to get the refund charge. But make sure it is one value only",
                'example' => 1.15,
                'default' => 0.0,
            ],
            'reference' => [
                'type' => 'string',
                'desc' => "Reference code for the task. take the ticket number from the file, which is usually stated at the end of the line where the price is stated. The ticket number is usually like this: T-K229-2833133219, and it is usually preceded by a 3-digit airline code, so you can just take the last 10 digits as the ticket number.",
                'example' => '2833133219',
                'default' => '',
            ],
            'created_by' => [
                'type' => 'string',
                'desc' => "GDS office ID, this indicates who created the task. Usually on line before line A , and to know who created the task, it is the first GDS office ID in the line",
                'example' => 'KWIKT2619',
                'default' => '',
            ],
            'issued_by' => [
                'type' => 'string',
                'desc' => "GDS office ID, this indicates who issued/pay the task. Usually on line before line A , and to know who issued the task, it is the last GDS office ID in the line/ or line after it. (still before line A), this is the example of real gds office id: \$gdsOfficeIdList",
                'example' => 'KWIKT2844',
                'default' => '',
            ],
            'type' => [
                'type' => 'string',
                'desc' => "Type of task. You can refer the type from this list: $taskTypes. You may always set the type to 'flight' if it airfile.",
                'example' => 'flight',
                'default' => 'flight',
            ],
            'agent_name' => [
                'type' => 'string',
                'desc' => "Name of the agent handling the task.",
                'example' => 'John Doe',
                'default' => '',
            ],
            'agent_email' => [
                'type' => 'string',
                'desc' => "Email of the agent handling the task.",
                'example' => 'agent@email.com',
                'default' => '',
            ],
            'agent_amadeus_id' => [
                'type' => 'string',
                'desc' => "Amadeus ID of the agent handling the task. Its located on C line of the file. The character often have 6 characters, 4 digit with 2 letters, like '1234AB'. However, the list of characters usually have 2 extra letters at the end, like '1234ABAS'. The last 2 letters are referring to the role of the agent (AS refer to agent, SU refer to the supplier), so you can just remove the last 2 letters and keep the first 6 characters. Then, usually the will put two amadeus id right next to each other, the first one is the agent that created the task, and the second one is the agent that issued the task. Take the second one, which is the agent that issued the task.  for example, if the agent amadeus id is '1234ABAS', you can just set it to '1234AB'. This is example list of amadeus id: $agentAmadeusIdList. This is the example of C line: 'C-7906/ 0070MBAS-0002ABAS-I-0', so the second amadeus id is '0002ABAS' is the one you need to take, and you can just take the first 6 characters, so it will be '0002AB'.",
                'example' => '1234AB',
                'default' => '',
            ],
            'client_name' => [
                'type' => 'string',
                'desc' => "Name of the client associated with the task.",
                'example' => 'Jane Smith',
                'default' => '',
            ],
            'supplier_name' => [
                'type' => 'string',
                'desc' => "Name of the supplier for the task, depends on supplier stated on the pdf, usually at the top or bottom of the pdf. They are responsible of sending this pdf. You can refer the supplier from this list: $supplierList. if the supplier is not in the list, just set it to null.",
                'example' => 'Amadeus',
                'default' => null,
            ],
            'supplier_country' => [
                'type' => 'string',
                'desc' => "Country of the supplier if stated anywhere in the pdf.",
                'example' => 'Kuwait',
                'default' => '',
            ],
            'cancellation_policy' => [
                'type' => 'string',
                'desc' => "Cancellation policy details.",
                'example' => 'Non-refundable after issue',
                'default' => '',
            ],
            'venue' => [
                'type' => 'string',
                'desc' => "Venue or location associated with the task.",
                'example' => 'Kuwait International Airport',
            ],
            'created_at' => [
                'type' => 'datetime',
                'desc' => "Timestamp when the ticket is issued provided in the airfile, like TKOK12FEB, turn this into datetime format. The date is usually in the format of 'TKOK12FEB', which means the ticket is issued on 12th February 2025, and the time is usually at 00:00:00, so you can just set the time to 00:00:00.",
                'example' => '2025-02-12 00:00:00',
                'default' => null,
            ],
            'task_flight_details' => [
                'type' => 'object',
                'desc' => "Flight details associated with the task.",
                'example' => [
                    'farebase' => '20.00',
                    'departure_time' => '2025-10-16 14:00:00',
                    'departure_from' => 'Kuwait',
                    'airport_from' => 'KWI',
                    'terminal_from' => '1',
                    'arrival_time' => '2025-10-16 16:00:00',
                    'duration_time' => '2h 5m',
                    'arrive_to' => 'Singapore',
                    'airport_to' => 'SIN',
                    'terminal_to' => '1',
                    'airline_name' => 'Kuwait Airways',
                    'flight_number' => 'KU-123',
                    'class_type' => 'economy',
                    'baggage_allowed' => '30kg',
                    'equipment' => 'A320',
                    'flight_meal' => 'Vegetarian',
                    'seat_no' => '12A',
                    'ticket_number' => '2833133219',
                ],

            ],
            'task_hotel_details' => [
                'type' => 'object',
                'desc' => "Hotel details associated with the task.",
                'example' => [
                    'hotel_name' => 'Grand Hotel',
                    'check_in_date' => '2025-10-16',
                    'check_out_date' => '2025-10-20',
                    'room_type' => 'Deluxe Suite',
                    'nights' => 4,
                    'guests' => 2,
                    'price_per_night' => 150.00,
                    'total_price' => 600.00,
                    'currency' => 'KWD',
                    'booking_reference' => 'GH123456',
                ],
            ],
        ];
    }

    public static function example(){
        $schema = static::getSchema();
        $example = [];

        foreach ($schema as $field => $details){
            $example[$field] = $details['example'] ?? '';
        }

        return $example;
    }

    public static function normalize(array $input)
    {
        $schema = static::getSchema();
        $normalized = [];
        
        foreach ($schema as $field => $meta) {
           
            if ($meta['type'] === 'object' && is_array($meta['example'])) {
                // Nested object normalization
                $nestedClass = null;
                if ($field === 'task_flight_details' && class_exists('\App\Schema\TaskFlightSchema')) {
                   
                    $nestedClass = '\App\Schema\TaskFlightSchema';
                } elseif ($field === 'task_hotel_details' && class_exists('\App\Schema\TaskHotelSchema')) {
                    $nestedClass = '\App\Schema\TaskHotelSchema';
                }
                $normalized[$field] = isset($input[$field]) && is_array($input[$field]) && $nestedClass
                    ? $nestedClass::normalize($input[$field])
                    : ($meta['default'] ?? null);
            } else {
                $normalized[$field] = array_key_exists($field, $input)
                    ? $input[$field]
                    : ($meta['default'] ?? $meta['example'] ?? null);
            }
        }
        
        return $normalized;
    }
}

