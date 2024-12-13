<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatCompletion extends Model
{
     protected $fillable = [
        'conversation_id',
        'chat_id',
        'object',
        'created',
        'model',
        'system_fingerprint',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'reasoning_tokens',
        'accepted_prediction_tokens',
        'rejected_prediction_tokens',
     ];

     public function message(){
        return $this->belongsTo(Message::class);
     }
}
