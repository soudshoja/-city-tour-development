<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Purpose-code registry row: maps (company, purpose_code, service_type) to a
 * single leaf Account. Written by database\seeders\SystemAccountsSeeder and
 * read exclusively by App\Services\Accounting\AccountResolver.
 *
 * Deliberately carries NO BelongsToCompany / auth-scoped global scope: the
 * resolver must be safe to query from unauthenticated gateway/queue context
 * (Accounting Gap/11-technical-implementation-plan.md §P1.1, L294-301), so
 * every query against this model filters company_id explicitly instead of
 * relying on an implicit scope.
 */
class SystemAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'purpose_code',
        'service_type',
        'account_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
