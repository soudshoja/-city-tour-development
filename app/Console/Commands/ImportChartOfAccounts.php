<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class ImportChartOfAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:import 
                            {--companyId= : The ID of the company to import accounts for} 
                            {--filePath= : The path to the CSV or XLSX file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports a chart of accounts from a CSV or XLSX file into the database.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $companyId = $this->option('companyId');
        $filePath = storage_path('app/' . $this->option('filePath'));

        if (!file_exists($filePath)) {
            $this->error('The specified file does not exist: ' . $filePath);
            return 1;
        }

        $this->info("Starting import for company ID: {$companyId} from file: {$filePath}");

        // Use a transaction to ensure all or nothing is imported
        DB::beginTransaction();

        try {
            // Use PhpSpreadsheet to load the file, automatically detecting the format
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // Store created accounts temporarily to find parents
            $accounts = [];

            // Loop through each row of the spreadsheet
            // We start at row 2 to skip the header
            for ($row = 2; $row <= $highestRow; ++$row) {
                // Get cell values using the non-deprecated getCell method
                // and the column letter.
                $rowData = [
                    'acc_group' => $sheet->getCell('A' . $row)->getCalculatedValue(),
                    'acc_code' => $sheet->getCell('B' . $row)->getCalculatedValue(),
                    'acc_name' => $sheet->getCell('C' . $row)->getCalculatedValue(),
                    'acc_type' => $sheet->getCell('E' . $row)->getCalculatedValue(),
                    'curr_code' => $sheet->getCell('G' . $row)->getCalculatedValue(),
                    'parent_acc_name' => $sheet->getCell('H' . $row)->getCalculatedValue(),
                    'acc_level' => $sheet->getCell('I' . $row)->getCalculatedValue(),
                ];

                // Skip any empty rows
                if (empty(array_filter($rowData))) {
                    continue;
                }

                // Determine report type based on account type
                $reportType = ($rowData['acc_type'] === 'A' || $rowData['acc_type'] === 'L') ? 'balance sheet' : 'profit loss';

                // Find the parent account's ID if it exists
                $parentId = null;
                if (!empty($rowData['parent_acc_name'])) {
                    // We need to look up the parent by name within the same company
                    $parentAccount = Account::where('name', 'like', '%' . $rowData['parent_acc_name'] . '%')
                        ->where('company_id', $companyId)
                        ->first();
                    if ($parentAccount) {
                        $parentId = $parentAccount->id;
                    } else {
                        $this->warn("Parent account not found for '{$rowData['acc_name']}': '{$rowData['parent_acc_name']}'");
                    }
                }

                $similarAccountName = Account::where('name', 'like', '%' . $rowData['acc_name'] . '%')->where('company_id', $companyId)->first();

                // Use updateOrCreate to find an existing account or create a new one
                $newAccount = Account::updateOrCreate(
                    [
                        'name' => $similarAccountName ? $similarAccountName->name : ucwords(strtolower($rowData['acc_name'])),
                        'company_id' => $companyId,
                    ],
                    [
                        'serial_number' => null,
                        'account_type' => $this->mapAccountType($rowData['acc_type']),
                        'report_type' => $reportType,
                        'level' => $rowData['acc_level'],
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'parent_id' => $parentId,
                        'root_id' => null,
                        'code' => $rowData['acc_code'],
                        'currency' => $rowData['curr_code'],
                        'is_group' => $this->isGroup($rowData['acc_name'], $rowData['acc_code']),
                        'disabled' => 0,
                        'balance_must_be' => null,
                    ]
                );

                // Store the created or updated account to use as a parent for subsequent accounts
                $accounts[$rowData['acc_name']] = $newAccount->id;
            }

            DB::commit();
            $this->info("Import successful! {$sheet->getHighestRow()} accounts processed.");
            return 0;
        } catch (ReaderException $e) {
            DB::rollBack();
            $this->error("File import failed: " . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Maps the single-letter account type to a full name.
     *
     * @param string $type
     * @return string
     */
    protected function mapAccountType(string $type): string
    {
        switch ($type) {
            case 'A':
                return 'Assets';
            case 'L':
                return 'Liabilities';
            case 'E':
                return 'Expenses';
            case 'I':
                return 'Income';
            default:
                return 'Unknown';
        }
    }

    /**
     * Determines if an account is a group.
     *
     * @param string $accName
     * @param string $accCode
     * @return bool
     */
    protected function isGroup(string $accName, string $accCode): bool
    {
        return substr($accCode, -1) === '0';
    }
}
