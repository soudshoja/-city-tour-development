<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reminder extends Model
{
    /** {@see \App\Services\Reminders\ReminderOptions::KINDS} is the canonical list this column's
     *  values are drawn from -- kept here too as the model-level contract every writer should
     *  target, since the column itself stays a plain string (see the P2.5.I migration's own
     *  docblock for why an enum was not used: existing rows/writers already write arbitrary or
     *  null values here and a hard enum would break them). */
    public const KIND_OVERDUE_INVOICE = 'overdue_invoice';

    public const KIND_STATEMENT_BALANCE = 'statement_balance';

    public const KIND_TICKETING_DEADLINE = 'ticketing_deadline';

    public const KIND_COMMISSION_UNEARNED = 'commission_unearned';

    public const KIND_PAYMENT_LINK_UNINVOICED = 'payment_link_uninvoiced';

    public const KIND_TASK_UNASSIGNED = 'task_unassigned';

    public const KIND_TASK_UNINVOICED = 'task_uninvoiced';

    public const KIND_CUSTOM = 'custom';

    public const STATUS_SENT = 'sent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    /** P2.5.I (p2_5-brief.md §P2.5.I) -- new terminal state: a pending row whose target already
     *  resolved (paid/ticketed/void) before it ever fired, or a group row past its
     *  `number_of_reminder` cap. Never sent, never counted as a failure. */
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
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
        'number_of_reminder',
        'scheduled_at',
        'sent_at',
        'status',
        'is_active',
        // P2.5.I (p2_5-brief.md §P2.5.I) additive columns -- see that migration's own docblock.
        'channel',
        'error_message',
        'dedupe_key',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'send_to_client' => 'boolean',
        'send_to_agent' => 'boolean',
        'is_active' => 'boolean',
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
