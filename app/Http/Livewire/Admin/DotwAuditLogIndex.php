<?php

namespace App\Http\Livewire\Admin;

use App\Models\DotwAuditLog;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class DotwAuditLogIndex extends Component
{
    use WithPagination;

    public string $filterOperation = '';

    public string $filterCompanyId = '';

    public string $filterMessageId = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public ?int $expandedRow = null;

    protected $queryString = [
        'filterOperation' => ['except' => ''],
        'filterCompanyId' => ['except' => ''],
        'filterMessageId' => ['except' => ''],
        'filterDateFrom'  => ['except' => ''],
        'filterDateTo'    => ['except' => ''],
    ];

    public function isSuperAdmin(): bool
    {
        return Auth::user()->role_id === Role::ADMIN;
    }

    public function toggleRow(int $id): void
    {
        $this->expandedRow = $this->expandedRow === $id ? null : $id;
    }

    public function resetFilters(): void
    {
        $this->filterOperation = '';
        $this->filterCompanyId = '';
        $this->filterMessageId = '';
        $this->filterDateFrom  = '';
        $this->filterDateTo    = '';
        $this->expandedRow     = null;
        $this->resetPage();
    }

    public function render(): \Illuminate\View\View
    {
        $query = DotwAuditLog::query()
            ->when(! $this->isSuperAdmin(), fn ($q) => $q->where('company_id', Auth::user()->company_id))
            ->when($this->filterOperation, fn ($q) => $q->where('operation_type', $this->filterOperation))
            ->when($this->isSuperAdmin() && $this->filterCompanyId, fn ($q) => $q->where('company_id', $this->filterCompanyId))
            ->when($this->filterMessageId, fn ($q) => $q->where('resayil_message_id', 'like', "%{$this->filterMessageId}%"))
            ->when($this->filterDateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->filterDateTo))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.admin.dotw-audit-log-index', [
            'logs'         => $query,
            'isSuperAdmin' => $this->isSuperAdmin(),
        ]);
    }
}
