<?php

namespace App\Http\Controllers;

use App\AIService;
use App\Http\Requests\OpenAiRequest;
use App\Http\Traits\HttpRequestTrait;
use App\Models\Client;
use App\Models\Task;
use App\Models\TaskFlightDetail;
use App\Models\Agent;
use App\Models\Supplier;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Airports;
use App\Models\Branch;
use App\Models\ChatCompletion;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Message;
use App\Models\Role;
use App\Models\TaskHotelDetail;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use PhpParser\Node\Expr\Throw_;
use Ramsey\Uuid\Type\Integer;

class OpenAiController extends Controller
{

    use HttpRequestTrait;
    
    private $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService =  $aiService;
    }

    public function index()
    {
        return view('ai.openai.index');
    }

    public function store(Request $request)
    {
        $prompt = $request->input('prompt');
        $url = config('services.open-ai.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];
        $data = [
            'model' => config('services.open-ai.model'),  // Use a valid model name like 'gpt-4' or 'gpt-3.5-turbo'
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an assistant in a travel agency. You will suggest the best flight options to a customer based on their preferences but limit you response to 100 words only.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'stream' => false
        ];

        $response =  $this->postRequest($url, $header, json_encode($data));

        return response()->json($response);
    }

    public function chatCompletion(array $message)
    {
        $url = config('services.open-ai.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];
        $data = [
            'model' => config('services.open-ai.model'),
            'messages' => $message,
        ];
        $response =  $this->postRequest($url, $header, json_encode($data));

        logger('chat completion response: ', $response);
        return $response;
    }

    public function chatCompletionTools(string $model = 'gpt-4o-mini', array $tools, array $message)
    {
        $url = config('services.open-ai.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];

        $data = [
            'model' => $model,
            'messages' => $message,
            'tools' => $tools,
            'user' => 'user',
        ];

        $response =  $this->postRequest($url, $header, json_encode($data));

        logger('chat completion tools response: ', $response);

        return $response;
    }

    public function chatCompletionImage($prompt, $image)
    {
        $url = config('services.open-ai.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'C:\Users\User\Documents\GitHub\city-tour\storage\app\public\passports\passportClient.jpeg',
                            ]
                        ],
                    ]
                ],
            ],
        ];

        $response =  $this->postRequest($url, $header, json_encode($data));
        logger('chat completion image response: ', $response);
        return response()->json($response);
    }

    public function extractPassport($content)
    {
        $prompt = "
        You are an assistant for a travel agency. You need to extract passport details from the uploaded content. This passport is extracted by tesseract-OCR. The details you need might be nearby the words or sentences. The passport details should include the following fields:
    
        - `passport_no`: Passport number or Passport No.
        - `civil_no`: Civil number or Civil No.
        - `name`: Full name as per the passport.
        - `nationality`: Nationality
        - `date_of_birth`: Date of birth
        - `date_of_issue`: Date of issue
        - `date_of_expiry`: Date of expiry
        - `place_of_birth`: Place of birth
        - `place_of_issue`: Place of issue
    
        only pass me the data extracted in JSON format.
        ";

        // Make the request to the OpenAI API
        $response = $this->chatCompletion([
            [
                'role' => 'user',
                'content' => $prompt,
            ],
            [
                'role' => 'user',
                'content' => $content,
            ],
        ]);

        if (isset($response['choices'][0]['message']['content'])) {
            $message = $response['choices'][0]['message']['content'];
            $message = $this->cleanJsonResponse($message);

            return [
                'status' => 'success',
                'message' => 'Data extracted successfully',
                'data' => $message,
            ];
        } else {
            $message = $response;

            return [
                'status' => 'error',
                'message' => 'Data extraction failed. No content returned from OpenAI.',
            ];
        }
    }

    /**
     * @param string $content
     * 
     * @return string
     */
    public function flightOrHotel($content)
    {
        $prompt = " Check if this document is for a flight or hotel booking. 
                    The document might contain information like booking reference, passenger name, flight details, hotel details, etc. 
                    Suggest if it's a flight or hotel booking. 
                    example answer : 
                    {
                        'type': 'flight'
                    }
                    ";

        $response = $this->aiService->chatCompletionJsonResponse([
            [
                'role' => 'user',
                'content' => $prompt,
            ],
            [
                'role' => 'user',
                'content' => $content,
            ],
        ]);
        
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
            
            $type = json_decode($content, true)['type'];
            if ($type !== 'flight' && $type !== 'hotel') {
                return [
                    'status' => 'error',
                    'message' => 'Invalid response. Please provide a valid response: flight or hotel'
                ];
            }
        }

        return [
            'status' => 'success',
            'message' => 'Document type identified successfully',
            'data' => $type,
        ];
    }

    /**
     * Extract flight data from the content
     * 
     * @param string $content
     * 
     * @return array from saveTasks()
     */
    public function extractFlightData($content)
    {
        $supplierList = json_encode(Supplier::all()->toArray());

        $airportList = json_encode(Airport::all()->toArray());

        $prompt = "
        You are an assistant for processing uploaded files to extract structured data for a task management system. The system has two models:
        
        1. `tasks` model with the following fields:
            - `additional_info`: Include summarized, relevant details from the airfile in fewer than 10 words, ensuring all information directly corresponds to the airfile's content.
            - `status`: Current status of the task. whether it's completed, hold or confirmed or any other status.
            - `price`: Price of the task in float type.
            - `surcharge`: Any surcharge applied in float type.
            - `total`: Total amount for the task in float type. this column is mandatory, please make sure to find the total amount in the pdf., if it is not available, set it to 0.
            - `tax`: Total tax amount in float type.
            - `reference`: Reference code for the task.
            - `type`: Type of task (e.g., flight).
            - `agent_name`: name of the agent handling the task.
            - `client_name`: name of the client associated with the task.
            - `supplier_name`: name of the supplier for the task, depends on supplier stated on the pdf, usually at the top or bottom of the pdf. They are responsible of sending this pdf.
                You can refer the supplier from this list: $supplierList
                if the supplier is not in the list, just set it to null.
            - `supplier_country`: Country of the supplier if stated anywhere in the pdf.
            - `client_name`: Name of the client.
            - `cancellation_policy`: Cancellation policy details.
            - `venue`: Venue or location associated with the task.
        
        2. `task_flight_details` model, which applies only if the task is a flight, with the following fields:
            - `farebase`: Fare basis of the flight in float type.
            - `departure_time`: Departure time of the flight.
            - `departure_from`: Location of departure, it must be a country. If the information retrieve is a city, state or any other than country, you must set it to suitable country.
            - `airport_from`: Airport code or name for departure.
            - `terminal_from`: Departure terminal.
            - `arrival_time`: Arrival time of the flight.
            - `arrive_to`: Location of arrival, it must be a country. If the information retrieve is a city, state or any other than country, you must set it to suitable country.
            - `airport_to`: Airport code or name for arrival.
            - `terminal_to`: Arrival terminal.
            - `airline_name`: Airline name. 
            - `flight_number`: Flight number.
            - `class_type`: Class type of the flight.
            - `baggage_allowed`: Baggage allowance.
            - `equipment`: Equipment used in the flight.
            - `flight_meal`: Meal options during the flight.
            - `seat_no`: Seat number.
        
        Extract relevant data from the uploaded content in JSON format, matching the structure of these models. Only include fields with available data, and omit any null or empty fields.
        if some of the fields are not available, you can set them to null.
        
        all related time should be in the format of 'Y-m-d H:i:s'

        Analyze the uploaded file to locate and extract relevant fields.
        If the file type is (.air) but the structure doesn't match the reference example, reject the file.
        If the uploaded file type is (.air), set it to amedeus as per supplier's list that i gave you,
        then bind the data to the `tasks` and `task_flight_details` models in JSON format, following the provided mapping examples. 

        Sample 1: Airfile Details Data Extract (For Mapping Reference)
           The following is a sample file extract for reference, demonstrating how to locate and map data to JSON fields.
            AIR-BLK207;7A;;233;0000000000;1A1437915;001001 AMD 2300000011;1/1; 
            1A1437915;1A1437915 MUC1A
            WW5BY4006;0101;KWIKT211N;42230215;KWIKT211N;42230215;KWIKT211N;42230215;KWIKT211N;
            42230215;PR;;;;;;;;;;;;;;;;;;;;;WY JAUNNU A-OMAN AIR;WY 9100 B-TTP/RT C-7906/ 9966SDSU-9966SDSU-I-0-- D-250223;250223;250223 G-X ;;
            KWIMCT; H-001;002OKWI;KUWAIT ;MCT;MUSCAT ;WY 0648 N N 23FEB2120 0025 24FEB;OK01;HK01;M ;0;738;;;20K;1 ;;ET;0205 ;N;747;KW;OM; K-FKWD87.000 ;;;;;;;;;;;;
            KWD100.350 ;;; KFTF; KWD4.000 YQ AC; KWD3.100 YR VA; KWD1.000 GZ SE; KWD2.000 KW AE; KWD3.000 N4 CB; KWD0.250 YX AP ;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;; L- M-NCMOKW 
            N-NUC281.47 O-23FEB23FEB;LD23FEB252359 Q-KWI WY MCT281.47NUC281.47END
            ROE0.309091;FXB I-001;01AL MASHAYKHI/SALIM ALI SULTAN MR;;APKWI +965 55524870 
            // SAEID //;; SSR CTCM WY HK1/96555524870 SSR CTCE WY 
            HK1/OPS//CITYTRAVELERS.CO SSR OTHS 1A /REF IATA PRVD PAX EMAIL N MBL CTC IN 
            SSR CTCE OR CTCM SSR OTHS 1A /REF IATA UPDATE SSR CTCR IF PAX REFUSING TO 
            PRVD CTC SSR OTHS 1A /ADTK BY 1626 23FEB25 KWI LT ELSE WY WILL XXL T-K910-3580675462 FEVALIDONWY;S2;P1 FMM0 
            FPCCCA0000000000002093/0425/A452524;S2;P1 FVWY;S2;P1 TKOK23FEB/KWIKT211N//ETWY ENDX

            #### Field Mapping Instructions
            Analyze the file's structure to locate the field data.
            Extract and map the fields to the corresponding JSON keys, following the binding example provided below.
            Ensure the gds_reference contains exactly six words.
            Format the price field consistently to two decimal points.
            Make sure that the supplier_name is set to 'Amadeus' only.

            **Field Binding Example for Sample 1**:  
            - line 3: 'WW5BY4' for gds_reference
            - line 4: 'JAUNNU' for airline_reference, 'A-OMAN AIR' for 'airline_name', 'WY' for aita_code
            - line 4: '910' for airline_code, '9966SD' for agent_code
            - line 5: 'KWI;KUWAIT' for airport_from, 'MCT;MUSCAT' for airport_to, 'WY 0648' for flight_number
            - line 5: 'N' for class_type, '23FEB' for departure_date, '2120' for departure_time
            - line 5: '0025' for arrival_time, '24FEB' for arrival_date, 'OK01' for status, 'M' for flight_meal
            - line 5: '738' for aircraft_type, '20K' for baggage_allowed, 'FKWD87.000' for fare
            - line 6: 'KWD100.350' for price, 'KWD4.000 YQ AC' for tax_1, 'KWD3.100 YR VA' for tax_2, 'KWD1.000 GZ SE' for tax_3
            - line 6: 'KWD2.000 KW AE' for tax_4, 'KWD3.000 N4 CB' for tax_5, 'KWD0.250 YX AP' for tax_6, 'NCMOKW' for farebaes
            - line 8: 'AL MASHAYKHI/SALIM ALI SULTAN MR' for client_name
            - line 12: '3580675462' for atia_invoice_number, 'S2' for segment_2, 'P1' for pax_number, 'FMM0'  for commission
            - line 13: 'FPCCCA0000000000002093/0425' for payment_terms

            Example JSON Output for Sample 1 =
            {
                'gds_reference': 'WW5BY4',
                'airline_reference': 'JAUNNU',
                'airline_name': 'A-OMAN AIR',
                'aita_code': 'WY',
                'airline_code': 910,
                'agent_code': '9966SD',
                'airport_from': 'KWI;KUWAIT',
                'airport_to': 'MCT;MUSCAT',
                'flight_number': 'WY 0648',
                'class_type': 'N',
                'departure_date': '2025-02-23',
                'departure_time': '21:20:00',
                'arrival_time': '00:25:00',
                'arrival_date': '2025-02-24',
                'status': 'OK01',
                'flight_meal': 'M',
                'aircraft_type': '738',
                'baggage_allowed': '20K',
                'fare': 'FKWD87.000',
                'price': 'KWD100.350',
                'tax_1': 'KWD4.000 YQ AC',
                'tax_2': 'KWD3.100 YR VA',
                'fare_base': 'NCMOKW',
                'client_name': 'AL MASHAYKHI/SALIM ALI SULTAN MR',
                'supplier_name': 'Amadeus',
                'atia_invoice_number': '3580675462',
                'segment_2': 'S2',
                'pax_number': 'P1',
                'commission': 'FMM0',
                'payment_terms': 'FPCCCA0000000000002093/0425'
            }

        Sample 2: Airfile Details Data Extract (For Mapping Reference)
            AIR-BLK207;7A;;233;0000000000;1A1437915;001001
            AMD 0600000009;1/1;              
            1A1437915;1A1437915
            MUC1A KK6WB5008;0101;KWIKT211N;42230215;KWIKT211N;42230215;KWIKT211N;42230215;KWIKT211N;42230215;PR;;;;;;;;;;;;;;;;;;;;;GF MASBGE
            A-GULF AIR;GF 0722
            B-TTP/RT
            C-7906/ 9966SDSU-9966SDSU-I-0--
            D-250205;250206;250206
            G-X  ;;KWIKWI;
            H-001;002OKWI;KUWAIT           ;BAH;BAHRAIN          ;GF    0214 N N 25FEB1140 1245 25FEB;OK01;HK01;S ;0;320;;;25K;1 ;;ET;0105 ;N;261;KW;BH;
            H-003;003OBAH;BAHRAIN          ;KWI;KUWAIT           ;GF    0215 W W 27FEB1705 1810 27FEB;OK01;HK01;S ;0;320;;;25K;;;ET;0105 ;N;261;BH;KW;1 
            K-FKWD37.000     ;;;;;;;;;;;;KWD81.950     ;;;
            KFTF; KWD28.000   YQ AC; KWD1.000    GZ SE; KWD2.000    KW AE; KWD5.000    N4 CB; KWD0.250    YX AP; KWD8.250    BH DP; KWD0.450    HM AP;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
            L-
            M-NCLIT1KW       ;WCLIT1KW       
            N-NUC72.79;45.29
            O-25FEB25FEB;27FEB27FEB;LD25FEB252359
            Q-KWI GF BAH72.79GF KWI45.29NUC118.08END ROE0.309091;FXB
            I-001;01ESMAIEL/HUSSAIN MR;;APKWI +965 55524870 // SAEID ////KWI +965 55524870 // SAEID //;;
            FQV GF  FQTV-GF11451682;S2;P1
            FQV GF  FQTV-GF11451682;S3;P1
            SSR CTCM GF  HK1/96555524870
            SSR CTCE GF  HK1/OPS//CITYTRAVELERS.CO
            SSR DOCS GF  HK1/P/KWT/P05348062/KWT/04DEC54/M/16JAN28/ESMAIEL/HUSSAIN/P;P1
            SSR OTHS 1A  /ISSUE TICKETS FOR GF FLIGHTS BY 06FEB25 1320GMT
            SSR OTHS 1A  /OR GF WILL CANCEL WITHOUT FURTHER NOTICE
            SSR OTHS 1A  /GF DO NOT ACCEPT TKNM
            T-K072-3580675460
            FEVALID ON GF ONLY/NON ENDO;S2-3;P1
            FM*M*0
            FPCCCA0000000000002093/0425/A327351;S2-3;P1
            FVGF;S2-3;P1
            TKOK05FEB/KWIKT211N//ETGF
            ENDX

            **Field Binding Example for Sample 2**:  
            - line 4: 'KK6WB5' for gds_reference, 'MASBGE' for airline_reference
            - line 5: 'A-GULF AIR' for 'airline_name', 'GF' for aita_code, '072' for airline_code
            - line 7: '9966SD' for agent_code
            - line 10: 'KWI;KUWAIT' for airport_from, 'BAH;BAHRAIN' for airport_to, 'GF    0214' for flight_number, 'N' for class_type
            - line 10: '25FEB' for departure_date, '1140' for departure_time, '1245' for arrival_time, '25FEB' for arrival_date
            - line 10: 'OK01' for status, 'S' for flight_meal, '320' for aircraft_type, '25K' for baggage_allowed, 
            - line 12: 'FKWD37.000' for fare, 'KWD81.950' for price
            - line 13: 'KWD28.000   YQ AC' for tax_1, 'KWD1.000    GZ SE' for tax_2, 'KWD2.000    KW AE' for tax_3
            - line 13: 'KWD5.000    N4 CB' for tax_4, 'KWD0.250    YX AP' for tax_5, 'KWD8.250    BH DP' for tax_6, 'KWD0.450    HM AP' for tax_7
            - line 15: 'NCLIT1KW' for farebaes
            - line 19: 'ESMAIEL/HUSSAIN MR' for client_name
            - line 28: '3580675460' for atia_invoice_number, 'S2-3' for segment_2, 'P1' for pax_number, 'FM*M*0'  for commission
            - line 31: 'FPCCCA0000000000002093/0425' for payment_terms

        Sample 3: Airfile Details Data Extract (For Mapping Reference)
            AIR-BLK207;7A;;233;0000000000;1A1437915;001001
            AMD 0900000010;1/1;              
            1A1437915;1A1437915
            MUC1A TQG8QU006;0101;KWIKT211N;42230215;KWIKT211N;42230215;KWIKT211N;42230215;KWIKT211N;42230215;PR;;;;;;;;;;;;;;;;;;;;;GF KZZZUJ
            A-GULF AIR;GF 0722
            B-TTP/RT
            C-7906/ 9966SDSU-9966SDSU-I-0--
            D-250208;250209;250209
            G-X  ;;KWIKWI;
            H-003;002OKWI;KUWAIT           ;BAH;BAHRAIN          ;GF    0214 O O 25FEB1140 1245 25FEB;OK01;HK01;S ;0;320;;;25K;1 ;;ET;0105 ;N;261;KW;BH;
            H-004;003OBAH;BAHRAIN          ;KWI;KUWAIT           ;GF    0215 O O 27FEB1705 1810 27FEB;OK01;HK01;S ;0;320;;;25K;;;ET;0105 ;N;261;BH;KW;1 
            K-FKWD55.000     ;;;;;;;;;;;;KWD99.950     ;;;
            KFTF; KWD28.000   YQ AC; KWD1.000    GZ SE; KWD2.000    KW AE; KWD5.000    N4 CB; KWD0.250    YX AP; KWD8.250    BH DP; KWD0.450    HM AP;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
            L-
            M-OCLIT1KW       ;OCLIT1KW       
            N-NUC88.97;88.97
            O-25FEB25FEB;27FEB27FEB;LD25FEB252359
            Q-KWI GF BAH88.97GF KWI88.97NUC177.94END ROE0.309091;FXB
            I-001;01ESMAIEL/ALZAHRAA MS;;APKWI +965 55524870 // SAEID //;;
            SSR CTCM GF  HK1/96555524870
            SSR CTCE GF  HK1/OPS//CITYTRAVELERS.CO
            SSR OTHS 1A  /ISSUE TICKETS FOR GF FLIGHTS BY 10FEB25 1425GMT
            SSR OTHS 1A  /OR GF WILL CANCEL WITHOUT FURTHER NOTICE
            SSR OTHS 1A  /GF DO NOT ACCEPT TKNM
            T-K072-3580675461
            FEVALID ON GF ONLY/NON ENDO;S2-3;P1
            FM*M*0
            FPCCCA0000000000002093/0425/A350967;S2-3;P1
            FVGF;S2-3;P1
            TKOK09FEB/KWIKT211N//ETGF
            ENDX
            
            **Field Binding Example for Sample 3**:  
            - line 4: 'TQG8QU' for gds_reference, 'KZZZUJ' for airline_reference
            - line 5: 'A-GULF AIR' for 'airline_name', 'GF' for aita_code, '072' for airline_code
            - line 7: '9966SD' for agent_code
            - line 10: 'KWI;KUWAIT' for airport_from, 'BAH;BAHRAIN' for airport_to, 'GF    0214' for flight_number
            - line 10: 'O' for class_type, '25FEB' for departure_date, '1140' for departure_time
            - line 10: '1245' for arrival_time, '25FEB' for arrival_date, 'OK01' for status, 'S' for flight_meal
            - line 10: '320' for aircraft_type, '25K' for baggage_allowed
            - line 12: 'FKWD55.000' for fare, 'KWD99.950' for price
            - line 13: 'KWD28.000   YQ AC' for tax_1, 'KWD1.000    GZ SE' for tax_2, 'KWD2.000    KW AE' for tax_3
            - line 13: 'KWD5.000    N4 CB' for tax_4, 'KWD0.250    YX AP' for tax_5, 'KWD8.250    BH DP' for tax_6, 'KWD0.450    HM AP' for tax_7
            - line 15: 'OCLIT1KW' for farebaes
            - line 19: 'ESMAIEL/ALZAHRAA MS' for client_name
            - line 25: '3580675461' for atia_invoice_number
            - line 26: 'S2-3' for segment_2, 'P1' for pax_number
            - line 27: 'FM*M*0'  for commission
            - line 28: 'FPCCCA0000000000002093/0425' for payment_terms

        The venue field is populated using the airport_to field from the file, which contains codes like 'DXB'. 
        These codes are matched against $airportList and the corresponding location data from the list is used to update the venue field.
        
        this is the content: $content

        only pass me the data extracted in JSON format.

        example answer = 
        {
            'additional_info': 'additional info',
            'status': 'completed'/ 'hold' / 'confirmed',
            'price': 100.00,
            'surcharge': 10.00,
            'total': 110.00,
            'tax': 5.00,
            'reference': 'gds_reference',
            'type': 'flight',
            'agent_name': 'agent name',
            'client_name': 'client name',
            'supplier_name': 'Amadeus',
            'supplier_country': 'Kuwait',
            'cancellation_policy': 'cancellation policy',
            'venue': 'venue',
            'task_flight_details': {
                'farebase': '20.00',
                'departure_time': '2024-10-16 14:00:00',
                'departure_from': 'Kuwait',
                'airport_from': 'KWI',
                'terminal_from': '1',
                'arrival_time': '2024-10-16 16:00:00',
                'arrive_to': 'Singapore',
                'airport_to': 'SIN',
                'terminal_to': '1',
                'airline_name': 'Kuwait Airways',
                'flight_number': 'KU-123',
                'class_type': 'economy',
                'baggage_allowed': 'baggage allowed',
                'equipment': 'equipment',
                'flight_meal': 'flight meal',
                'seat_no': 'seat no',
            }
        }

        ";

        $response = $this->aiService->chatCompletionJsonResponse([
            [
                'role' => 'user',
                'content' => $prompt,
            ],
            [
                'role' => 'user',
                'content' => $content,
            ],
        ]);

        if (isset($response['choices'][0]['message']['content'])) {
            $message = $response['choices'][0]['message']['content'];

            $decodedResponse = json_decode($message, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'status' => 'success',
                    'message' => 'Data extracted successfully',
                    'data' => $decodedResponse,
                ];
                // return $taskController->saveTasks($decodedResponse);
            } else {
                $cleanedResponse = $this->cleanJsonResponse($message);
                $data = json_decode($cleanedResponse, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return [
                        'status' => 'success',
                        'message' => 'Data extracted successfully',
                        'data' => $data,
                    ];

                    // return $taskController->saveTasks($data);
                } else {
                    return [
                        'status' => 'error',
                        'message' => 'Failed to parse JSON or missing required fields.',
                    ];
                }
            }
        }
    }

    /**
     * Extract hotel data from the content
     * 
     * @param string $content
     * 
     * @return array from saveTasks()
     */
    public function extractHotelData($content)
    {
        $supplierList = json_encode(Supplier::all()->toArray());

        $prompt = "
        You are an assistant for processing uploaded files to extract structured data for a task management system. The system has two models:

        1. `tasks` model with the following fields:
            - `additional_info`: Additional information but make sure to only include relevant data and below 10 words, summarize it.
            - `status`: Current status of the task.
            - `price`: Price of the task in float type.
            - `surcharge`: Any surcharge applied in float type.
            - `total`: Total amount for the task in float type.
            - `tax`: Total tax amount in float type.
            - `reference`: Reference code for the task.
            - `type`: Type of task (e.g., flight).
            - `agent_name`: name of the agent handling the task.
            - `client_name`: name of the client associated with the task, some pdfs have the client name as holder name.
            - `supplier_name`: name of the supplier for the task, depends on supplier stated on the pdf, usually at the top or bottom of the pdf. They are responsible of sending this pdf.
                You can refer the supplier from this list: $supplierList
                if the supplier is not in the list, just set it to null.
            - `supplier_country`: Country of the supplier if stated anywhere in the pdf.
            - `client_name`: Name of the client.
            - `cancellation_policy`: Cancellation policy details.
            - `venue`: Venue or location associated with the task.
        
        2. `task_hotel_details` model, which applies only if the task is a hotel booking, with the following fields:
            - `hotel_name`: Name of the hotel.
            - `hotel_address`: Address of the hotel.
            - `hotel_city`: City of the hotel.
            - `hotel_state`: State of the hotel.
            - `hotel_country`: Country of the hotel.
            - `hotel_zip`: Zip code of the hotel.
            - `booking_time`: Time of booking.
            - `check_in`: Check-in date.
            - `check_out`: Check-out date.
            - `room_number`: Room number.
            - `room_type`: Type of room.
            - `room_amount`: Amount of the room in float type.
            - `room_details`: Details of the room.
            - `rate`: Rate of the room in float type.

        Extract relevant data from the uploaded content in JSON format, matching the structure of these models. Only include fields with available data, and omit any null or empty fields.
        if some of the fields are not available, you can set them to null.
        this is the content: $content

        only pass me the data extracted in JSON format.

        example answer = 

        {
            'additional_info': 'King Bed Deluxe High Floor - 2408 Oaks Liwa Heights, Jumeirah Lake Towers',
            'status': 'completed',
            'price': 100.00,
            'surcharge': 10.00,
            'total': 110.00,
            'tax': 5.00,
            'reference': 'relevant reference',
            'type': 'hotel',
            'agent_name': 'agent name',
            'client_name': 'Khaled Alajmi',
            'supplier_name': 'Magic Holidays',
            'supplier_country': 'Kuwait',
            'cancellation_policy': 'cancellation policy',
            'venue': 'venue',
            'task_hotel_details': {
                'hotel_name': 'Oaks Liwa Heights',
                'hotel_address': 'Jumeirah Lake Towers',
                'hotel_city': null,
                'hotel_state': 'Dubai',
                'hotel_country': 'United Arab Emirates',
                'hotel_zip': '12345',
                'booking_time': '2024-10-16 14:00:00',
                'check_in': '2024-10-17',
                'check_out': '2024-10-20',
                'room_number': '101',
                'room_type': 'Deluxe Room',
                'room_amount': '100.00',
                'room_details': 'Sea View',
                'rate': '40.00', 
            }
        }
        ";

        $response = $this->aiService->chatCompletionJsonResponse([
            [
                'role' => 'user',
                'content' => $prompt,
            ],
            [
                'role' => 'user',
                'content' => $content,
            ],
        ]);

        if (isset($response['choices'][0]['message']['content'])) {
            $message = $response['choices'][0]['message']['content'];

            $decodedResponse = json_decode($message, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'status' => 'success',
                    'message' => 'Data extracted successfully',
                    'data' => $decodedResponse,
                ];
                // return $taskController->saveTasks($decodedResponse);
            } else {
                $cleanedResponse = $this->cleanJsonResponse($message);
                $data = json_decode($cleanedResponse, true);

                if (json_last_error() === JSON_ERROR_NONE) {

                    return [
                        'status' => 'success',
                        'message' => 'Data extracted successfully',
                        'data' => $data,
                    ];
                    // return $taskController->saveTasks($data);
                } else {
                    return [
                        'status' => 'error',
                        'message' => 'Failed to parse JSON or missing required fields.',
                    ];
                }
            }
        }
    }

    function cleanJsonResponse($responseText)
    {
        // Remove code block delimiters like """ and ```
        $responseText = preg_replace('/^[\s]*"""|```json|```|"""[\s]*$/', '', $responseText);

        // Remove any newlines or excess whitespace around the JSON
        $responseText = trim($responseText);

        // Decode the JSON to verify it's valid, then re-encode it to return clean JSON
        $jsonData = json_decode($responseText, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($jsonData, JSON_PRETTY_PRINT); // Return clean, pretty JSON
        } else {
            // Handle JSON decoding errors if needed
            throw new Exception("Invalid JSON format in AI response.");
        }
    }

    /**
     * Dont used yet
     * 
     * @return view
     */
    public function fineTuningView()
    {
        return view('ai.openai.fine-tuning');
    }

    /**
     * Ask the AI system
     * 
     * @param string $content
     * @param int $userId
     * 
     * @return array ['status', 'message', 'data']
     */
    public function askOpenAi($content, $userId) : array
    {
        $user = User::find($userId);
        $conversation = collect();
        $createNewThread = true;

        //Check if the message is question or action
        $response = $this->promptOrAction($content);
        
        logger('prompt or action response: ', $response);

        if (isset($response['error']) || $response['status'] === 'error') {
            return $response;
        }

        if ($response['data']['type'] === 'prompt') {

            //Check thread for this user, if not exist create new thread, by default create new thread is true
            $conversation = Conversation::where('user_id', $userId)->latest()->first();

            if ($conversation) {
                $createNewThread = $conversation->thread_id == null || $conversation->assistant_id == null; // return false or true
            } 

            if ($createNewThread) {

                $threadRunResponse = $this->aiService->createThread($user);

                if ($threadRunResponse['status'] == 'error') {
                    return $threadRunResponse;
                }
                
                // one user can only one thread at a time
                $conversation = Conversation::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'assistant_id' => env('OPENAI_ASSISTANT_ID'),
                    ],
                    [
                        'thread_id' => $threadRunResponse['data']['id'],
                    ]
                );
            }

            $assistantId = $conversation->assistant_id;
            $threadId = $conversation->thread_id;

            //Create message for thread
            $messageResponse = $this->aiService->createMessage($threadId, $content);

            if($messageResponse['status'] == 'error') return $messageResponse;
            

            $messageResponse = $messageResponse['data'];
            
            logger('message response: ', $messageResponse);

            $createdMessageId = $this->saveMessagesDB(
                $conversation->id,
                null,
                $messageResponse['id'],
                'prompt',
                $tokens = []
            );

        $agents = json_encode($this->getAgents($user));
        $branch = json_encode($this->getBranches($user));
        $clients = json_encode($this->getClients($user));
        $invoices = json_encode($this->getInvoices($user));
        $tasks = json_encode($this->getTasks($user));


        $data = [
            'assistant_id' => $assistantId,
            'additional_instructions' => "Address the user as" . $user->name . ", but you don't need to call his name every time you respond. My user id is " . $user->id . ".
                                        Today's date is " . date('Y-m-d') . ".
                                        This is the list of agents for this user: " . $agents . ".
                                        This is the list of branches for this user: " . $branch . ".
                                        This is the list of clients for this user: " . $clients . ".
                                        This is the list of invoices for this user: " . $invoices . ".
                                        This is the list of tasks for this user: " . $tasks . ".",
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ];
            //Run thread
            $runResponse = $this->aiService->createRun($threadId, $data);

            if($runResponse['status'] === 'error') return $runResponse; 

            $runId = $runResponse['data']['id'];

            logger('create run response: ', $runResponse);

            $this->updateMessageDB($createdMessageId, ['run_id' => $runId]);

            //Run status check, if status is complete, get messages and show to user
            $checkRunResponse = $this->aiService->checkRun($threadId, $runId);

            if($checkRunResponse['status'] === 'error') return $checkRunResponse;
            
            logger('check run response: ', $checkRunResponse);

            $toolOutputs = [];

            if( $checkRunResponse['status']==='requires_action'){
                foreach($checkRunResponse['data']['required_action']['submit_tool_outputs']['tool_calls'] as $tool){
                    $toolId = $tool['id'];
                    $toolName = $tool['function']['name'];
                    $toolArguments = json_decode($tool['function']['arguments'], true);
                    $functionResponse = $this->callFunction($toolName, $toolArguments, $userId);

                    if(isset($functionResponse['error'])) return $functionResponse;

                    $toolOutputs[] = [
                        'tool_call_id' => $toolId,
                        'output' => (string)$functionResponse['success'],
                    ];
                }

                $toolOutputResponse = $this->aiService->submitToolOutputs($threadId, $runId, $toolOutputs);

                if($toolOutputResponse['status'] === 'error') return $toolOutputResponse;
            }

            $tokens = $checkRunResponse['data']['usage'] ?? [];

            $messages = $this->aiService->getMessages($threadId, $assistantId, $user);

            if($messages['status'] === 'error') return $messages;

            $latestMessage = $messages['data'][0];

            $answer = $latestMessage['content'][0]['text']['value'];

            if ($latestMessage['role'] == 'assistant') {
                $this->saveMessagesDB(
                    $conversation->id,
                    $runId,
                    $latestMessage['id'],
                    'answer',
                    $tokens
                );
            }

            return [
                'status' => 'success',
                'message' => 'Question asked successfully',
                'data' => $messages['data'],
            ];


        } else if($response['data']['type'] === 'action') {

            return [
                'status' => 'error',
                'message' => 'Sorry, action is not yet supported',
                'data' => [
                    'type' => $response['data']['type'],
                ]
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Invalid response. Please provide a valid response: action or question',
                'data' => $response['data']
            ];
        }
    }

    public function promptOrAction($data)
    {

        $content = [
            [
                'role' => 'user',
                'content' => 'determined if the content is prompt or action : ' . $data . ' and return the answer either , "action" or "prompt", usually the it will be a prompt, if you are not sure just default to prompt
                action usually is a command or instruction so that the system can perform an action based on the command or instruction, while prompt is a question or request for information',
            ],
            [
                'role' => 'user',
                'content' => 'example answer in json format:
                    {
                     "type": "prompt"
                    }     
                "',
            ]
        ];

        $response = $this->aiService->chatCompletionJsonResponse($content);

        if (isset($response['error'])) {
            return [
                'status' => 'error',
                'message' => 'Failed to determine the type of content',
                'data' => $response['error']
            ];
        }

        if (isset($response['choices'][0]['message']['content'])); {
            $message = $response['choices'][0]['message']['content'];

            $message = json_decode($message)->type;

            if ($message == 'action') {
                return [
                    'status' => 'error',
                    'message' => 'Sorry, action is not yet supported',
                    'data' => [
                        'type' => $message,
                    ]
                ];
            }

            if ($message !== 'prompt') {
                return [
                    'status' => 'error',
                    'message' => 'Invalid response. Please provide a valid response: action or question',
                    'data' => $message
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Prompt type identified successfully',
                'data' => [
                    'type' => $message,
                ]
            ];
        }
    }

 
    /**
     * @param int $conversationId
     * @param string $type of content
     * @param string $runId
     * @param string $messageId
     * @param array $tokens [prompt_tokens, completion_tokens, total_tokens, cache_tokens]
     * 
     * @return int $messageId
     */
    public function saveMessagesDB(int $conversationId, ?string $runId = null, string $messageId, string $type, array $tokens)
    {
        return Message::create([
            'conversation_id' => $conversationId,
            'run_id' => $runId,
            'message_id' => $messageId,
            'type' => $type,
            'prompt_tokens' => $tokens['prompt_tokens'] ?? null,
            'completion_tokens' => $tokens['completion_tokens'] ?? null,
            'total_tokens' => $tokens['total_tokens'] ?? null,
            'cache_tokens' => $tokens['prompt_token_details']['cached_tokens'] ?? null,
        ])->id;
    }

    public function updateMessageDB(int $id, array $columns)
    {
        Message::where('id', $id)->update($columns);
    }

    //TODO: Upload a file to OpenAi embeddings
    public function uploadFileToOpenAi(Request $request)
    {
        $file = $request->file('file');

        $url = config('services.open-ai.url') . '/files';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: embeddings=v1',
        ];

        $data = [
            'file' => $file,
        ];

        $response = $this->postRequest($url, $header, $data);

        return response()->json($response);
    }

    public function addFunctionTool()
    { 
        return view('ai.openai.tools');
    }

    public function storeFunctionTool(Request $request)
    {
        $request->validate($request, [
            'type' => 'required|string',
            'name' => 'required|string',
            'description' => 'required|string',
            'strict' => 'nullable|boolean',
            'parameters' => 'nullable|array',
            'parameters.type' => 'required if parameters|string',
            'parameters.properties' => 'required if parameters|array', 
            'additionalProperties' => 'nullable|boolean',
            'required' => 'nullable|array',
        ]);

        $assistantId = Conversation::where('user_id', auth()->id())->latest()->first()->assistant_id;
        $response = $this->aiService->modifyAssistant($assistantId, $request->all());

        if(isset($response['error'])) {
            logger('error: ', $response['error']);
            return Redirect::back()->with('error', 'Failed to add function tool');
        }

        return Redirect::back()->with('success', 'Function tool added successfully');
    }


    public function getUserTask(array $arguments,int $userId)
    {
        $user = User::find($userId);
        $dateFrom = $arguments['date_from'] . ' 00:00:00';
        $dateTo = $arguments['date_to'] . ' 23:59:59';
        
        if ($user->role_id == Role::ADMIN) {

            $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')
                ->where('created_at', '>=', $dateFrom)
                ->where('created_at', '<=', $dateTo)
                ->get(); // Retrieve all tasks


        } elseif ($user->role_id == Role::COMPANY) {
          
            $agents = Agent::with(['branch'=> function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            }])->get();

            $clients = Client::whereIn('agent_id', $agents->pluck('id'))->get();

            // Get all agents for this company
            $agentIds = $agents->pluck('id'); // Get all agents for this company

            $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')->whereIn('agent_id', $agentIds)
                ->where('created_at', '>=', $dateFrom)
                ->where('created_at', '<=', $dateTo)
                ->get(); // Retrieve tasks for this company


        } elseif ($user->role_id == Role::AGENT) {
            $tasks = Task::where('agent_id', $user->id)->get(); // Retrieve tasks for this agent    
 
        } 

        // if(isset($arguments['task_type'])){
        //     $tasks = $tasks->where('type', $arguments['task_type']);
        // }

        if(isset($arguments['task_status'])){
            $tasks = $tasks->where('status', $arguments['task_status']);
        }

        if(isset($arguments['task_output'])){
            $tasks = $arguments['task_output'] == 'list' ? $tasks : $tasks->count();
            logger('count task: ' . (string)$tasks);
            return (string)$tasks;
        }

        logger('list task: ', $tasks->toArray());

        return json_encode($tasks->toArray());
    }

    public function createInvoice(array $arguments)
    {

        $taskIds = $arguments['task_ids'];

        if (gettype($taskIds) == 'string') {
            $taskIdsArray = explode(',', $taskIds); // Multiple tasks
        } else {
            $taskIdsArray = $taskIds; // Single task
        }

        $selectedTasks = Task::with('invoiceDetail.invoice')->whereIn('id', $taskIdsArray)->get();

        foreach ($selectedTasks as $task) {
            if ($task->invoiceDetail) {
                return Redirect::route('tasks.index')->with('error', 'Task already invoiced!');
            }
        }

            $user = User::find($arguments['user_id']);

        $agents = collect();
        if ($user->role_id == Role::COMPANY) {
            $company = $user->company;

            $agents = Agent::with(['branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company->id);
            }])->get();
        } elseif ($user->role_id == Role::AGENT) {
            $agent = $user->agent;
            $company = Company::find($agent->branch->company_id);
        }

        $invoiceSequence = InvoiceSequence::lockForUpdate()->first();

        if (!$invoiceSequence) {
            $invoiceSequence = InvoiceSequence::create(['current_sequence' => 1]);
        }

        $currentSequence = $invoiceSequence->current_sequence;
        $invoiceController = new InvoiceController();
        $invoiceNumber = $invoiceController->generateInvoiceNumber($currentSequence);

        $invoiceSequence->current_sequence++;
        $invoiceSequence->save();

        $invoiceController->storeNotification([
            'user_id' => $user->id,
            'title' => 'Invoice' . $invoiceNumber . ' Created By ' . $user->name,
            'message' => 'Invoice ' . $invoiceNumber . ' has been created.'
        ]);

        // Fetch tasks
        // Handle client association
        if ($selectedTasks->count() > 0) {
            $clientIds = $selectedTasks->pluck('client_id')->unique();
            $agentIds =  $selectedTasks->pluck('agent_id')->unique();
            $selectedAgent = Agent::find($agentIds->first());

            if ($clientIds->count() >= 1) {
                $selectedClient = Client::find($clientIds->first());
            } else {
                $selectedClient = null; // Handle multi-client case
            }
        } else {
            $selectedClient = null; // No tasks selected
            $selectedAgent = null;
        }

        // if selected agent is null, get all agents under the company if the user is a company, if not get the agent data from the user
        $agentId =  $selectedAgent == null ? $user->role_id == Role::COMPANY ? $agentsId = array_map(function ($agent) {
            return $agent['id'];
        }, $agents->toArray()) : $user->agent->id : $selectedAgent->id;

        $clientId = $selectedClient ? $selectedClient->id : null;


        return 'invoice created: ' . $invoiceNumber;
    
    }

    public function callFunction($functionName, $arguments,int $userId)
    {
        switch ($functionName) {
            case 'get_user_tasks':
                return [
                    'success' => $this->getUserTask($arguments,$userId),
                ];
            case 'get_clients':
                return [
                    'success' => $this->getClient($arguments),
                ];
            case 'create_invoice':
                

                return [
                    'success' => $this->createInvoice($arguments),
                ];
            default:
                return ['error' => 'Function not implemented.'];
        }
    }

    public function steps()
    {
        $runsId = [];
        $runs = [];
        
        if ($conversation = Conversation::where('user_id', auth()->id())->latest()->first()) {
            $threadId = $conversation->thread_id;
        }
        
        if($threadId){
            $runsId = $this->aiService->listRun($threadId)['data'];
        }

        if(count($runsId) > 0)
        {
            foreach($runsId as $run){
                $runId = $run['id'];
                $this->aiService->listStep($threadId, $runId);

                $runs[$runId] = $this->aiService->listStep($threadId, $runId)['data'];
            }
        }

        return view('ai.openai.steps', compact('runs'));
      
    }

    // FUNCTION FOR GETTING INFORMATION
    public function getAgents(User $user) : array
    {
        if($user->role_id == Role::ADMIN){
            return Agent::get()->select('id', 'name', 'branch_id')->toArray();
        } else if($user->role_id == Role::COMPANY) {

            return Agent::with(['branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            }])->get()->select('id', 'name', 'branch_id')->toArray();

        } else if ($user->role_id == Role::AGENT) {
            return Agent::where('id', $user->agent->id)->get();
        } else {
            return [];
        }
    }

    public function getBranches(User $user) : array
    {
        if($user->role_id == Role::ADMIN){
            return Branch::select('id', 'name', 'company_id')->toArray();
        } else if($user->role_id == Role::COMPANY) {
            return Branch::where('company_id', $user->company->id)->get('id', 'name', 'company_id')->toArray();
        } else if ($user->role_id == Role::BRANCH) {
            return Branch::where('id', $user->branch->id)->get('id', 'name', 'company_id')->toArray();
        } else {
            return [];
        }
    }

    public function getClients(User $user) : array
    {

        if($user->role_id == Role::ADMIN){
            $client = Client::select('id','name')->all();
        } else if ($user->role_id == Role::COMPANY) {
            $client = Client::select('id','name')->with(['agent.branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            }]);


            $client = $client->get();
        } else if ($user->role_id == Role::BRANCH) {
            $client = Client::select('id','name')->with(['agent' => function ($query) use ($user) {
                $query->where('branch_id', $user->branch_id);
            }])->get();
        } else {
            $client = Client::select('id','name')->where('agent_id', $user->id)->get();
        }

        return $client->toArray();
    }

    public function getInvoices(User $user) : array
    {

        if($user->role_id == Role::ADMIN){
            return Invoice::with('invoiceDetails','invoicePartials')->get()->select('invoice_number', 'client_id', 'agent_id', 'amount', 'status', 'invoice_date', 'paid_date', 'due_date', 'invoiceDetails')->toArray();
        } else if($user->role_id == Role::COMPANY) {

            $agentsId = Agent::with(['branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            }])->get()->pluck('id');
            return Invoice::with('invoiceDetails', 'invoicePartials')->get()->select('invoice_number', 'client_id', 'agent_id', 'amount', 'status', 'invoice_date', 'paid_date', 'due_date', 'invoiceDetails', 'invoicePartials')->whereIn('agent_id', $agentsId)->toArray();

        } else if ($user->role_id == Role::AGENT) {
            return Invoice::with('invoiceDetails', 'invoicePartials')->get()->select('invoice_number', 'client_id', 'agent_id', 'amount', 'status', 'invoice_date', 'paid_date', 'due_date', 'invoiceDetails', 'invoicePartials')->where('agent_id', $user->agent->id)->toArray();
        } else {
            return [];
        }
   }

   public function getTasks(User $user) : array
   {
        if($user->role_id == Role::ADMIN){
            return Task::get()->select('id', 'agent_id', 'client_id', 'agent_id', 'type', 'status', 'reference', 'price', 'tax', 'total')->toArray();
        } else if($user->role_id == Role::COMPANY) {
            $agents = Agent::with(['branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            }])->get();

            $agentIds = $agents->pluck('id');

            return Task::whereIn('agent_id', $agentIds)->get()->select('id', 'agent_id', 'client_id', 'agent_id', 'type', 'status', 'reference', 'price', 'tax', 'total')->toArray();
        } else if ($user->role_id == Role::AGENT) {
            return Task::where('agent_id', $user->agent->id)->get()->select('id', 'agent_id', 'client_id', 'agent_id', 'type', 'status', 'reference', 'price', 'tax', 'total')->toArray();
        } else {
            return [];
        }
   }

}
