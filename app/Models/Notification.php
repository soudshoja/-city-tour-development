<?php

namespace App\Models;

use Attribute;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'data',
        'status',
        'close',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function getFormattedCreatedAtAttribute()
    {
        return Carbon::parse($this->created_at)->diffForHumans();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to find notifications by request token in JSON data
     */
    public function scopeByRequestToken($query, $token)
    {
        return $query->whereNotNull('data')->get()->filter(function ($notification) use ($token) {
            $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
            return isset($data['request_token']) && $data['request_token'] === $token;
        });
    }

    /**
     * Find notification by user ID and request token
     */
    public static function findByUserAndToken($userId, $type, $token)
    {
        $notifications = self::where('user_id', $userId)
            ->where('type', $type)
            ->whereNotNull('data')
            ->get();

        foreach ($notifications as $notification) {
            $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
            if (isset($data['request_token']) && $data['request_token'] === $token) {
                return $notification;
            }
        }

        return null;
    }
}
