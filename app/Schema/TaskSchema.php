<?php

namespace App\Schema;
use App\Models\Account;
use App\Enums\TaskType;
use App\Models\Agent;
use App\Models\Supplier;
use App\Models\Task;

class TaskSchema
{
    public static function getSchema()
    {
        $suppliers = Supplier::all();
        $supplierList = json_encode($suppliers->toArray());

        $vfsSupplier = $suppliers->where('name', 'VFS')->first();

        $vfsAccount = $vfsSupplier ? $vfsSupplier->account : null;

        $issuedByAccount = [];

    if ($vfsSupplier) {
        $vfsAccount = Account::where('name', $vfsSupplier->name)
            ->where('report_type', 'balance sheet')
            ->whereHas('root', function ($query) {
                $query->where('name', 'Liabilities');
            })
            ->first();

        if ($vfsAccount) {
            $vfsChildAccount = $vfsAccount->children()->pluck('name')->toArray();

            $issuedByAccount = array_push($vfsChildAccount);
        }
    }

    // dump('VFS Supplier:', $vfsSupplier); 
    // dump('VFS Account:', $vfsAccount);
    // dump('VFS Child Accounts:', $vfsChildAccount);
    // dd($vfsChildAccount);

        $taskTypes = Task::where('type', [TaskType::hotel, TaskType::flight])->get();

        $agentsAmadeusId = Agent::pluck('amadeus_id')->toArray();

        $agentAmadeusIdList = json_encode($agentsAmadeusId);

        return [
            'additional_info' => [
                'type' => 'string',
                'desc' => "Additional relevant information about the booking/task. For air files: Include summarized details in fewer than 10 words. For other documents: Extract any special notes, restrictions, or important remarks related to the booking (e.g., 'Non-refundable ticket', 'Special assistance required', 'Group booking').",
                'example' => 'Non-refundable ticket',
                'default' => '',
            ],
            'ticket_number' => [
                'type' => 'string',
                'desc' => "Document or ticket reference number. For air files: Usually like T-K229-2833133219, take the last 10 digits (e.g., '2833133219'). For other documents: Look for ticket numbers, e-ticket numbers, document numbers, or confirmation numbers. May be labeled as 'Ticket No', 'E-ticket', 'Document Number', 'Confirmation Code', or similar.",
                'example' => '2833133219',
                'default' => '',
            ],
            'gds_reference' => [
                'type' => 'string',
                'desc' => "GDS booking reference or PNR (Passenger Name Record). For Amadeus air files: 6 characters after 'MUC1A' (e.g., from 'MUC1A 8DROXL0101', take '8DROXL'). For other documents: Look for PNR, booking reference, reservation code, or locator code. Usually 6-8 alphanumeric characters labeled as 'PNR', 'Booking Ref', 'Locator', 'Reservation Code'.",
                'example' => '80DROXL',
                'default' => '',
            ],
            'airline_reference' => [
                'type' => 'string',
                'desc' => "Airline-specific booking reference. For Amadeus air files: Last 6 characters of the line containing GDS reference. For other documents: Look for airline confirmation codes, carrier references, or airline booking numbers. May be different from GDS reference and usually provided by the operating airline.",
                'example' => 'NK2B7Y',
                'default' => '',
            ],
            'status' => [
                'type' => 'string',
                'desc' => "Current status of the booking/ticket. For air files: 'refund' (RF indicator), 'reissued' (FO + original ticket), 'emd' (EMD ticket/penalty), 'issued', 'void'. For other documents: Look for status indicators like 'Confirmed', 'Cancelled', 'Pending', 'Issued', 'Refunded', 'Voided', 'On Hold', or similar status information.",
                'example' => 'refund',
                'default' => '',
            ],
            'supplier_status' => [
                'type' => 'string',
                'desc' => "Status from supplier's perspective. Should mirror the 'status' field. Represents the same booking status but from the supplier's system viewpoint.",
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
                'desc' => "Base price of the service before taxes and fees. For air files: Use exchanged price if different currency (e.g., from 'USD 100.00 ; KWD 30.000', use 30.000). For other documents: Look for base fare, net price, or principal amount before taxes. May be labeled as 'Base Fare', 'Net Price', 'Fare', 'Amount', or 'Price'.",
                'example' => 100.00,
                'default' => 0.0,
            ],
            'exchange_currency' => [
                'type' => 'string',
                'desc' => "Currency code used for pricing (usually your default currency). For air files: Extract from exchange format like 'USD 100.00 ; KWD 30.000' (use 'KWD'). For other documents: Look for currency codes like USD, EUR, KWD, GBP, etc. Usually 3-letter ISO currency codes.",
                'example' => 'KWD',
                'default' => '',
            ],
            'original_price' => [
                'type' => 'float',
                'desc' => "Original price before currency conversion. For air files: First amount in exchange format 'USD 100.00 ; KWD 30.000' (use 100.00). For other documents: If document shows original pricing in different currency, extract that amount. Set to null if same as price.",
                'example' => 100.00,
                'default' => null,
            ],
            'original_currency' => [
                'type' => 'string',
                'desc' => "Original currency before conversion. For air files: First currency in exchange format 'USD 100.00 ; KWD 30.000' (use 'USD'). For other documents: Original currency code if different from exchange currency. Set to null if same as exchange currency.",
                'example' => 'USD',
                'default' => null,
            ],
            'total' => [
                'type' => 'float',
                'desc' => "Final total amount including all fees, taxes, and charges. For air files: Total stated at end of pricing line. For other documents: Look for 'Total', 'Total Amount', 'Grand Total', 'Final Amount', 'Booking Total',or 'Amount Due'. You don't need to calculate this, just extract the final total amount as stated in the document. It should include base price + taxes + fees. For multiple passengers, it should be the total for that passenger like 'Passenger total'. For some documents, the total price of a single passenger is not stated, since we separate the task for each passenger, you need to divide the total by number of passengers. If no total amount is stated, set to 0.0.",
                'example' => 115.00,
                'default' => 0.0,
            ],
            'surcharge' => [
                'type' => 'float',
                'desc' => "Additional surcharge or service fee. For air files: Any surcharge mentioned. For other documents: Look for surcharges, service fees, booking fees, or additional charges. May be labeled as 'Surcharge', 'Service Fee', 'Booking Fee', or 'Additional Charge'.",
                'example' => 10.00,
                'default' => 0.0,
            ],
            'penalty_fee' => [
                'type' => 'float',
                'desc' => "Penalty or change fee amount. For air files: Especially for reissued tickets or EMD documents. For other documents: Look for penalty fees, change fees, cancellation fees, or similar charges related to modifications or cancellations.",
                'example' => 10.00,
                'default' => 0.0,
            ],
            'tax' => [
                'type' => 'float',
                'desc' => "Total tax amount. For air files: Sum of all tax components. For other documents: Look for tax amounts, VAT, government fees, or similar charges. May be labeled as 'Tax', 'VAT', 'Government Tax', 'Fees', or 'Taxes'.",
                'example' => 5.00,
                'default' => 0.0,
            ],
            'taxes_record' => [
                'type' => 'string',
                'desc' => "Detailed breakdown of all taxes and fees. For air files: Parse from KRF line, format as 'CODE:AMOUNT,CODE:AMOUNT'. For other documents: Extract all tax/fee breakdowns in similar format. Include all applicable tax codes and their amounts.",
                'example' => 'KRF:7.500,CJ:7.600,F6:1.000,GZ:2.000,KW:5.000,N4:10.650,RN:9.900,VV:80.300,YQ:0.250,YX:0.900',
                'default' => '',
            ],
            'refund_charge' => [
                'type' => 'float',
                'desc' => "Non-refundable charges (single float value). For air files: Sum of YQ, YR, YX and other non-refundable taxes from taxes_record. For other documents: Look for non-refundable fees, service charges that cannot be refunded, or similar non-recoverable amounts. If there is multiple non-refundable charges, sum them up into a single float value. If no non-refundable charges, set to 0.0.",
                'example' => 1.15,
                'default' => 0.0,
            ],
            'reference' => [
                'type' => 'string',
                'desc' => "Primary reference code for the booking. For air files: Same as ticket_number (last 10 digits). For other documents: Main booking reference, confirmation number, or primary identifier for the reservation. It might have different labels like 'Booking Reference', 'Reservation Code', 'Confirmation Number', or 'PNR'. It also may be same as gds_reference or airline_reference. If not available, use ticket_number.",
                'example' => '2833133219',
                'default' => '',
            ],
            'created_by' => [
                'type' => 'string',
                'desc' => "Identifier of who created the booking. For Amadeus air files: First GDS office ID before line A. For other documents: Look for agent codes, user IDs, office codes, or creator identifiers. May be in headers, footers, or booking details.",
                'example' => 'KWIKT2619',
                'default' => '',
            ],
            'issued_by' => [
                'type' => 'string',
                'desc' => "Country name that issued or received the visa application (e.g., as extracted from VFS documents).\nThis is the list of issued_by in my system: " . json_encode($issuedByAccount) . ". If there is no matching name, create a new one (e.g , 'VFS Italy').",
                'example' => 'Italy',
                'default' => '',
            ],
            'type' => [
                'type' => 'string',
                'desc' => "Service type of the booking. For air files: Always 'flight'. For other documents: Determine from content - 'flight' for airline tickets, 'hotel' for accommodation, 'car' for rentals, 'package' for combinations, etc. Refer to available types: $taskTypes.",
                'example' => 'flight',
                'default' => 'flight',
            ],
            'agent_name' => [
                'type' => 'string',
                'desc' => "Name of the handling agent or representative. Look for agent names, representative names, or staff names mentioned in the document. May be in signatures, headers, or contact information sections.",
                'example' => 'John Doe',
                'default' => '',
            ],
            'agent_email' => [
                'type' => 'string',
                'desc' => "Email address of the handling agent. Look for agent email addresses, contact emails, or representative email addresses in the document.",
                'example' => 'agent@email.com',
                'default' => '',
            ],
            'agent_amadeus_id' => [
                'type' => 'string',
                'desc' => "Agent system identifier. For Amadeus air files: Extract from C line (e.g., 'C-7906/ 0070MBAS-0002ABAS-I-0'), take second ID's first 6 chars ('0002AB' from '0002ABAS'). For other documents: Look for agent codes, system IDs, or user identifiers. Examples: $agentAmadeusIdList.",
                'example' => '1234AB',
                'default' => '',
            ],
            'client_name' => [
                'type' => 'string',
                'desc' => "Name of the customer or passenger. Look for passenger names, customer names, traveler names, or client names. Usually the main person for whom the booking is made.",
                'example' => 'Jane Smith',
                'default' => '',
            ],
            'supplier_name' => [
                'type' => 'string',
                'desc' => "Name of the service supplier or vendor. Look for company names, supplier names, airline names, hotel chains, or service provider names. Check headers, footers, logos, or contact information. Available suppliers: $supplierList.",
                'example' => 'Amadeus',
                'default' => null,
            ],
            'supplier_country' => [
                'type' => 'string',
                'desc' => "Country where the supplier is located or operates from. Look for country names, addresses, or location information associated with the supplier or service provider.",
                'example' => 'Kuwait',
                'default' => '',
            ],
            'cancellation_policy' => [
                'type' => 'string',
                'desc' => "Cancellation and refund policy details. Look for cancellation terms, refund conditions, change policies, or restrictions. May include refundability status, deadlines, or penalty information.",
                'example' => 'Non-refundable after issue',
                'default' => '',
            ],
            'cancellation_deadline' => [
                'type' => 'datetime',
                'desc' => "Deadline for cancellation or refund eligibility. Look for specific dates or timeframes mentioned in the cancellation policy. If no specific deadline, set to null.",
                'example' => '2025-06-01 10:00:00',
                'default' => null,
            ],
            'venue' => [
                'type' => 'string',
                'desc' => "Primary location or venue for the service. For flights: departure/arrival airports. For hotels: hotel location/city. For events: venue name/location. Extract the main location where the service takes place.",
                'example' => 'Kuwait International Airport',
            ],
            'issued_date' => [
                'type' => 'datetime',
                'desc' => "Date and time when the booking was created or issued. Look for issue dates, booking dates, or creation timestamps in YYYY-MM-DD HH:MM:SS format. Please don't use the example date, it is just an example of the format. Set to null if not available.",
                'example' => '2025-02-12 00:00:00',
                'default' => null,
            ],
            'task_flight_details' => [
                'type' => 'object',
                'desc' => "Flight details associated with the task. For flight details that have multiple segments, you can use the same schema for each segment. For example, if the flight come from Kuwait to Singapore with a stopover in Dubai, you can use flight details schema for each segment like Kuwait to Dubai, and Dubai to Singapore. This means one task can have multiple flight details. and also notes that if the flight has multiple passenger, the same segment/flight details should be used for each passenger. If the flight details are not available, you can set it to null.",
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
                // Nested object normalization - handle arrays of objects
                $nestedClass = null;
                if ($field === 'task_flight_details' && class_exists('\App\Schema\TaskFlightSchema')) {
                   
                    $nestedClass = '\App\Schema\TaskFlightSchema';
                } elseif ($field === 'task_hotel_details' && class_exists('\App\Schema\TaskHotelSchema')) {
                    $nestedClass = '\App\Schema\TaskHotelSchema';
                }
                
                if (isset($input[$field]) && is_array($input[$field]) && $nestedClass) {
                    // Check if it's an array of objects (multiple flight/hotel details)
                    if (isset($input[$field][0]) && is_array($input[$field][0])) {
                        // Array of objects - normalize each one
                        $normalized[$field] = [];
                        foreach ($input[$field] as $nestedItem) {
                            $normalized[$field][] = $nestedClass::normalize($nestedItem);
                        }
                    } else {
                        // Single object - normalize directly
                        $normalized[$field] = [$nestedClass::normalize($input[$field])];
                    }
                } else {
                    $normalized[$field] = $meta['default'] ?? null;
                }
            } else {
                $normalized[$field] = array_key_exists($field, $input)
                    ? $input[$field]
                    : ($meta['default'] ?? $meta['example'] ?? null);
            }
        }
        
        return $normalized;
    }
}
