<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * BulkInvoice Model
 *
 * Tracks bulk invoice sessions including validation status and row counts.
 */
class BulkInvoice extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'agent_id',
        'user_id',
        'original_filename',
        'stored_path',
        'status',
        'total_rows',
        'valid_rows',
        'error_rows',
        'flagged_rows',
        'error_summary',
        'invoice_ids',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'error_summary' => 'array',
        'invoice_ids' => 'array',
        'status' => 'string',
        'total_rows' => 'integer',
        'valid_rows' => 'integer',
        'error_rows' => 'integer',
        'flagged_rows' => 'integer',
    ];

    /**
     * Get the company that owns the bulk invoice.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the agent that created the bulk invoice.
     */
    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the user that created the bulk invoice.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the rows for the bulk invoice.
     */
    public function rows()
    {
        return $this->hasMany(BulkInvoiceRow::class);
    }

    /**
     * Scope a query to only include uploads for a specific company.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $companyId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
