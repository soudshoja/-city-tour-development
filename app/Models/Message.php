<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'run_id',
        'message_id',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cache_tokens',
        'type',
        'role',
        'content',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
