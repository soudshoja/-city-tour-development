<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class LedgerExport implements FromArray
{
    protected $ledgers;
    protected $totalDebit;
    protected $totalCredit;

    public function __construct($ledgers, $totalDebit, $totalCredit)
    {
        $this->ledgers = $ledgers;
        $this->totalDebit = $totalDebit;
        $this->totalCredit = $totalCredit;
    }

    // This method converts the ledger data to an array for export
    public function array(): array
    {
        $data = [];

        // Add the headers row
        $data[] = ['Invoice Number', 'Transaction Date', 'Description', 'Branch Name', 'Agent Name', 'General Ledger Name', 'Debit', 'Credit'];

        // Add ledger data rows
        foreach ($this->ledgers as $ledger) {
            $data[] = [
                $ledger['invoice_number'],
                $ledger['transaction_date'],
                $ledger['description'],
                $ledger['branch_name'],
                $ledger['agent_name'],
                $ledger['JournalEntry_name'],
                $ledger['debit'],
                $ledger['credit'],
            ];
        }

            // Add a row for totals at the end
            $data[] = [
                'Total', // Label for the total row
                '', // Empty cell for transaction date
                '', // Empty cell for description
                '', // Empty cell for branch name
                '', // Empty cell for agent name
                '', // Empty cell for general ledger name
                number_format($this->totalDebit, 2), // Total Debit
                number_format($this->totalCredit, 2), // Total Credit
            ];

        return $data;
    }
}