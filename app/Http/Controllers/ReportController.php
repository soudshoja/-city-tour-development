<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $reportType = $request->input('report_type', 'agents'); // Default to 'agents' if no type is selected
        $reportData = [];

        if ($reportType === 'agents') {
            $reportData = $this->getAgentReport();
        } elseif ($reportType === 'clients') {
            $reportData = $this->getClientReport();
        } elseif ($reportType === 'suppliers') {
            $reportData = $this->getSupplierReport();
        }

        return view('reports.index', compact('reportData', 'reportType'));
    }

    private function getAgentReport()
    {
        $agentTransactions = DB::table('transactions')
            ->join('agents', 'transactions.entity_id', '=', 'agents.id')
            ->where('transactions.entity_type', 'agent')
            ->select('agents.name as name', 'transactions.type', 'transactions.amount', 'transactions.transaction_date')
            ->get()
            ->groupBy('name');

        return $this->calculateBalances($agentTransactions);
    }

    private function getClientReport()
    {
        $clientTransactions = DB::table('transactions')
            ->join('clients', 'transactions.entity_id', '=', 'clients.id')
            ->where('transactions.entity_type', 'client')
            ->select('clients.name as name', 'transactions.type', 'transactions.amount', 'transactions.transaction_date')
            ->get()
            ->groupBy('name');

        return $this->calculateBalances($clientTransactions);
    }

    private function getSupplierReport()
    {
        $supplierTransactions = DB::table('transactions')
            ->join('suppliers', 'transactions.entity_id', '=', 'suppliers.id')
            ->where('transactions.entity_type', 'supplier')
            ->select('suppliers.name as name', 'transactions.type', 'transactions.amount', 'transactions.transaction_date')
            ->get()
            ->groupBy('name');

        return $this->calculateBalances($supplierTransactions);
    }

    private function calculateBalances($transactions)
    {
        return $transactions->map(function ($transactions, $name) {
            $balance = 0;
            foreach ($transactions as $transaction) {
                $balance += $transaction->type === 'credit' ? $transaction->amount : -$transaction->amount;
            }

            return [
                'name' => $name,
                'transactions' => $transactions,
                'balance' => $balance,
            ];
        });
    }
}
