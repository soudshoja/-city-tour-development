<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class SupplierTasksExport implements FromView
{
    public $supplier;
    public $filteredTasks;

    public function __construct($supplier, $filteredTasks)
    {
        $this->supplier = $supplier;
        $this->filteredTasks = $filteredTasks;
    }

    public function view(): View
    {
        return view('suppliers.excel', [
            'supplier' => $this->supplier,
            'filteredTasks' => $this->filteredTasks,
        ]);
    }
}