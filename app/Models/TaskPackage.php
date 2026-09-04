<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An agent-marked, agent-named grouping of tasks (plan §3.2/§4). The
 * package_type label is never inferred from its items — it is stored
 * verbatim from what the agent typed/picked.
 */
class TaskPackage extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'client_id',
        'reference',
        'name',
        'package_type',
        'status',
        'notes',
        'created_by',
    ];

    // Deliberately NOT using App\Traits\BelongsToCompany — see VoucherTemplate
    // for why (plan §2.4). Every query here carries an explicit company_id.

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(TaskPackageItem::class)->orderBy('sort_order');
    }

    /**
     * The tasks in this package, in agent-controlled order, with the
     * per-item pivot data (sort_order, section_label) available on
     * ->pivot (plan §7).
     */
    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'task_package_items')
            ->withPivot(['sort_order', 'section_label'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function vouchers()
    {
        return $this->morphMany(TravelVoucher::class, 'subject');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
