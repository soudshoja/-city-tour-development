<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskActionRequest extends Model
{
    use HasFactory;

    protected $table = 'task_action_requests';

    protected $fillable = [
        'request_token',
        'task_id',
        'original_task_id',
        'client_id',
        'owner_agent_id',
        'actor_agent_id',
        'action_type',
        'bundled_task_ids',
        'notify_only',
        'status',
        'escalated_at',
        'processed_at',
        'processed_by',
        'processed_via',
        'process_note',
    ];

    protected $casts = [
        'bundled_task_ids' => 'array',
        'notify_only' => 'boolean',
        'escalated_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED = 'denied';
    const STATUS_AUTO_APPROVED = 'auto_approved';
    const STATUS_EXPIRED = 'expired';

    const ACTION_REFUND = 'refund';
    const ACTION_VOID = 'void';
    const ACTION_REISSUE = 'reissue';

    const VIA_WHATSAPP = 'whatsapp';
    const VIA_WEB = 'web';
    const VIA_API = 'api';
    const VIA_ADMIN_OVERRIDE = 'admin_override';
    const VIA_CRON = 'cron';

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function originalTask()
    {
        return $this->belongsTo(Task::class, 'original_task_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function ownerAgent()
    {
        return $this->belongsTo(Agent::class, 'owner_agent_id');
    }

    public function actorAgent()
    {
        return $this->belongsTo(Agent::class, 'actor_agent_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForOwnerUser($query, int $userId)
    {
        return $query->whereHas('ownerAgent', fn ($q) => $q->where('user_id', $userId));
    }

    public function scopeByToken($query, string $token)
    {
        return $query->where('request_token', $token);
    }

    public function scopeMostRecentPendingForUser($query, int $userId)
    {
        return $query->pending()->forOwnerUser($userId)->orderByDesc('created_at');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_DENIED,
            self::STATUS_AUTO_APPROVED,
            self::STATUS_EXPIRED,
        ], true);
    }

    public function approve(?int $userId = null, string $via = self::VIA_WEB, ?string $note = null, bool $auto = false): void
    {
        $this->update([
            'status' => $auto ? self::STATUS_AUTO_APPROVED : self::STATUS_APPROVED,
            'processed_at' => now(),
            'processed_by' => $userId,
            'processed_via' => $via,
            'process_note' => $note,
        ]);
    }

    public function deny(?int $userId = null, string $via = self::VIA_WEB, ?string $note = null): void
    {
        $this->update([
            'status' => self::STATUS_DENIED,
            'processed_at' => now(),
            'processed_by' => $userId,
            'processed_via' => $via,
            'process_note' => $note,
        ]);
    }

    /**
     * Affected task ids = the primary task_id plus any bundled ones.
     */
    public function affectedTaskIds(): array
    {
        $ids = [$this->task_id];
        if (is_array($this->bundled_task_ids)) {
            foreach ($this->bundled_task_ids as $id) {
                $ids[] = (int) $id;
            }
        }
        return array_values(array_unique($ids));
    }

    public static function generateToken(): string
    {
        do {
            $token = \Illuminate\Support\Str::random(32);
        } while (self::where('request_token', $token)->exists());
        return $token;
    }
}
