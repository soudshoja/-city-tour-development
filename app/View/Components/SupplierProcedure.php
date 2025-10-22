<?php

namespace App\View\Components;

use App\Models\Supplier;
use App\Models\SupplierProcedure as ModelsSupplierProcedure;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;

class SupplierProcedure extends Component
{
    public int $supplierId;
    public int $companyId;
    public ?ModelsSupplierProcedure $procedure;

    public function __construct(int $supplierId, int $companyId)
    {
        $this->supplierId = $supplierId;
        $this->companyId = $companyId;

        $this->procedure = $this->getSupplierProcedure();
    }

    private function getSupplierProcedure(): ?ModelsSupplierProcedure
    {
        return ModelsSupplierProcedure::whereHas('supplierCompany', function($query) {
            $query->where('supplier_id', $this->supplierId)
                  ->where('company_id', $this->companyId);
        })
        ->where('is_active', true)
        ->whereNotNull('procedure')
        ->where('procedure', '!=', '')
        ->first();
    }

    public function render(): View|Closure|string
    {
        return view('components.supplier-procedure', [
            'procedure' => $this->procedure,
        ]);
    }
}
