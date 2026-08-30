<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reminder extends Model
{
    protected $fillable = [
        'target_type',
        // W6.U "Reminders" (owner addition, 2026-08-28): additive columns from
        // 2026_08_30_100001_w6u_add_task_fields_to_reminders_table.php. 'reminder_kind' stays null
        // on every pre-existing row ("general", per that migration's own docblock) -- only
        // reminder:generate-deadlines ever sets it to 'ticketing_deadline'.
        'reminder_kind',
        'task_id',
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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
