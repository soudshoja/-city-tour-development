<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reminder extends Model
{
    protected $fillable = [
        'target_type',
        'invoice_id',
        'payment_id',
        'agent_id',
        'client_id',
        'message',
        'group_id',
        'send_to_client',
        'send_to_agent',
        'frequency',
        'value',
        'unit',
        'scheduled_at',
        'sent_at',
        'status',
        'is_active',
    ];

    protected static function boot() 
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->group_id)) {
                $model->group_id = Str::uuid()->toString();
            }
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
