<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot LP4 test helper.
 *
 * Builds a tiny, fully SYNTHETIC world -- an Akeed chart, replayed journal
 * entries, LP1 mapping rows and staged anchor tables -- in which parity
 * holds exactly. Every test then breaks ONE thing and asserts the harness
 * says so. No file here reads D:\akeedac, and no figure here comes from the
 * real export.
 *
 * The shape mirrors the real one at 1/100th scale and keeps every structure
 * the harness has to handle:
 *
 *   Assets      1000 (root)
 *     BANK      1010          direct leaf                 legacy AccCode 1010
 *     AR CTRL   1090400       pooled RECEIVABLE_CONTROL   legacy leaves 10904001 (PARTY-11)
 *                                                                       10904002 (PARTY-12)
 *   Liabilities 2000 (root)
 *     AP CTRL   2060100       pooled PAYABLE_CONTROL      legacy leaf   20600101 (PARTY-21)
 *   Income      3000 (root)
 *     SALES     3001          direct leaf                 legacy AccCode 30010
 *
 * Two replayed documents:
 *   LEGACY_OJV  2025-01-01  the opening journal (dated INSIDE 2025 -- the
 *                           OJV date trap LedgerFigures::openingNet()
 *                           documents; this fixture is what proves the
 *                           partition holds)
 *   LEGACY_INV  2025-06-15  one period document
 */
trait BuildsParityFixtures
{
    protected int $parityCompanyId;

    /** @var array<string, int> code => account id */
    protected array $parityAccounts = [];

    protected int $parityNextTransactionId = 0;

