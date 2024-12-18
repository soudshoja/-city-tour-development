<?php

namespace App\Livewire;

use App\Http\Controllers\OpenAiController;
use App\Models\Conversation;
use App\Models\Message;
use Livewire\Component;

class Chat extends Component
{
    public $conversation;
    public $messages = [];
    public $prompt;
    public $error = null;

    // public function getConversation(int $userId){
    //     $this->conversation = Conversation::with('messages')->where('user_id', $userId)->where('assistant_id', env('OPENAI_ASSISTANT_ID'))->first();
    // }

    public function mount(){
    }

    public function getMessage()
    {
       
        $openAiController = new OpenAiController();
        $conversation = Conversation::where('user_id', auth()->user()->id)->where('assistant_id', env('OPENAI_ASSISTANT_ID'))->latest()->first();
        
        if(!$conversation){
            $conversation = new Conversation();
            $conversation->user_id = auth()->user()->id;
            $conversation->assistant_id = env('OPENAI_ASSISTANT_ID');
            $conversation->save();
        }

        if(!isset($conversation->thread_id)){
            $threadCreated = $openAiController->createThread();

            if ($threadCreated['status'] == 'error') {
                $this->error = $threadCreated['message'];
                return;
            }
            $conversation->thread_id = $threadCreated['data']['id'];
            $conversation->save();
        }

        $messages = $openAiController->getMessages($conversation->thread_id);

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
        $rand = rand(0, 1);

        if($this->prompt == null || $this->prompt == ''){
            $this->error = 'Please enter a message';
            return;
        }

        $openAiController = new OpenAiController();
        $response = $openAiController->askOpenAi($this->prompt, auth()->user()->id);

        if($response['status'] == 'error'){
           
            logger('error',$response);

            $this->error = $response['message'];
            return;
        }

        logger('response: \n',$response);

        $this->messages = $response['data'];
    }

    public function render()
    {
        return view('livewire.chat');
    }
}
