<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

/**
 * accounting-builds T9 (Wave 2). Everything {@see BankStatementImporter::import()} needs, gathered
 * in one place — mirrors T8's `SupplierStatementImportInput` shape.
 */
final class BankStatementImportInput
{
    /**
     * @param  array<string,string>|null  $columnMapOverride  per-import override merged onto
     *                                                        config('accounting.bank_statements.columns') (L15) — only the keys present override;
     *                                                        anything absent falls back to the config default.
     * @param  string|null  $dateFormatOverride  per-import override of config('accounting.bank_statements.date_format')
     *                                           (post-sign-off fix, T9 §12 note 2) — a `Carbon::createFromFormat` pattern
     *                                           tried strictly before the configured fallback list; null keeps the config default.
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $bankAccountId,
        public readonly string $absoluteFilePath,
        public readonly string $fileName,
        public readonly string $statementCurrency,
        public readonly ?array $columnMapOverride = null,
        public readonly ?string $statementReference = null,
        public readonly ?string $statementFrom = null,
        public readonly ?string $statementTo = null,
        public readonly ?float $openingBalance = null,
        public readonly ?float $closingBalance = null,
        public readonly ?int $importedBy = null,
        public readonly ?string $dateFormatOverride = null,
    ) {}
}
