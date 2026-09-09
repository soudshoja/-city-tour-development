<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CT-A4 — one row of `accounting:coa-linkage`'s flag-only report.
 *
 * Deliberately has NO `BelongsToCompany` global scope, unlike App\Models\Account: the command
 * that writes it runs from the console with no Auth context, and a scope that silently no-ops
 * when unauthenticated (Account's does) would make "which company's findings am I looking at?"
 * depend on how the caller got here. Every read in the command filters `company_id` explicitly.
 *
 * @property int $company_id
 * @property string $code
 * @property string $subject_type
 * @property int|null $subject_id
 * @property string $severity
 * @property string $summary
 * @property array|null $details
 */
class CoaLinkageFinding extends Model
{
    public const SEVERITY_BLOCKING = 'blocking';

    public const SEVERITY_REPORTING = 'reporting';

    public const SEVERITY_HYGIENE = 'hygiene';

    public const SEVERITY_RULING = 'ruling';

    protected $table = 'coa_linkage_findings';

    protected $fillable = [
        'company_id',
        'code',
        'subject_type',
        'subject_id',
        'severity',
        'summary',
        'details',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'subject_id' => 'integer',
        'details' => 'array',
    ];
}
