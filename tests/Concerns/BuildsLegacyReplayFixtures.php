<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Services\Onboarding\LegacyColumn;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot LP3 test fixtures. SYNTHETIC ONLY -- nothing under this
 * helper reads D:\akeedac, and no value below came from the real export. The
 * header shapes mirror the export's own PUBLIC SCHEMA (column names, documented
 * in MAPPING-RULES.md), never its data.
 *
 * The stg_* tables are created here the same way {@see \App\Services\Onboarding\LegacyCsvLoader}
 * creates them in production: EVERY COLUMN IS `text`, named through
 * {@see LegacyColumn::name()}. That is not a shortcut -- it is the property
 * under test. `Posted` really is the literal string 'True', `Debit` really is a
 * decimal string, `DocDt` really is a date string. A fixture that typed these
 * columns properly would test a staging table the pilot does not have.
 */
trait BuildsLegacyReplayFixtures
{
    /** @var array<string, int> */
    private array $legacyAutoIds = ['doc' => 0, 'line' => 0];

    private const ACC_HEADER_COLUMNS = [
        'CompanyID', 'BranchID_FK', 'DocID', 'DocNo', 'DocType', 'SubType', 'DocDt', 'RefNo',
        'RefCode', 'RefType', 'PayTo', 'Narration', 'NarrationFL', 'InternalRemarks', 'Posted',
        'AccessLevel', 'DocYear', 'IsImported', 'IsExported', 'CreateID', 'CreateDt', 'ModID', 'ModDt',
    ];

    private const ACC_DETAIL_COLUMNS = [
        'CompanyID', 'AccDetailID', 'DocID_FK', 'DocNo', 'DocType', 'SubType', 'DocDt',
        'TransactionDtlNo', 'TransactionType', 'AccID_FK', 'BranchID_FK', 'CostID_FK',
        'FCDebit', 'FCCredit', 'FcCurrID_FK', 'FcExchRate', 'Debit', 'Credit', 'DC',
        'DebitAdj', 'CreditAdj', 'Narration', 'ChequeNo', 'ChequeDt', 'BankName', 'AuthNo',
        'ChqClearanceDt', 'Reconciled', 'Tax', 'Discount',
    ];

    private const ACC_IS_APPLY_COLUMNS = [
        'SourceDocID_FK', 'SourceAccDetailID_FK', 'AppliedDocID_FK', 'AppliedAccDetailID_FK',
        'AccID_FK', 'Amount', 'sourceDC', 'ModId', 'ModDt',
    ];

    protected function createLegacyReplayStagingTables(): void
    {
        $this->createTextTable('stg_acc_header', self::ACC_HEADER_COLUMNS);
        $this->createTextTable('stg_acc_detail', self::ACC_DETAIL_COLUMNS);
        $this->createTextTable('stg_acc_is_apply', self::ACC_IS_APPLY_COLUMNS);
        $this->legacyAutoIds = ['doc' => 0, 'line' => 0];
    }

