<?php

namespace App\Console\Commands;

use App\Http\Controllers\TaskController;
use App\Http\Traits\HttpRequestTrait;
use App\Models\Company;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class getMagicHolidayReservationList extends Command
{
    use HttpRequestTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:magic-holiday-reservation';
    protected $companies;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Magic Holiday Reservation List';


    public function __construct()
    {
        parent::__construct();
        $this->companies = Company::whereHas('suppliers', function ($query) {
            $query->where('name', 'Magic Holiday');
        })->with(['suppliers' => function ($query) {
            $query->where('name', 'Magic Holiday');
        }, 'suppliers.credentials'])->get();
   }      

    public function handle()
    {
        foreach ($this->companies as $company) {

            $companyId = $company->id;

            $this->info("Processing company: " . $company->name);

            if ($company->suppliers->isNotEmpty()) {

                foreach ($company->suppliers as $supplier) {
                    
                    $this->info("Magic Holiday found for company: " . $company->name);

                    if ($supplier->credentials->isEmpty()) {
                       $this->error('Magic Holiday credentials not found for company: ' . $company->name);
                       continue;
                    } 
                
                    if($supplier->name == 'Magic Holiday'){

                        foreach ($supplier->credentials as $credential) {

                            $this->info("Processing Magic Holiday credentials for company: " . $company->name);

                            if($credential->type == 'oauth'){

                                $this->info("Processing Magic Holiday OAuth credentials for company: " . $company->name);

                                if($credential->client_id == null || $credential->client_secret == null){
                                    $this->error('Magic Holiday OAuth credentials not found for company: ' . $company->name);
                                    continue;
                                }

                                $response = $this->getMagicHoliday($credential->client_id, $credential->client_secret);

                                $data = json_decode($response->getContent(), true);

                                Log::channel('magic_holidays')->info('Magic Holiday response: ', $data);

                                if (isset($data['error'])) {
                                    Log::channel('magic_holidays')->error('Error getting task from supplier: ', $data['error']);
                                    $this->error('Error getting task from supplier: ' . $data['error']);
                                    return 0;
                                }

                                if (isset($data['status']) && $data['status'] == 'error') {
                                    Log::channel('magic_holidays')->error('Error getting task from supplier: ', $data);
                                    $this->error('Error getting task from supplier: ' . $data['data']['detail']);
                                    return 0;
                                }

                                $data = $data['data'];

                                if (isset($data['_embedded'])) {
                                    $this->info('Magic Holiday task received successfully');
                                    foreach ($data['_embedded']['reservation'] as $reservation) {
                                        Log::channel('magic_holidays')->info('Processing reservation: ', $reservation);
                                        $taskController = new TaskController(); 

                                        try{
                                            $response = $taskController->processSingleReservation($reservation, null, $companyId);

                                            if ($response['status'] == 'error') {
                                                $this->error('Error processing reservation: ' . $response['message']);
                                            }

                                            if (isset($reservation['id'])) {
                                                $this->magicReserveWebhook($credential->client_id, $credential->client_secret, $reservation['id']);
                                            } else {
                                                $this->error('Reservation ID not found in the response');
                                            }

                                        } catch (Exception $e) {
                                            Log::channel('magic_holidays')->error('Error processing reservation: ', ['error' => $e->getMessage()]);
                                            $this->error('Error processing reservation: ' . $e->getMessage());
                                            continue;
                                        }


                                        $this->info('Reservation processed successfully: ' . $reservation['id']);
                                    }
                                } else {
                                    $this->error('No reservations found in the response');
                                }
                            } else {
                                $this->error('Unsupported credential type for Magic Holiday: ' . $credential->type);
                            }
                        }
                    }
                }
            } else {
                $this->error("No suppliers found for this company.");
            }

        }

        return 1;
    }

    public function getMagicHoliday($clientId, $clientSecret)
    {
        $url = config('services.magic-holiday.url') . '/reservationsApi/v1/reservations';

        $scopes = ['read:reservations'];

        $response = $this->magicApiRequest(
            $clientId,
            $clientSecret,
            'GET',
            $url,
            [],
            [],
            $scopes
        );

        return response()->json($response);
    }

    public function magicApiRequest(
        string $clientId,
        string $clientSecret,
        string $method = 'GET',
        string $url,
        array $header = [],
        array $data = [],
        array $scopes = ['read:reservations']
    )
    {

        $responseCredential = $this->getClientCredential(
            $clientId,
            $clientSecret,
            $scopes
        );

        if(isset($responseCredential['error'])){
            return [
                'status' => 'error',
                'data' => $responseCredential,
                'message' => $responseCredential['error']
            ];
        }

        $accessToken = $responseCredential['token_type'] . ' ' . $responseCredential['access_token'];

        $header = [
            'Authorization: ' . $accessToken,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        Log::channel('magic_holidays')->info('Request', [
            'method' => $method,
            'url' => $url,
            'header' => $header,
            'data' => $data
        ]);

        $data = json_encode($data);
        
         
         switch ($method) {
            case 'GET':
            $response = $this->getRequest($url, $header);
            break;
            case 'POST':
            $response = $this->postRequest($url, $header, $data);
            break;
            case 'PUT':
            $response = $this->putRequest($url, $header, $data);
            break;
            case 'DELETE':
            $response = $this->deleteRequest($url, $header);
            break;
            default:
            throw new \InvalidArgumentException("Unsupported HTTP method: $method");
        }

        Log::channel('magic_holidays')->info('Response', $response);

        if(isset($response['status']) && $response['status'] !== 200){
            return [
                'status' => 'error',
                'data' => $response,
                'message' => $response['detail']
            ];
        }

        return [
            'status' => 'success',
            'data' => $response
        ];
    }

    public function getClientCredential(
        string $clientId,
        string $clientSecret,
        array $scopes
    )
    {
        $tokenUrl = config('services.magic-holiday.token_url');

        $data = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
            'scope' => $scopes,
        ];

        Log::channel('magic_holidays')->info('Credential Request', [
            'token_url' => $tokenUrl,
            'data' => $data
        ]);

        $response = $this->postRequest($tokenUrl, [], $data);

        Log::channel('magic_holidays')->info('Credential Response', $response);

        return $response;
    }


    public function magicReserveWebhook($clientId, $clientSecret, $id)
    {

        $data = $this->getClientCredential($clientId, $clientSecret, ['write:reservations-webhooks']);


        if(isset($data['error'])){
            return;
        } 

        $accessToken = $data['token_type'] . ' ' . $data['access_token'];

        $url = config('services.magic-holiday.url') . '/reservationsApi/v1/reservations/' . $id . '/webhooks';

        $header = [
            'Authorization: ' . $accessToken,
            'Accept: application/json',
        ];
        $data = [
            'url' => route('suppliers.magic-webhook-callback'),
        ];
        
        Log::channel('magic_holidays')->info('Magic Holiday Webhook Request', [
            'url' => $url,
            'header' => $header,
            'data' => $data
        ]);

        $response = $this->magicApiRequest(
            $clientId,
            $clientSecret,
            'PUT',
            $url,
            $header,
            $data,
            ['write:reservations-webhooks']
        );
        
        Log::channel('magic_holidays')->info('Magic Holiday Webhook Response', $response);

        return;
    }
}
