<?php

namespace App\Livewire;

use App\AIService;
use App\Http\Controllers\OpenAiController;
use App\Models\Conversation;
use App\Models\Message;
use Livewire\Component;
use App\Models\Agent;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\Role;

class Chat extends Component
{
    public $conversation;
    public $messages = [];
    public $prompt;
    public $error = null;
    private $aiService;
    // public function getConversation(int $userId){
    //     $this->conversation = Conversation::with('messages')->where('user_id', $userId)->where('assistant_id', env('OPENAI_ASSISTANT_ID'))->first();
    // }


    public function boot(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function loadMessages()
    {
      
        $conversation = Conversation::where('user_id', auth()->user()->id)->where('assistant_id', env('OPENAI_ASSISTANT_ID'))->latest()->first();
       
        // return if no conversation, or if conversation has no thread_id or assistant_id
        if($conversation == null ? true : !($conversation->thread_id && $conversation->assistant_id)) return;

        $messages = $this->aiService->getMessages($conversation->thread_id, $conversation->assistant_id, auth()->user());

        logger('messages: \n',$messages);

        if($messages['status'] == 'error'){
            $this->error = $messages['message'];
            return;
        }

        $this->messages = $messages['data'];
    }

    public function sendMessage()
    {
        $this->error = null;

        if($this->prompt == null || $this->prompt == ''){
            $this->error = 'Please enter a message';
            return;
        }

        $openAiController = new OpenAiController($this->aiService);
        $response = $openAiController->askOpenAi($this->prompt, auth()->user()->id);

        if($response['status'] == 'error'){
           
            logger('error',$response);

            return $this->error = $response['message'];
        }

        $this->messages = $response['data'];
    }

    public function render()
    {
        $user = Auth::user();

        if ($user->role_id == Role::ADMIN) {

        } elseif ($user->role_id == Role::COMPANY) {
            // Company can only see trips with tasks under their agents
            $agents = Agent::with(['branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company->id);
            }])->get();

            $agentIds = Agent::with(['branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company->id);
            }])->pluck('id');

            $clients = Client::whereIn('agent_id', $agentIds)->get();

        };

        // $agents = Agent::with('branch')->get();
        // $clients = Client::with('agent')->get();
        return view('livewire.chat', compact('agents', 'clients'));
    }
}
