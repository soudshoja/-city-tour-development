<?php

declare(strict_types=1);

namespace Tests\Concerns;

/**
 * Writes tiny SYNTHETIC CSVs (never real export data -- no file under this
 * helper ever reads D:\akeedac) into a throwaway temp directory that tests
 * point `legacy_pilot.allowed_root` at. Header shapes mirror the real
 * export's own headers (public schema, not data) documented in
 * config/legacy_pilot.php's neighbouring PLAN.md.
 */
trait BuildsLegacyCsvFixtures
{
    protected string $legacyFixtureRoot;

    protected function makeLegacyFixtureRoot(): string
    {
        $this->legacyFixtureRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lp1_fixture_'.bin2hex(random_bytes(6));
        mkdir($this->legacyFixtureRoot.DIRECTORY_SEPARATOR.'ledger-export-2025-2026Q1', 0777, true);

        config(['legacy_pilot.allowed_root' => $this->legacyFixtureRoot, 'legacy_pilot.export_dir' => 'ledger-export-2025-2026Q1']);

        return $this->legacyFixtureRoot;
    }

    protected function writeLegacyCsv(string $filename, array $header, array $rows): string
    {
        $path = $this->legacyFixtureRoot.DIRECTORY_SEPARATOR.'ledger-export-2025-2026Q1'.DIRECTORY_SEPARATOR.$filename;

        $fh = fopen($path, 'w');
        fputcsv($fh, $header);

        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }

        fclose($fh);

        return $path;
    }

    protected function cleanupLegacyFixtureRoot(): void
    {
        if (! isset($this->legacyFixtureRoot) || ! is_dir($this->legacyFixtureRoot)) {
            return;
        }

        $files = glob($this->legacyFixtureRoot.DIRECTORY_SEPARATOR.'ledger-export-2025-2026Q1'.DIRECTORY_SEPARATOR.'*');

        foreach ($files ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->legacyFixtureRoot.DIRECTORY_SEPARATOR.'ledger-export-2025-2026Q1');
        @rmdir($this->legacyFixtureRoot);
    }

    protected function accountHeader(): array
    {
        return ['Company_ID', 'AccGroup', 'Acc_ID', 'AccCode', 'Group_ID', 'AccName', 'AccName_FL', 'AccType', 'HasSubAcc', 'AllowMultiCurr', 'AccTransType', 'IsApply', 'CurrID_FK', 'ParentAccID_FK', 'AccLevel', 'AccStatus', 'IsFreeze', 'OutstandingAmt', 'AltAccCodeExp', 'AltAccCodeImp', 'TransLockdt', 'TransOpenFromdt', 'TransOpenTodt', 'CreateID', 'Createdt', 'ModID', 'Moddt'];
    }

    protected function partnerHeader(): array
    {
        return ['Partner_ID', 'IsCust', 'IsSupp', 'IsAirline', 'PartnerCode1', 'PartnerCode2', 'PartnerName', 'Type_FK', 'Group_FK', 'CityID_FK', 'CustAccID_FK', 'SuppAccID_FK', 'CurrID_FK'];
    }

    protected function branchHeader(): array
    {
        return ['Branch_ID', 'Company_ID', 'BranchCode', 'BranchName', 'BranchName_FL', 'BranchAddress', 'BranchTel', 'IsIATA', 'IATANo', 'BranchAccID_FK', 'IsFreeze', 'CashAccID_FK', 'CashControlAccID_FK', 'BankAccID_FK', 'DiscountAcc', 'CreateID', 'CreateDt', 'ModID', 'ModDt'];
    }

    protected function currencyHeader(): array
    {
        return ['Slno', 'Curr_ID', 'CurrCode', 'CurrName', 'CoinsName', 'ExchangeRate', 'DecimalPlaces', 'ValidFrom', 'ValidTill', 'CreateID', 'Createdt', 'ModID'];
    }

    protected function accHeaderHeader(): array
    {
        return ['CompanyID', 'BranchID_FK', 'DocID', 'DocNo', 'DocType', 'SubType', 'DocDt', 'RefNo', 'RefCode', 'RefType', 'PayTo', 'Narration', 'NarrationFL', 'InternalRemarks', 'Posted', 'AccessLevel', 'DocYear', 'IsImported', 'IsExported', 'FlxField1', 'FlxField2', 'FlxField3', 'CreateID', 'CreateDt', 'ModID', 'ModDt', 'RefDtlNo', 'ReceiptNo', 'CostCenter_FK', 'DueDate', 'SalesInchargeID_FK', 'BillID_FK'];
    }

    protected function accDetailHeader(): array
    {
        return ['CompanyID', 'AccDetailID', 'DocID_FK', 'DocNo', 'DocType', 'SubType', 'DocDt', 'TransactionDtlNo', 'TransactionType', 'AccID_FK', 'FCDebit', 'FCCredit', 'FcCurrID_FK', 'FcExchRate', 'Debit', 'Credit', 'DC'];
    }

    protected function systemParametersHeader(): array
    {
        return ['ParameterName', 'ParameterValue'];
    }

    protected function costCenterHeader(): array
    {
        return ['CostCenter_ID', 'CostCenterCode', 'CostCenterName', 'HasSubItems', 'CreateID', 'CreateDate', 'ModID', 'ModDate'];
    }
}
