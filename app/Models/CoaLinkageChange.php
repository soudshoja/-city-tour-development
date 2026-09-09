<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CT-A3 R2-5 (verify-R1 D14) — one BEFORE-IMAGE written by `accounting:coa-linkage --apply`.
 *
 * See the table's own migration for why it exists and why it is not part of
 * {@see CoaLinkageFinding}: findings are the latest snapshot and are rewritten wholesale on every
 * run; a before-image must outlive every later run or the change it records can never be undone.
 *
 * Deliberately has NO `BelongsToCompany` global scope, for the same reason
 * {@see CoaLinkageFinding} does not: the command that writes it runs from the console with no Auth
 * context, and a scope that silently no-ops when unauthenticated would make "whose changes am I
 * rolling back?" depend on how the caller got here. Every read filters `company_id` explicitly.
 *
 * @property string $run_id
 * @property int $company_id
 * @property string $subject_table
 * @property int $subject_id
 * @property string $column_name
 * @property string|null $before_value
 * @property string|null $after_value
 * @property \Illuminate\Support\Carbon|null $rolled_back_at
 */
class CoaLinkageChange extends Model
{
    /** The three `accounts` classification columns the linkage command is allowed to rewrite. */
    public const REVERSIBLE_COLUMNS = ['report_type', 'is_group', 'account_type_id'];

    protected $table = 'coa_linkage_changes';

    protected $fillable = [
        'run_id',
        'company_id',
        'subject_table',
        'subject_id',
        'column_name',
        'before_value',
        'after_value',
        'rolled_back_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'subject_id' => 'integer',
        'rolled_back_at' => 'datetime',
    ];
}
