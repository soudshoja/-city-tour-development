<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

/**
 * accounting-builds T8 (Lane E). Everything {@see SupplierStatementImporter::import()} needs,
 * gathered in one place rather than a long positional-argument list.
 */
final class SupplierStatementImportInput
{
    /**
     * @param  array<string,string>|null  $columnMapOverride  per-import override merged onto
     *     config('accounting.supplier_statements.dotw.columns') (L15) — only the keys present
     *     override; anything absent falls back to the config default.
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $supplierId,
        public readonly string $absoluteFilePath,
        public readonly string $fileName,
        public readonly string $statementCurrency,
        public readonly ?array $columnMapOverride = null,
        public readonly ?string $statementReference = null,
        public readonly ?string $periodFrom = null,
        public readonly ?string $periodTo = null,
        public readonly ?int $importedBy = null,
    ) {}
}
