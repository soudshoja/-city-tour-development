<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ClientAssignmentRequest extends Model
{
    use HasFactory;

    protected $table = 'client_assignment_requests';

    protected $fillable = [
        'request_token',
        'owner_agent_id',
        'requesting_agent_id',
        'client_id',
        'reason',
        'status',
        'expires_at',
        'processed_at',
        'processed_by',
        'process_note'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED = 'denied';
    const STATUS_EXPIRED = 'expired';

    /**
     * Relationships
     */
    public function ownerAgent()
    {
        return $this->belongsTo(Agent::class, 'owner_agent_id');
    }

    public function requestingAgent()
    {
        return $this->belongsTo(Agent::class, 'requesting_agent_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeActive($query)
    {
        return $query->pending()->notExpired();
    }

    public function scopeByToken($query, $token)
    {
        return $query->where('request_token', $token);
    }

    /**
     * Methods
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at > now();
    }

    public function isExpired()
    {
        return $this->expires_at <= now() && $this->status === self::STATUS_PENDING;
    }

    public function approve($userId, $note = null)
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'processed_at' => now(),
            'processed_by' => $userId,
            'process_note' => $note
        ]);
    }

    public function deny($userId, $note = null)
    {
        $this->update([
            'status' => self::STATUS_DENIED,
            'processed_at' => now(),
            'processed_by' => $userId,
            'process_note' => $note
        ]);
    }

    public function markExpired()
    {
        if ($this->isExpired()) {
            $this->update(['status' => self::STATUS_EXPIRED]);
        }
    }

    /**
     * Automatically mark expired requests
     */
    public static function markAllExpired()
    {
        return self::where('status', self::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => self::STATUS_EXPIRED]);
    }

    /**
     * Generate unique request token
     */
    public static function generateToken()
    {
        do {
            $token = \Illuminate\Support\Str::random(32);
        } while (self::where('request_token', $token)->exists());
        
        return $token;
    }
}
