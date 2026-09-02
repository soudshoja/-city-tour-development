<?php

declare(strict_types=1);

namespace App\Http\Livewire\Accounting;

use App\Models\Reminder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) UI sub-scope: "a reminder log screen (kind, target, status,
 * error, resend action)". Modelled on {@see \App\Http\Livewire\Accounting\AuditLogIndex}'s
 * query-per-render + URL-mirrored-filter pattern, scaled down to this screen's own, smaller
 * filter set.
 */
class ReminderLogIndex extends Component
{
    use WithPagination;

    public const KINDS = [
        Reminder::KIND_OVERDUE_INVOICE, Reminder::KIND_STATEMENT_BALANCE, Reminder::KIND_TICKETING_DEADLINE,
        Reminder::KIND_COMMISSION_UNEARNED, Reminder::KIND_PAYMENT_LINK_UNINVOICED,
        Reminder::KIND_TASK_UNASSIGNED, Reminder::KIND_TASK_UNINVOICED, Reminder::KIND_CUSTOM,
    ];

    public const STATUSES = [Reminder::STATUS_SENT, Reminder::STATUS_PENDING, Reminder::STATUS_FAILED, Reminder::STATUS_CANCELLED];

    public const TARGET_TYPES = ['invoice', 'payment', 'client', 'agent', 'task'];

    public string $search = '';

    /** @var array<int,string> */
    public array $kinds = [];

    /** @var array<int,string> */
    public array $statuses = [];

    /** @var array<int,string> */
    public array $targetTypes = [];

    public string $channel = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $sortField = 'scheduled_at';

    public string $sortDirection = 'desc';

    public ?int $resentId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'kinds' => ['except' => []],
        'statuses' => ['except' => []],
        'targetTypes' => ['except' => []],
        'channel' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function mount(): void
    {
        Gate::authorize('view', Reminder::class);
    }

    public function updated(string $name): void
    {
        if ($name !== 'resentId') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->kinds = [];
        $this->statuses = [];
        $this->targetTypes = [];
        $this->channel = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
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

    /**
     * P2.5.I: "resend action" -- only meaningful on a row that did NOT already send
     * (`failed`/`cancelled`), and only with the `manage` ability (a step up from `view`, matching
     * {@see \App\Policies\ReminderPolicy}'s own dual-ability split). Resetting to `pending` with
     * `scheduled_at = now()` puts it back in front of the very next `process:reminder` run;
     * `error_message` is cleared so a stale error does not linger next to a fresh attempt.
     */
    public function resend(int $reminderId): void
    {
        Gate::authorize('manage', Reminder::class);

        $reminder = Reminder::find($reminderId);
        if ($reminder === null || ! in_array($reminder->status, [Reminder::STATUS_FAILED, Reminder::STATUS_CANCELLED], true)) {
            return;
        }

        $reminder->update([
            'status' => Reminder::STATUS_PENDING,
            'scheduled_at' => now(),
            'error_message' => null,
            'is_active' => true,
        ]);

        $this->resentId = $reminderId;
    }

    public function canManage(): bool
    {
        return Gate::allows('manage', Reminder::class);
    }

    private function resolveCompanyId(): ?int
    {
        $user = Auth::user();

        return $user === null ? null : getCompanyId($user);
    }

    private function baseQuery(): Builder
    {
        $companyId = $this->resolveCompanyId();

        $query = Reminder::query()
            ->with(['client', 'agent', 'invoice', 'payment', 'task'])
            ->when($companyId !== null, fn (Builder $q) => $q->where('company_id', $companyId))
            ->when($this->kinds !== [], fn (Builder $q) => $q->whereIn('reminder_kind', $this->kinds))
            ->when($this->statuses !== [], fn (Builder $q) => $q->whereIn('status', $this->statuses))
            ->when($this->targetTypes !== [], fn (Builder $q) => $q->whereIn('target_type', $this->targetTypes))
            ->when($this->channel !== '', fn (Builder $q) => $q->where('channel', $this->channel))
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('scheduled_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('scheduled_at', '<=', $this->dateTo));

        $term = trim($this->search);
        if ($term !== '') {
            $like = "%{$term}%";
            $query->where(function (Builder $q) use ($like, $term) {
                $q->where('error_message', 'like', $like)
                    ->orWhere('dedupe_key', 'like', $like)
                    ->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', $like))
                    ->orWhereHas('agent', fn (Builder $a) => $a->where('name', 'like', $like))
                    ->orWhereHas('invoice', fn (Builder $i) => $i->where('invoice_number', 'like', $like))
                    ->orWhereHas('task', fn (Builder $t) => $t->where('reference', 'like', $like));
                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        return $query->orderBy($this->sortField, $this->sortDirection);
    }

    public function render(): View
    {
        return view('livewire.accounting.reminder-log-index', [
            'entries' => $this->baseQuery()->paginate(25),
        ]);
    }
}
