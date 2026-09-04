<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-company running counter behind `VoucherService::nextVoucherNumber()`
 * (plan §14.6: "own sequence — serial_schemas is accounting-owned").
 * Deliberately its own table rather than the accounting-adjacent
 * `sequences` table PaymentController already uses for receipt-voucher
 * numbers — see the migration's own comment for why sharing that row
 * would be unsafe. One row per company; always read with lockForUpdate()
 * inside the caller's transaction, never read bare.
 */
class VoucherNumberSequence extends Model
{
    protected $fillable = [
        'company_id',
        'current_sequence',
    ];

    protected $casts = [
        'current_sequence' => 'integer',
    ];

    // Deliberately NOT App\Traits\BelongsToCompany — same reason as every
    // other model in this feature (plan §2.4): no authenticated user in a
    // console/queue/public-route context. Callers pass company_id explicitly.

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
