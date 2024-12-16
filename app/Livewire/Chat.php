<?php

namespace App\Livewire;

use App\Http\Controllers\OpenAiController;
use App\Models\Conversation;
use App\Models\Message;
use Livewire\Component;

class Chat extends Component
{
    public $conversation;
    public $messages;
    public $prompt;
    public $loading = false;

    // public function getConversation(int $userId){
    //     $this->conversation = Conversation::with('messages')->where('user_id', $userId)->where('assistant_id', env('OPENAI_ASSISTANT_ID'))->first();
    // }

    public function mount(){
        $openAiController = new OpenAiController();
        $conversation = Conversation::where('user_id', auth()->user()->id)->where('assistant_id', env('OPENAI_ASSISTANT_ID'))->first();

        $this->messages = $openAiController->getMessages($conversation->thread_id)['data'];
    }

    public function getMessage($parameter)
    {
    }

    public function sendMessage()
    {
        if($this->prompt == null || $this->prompt == ''){
            return;
        }

        $this->loading = true;
        
        $openAiController = new OpenAiController();
        $this->messages = $openAiController->askOpenAi($this->prompt, auth()->user()->id);
    }

    public function render()
    {
        return view('livewire.chat');
    }
}
