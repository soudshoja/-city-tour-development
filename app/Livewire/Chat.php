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

    public function getConversation(int $userId){
        $this->conversation = Conversation::where('user_id', $userId)->get();
        $this->messages = $this->conversation->messages;
    }

    public function sendMessage(string $content){
        $conversationId = Conversation::create([
            'user_id' => auth()->id(),
        ])->id;

        $createdMessage = Message::create([
            'content' => $content,
            'conversation_id' => $conversationId,
            'type' => 'question',
        ]);

        $openAiController = new OpenAiController();
        $response = $openAiController->askOpenAi($content, $conversationId);

        if($response['status'] == 'success'){
            $this->conversation = Conversation::find($conversationId);
            $this->messages = $this->conversation->messages;
        }

    }
    public function render()
    {
        return view('livewire.chat');
    }
}
