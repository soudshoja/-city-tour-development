<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * W6.S audit trail for status-only task events. See
 * database/migrations/2026_08_29_140004_w6s_create_task_status_events_table.php for why this is a
 * dedicated table rather than the engine's Log::-based accounting.* audit convention.
 */
class TaskStatusEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'task_id',
        'event',
        'from_status',
        'to_status',
        'channel',
        'raw_status',
        'meta',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
