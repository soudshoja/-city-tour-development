<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DotwRoom — Eloquent model for the `dotw_rooms` table.
 *
 * Represents a single room line item belonging to a DotwPrebook record.
 * Phase 33 is the writer; Phase 31 (search + browse) never inserts rows.
 *
 * No BelongsToCompany global scope — the AkeedDotwAI module is single-tenant,
 * pinned to config('akeed_dotwai.company_id').
 *
 * Migration: app/Modules/AkeedDotwAI/Database/Migrations/
 */
class DotwRoom extends Model
{
    protected $table = 'dotw_rooms';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, string> */
    protected $casts = [
        'children_ages' => 'array',
        'actual_children_ages' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * The prebook record this room belongs to.
     */
    public function prebook(): BelongsTo
    {
        return $this->belongsTo(DotwPrebook::class, 'dotw_prebook_id');
    }
}