    /** @param string[] $columns */
    private function createTextTable(string $name, array $columns): void
    {
        Schema::connection('legacy_pilot')->dropIfExists($name);

        Schema::connection('legacy_pilot')->create($name, function (Blueprint $table) use ($columns): void {
            // The loader's own tables carry no id; one is added here purely so
            // §3.2 (1)'s "tie-broken by the staged row's insertion order" is
            // expressible in a test.
            $table->id();

            foreach ($columns as $column) {
                $table->text(LegacyColumn::name($column))->nullable();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $overrides  keyed by the LEGACY column name
     */
    protected function stageHeader(array $overrides = []): int
    {
        $docId = (int) ($overrides['DocID'] ?? ++$this->legacyAutoIds['doc']);
        $this->legacyAutoIds['doc'] = max($this->legacyAutoIds['doc'] ?? 0, $docId);

        $row = array_merge([
            'CompanyID' => '1',
            'BranchID_FK' => '1',
            'DocID' => (string) $docId,
            'DocNo' => 'INV/CO/25'.$docId,
            'DocType' => 'INV',
            'SubType' => 'INV',
            'DocDt' => '2025-03-15',
            'RefNo' => '',
            'RefCode' => '',
            'RefType' => 'SINV',
            'Narration' => 'INV-'.$docId,
            // The literal token the export writes -- never an integer 1.
            'Posted' => 'True',
            'DocYear' => '2025',
        ], $overrides);

        DB::connection('legacy_pilot')->table('stg_acc_header')->insert($this->toStagedRow($row));

        return $docId;
    }

    /**
     * @param  array<string, mixed>  $overrides  keyed by the LEGACY column name
     */
    protected function stageLine(int $docId, array $overrides = []): int
    {
        $detailId = (int) ($overrides['AccDetailID'] ?? ++$this->legacyAutoIds['line']);
        $this->legacyAutoIds['line'] = max($this->legacyAutoIds['line'] ?? 0, $detailId);

        $row = array_merge([
            'CompanyID' => '1',
            'AccDetailID' => (string) $detailId,
            'DocID_FK' => (string) $docId,
            'DocDt' => '2025-03-15',
            'BranchID_FK' => '1',
            'FCDebit' => '0.000',
            'FCCredit' => '0.000',
            'FcCurrID_FK' => '',
            'FcExchRate' => '1.000000000000',
            'Debit' => '0.000',
            'Credit' => '0.000',
            'DC' => 'D',
            'DebitAdj' => '0.000',
            'CreditAdj' => '0.000',
            'Narration' => 'LINE-'.$detailId,
        ], $overrides);

        DB::connection('legacy_pilot')->table('stg_acc_detail')->insert($this->toStagedRow($row));

        return $detailId;
    }

    /** @param array<string, mixed> $overrides */
    protected function stageAllocation(array $overrides = []): void
    {
        $row = array_merge([
            'AccID_FK' => '1',
            'Amount' => '0.000',
            'sourceDC' => 'C',
            'ModId' => '1',
            'ModDt' => '2025-04-01 09:00:00',
        ], $overrides);

        DB::connection('legacy_pilot')->table('stg_acc_is_apply')->insert($this->toStagedRow($row));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string|null>
     */
    private function toStagedRow(array $row): array
    {
        $staged = [];

        foreach ($row as $column => $value) {
            $staged[LegacyColumn::name($column)] = $value === null ? null : (string) $value;
        }

        return $staged;
    }

    protected function mapLegacyAccount(int $companyId, int $accId, ?int $accountId, string $resolution = 'direct', ?int $partyId = null, bool $frozen = false): void
    {
        DB::connection('legacy_pilot')->table('legacy_acc_map')->insert([
            'company_id' => $companyId,
            'acc_id' => $accId,
            'acc_code' => (string) $accId,
            'account_id' => $accountId,
            'resolution' => $resolution,
            'legacy_is_freeze' => $frozen,
            'party_id' => $partyId,
            'party_role' => $partyId === null ? null : ($resolution === 'pooled_payable' ? 'supplier' : 'customer'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function mapLegacyBranch(int $companyId, int $legacyBranchId, ?int $akeedBranchId): void
    {
        DB::connection('legacy_pilot')->table('map_branch')->insert([
            'company_id' => $companyId,
            'branch_id_fk' => $legacyBranchId,
            'branch_code' => 'CO',
            'akeed_branch_id' => $akeedBranchId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function mapLegacyCurrency(int $companyId, int $currId, ?string $code, bool $poison = false, string $status = 'mapped'): void
    {
        DB::connection('legacy_pilot')->table('map_currency')->insert([
            'company_id' => $companyId,
            'curr_id_fk' => $currId,
            'curr_code' => $code,
            'akeed_currency_id' => null,
            'is_poison' => $poison,
            // LP1e: an `unresolved` row is what LegacyCurrencyMapper writes
            // when derivation from line usage found nothing -- it is a real
            // row, not an absent one, and the replay treats it as metadata.
            'status' => $status,
            'derivation' => $status === 'mapped' ? 'rate_history' : 'none',
            'line_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
