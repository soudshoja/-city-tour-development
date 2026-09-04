<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One task's membership in a package: its order and an optional
 * agent-supplied section label override ("Return transfer") — plan §3.2.
 * A task belongs to at most one package (unique(task_id) at the DB level).
 */
class TaskPackageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_package_id',
        'task_id',
        'sort_order',
        'section_label',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function package()
    {
        return $this->belongsTo(TaskPackage::class, 'task_package_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
