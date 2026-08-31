<?php

declare(strict_types=1);

namespace App\Http\Livewire\Accounting;

use App\Models\Account;
use App\Models\AccountingAuditLog;
use App\Models\AccountingAuditLogPreset;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CsvSafe;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * P2.5.F (p2_5-brief.md §P2.5.F, owner refinements 2026-08-30): the Accounting Log Center screen.
 * Modelled on {@see \App\Http\Livewire\Admin\DotwAuditLogIndex}'s query-per-render pattern, scaled
 * up to the brief's combinable filter set. Every public filter property is mirrored to the URL via
 * `$queryString` (URL-shareable filter state) and to `accounting_audit_log_presets` (saved presets).
 *
 * SEARCH BOX CONTRACT (owner 2026-08-30): {@see self::applySearch()} scopes the free-text `search`
 * term to the fields relevant to whichever filter dimensions are already active, and to every
 * relevant field when no filter is active — see that method's own docblock.
 *
 * "resolve by id, never by name matching" (account filter): {@see self::updatedAccountCode()}
 * resolves a typed account CODE to its id once, on input, rather than filtering journal_entries by
 * account NAME at query time.
 */
class AuditLogIndex extends Component
{
    use WithPagination;

    /** Curated action vocabulary (brief's own list + this build's writers) — a fixed list renders
     *  faster than a DISTINCT query on every load and gives a stable checkbox set. */
    public const KNOWN_ACTIONS = [
        'post', 'reverse', 'repost', 'approve', 'reject', 'unlock', 'unlock_blocked', 'lock',
        'reopen', 'close', 'soft_close', 'reconcile', 'unreconcile', 'option_change',
        'gateway_refund_completed', 'gateway_refund_rejected', 'period_locked_override',
        'posting_date_shifted', 'legacy_path', 'refund_store_draft_deferred',
        'refund_update_draft_deferred', 'refund_crn_legacy', 'revenue_recognized',
        'revenue_recognition_leaf_unmapped',
        // P2.5.G (p2_5-brief.md §P2.5.G) additions: the Reconciliation Center's FIX-NOW draft
        // lifecycle actions — 'reconcile'/'reject'/'unreconcile'/'post' above already cover its
        // proposal approve/reject/unmatch and post-a-draft writes.
        'fix_now_draft_created', 'discard',
    ];

    public const KNOWN_SUBJECT_TYPES = [
        'transaction', 'invoice', 'refund', 'refund_detail', 'task', 'accounting_period',
        'company_setting', 'journal_entry',
        // P2.5.G additions.
        'reconciliation_proposal', 'reconciliation_fix_draft',
    ];

    public const AVAILABLE_COLUMNS = [
        'created_at' => 'Time',
        'action' => 'Action',
        'subject' => 'Subject',
        'actor' => 'Actor',
        'transaction' => 'Document',
        'posting_period' => 'Period',
        'reason' => 'Reason',
        'route' => 'Route',
        'ip' => 'IP',
    ];

    // ── Search + filters (URL + preset state) ──────────────────────────────────────────────────
    public string $search = '';

    public string $companyIdOverride = '';

    /** @var array<int,string> */
    public array $actorTypes = [];

    /** @var array<int,int> */
    public array $actorIds = [];

    /** @var array<int,string> */
    public array $actions = [];

    /** @var array<int,string> */
    public array $subjectTypes = [];

    public string $subjectNumber = '';

    public string $transactionId = '';

    public string $docType = '';

    public string $subType = '';

    public string $accountCode = '';

    public ?int $accountId = null;

    public string $branchId = '';

    /** @var array<int,int> */
    public array $clientIds = [];

    /** @var array<int,int> */
    public array $agentIds = [];

    /** @var array<int,int> */
    public array $supplierIds = [];

    public string $amountMin = '';

    public string $amountMax = '';

    public string $postingPeriod = '';

    public string $datePreset = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $reason = '';

    public string $route = '';

    public string $ip = '';

    public string $changedField = '';

    // ── UI state (not URL-persisted) ───────────────────────────────────────────────────────────
    public bool $filtersOpen = false;

    public ?int $expandedRow = null;