    /**
     * accounts/journal_entries/transactions all carry a company_id FOREIGN
     * KEY, so a synthetic world needs a real company row (and the country +
     * user rows CompanyFactory depends on) before a single ledger row can
     * be inserted.
     */
    protected function makeParityCompany(): int
    {
        $country = \App\Models\Country::factory()->create();
        $user = \App\Models\User::factory()->create();

        return (int) \App\Models\Company::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country->id,
        ])->id;
    }

    /**
     * @return array<string, int>
     */
    protected function seedParityChart(int $companyId): array
    {
        $this->parityCompanyId = $companyId;

        $roots = [];

        foreach (['Assets', 'Liabilities', 'Income', 'Expenses'] as $rootName) {
            $roots[$rootName] = $this->insertAccount($companyId, null, null, strtoupper(substr($rootName, 0, 1)).'000', $rootName, 1, true);
        }

        $this->parityAccounts = [
            'BANK' => $this->insertAccount($companyId, $roots['Assets'], $roots['Assets'], '1010', 'BANK ACCOUNT', 2, false),
            'AR_CONTROL' => $this->insertAccount($companyId, $roots['Assets'], $roots['Assets'], '1090400', 'RECEIVABLE CONTROL', 2, false),
            'AP_CONTROL' => $this->insertAccount($companyId, $roots['Liabilities'], $roots['Liabilities'], '2060100', 'PAYABLE CONTROL', 2, false),
            'SALES' => $this->insertAccount($companyId, $roots['Income'], $roots['Income'], '3001', 'SALES', 2, false),
        ];

        return $this->parityAccounts;
    }

    protected function insertAccount(int $companyId, ?int $parentId, ?int $rootId, string $code, string $name, int $level, bool $isGroup): int
    {
        return (int) DB::table('accounts')->insertGetId([
            'company_id' => $companyId,
            'parent_id' => $parentId,
            'root_id' => $rootId,
            'code' => $code,
            'name' => $name,
            'level' => $level,
            'is_group' => $isGroup,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Posts one synthetic document. `$lines` are
     * [account_id, debit, credit, party_id|null].
     *
     * Written with DB::table() rather than through PostingSeam on purpose:
     * LP4 must be testable without LP2/LP3's mappers and without the engine
     * kill-switch being on, and the harness under test only ever READS
     * these rows. tests/Feature/Accounting/ArchitectureTest.php's
     * sole-writer ratchet scans app/, not tests/.
     *
     * @param  list<array{0:int,1:float,2:float,3:int|null}>  $lines
     */
    protected function postSyntheticDocument(
        int $companyId,
        string $docType,
        string $subType,
        string $date,
        array $lines,
        ?string $postingDate = null,
        ?string $voucherNumber = 'V-1',
    ): int {
        $transactionId = (int) DB::table('transactions')->insertGetId([
            'company_id' => $companyId,
            'entity_id' => $companyId,
            'entity_type' => 'company',
            'transaction_type' => 'journal',
            'amount' => array_sum(array_column($lines, 1)),
            'total_debit' => array_sum(array_column($lines, 1)),
            'total_credit' => array_sum(array_column($lines, 2)),
            'description' => 'synthetic parity fixture',
            'reference_type' => 'Invoice',
            'doc_type' => $docType,
            'sub_type' => $subType,
            'reference_number' => strtoupper($subType).'-'.(++$this->parityNextTransactionId),
            'idempotency_key' => 'legacy:'.$subType.':'.$this->parityNextTransactionId,
            'posting_status' => 'posted',
            'transaction_date' => $date.' 00:00:00',
            'posting_date' => $postingDate ?? $date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($lines as $line) {
            DB::table('journal_entries')->insert([
                'company_id' => $companyId,
                'transaction_id' => $transactionId,
                'account_id' => $line[0],
                'type_reference_id' => $line[3] ?? null,
                'debit' => $line[1],
                'credit' => $line[2],
                'amount' => $line[1] ?: $line[2],
                'exchange_rate' => 1,
                'currency' => 'KWD',
                'name' => 'synthetic',
                'description' => 'synthetic parity fixture line',
                'transaction_date' => $date.' 00:00:00',
                'posting_date' => $postingDate ?? $date,
                'voucher_number' => $voucherNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $transactionId;
    }

    /**
     * LP1's mapping rows, hand-written (LP1's importer is not exercised
     * here -- LP4 reads the TABLES, never the importer).
     *
     * @param  list<array{acc_id:int,acc_code:string,account_id:int|null,resolution:string,party_id?:int|null,party_role?:string|null}>  $rows
     */
    protected function seedLegacyAccMap(int $companyId, array $rows): void
    {
        foreach ($rows as $row) {
            DB::connection('legacy_pilot')->table('legacy_acc_map')->insert([
                'company_id' => $companyId,
                'acc_id' => $row['acc_id'],
                'acc_code' => $row['acc_code'],
                'account_id' => $row['account_id'],
                'resolution' => $row['resolution'],
                'party_id' => $row['party_id'] ?? null,
                'party_role' => $row['party_role'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<array{partner_id:int,cust_acc_id?:int|null,supp_acc_id?:int|null}>  $rows
     */
    protected function seedMapParty(int $companyId, array $rows): void
    {
        foreach ($rows as $row) {
            DB::connection('legacy_pilot')->table('map_party')->insert([
                'company_id' => $companyId,
                'partner_id_fk' => $row['partner_id'],
                'is_customer' => isset($row['cust_acc_id']),
                'is_supplier' => isset($row['supp_acc_id']),
                'cust_acc_id_fk' => $row['cust_acc_id'] ?? null,
                'supp_acc_id_fk' => $row['supp_acc_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Creates a staged anchor table with the loader's own normalised column
     * names and fills it. Mirrors what LegacyCsvLoader would have produced
     * from the real CSV header, so the harness is exercised against the
     * same shape it meets in staging.
     *
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    protected function seedAnchorTable(string $table, array $columns, array $rows): void
    {
        Schema::connection('legacy_pilot')->dropIfExists($table);

        Schema::connection('legacy_pilot')->create($table, function ($blueprint) use ($columns) {
            $blueprint->id();

            foreach ($columns as $column) {
                $blueprint->text($column)->nullable();
            }
        });

        if ($rows !== []) {
            DB::connection('legacy_pilot')->table($table)->insert($rows);
        }
    }

    /**
     * @param  list<array{acc_code:string,acc_id:int,opening:float,dr:float,cr:float}>  $rows
     */
    protected function seedTrialBalanceAnchor(string $table, array $rows): void
    {
        $this->seedAnchorTable($table, ['acccode', 'accid_fk', 'branchid', 'openingnet', 'perioddr', 'periodcr', 'closingnet'], array_map(fn ($row) => [
            'acccode' => $row['acc_code'],
            'accid_fk' => (string) $row['acc_id'],
            'branchid' => 'ALL',
            'openingnet' => number_format($row['opening'], 3, '.', ''),
            'perioddr' => number_format($row['dr'], 3, '.', ''),
            'periodcr' => number_format($row['cr'], 3, '.', ''),
            'closingnet' => number_format($row['opening'] + $row['dr'] - $row['cr'], 3, '.', ''),
        ], $rows));
    }

    /**
     * @param  list<array{acc_code:string,acc_id:int,dr:float,cr:float}>  $rows
     */
    protected function seedProfitLossAnchor(string $table, array $rows): void
    {
        $this->seedAnchorTable($table, ['acccode', 'accid_fk', 'class', 'cr', 'dr', 'netincome'], array_map(fn ($row) => [
            'acccode' => $row['acc_code'],
            'accid_fk' => (string) $row['acc_id'],
            'class' => 'I',
            'cr' => number_format($row['cr'], 3, '.', ''),
            'dr' => number_format($row['dr'], 3, '.', ''),
            'netincome' => number_format($row['cr'] - $row['dr'], 3, '.', ''),
        ], $rows));
    }

    /**
     * @param  list<array{acc_code:string,acc_id:int,closing:float}>  $rows
     */
    protected function seedPartyAnchor(string $table, array $rows): void
    {
        $this->seedAnchorTable($table, ['acccode', 'accid_fk', 'closingnet'], array_map(fn ($row) => [
            'acccode' => $row['acc_code'],
            'accid_fk' => (string) $row['acc_id'],
            'closingnet' => number_format($row['closing'], 3, '.', ''),
        ], $rows));
    }

    /**
     * Points config at the synthetic anchor tables and disables the two
     * real-world assertions (exact row counts, the 35,859,419.537 total)
     * that only hold for the real export.
     */
    protected function useSyntheticAnchorConfig(): void
    {
        config([
            'legacy_pilot.parity.anchors' => [
                'opening' => ['table' => 'stg_tb_20241231_consolidated', 'kind' => 'tb'],
                'closing' => ['table' => 'stg_tb_20251231_consolidated', 'kind' => 'tb'],
                'pl' => ['table' => 'stg_pl_2025', 'kind' => 'pl'],
                'ar' => ['table' => 'stg_ar_20251231', 'kind' => 'party', 'role' => 'customer'],
                'ap' => ['table' => 'stg_ap_20251231', 'kind' => 'party', 'role' => 'supplier'],
            ],
            'legacy_pilot.parity.expected_closing_total' => null,
        ]);
    }

    /**
     * The world in which parity holds EXACTLY. Every test starts here and
     * breaks one thing.
     *
     * Opening (OJV, 2025-01-01):  BANK Dr 1,000.500 · AR PARTY-11 Dr 250.250
     *                             AP PARTY-21 Cr 1,250.750
     * Period  (INV, 2025-06-15):  AR PARTY-12 Dr 400.125 · SALES Cr 400.125
     */
    protected function buildBalancedParityWorld(int $companyId): void
    {
        $accounts = $this->seedParityChart($companyId);

        $this->postSyntheticDocument($companyId, 'OJV', 'LEGACY_OJV', '2025-01-01', [
            [$accounts['BANK'], 1000.500, 0.0, null],
            [$accounts['AR_CONTROL'], 250.250, 0.0, 11],
            [$accounts['AP_CONTROL'], 0.0, 1250.750, 21],
        ]);

        $this->postSyntheticDocument($companyId, 'INV', 'LEGACY_INV', '2025-06-15', [
            [$accounts['AR_CONTROL'], 400.125, 0.0, 12],
            [$accounts['SALES'], 0.0, 400.125, null],
        ]);

        $this->seedLegacyAccMap($companyId, [
            ['acc_id' => 1, 'acc_code' => '1010', 'account_id' => $accounts['BANK'], 'resolution' => 'direct'],
            ['acc_id' => 2, 'acc_code' => '10904001', 'account_id' => $accounts['AR_CONTROL'], 'resolution' => 'pooled_receivable', 'party_id' => 11, 'party_role' => 'customer'],
            ['acc_id' => 3, 'acc_code' => '10904002', 'account_id' => $accounts['AR_CONTROL'], 'resolution' => 'pooled_receivable', 'party_id' => 12, 'party_role' => 'customer'],
            ['acc_id' => 4, 'acc_code' => '20600101', 'account_id' => $accounts['AP_CONTROL'], 'resolution' => 'pooled_payable', 'party_id' => 21, 'party_role' => 'supplier'],
            ['acc_id' => 5, 'acc_code' => '30010', 'account_id' => $accounts['SALES'], 'resolution' => 'direct'],
        ]);

        $this->seedMapParty($companyId, [
            ['partner_id' => 11, 'cust_acc_id' => 2],
            ['partner_id' => 12, 'cust_acc_id' => 3],
            ['partner_id' => 21, 'supp_acc_id' => 4],
        ]);

        // Opening anchor: the position at 2024-12-31, no period activity.
        $this->seedTrialBalanceAnchor('stg_tb_20241231_consolidated', [
            ['acc_code' => '1010', 'acc_id' => 1, 'opening' => 1000.500, 'dr' => 0.0, 'cr' => 0.0],
            ['acc_code' => '10904001', 'acc_id' => 2, 'opening' => 250.250, 'dr' => 0.0, 'cr' => 0.0],
            ['acc_code' => '20600101', 'acc_id' => 4, 'opening' => -1250.750, 'dr' => 0.0, 'cr' => 0.0],
        ]);

        // Closing anchor: opening + the 2025 non-OJV activity. Two pooled
        // receivable leaves fold onto ONE control account, which is exactly
        // the many-to-one the harness must sum before comparing.
        $this->seedTrialBalanceAnchor('stg_tb_20251231_consolidated', [
            ['acc_code' => '1010', 'acc_id' => 1, 'opening' => 1000.500, 'dr' => 0.0, 'cr' => 0.0],
            ['acc_code' => '10904001', 'acc_id' => 2, 'opening' => 250.250, 'dr' => 0.0, 'cr' => 0.0],
            ['acc_code' => '10904002', 'acc_id' => 3, 'opening' => 0.0, 'dr' => 400.125, 'cr' => 0.0],
            ['acc_code' => '20600101', 'acc_id' => 4, 'opening' => -1250.750, 'dr' => 0.0, 'cr' => 0.0],
            ['acc_code' => '30010', 'acc_id' => 5, 'opening' => 0.0, 'dr' => 0.0, 'cr' => 400.125],
        ]);

        $this->seedProfitLossAnchor('stg_pl_2025', [
            ['acc_code' => '30010', 'acc_id' => 5, 'dr' => 0.0, 'cr' => 400.125],
        ]);

        $this->seedPartyAnchor('stg_ar_20251231', [
            ['acc_code' => '10904001', 'acc_id' => 2, 'closing' => 250.250],
            ['acc_code' => '10904002', 'acc_id' => 3, 'closing' => 400.125],
        ]);

        $this->seedPartyAnchor('stg_ap_20251231', [
            ['acc_code' => '20600101', 'acc_id' => 4, 'closing' => -1250.750],
        ]);

        $this->useSyntheticAnchorConfig();
    }

    /**
     * LP4b bucket B1, in miniature: a payable leaf that the AP anchor LISTS
     * but that LP1 imported `direct` -- its own Akeed account, never pooled
     * onto PAYABLE_CONTROL.
     *
     * Shaped like the real population it stands for: the per-branch REFUNDS
     * PAYABLE leaves (legacy Acc_ID 2403003/4/5, group 206010300). They sit
     * under `206%`, so the AP export lists them; no `tblPartner` FK points at
     * them, so O8-refunds / `payable_pooling_requires_partner_fk` imports
     * them `direct`. A party-only decomposition cannot see them at all -- it
     * compares their real balance against the 0.000 the pool holds for them.
     *
     * Adds one balanced period document (BANK Dr 500.000 / REFUNDS PAYABLE
     * Cr 500.000) and threads it through every anchor, so the world it
     * leaves behind is still one in which parity holds EXACTLY.
     *
     * @return int the new account's id
     */
    protected function addDirectPayableAnchorLeaf(int $companyId): int
    {
        $liabilitiesRoot = (int) DB::table('accounts')
            ->where('company_id', $companyId)->where('name', 'Liabilities')->value('id');

        $accountId = $this->insertAccount($companyId, $liabilitiesRoot, $liabilitiesRoot, '2060103', 'REFUNDS PAYABLE', 2, false);
        $this->parityAccounts['REFUNDS_PAYABLE'] = $accountId;

        $this->postSyntheticDocument($companyId, 'CRN', 'LEGACY_CRN', '2025-06-20', [
            [$this->parityAccounts['BANK'], 500.000, 0.0, null],
            [$accountId, 0.0, 500.000, null],
        ]);

        $this->seedLegacyAccMap($companyId, [
            ['acc_id' => 6, 'acc_code' => '20603001', 'account_id' => $accountId, 'resolution' => 'direct'],
        ]);

        // The bank leaf now carries 500.000 of period debit it did not have.
        DB::connection('legacy_pilot')->table('stg_tb_20251231_consolidated')
            ->where('acccode', '1010')
            ->update(['perioddr' => '500.000', 'closingnet' => '1500.500']);

        DB::connection('legacy_pilot')->table('stg_tb_20251231_consolidated')->insert([
            'acccode' => '20603001', 'accid_fk' => '6', 'branchid' => 'ALL',
            'openingnet' => '0.000', 'perioddr' => '0.000', 'periodcr' => '500.000', 'closingnet' => '-500.000',
        ]);

        DB::connection('legacy_pilot')->table('stg_ap_20251231')->insert([
            'acccode' => '20603001', 'accid_fk' => '6', 'closingnet' => '-500.000',
        ]);

        return $accountId;
    }
}
