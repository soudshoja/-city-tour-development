<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * W6.U — void-with-fee / reissue-with-fee approval queue. See the migration's own docblock
 * (2026_08_30_100000_w6u_create_task_pending_actions_table.php) for why this is a plain string
 * `action` rather than an enum, and why this model never calls into the posting engine itself.
 */
class TaskPendingAction extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const ACTION_VOID_WITH_FEE = 'void_with_fee';

    public const ACTION_REISSUE_WITH_FEE = 'reissue_with_fee';

    protected $fillable = [
        'company_id',
        'task_id',
        'action',
        'payload',
        'status',
        'requested_by',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    protected $casts = [
        'payload' => 'array',
        'decided_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
