<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'run_id',
        'status',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cache_tokens'
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
