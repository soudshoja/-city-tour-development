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
    /**
     * The `accounts` columns the linkage command is allowed to rewrite AND restore.
     *
     * CT-A3 R3-2 (verify-R2 finding V1) added `parent_id` and `level`: `--allow-move` relocates a
     * control pool's children up one level, and before R3 that was the one repair a `--rollback`
     * silently left in place while still printing *"Undo this run in full"*. On the City Travelers
     * chart those six children carry 2,989 journal rows between them, so which group every
     * historical report rolls them into was NOT restorable. It is now.
     */
    public const REVERSIBLE_COLUMNS = ['report_type', 'is_group', 'account_type_id', 'parent_id', 'level'];

    /**
     * CT-A3 R3-2. `column_name` sentinel for "this whole ROW was created by the run" — the undo is
     * a DELETE of `subject_id` in `subject_table`, not a column restore. Written for a leaf the run
     * minted and for a `system_accounts` purpose mapping it created; both were invisible to the
     * pre-R3 rollback, which is exactly what made *"Undo this run in full"* untrue.
     */
    public const ROW_CREATED = '__row_created__';

    /**
     * CT-A3 R3-2. `column_name` sentinel for "this whole ROW was deleted by the run" — the undo is
     * an INSERT. No repair path in this command deletes a `system_accounts` row today (every write
     * is an `updateOrCreate`/`updateOrInsert`), so this exists so that a future one cannot make the
     * rollback quietly partial again: the diff RECORDS the deletion and the rollback restores it.
     */
    public const ROW_DELETED = '__row_deleted__';

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
