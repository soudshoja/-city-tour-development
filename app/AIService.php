<?php

namespace App;

use App\Http\Traits\HttpRequestTrait;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Role;
use App\Models\User;
use Exception;

class AIService 
{

    use HttpRequestTrait;

    private $apiKey;
    private $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.open-ai.key');
        $this->apiUrl = config('services.open-ai.url');
    }

    public function createAssistant() : array
    {
        $url = $this->apiUrl . '/assistants';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $data = [
            'model' => 'gpt-4o-mini',
            'name' => 'City Tour Travel Assistant',
            'description' => 'Assistant for City Tour travel agency system',
            'instructions' => 'You are an assistant in a travel agency system. You will learn everything about this system and help users to get the information they need. You can ask for help if you need it.',
            'metadata' => [
                'user_id' => 'user-test',
            ],
            'temperature' => 0.5,
        ];

        $response = $this->postRequest($url, $header, json_encode($data));

        if(isset($response['error'])){
            return [
                'status' => 'error',
                'message' => 'Failed to create assistant',
                'data' => $response,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Assistant created successfully',
            'data' => $response,
        ];
    }

    public function modifyAssistant(string $assistantId, array $data) : array
    {
        $url = $this->apiUrl . '/assistants/' . $assistantId;
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->postRequest($url, $header, json_encode($data));

        if(isset($response['error'])){
            return [
                'status' => 'error',
                'message' => 'Failed to modify assistant',
                'data' => $response,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Assistant modified successfully',
            'data' => $response,
        ];
    }

    public function createThread(User $user) : array
    {
        $url = $this->apiUrl . '/threads';
        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];
        $data = [
            'messages' => [
                [
                    'role' => 'assistant',
                    'content' => 'Hello, I am your assistant. How can I help you today?',
                ],
            ],
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ];

        $response = $this->postRequest($url, $header, json_encode($data));

        logger('create thread response: ', $response);

        return [
            'status' => isset($response['id']) ? 'success' : 'error',
            'message' => isset($response['id']) ? 'Thread created successfully' : 'Failed to create thread',
            'data' => $response,
        ];
    }

    public function retrieveThread(string $threadId) : array
    {
        $url = $this->apiUrl . '/threads/' . $threadId;
        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->getRequest($url, $header);

        if(isset($response['error'])) {
            return [
                'status' => 'error',
                'message' => 'Thread not found',
                'data' => $response,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Thread retrieved successfully',
            'data' => $response,
        ];
    }
    
    public function deleteThread(string $threadId) : array
    {
        $url = $this->apiUrl . '/threads/' . $threadId;
        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->deleteRequest($url, $header);

        if(isset($response['error'])) {
            return [
                'status' => 'error',
                'message' => 'Thread not found',
                'data' => $response,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Thread deleted successfully',
            'data' => $response,
        ];
    }

    public function createMessage(string $threadId, string $message) : array
    {
        $url = $this->apiUrl . '/threads/' . $threadId . '/messages';
        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];
        $data = [
            'role' => 'user',
            'content' => $message,
        ];

        $messageResponse =  $this->postRequest($url, $header, json_encode($data)); 

        return [
            'status' => isset($messageResponse['id']) ? 'success' : 'error',
            'message' => isset($messageResponse['id']) ? 'Message created successfully' : 'Failed to create message',
            'data' => $messageResponse,
        ];
    }

    public function getMessages(string $threadId, string $assistantId, User $user): array
    {
        $url = $this->apiUrl . '/threads/' . $threadId . '/messages';

        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];


        $response = $this->getRequest($url, $header);
       
        if(isset($response['error']['message'])){
            if(str_contains($response['error']['message'], 'No thread found')){
               
                $createThread = $this->createThread($user);

                if($createThread['status'] == 'error'){
                    return $createThread;
                }

                logger('create thread response: ', $createThread['data']);

                $threadId = $createThread['data']['id'];

                Conversation::updateOrCreate([
                    'user_id' => $user->id,
                    'assistant_id' => env('OPENAI_ASSISTANT_ID'),
                    'thread_id' => $threadId,
                ]);

                $response = $this->getMessages($threadId, $assistantId, $user);

            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to retrieve messages',
                    'data' => $response,
                ];
            }
        }

        logger('get messages response: ', $response);

        return [
            'status' => 'success',
            'message' => 'Messages retrieved successfully',
            'data' => $response['data']
        ];
    }

    public function createRun(string $threadId, array $data): array
    {
        $url = $this->apiUrl . '/threads/' . $threadId . '/runs';
        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->postRequest($url, $header, json_encode($data));
        
        return [
            'status' => isset($response['id']) ? 'success' : 'error',
            'message' => isset($response['id']) ? 'Run created successfully' : 'Failed to create run',
            'data' => $response,
        ];
    }

    public function checkRun(string $threadId, string $runId): array
    {
        $url = $this->apiUrl . '/threads/' . $threadId . '/runs/' . $runId;

        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];


        $countCheck = 0;

        while (true) {

            try {

                $response = $this->getRequest($url, $header);

                if (isset($response['status']) ? $response['status'] === 'completed' : false) {
                    return [
                        'status' => 'success',
                        'message' => 'Run completed successfully',
                        'data' => $response
                    ];
                } if (isset($response['status']) ? $response['status'] === 'requires_action' : false) {
                    return [
                        'status' => 'requires_action',
                        'message' => 'Run requires action',
                        'data' => $response,
                    ];
                } else

                if ($countCheck > 5) {
                    return [
                        'status' => 'error',
                        'message' => 'Run is taking too long to complete',
                        'data' => $response,
                    ];
                }
            } catch (Exception $e) {
                throw $e;
            }

            sleep(1);
            $countCheck++;
        }
    }
    
    public function listRun($threadId): array
    {
        $url = $this->apiUrl . '/threads/' . $threadId . '/runs?limit=10';
        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->getRequest($url, $header);

        if(isset($response['error'])){
            return [
                'status' => 'error',
                'message' => 'Failed to list run',
                'data' => $response,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Run listed successfully',
            'data' => $response['data']
        ];
    }
    
    public function cancelRun($threadId, $runId): array
    {
        $url = $this->apiUrl . '/threads/' . $threadId . '/runs/' . $runId . '/cancel';
        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'OpenAI-Beta: assistants=v2',
        ];

        $cancelRunResponse = $this->postRequest($url, $header, null);

        if(isset($cancelRunResponse['error'])){
            return [
                'status' => 'error',
                'message' => 'Failed to cancel run',
                'data' => $cancelRunResponse,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Run cancelled successfully',
            'data' => $cancelRunResponse,
        ];

    }
    
    public function listStep(string $threadId, string $runId): array
    {
        $url = $this->apiUrl . '/threads/' . $threadId . '/runs/' . $runId . '/steps';
        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->getRequest($url, $header);

        if(isset($response['error'])){
            return [
                'status' => 'error',
                'message' => 'Failed to step run',
                'data' => $response,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Run stepped successfully',
            'data' => $response['data']
        ];
    }
    
    public function retrieveStep(string $threadId, string $runId, string $stepId): array
    {
        $url = $this->apiUrl . '/threads/' . $threadId . '/runs/' . $runId . '/steps';
        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->getRequest($url, $header);

        if(isset($response['error'])){
            return [
                'status' => 'error',
                'message' => 'Failed to step run',
                'data' => $response,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Run stepped successfully',
            'data' => $response['data']
        ];
    }

    public function embedding(string $query)
    {
        $url = $this->apiUrl . '/embeddings';
        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        $data = [
            'model' => 'text-embedding-ada-002',
            'input' => $query,
            'encoding_format' => 'float',
        ];

        $response = $this->postRequest($url, $header, json_encode($data));

        if(isset($response['error'])){
            return [
                'status' => 'error',
                'message' => 'Failed to get embeddings',
                'data' => $response,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Embeddings retrieved successfully',
            'data' => $response['data'],
        ];
    }


    public function submitToolOutputs(string $threadId, string $runId, array $toolOutputs)
    {
        $url = $this->apiUrl . "/threads/{$threadId}/runs/{$runId}/submit_tool_outputs";
        $header = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];


        $data = [
            'tool_outputs' => $toolOutputs,
        ];

        $response = $this->postRequest($url, $header, json_encode($data));
        
        logger('submit tool outputs response: ', $response);

        return [
            'status' => isset($response['id']) ? 'success' : 'error',
            'message' => isset($response['id']) ? 'Tool output submitted successfully' : 'Failed to submit tool output',
            'data' => $response,
        ];
    }
}

