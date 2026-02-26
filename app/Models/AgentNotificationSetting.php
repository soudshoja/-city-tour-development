<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentNotificationSetting extends Model
{
    use HasFactory;

    protected $table = 'agent_notification_settings';

    const CHANNEL_EMAIL = 'email';
    const CHANNEL_WHATSAPP = 'whatsapp';
    const CHANNEL_BOTH = 'both';

    const TYPE_TASK_CLOSE = 'task_close';

    protected $fillable = [
        'agent_id',
        'company_id',
        'notification_type',
        'channel',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function getForAgent(int $agentId, int $companyId, string $type): self
    {
        $setting = self::where('agent_id', $agentId)
            ->where('company_id', $companyId)
            ->where('notification_type', $type)
            ->first();

        if (!$setting) {
            return new self([
                'agent_id' => $agentId,
                'company_id' => $companyId,
                'notification_type' => $type,
                'channel' => self::CHANNEL_EMAIL,
                'is_active' => false,
            ]);
        }

        return $setting;
    }

    public static function getChannelOptions(): array
    {
        return [
            self::CHANNEL_EMAIL => 'Email',
            self::CHANNEL_WHATSAPP => 'WhatsApp',
            self::CHANNEL_BOTH => 'Both (Email & WhatsApp)',
        ];
    }

    public function getChannelLabel(): string
    {
        return self::getChannelOptions()[$this->channel] ?? 'Unknown';
    }
}