    /** @var array<int,string> */
    public array $visibleColumns = ['created_at', 'action', 'subject', 'actor', 'transaction', 'posting_period'];

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public string $presetName = '';

    /** Snapshot of the row-id ceiling at the time this render's page loaded — the "N new entries"
     *  banner compares the live max id against this, never auto-refreshing the table itself. */
    public int $knownMaxId = 0;

    public int $newEntryCount = 0;

    protected $queryString = [
        'search' => ['except' => ''],
        'actorTypes' => ['except' => []],
        'actorIds' => ['except' => []],
        'actions' => ['except' => []],
        'subjectTypes' => ['except' => []],
        'subjectNumber' => ['except' => ''],
        'transactionId' => ['except' => ''],
        'docType' => ['except' => ''],
        'subType' => ['except' => ''],
        'accountCode' => ['except' => ''],
        'branchId' => ['except' => ''],
        'clientIds' => ['except' => []],
        'agentIds' => ['except' => []],
        'supplierIds' => ['except' => []],
        'amountMin' => ['except' => ''],
        'amountMax' => ['except' => ''],
        'postingPeriod' => ['except' => ''],
        'datePreset' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'reason' => ['except' => ''],
        'route' => ['except' => ''],
        'ip' => ['except' => ''],
        'changedField' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('view', AccountingAuditLog::class);

        $this->knownMaxId = (int) (AccountingAuditLog::query()->max('id') ?? 0);
        $this->applyDatePreset($this->datePreset ?: 'skip');
    }

    /** Any filter or search change resets pagination and re-snapshots the "new entries" ceiling so
     *  the banner does not immediately reappear for rows the new filter itself would now include. */
    public function updated(string $name): void
    {
        if ($name === 'datePreset') {
            $this->applyDatePreset($this->datePreset);
        }

        if (! str_starts_with($name, 'visibleColumns') && $name !== 'filtersOpen' && $name !== 'expandedRow') {
            $this->resetPage();
            $this->newEntryCount = 0;
            $this->knownMaxId = (int) (AccountingAuditLog::query()->max('id') ?? 0);
        }
    }

