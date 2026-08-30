<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * W4.R (w4-brief.md §4 — "Fold refund_clients into the refund doc ... keep the model read-only
 * for now, remove write paths"). `refund_clients` was a pre-engine orphan mini-workflow with no
 * FK to `refunds`/`invoices` at all (ct-refund-map.md §1) — its `status`/`amount` rows are not
 * ledger-derived and never routed through PostingSeam, so this model's status/amount can no
 * longer be mutated: RefundController::completeRefundClient()/deleteRefundClient() (the only two
 * write paths) are retired on the ON path (see that controller's own docblock) — this guard is
 * the backstop so no OTHER call site can resurrect a write either. `refund_id` (migration
 * 2026_08_28_140000_w4r_refund_document_columns.php) exists only so a FUTURE refund can populate
 * it going forward; it is never backfilled for existing orphan rows (unmappable — see that
 * migration's own docblock).
 */
class RefundClient extends Model
{
    protected $fillable = [
        'refund_id',
        'client_id',
        'agent_id',
        'status',
        'amount',
        'currency',
        'remark',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->exists) {
                throw new \RuntimeException(
                    'RefundClient is read-only (W4.R — folded into the Refund document; see class docblock). '
                    .'Existing rows may no longer be mutated.'
                );
            }
        });

        static::deleting(function () {
            throw new \RuntimeException(
                'RefundClient is read-only (W4.R — folded into the Refund document; see class docblock). '
                .'Existing rows may no longer be deleted.'
            );
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