    public function updatedAccountCode(): void
    {
        $companyId = $this->resolveCompanyId();
        $this->accountId = $this->accountCode !== '' && $companyId !== null
            ? (int) (Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $this->accountCode)->value('id') ?: 0) ?: null
            : null;
    }

    private function applyDatePreset(string $preset): void
    {
        $today = now()->startOfDay();

        match ($preset) {
            'today' => [$this->dateFrom, $this->dateTo] = [$today->toDateString(), $today->toDateString()],
            'yesterday' => [$this->dateFrom, $this->dateTo] = [$today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
            '7d' => [$this->dateFrom, $this->dateTo] = [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
            'month' => [$this->dateFrom, $this->dateTo] = [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
            default => null, // 'custom' or 'skip' — leave whatever dateFrom/dateTo the user set
        };
    }

    public function toggleRow(int $id): void
    {
        $this->expandedRow = $this->expandedRow === $id ? null : $id;
    }

    /** Design-pass fix: a column chooser with every checkbox unchecked leaves a table with only
     *  the expand-chevron column, which reads as broken rather than empty. Refuses to drop below
     *  one visible column, restoring 'created_at' (always present, the most orienting column). */
    public function updatedVisibleColumns(): void
    {
        if ($this->visibleColumns === []) {
            $this->visibleColumns = ['created_at'];
        }
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function resetFilters(): void
    {
        foreach (array_keys($this->queryString) as $key) {
            if ($key === 'sortField') {
                $this->sortField = 'created_at';
            } elseif ($key === 'sortDirection') {
                $this->sortDirection = 'desc';
            } elseif (in_array($key, ['actorTypes', 'actorIds', 'actions', 'subjectTypes', 'clientIds', 'agentIds', 'supplierIds'], true)) {
                $this->{$key} = [];
            } else {
                $this->{$key} = '';
            }
        }
        $this->accountId = null;
        $this->resetPage();
    }

    public function loadNewEntries(): void
    {
        $this->knownMaxId = (int) (AccountingAuditLog::query()->max('id') ?? 0);
        $this->newEntryCount = 0;
        $this->resetPage();
    }

    /** wire:poll.visible target — count-only, never mutates the table itself (owner contract:
     *  "never auto-jump the table"). */
    public function pollForNewEntries(): void
    {
        $companyId = $this->resolveCompanyId();
        $this->newEntryCount = (int) AccountingAuditLog::query()
            ->when($companyId !== null, fn (Builder $q) => $q->where('company_id', $companyId))
            ->where('id', '>', $this->knownMaxId)
            ->count();
    }

    public function savePreset(): void
    {
        if (trim($this->presetName) === '') {
            return;
        }

        AccountingAuditLogPreset::create([
            'user_id' => (int) Auth::id(),
            'company_id' => $this->resolveCompanyId(),
            'name' => trim($this->presetName),
            'filters' => $this->filterState(),
        ]);

        $this->presetName = '';
    }

    public function loadPreset(int $presetId): void
    {
        $preset = AccountingAuditLogPreset::query()
            ->where('user_id', Auth::id())
            ->find($presetId);

        if ($preset === null) {
            return;
        }

        foreach ($preset->filters as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
        $this->resetPage();
    }

    public function deletePreset(int $presetId): void
    {
        AccountingAuditLogPreset::query()->where('user_id', Auth::id())->where('id', $presetId)->delete();
    }

    /** @return array<string,mixed> */
    private function filterState(): array
    {
        return collect(array_keys($this->queryString))
            ->mapWithKeys(fn (string $key) => [$key => $this->{$key}])
            ->all();
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('view', AccountingAuditLog::class);

        $rows = $this->baseQuery()->limit(50000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'created_at', 'action', 'actor_type', 'actor_id', 'subject_type', 'subject_id', 'transaction_id', 'posting_period', 'reason', 'route', 'ip']);
            foreach ($rows as $row) {
                // SEC-1: every cell is routed through CsvSafe before fputcsv() so a stored
                // reason/route/ip (or any other column) beginning with = + - @ / tab / CR can
                // never be re-opened as a live formula in the exporting user's spreadsheet app.
                fputcsv($out, CsvSafe::row([
                    $row->id, optional($row->created_at)->toDateTimeString(), $row->action,
                    $row->actor_type, $row->actor_id, $row->subject_type, $row->subject_id,
                    $row->transaction_id, $row->posting_period, $row->reason, $row->route, $row->ip,
                ]));
            }
            fclose($out);
        }, 'accounting-audit-log-'.now()->format('Ymd-His').'.csv');
    }

    private function resolveCompanyId(): ?int
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        if ($this->companyIdOverride !== '' && ($user->hasRole('admin') || $user->role_id === Role::ADMIN)) {
            return (int) $this->companyIdOverride;
        }

        return getCompanyId($user);
    }

    public function isAdmin(): bool
    {
        $user = Auth::user();

        return $user !== null && ($user->hasRole('admin') || $user->role_id === Role::ADMIN);
    }

    /**
     * The single query every read path (render()/exportCsv()/pollForNewEntries()'s count) is built
     * from, so CSV export always "honours the active filters" identically to what the table shows.
     */
    private function baseQuery(): Builder
    {
        $companyId = $this->resolveCompanyId();
        $hasActiveFilter = $this->hasActiveFilter();

        $query = AccountingAuditLog::query()
            ->leftJoin('transactions', 'accounting_audit_log.transaction_id', '=', 'transactions.id')
            ->select('accounting_audit_log.*')
            ->when($companyId !== null, fn (Builder $q) => $q->where('accounting_audit_log.company_id', $companyId))
            ->when($this->actorTypes !== [], fn (Builder $q) => $q->whereIn('accounting_audit_log.actor_type', $this->actorTypes))
            ->when($this->actorIds !== [], fn (Builder $q) => $q->whereIn('accounting_audit_log.actor_id', $this->actorIds))
            ->when($this->actions !== [], fn (Builder $q) => $q->whereIn('accounting_audit_log.action', $this->actions))
            ->when($this->subjectTypes !== [], fn (Builder $q) => $q->whereIn('accounting_audit_log.subject_type', $this->subjectTypes))
            ->when($this->resolveSubjectNumber() !== null, fn (Builder $q) => $q->where('accounting_audit_log.subject_id', $this->resolveSubjectNumber()))
            ->when($this->transactionId !== '', fn (Builder $q) => $q->where(function (Builder $q2) {
                $q2->where('accounting_audit_log.transaction_id', $this->transactionId)
                    ->orWhere('transactions.reference_number', 'like', "%{$this->transactionId}%");
            }))
            ->when($this->docType !== '', fn (Builder $q) => $q->where('transactions.doc_type', $this->docType))
            ->when($this->subType !== '', fn (Builder $q) => $q->where('transactions.sub_type', $this->subType))
            ->when($this->branchId !== '', fn (Builder $q) => $q->where('transactions.branch_id', $this->branchId))
            ->when($this->clientIds !== [], fn (Builder $q) => $this->applyClientFilter($q, $this->clientIds))
            ->when($this->agentIds !== [], fn (Builder $q) => $this->applyAgentFilter($q, $this->agentIds))
            ->when($this->supplierIds !== [], fn (Builder $q) => $this->applySupplierFilter($q, $this->supplierIds))
            ->when($this->amountMin !== '', fn (Builder $q) => $q->where('transactions.total_debit', '>=', (float) $this->amountMin))
            ->when($this->amountMax !== '', fn (Builder $q) => $q->where('transactions.total_debit', '<=', (float) $this->amountMax))
            ->when($this->accountId !== null, fn (Builder $q) => $q->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('journal_entries')
                    ->whereColumn('journal_entries.transaction_id', 'transactions.id')
                    ->where('journal_entries.account_id', $this->accountId)
                    ->whereNull('journal_entries.deleted_at');
            }))
            ->when($this->postingPeriod !== '', fn (Builder $q) => $q->where('accounting_audit_log.posting_period', $this->postingPeriod))
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('accounting_audit_log.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('accounting_audit_log.created_at', '<=', $this->dateTo))
            ->when($this->reason !== '', fn (Builder $q) => $q->where('accounting_audit_log.reason', 'like', "%{$this->reason}%"))
            ->when($this->route !== '', fn (Builder $q) => $q->where('accounting_audit_log.route', 'like', "%{$this->route}%"))
            ->when($this->ip !== '', fn (Builder $q) => $q->where('accounting_audit_log.ip', 'like', "%{$this->ip}%"))
            ->when($this->changedField !== '', fn (Builder $q) => $q->where(function (Builder $q2) {
                // whereJsonContainsKey() takes a single dot/arrow-path COLUMN string, not a
                // (column, key) pair — the previous two-argument call passed $this->changedField
                // as the `$boolean` parameter instead, producing a fatal MySQL syntax error on
                // every request (confirmed live, see AuditLogScreenTest::
                // test_changed_field_filter_matches_a_key_in_before_or_after()). The path must be
                // built into the column string itself: "before->status" for a top-level key.
                $q2->whereJsonContainsKey('accounting_audit_log.before->'.$this->changedField)
                    ->orWhereJsonContainsKey('accounting_audit_log.after->'.$this->changedField);
            }));

        $this->applySearch($query, $hasActiveFilter);

        return $query->orderBy('accounting_audit_log.'.$this->sortField, $this->sortDirection);
    }

    /**
     * SEARCH BOX CONTRACT (owner 2026-08-30): with one or more filters ACTIVE, `search` narrows
     * further within them, scoped to subject number / reason / route / free-text (the dimensions
     * the contract names: "subject number/reason/free-text" on top of whatever the active filters
     * already selected). With NO filter active, `search` runs across every relevant field —
     * subject id, action, reason, route, ip, transaction reference number, and the before/after
     * JSON blobs — ranked simply by "any field matched" (a full relevance ranker is out of scope
     * for a `LIKE`-based MySQL search; every match is returned, order stays the explicit sort).
     */
    private function applySearch(Builder $query, bool $hasActiveFilter): void
    {
        $term = trim($this->search);
        if ($term === '') {
            return;
        }

        $like = "%{$term}%";

        $query->where(function (Builder $q) use ($like, $hasActiveFilter) {
            $q->where('accounting_audit_log.reason', 'like', $like)
                ->orWhere('accounting_audit_log.action', 'like', $like)
                ->orWhere('transactions.reference_number', 'like', $like);

            if (ctype_digit(trim($this->search))) {
                $q->orWhere('accounting_audit_log.subject_id', (int) $this->search);
            }

            if (! $hasActiveFilter) {
                $q->orWhere('accounting_audit_log.route', 'like', $like)
                    ->orWhere('accounting_audit_log.ip', 'like', $like)
                    ->orWhereRaw('JSON_SEARCH(accounting_audit_log.before, "one", ?) IS NOT NULL', [$like])
                    ->orWhereRaw('JSON_SEARCH(accounting_audit_log.after, "one", ?) IS NOT NULL', [$like]);
            }
        });
    }

    private function hasActiveFilter(): bool
    {
        foreach (array_keys($this->queryString) as $key) {
            if (in_array($key, ['sortField', 'sortDirection', 'search'], true)) {
                continue;
            }
            $value = $this->{$key};
            if (is_array($value) ? $value !== [] : $value !== '') {
                return true;
            }
        }

        return false;
    }

    /** invoice/refund number -> subject_id, for the two subject types this build's own writers
     *  actually produce (see AccountingAuditLog::subjectUrl()'s identical scope note). */
    private function resolveSubjectNumber(): ?int
    {
        if (trim($this->subjectNumber) === '') {
            return null;
        }

        if (ctype_digit(trim($this->subjectNumber))) {
            return (int) $this->subjectNumber;
        }

        if (in_array('refund', $this->subjectTypes, true) || $this->subjectTypes === []) {
            $id = Refund::withoutGlobalScopes()->where('refund_number', $this->subjectNumber)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        if (in_array('invoice', $this->subjectTypes, true) || $this->subjectTypes === []) {
            $id = Invoice::withoutGlobalScopes()->where('invoice_number', $this->subjectNumber)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        return -1; // no match: force an empty result rather than ignoring the typed number
    }

    /**
     * Client filter (owner refinement 2026-08-30: "client / agent / supplier (resolved through
     * the subject or the linked transaction)"). Two resolution paths, both id-based (never a name
     * match at query time — ids are picked from {@see self::clientOptions()}'s dropdown):
     *   1. the linked transaction's own header names the client directly
     *      (`transactions.entity_type = 'client'`, matching the `Transaction::entity_type` enum);
     *   2. the linked transaction (via `transactions.invoice_id`) OR the audit row's own subject
     *      (when `subject_type = 'invoice'`, `subject_id` IS the invoice id) points at an invoice
     *      whose `client_id` matches.
     *
     * @param  array<int,int>  $clientIds
     */
    private function applyClientFilter(Builder $query, array $clientIds): Builder
    {
        return $query->where(function (Builder $q) use ($clientIds) {
            $q->where(function (Builder $q2) use ($clientIds) {
                $q2->where('transactions.entity_type', 'client')
                    ->whereIn('transactions.entity_id', $clientIds);
            })->orWhereExists(function ($sub) use ($clientIds) {
                $sub->selectRaw('1')->from('invoices')
                    ->whereColumn('invoices.id', 'transactions.invoice_id')
                    ->whereIn('invoices.client_id', $clientIds);
            })->orWhere(function (Builder $q2) use ($clientIds) {
                $q2->where('accounting_audit_log.subject_type', 'invoice')
                    ->whereExists(function ($sub) use ($clientIds) {
                        $sub->selectRaw('1')->from('invoices')
                            ->whereColumn('invoices.id', 'accounting_audit_log.subject_id')
                            ->whereIn('invoices.client_id', $clientIds);
                    });
            });
        });
    }

    /**
     * Agent filter — same two resolution paths as {@see self::applyClientFilter()}, against
     * `transactions.entity_type = 'agent'` and `invoices.agent_id`.
     *
     * @param  array<int,int>  $agentIds
     */
    private function applyAgentFilter(Builder $query, array $agentIds): Builder
    {
        return $query->where(function (Builder $q) use ($agentIds) {
            $q->where(function (Builder $q2) use ($agentIds) {
                $q2->where('transactions.entity_type', 'agent')
                    ->whereIn('transactions.entity_id', $agentIds);
            })->orWhereExists(function ($sub) use ($agentIds) {
                $sub->selectRaw('1')->from('invoices')
                    ->whereColumn('invoices.id', 'transactions.invoice_id')
                    ->whereIn('invoices.agent_id', $agentIds);
            })->orWhere(function (Builder $q2) use ($agentIds) {
                $q2->where('accounting_audit_log.subject_type', 'invoice')
                    ->whereExists(function ($sub) use ($agentIds) {
                        $sub->selectRaw('1')->from('invoices')
                            ->whereColumn('invoices.id', 'accounting_audit_log.subject_id')
                            ->whereIn('invoices.agent_id', $agentIds);
                    });
            });
        });
    }

    /**
     * Supplier filter — resolved only "through the subject" (the brief's own wording): a supplier
     * is not a party to any journal transaction header in this schema (`transactions.entity_type`
     * has no 'supplier' member — {@see \App\Models\Transaction} — and `invoices` carries no
     * `supplier_id`), it is a property of the underlying `tasks` row a task-related audit entry
     * names as its subject (`subject_type = 'task'`).
     *
     * @param  array<int,int>  $supplierIds
     */
    private function applySupplierFilter(Builder $query, array $supplierIds): Builder
    {
        return $query->where('accounting_audit_log.subject_type', 'task')
            ->whereExists(function ($sub) use ($supplierIds) {
                $sub->selectRaw('1')->from('tasks')
                    ->whereColumn('tasks.id', 'accounting_audit_log.subject_id')
                    ->whereIn('tasks.supplier_id', $supplierIds);
            });
    }

    /** @return \Illuminate\Support\Collection<int,Client> */
    public function clientOptions(): \Illuminate\Support\Collection
    {
        $companyId = $this->resolveCompanyId();

        return Client::query()
            ->when($companyId !== null, fn (Builder $q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name']);
    }

    /** @return \Illuminate\Support\Collection<int,Agent> */
    public function agentOptions(): \Illuminate\Support\Collection
    {
        $companyId = $this->resolveCompanyId();

        return Agent::query()
            ->when($companyId !== null, fn (Builder $q) => $q->whereHas('branch', fn (Builder $b) => $b->where('company_id', $companyId)))
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name']);
    }

    /** @return \Illuminate\Support\Collection<int,Supplier> */
    public function supplierOptions(): \Illuminate\Support\Collection
    {
        $companyId = $this->resolveCompanyId();

        return Supplier::query()
            ->when($companyId !== null, fn (Builder $q) => $q->whereHas('companies', fn (Builder $c) => $c->where('companies.id', $companyId)))
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name']);
    }

    /** @return \Illuminate\Support\Collection<int,\App\Models\Branch> */
    public function branchOptions(): \Illuminate\Support\Collection
    {
        $companyId = $this->resolveCompanyId();

        return \App\Models\Branch::query()
            ->when($companyId !== null, fn (Builder $q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name']);
    }

    /** @return \Illuminate\Support\Collection<int,User> */
    public function actorOptions(): \Illuminate\Support\Collection
    {
        $companyId = $this->resolveCompanyId();

        $ids = AccountingAuditLog::query()
            ->when($companyId !== null, fn (Builder $q) => $q->where('company_id', $companyId))
            ->whereNotNull('actor_id')
            ->distinct()
            ->limit(200)
            ->pluck('actor_id');

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    public function render(): View
    {
        $entries = $this->baseQuery()->paginate(25);

        return view('livewire.accounting.audit-log-index', [
            'entries' => $entries,
            'actorOptions' => $this->actorOptions(),
            'clientOptions' => $this->clientOptions(),
            'agentOptions' => $this->agentOptions(),
            'supplierOptions' => $this->supplierOptions(),
            'branchOptions' => $this->branchOptions(),
            'presets' => AccountingAuditLogPreset::query()->where('user_id', Auth::id())->orderBy('name')->get(),
        ]);
    }
}
