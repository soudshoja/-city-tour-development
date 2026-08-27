<?php

namespace App\Services\Vouchers;

use App\Models\Agent;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskFlightDetail;
use App\Models\TaskHotelDetail;
use App\Models\TaskInsuranceDetail;
use App\Models\TaskPackage;
use App\Models\TaskVisaDetail;
use App\Models\Term;
use App\Models\VoucherTemplate;
use App\Services\Vouchers\Exceptions\VoucherCompanyMismatchException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The ONLY class that reads booking/money data for a voucher (plan §2.6,
 * §2.7). Every Blade template — and the frozen `travel_vouchers.snapshot`
 * — sees nothing but the plain array this class returns.
 *
 * Hard rules this class exists to enforce structurally (plan §2, §6, §9,
 * §11.1):
 *  1. Every field traces to a real column and MUST render as null on a
 *     missing row — never a fatal. A payload built from a fully-null task
 *     is a required, passing shape, not an edge case to special-case away.
 *  2. Paid/unpaid/partial comes from `invoice_details.paid` + the parent
 *     `invoices.status`/`paid_date` ONLY. Never `accounts.actual_balance`,
 *     never `journal_entries`. See paymentState().
 *  3. Every query carries company_id explicitly, sourced from the
 *     record itself — never Auth::user(). This class is called from the
 *     public tokenised voucher route, which has no authenticated user, so
 *     there is no global scope to lean on even by accident (plan §2.4).
 *  4. Money and payment-status blocks are opt-in per template
 *     (show_price / show_payment_status) — omitted (null) by default,
 *     per the plan §9 recommendation that a voucher shows no prices.
 *
 * Naming: task_flight_details/task_hotel_details/task_visa_details/
 * task_insurance_details are the only four task types with a real detail
 * table (plan §5). Every other task type — and any of those four typed
 * tasks that turn out to have NO detail row, which happens in real data
 * (verified live: 78 flight tasks, 8 hotel tasks with zero detail rows) —
 * degrades honestly to the generic segment block. It never renders an
 * error and never a silent empty hole (plan §7).
 */
class VoucherDataRepository
{
    /**
     * DOTW/board-code -> human label (mirrors TaskController::hotelPdf's
     * inline map exactly, plan §6 hotel row).
     */
    private const BOARD_LABELS = [
        'RO' => 'Room Only',
        'SC' => 'Self-Catering',
        'BB' => 'Bed & Breakfast',
        'HB' => 'Half Board',
        'FB' => 'Full Board',
        'AI' => 'All Inclusive',
        'RD' => 'Room Description',
    ];

    // ------------------------------------------------------------------
    // Public entry points
    // ------------------------------------------------------------------

    /**
     * The full variable-contract payload (plan §6) for one task, shaped
     * for direct template consumption and for freezing into
     * `travel_vouchers.snapshot` at issue time.
     *
     * $template drives the show_price/show_payment_status toggles and the
     * terms-language fallback; pass null for a template-less context (a
     * package item, a raw preview) — money/payment/terms then resolve to
     * their safe defaults (no money, no payment state, EN terms).
     *
     * $voucherMeta carries the travel_vouchers-row fields that only exist
     * once a voucher has actually been issued (number/version/issued_at/
     * qr_url/language). Pass [] for a Settings-gallery preview (plan §8:
     * "no travel_vouchers row created, no number consumed, no token
     * minted") — every voucher.* field then resolves to null.
     */
    public function payloadForTask(Task $task, int $companyId, ?VoucherTemplate $template = null, array $voucherMeta = []): array
    {
        $this->assertBelongsToCompany('task', $task->id, $task->company_id, $companyId);
        $this->assertTemplateBelongsToCompany($template, $companyId);

        $language = $voucherMeta['language'] ?? ($template->language ?? VoucherTemplate::LANGUAGE_EN);
        $showPrice = (bool) ($template->show_price ?? false);
        $showPaymentStatus = (bool) ($template->show_payment_status ?? false);

        $typeBlocks = $this->resolveTypeBlocks($task, $companyId);

        return [
            'task_type' => $task->type,
            'resolved_type' => $typeBlocks['resolved_type'],
            'company' => $this->companyBlock($companyId),
            'client' => $this->clientBlock($task->client_id, $companyId),
            'agent' => $this->agentBlock($task->agent_id),
            'task' => $this->taskCommonBlock($task),
            'flight' => $typeBlocks['flight'],
            'hotel' => $typeBlocks['hotel'],
            'visa' => $typeBlocks['visa'],
            'insurance' => $typeBlocks['insurance'],
            'segment' => $typeBlocks['segment'],
            'voucher' => $this->voucherMetaBlock($voucherMeta, $language),
            'terms' => $this->termsBlock($template, $companyId, $language),
            'money' => $showPrice ? $this->moneyBlock($task) : null,
            'payment' => $showPaymentStatus ? $this->paymentState($task, $companyId) : null,
        ];
    }

    /**
     * The composed payload for a package voucher (plan §7): a cover block
     * plus each member task's own payload, in the agent-controlled order
     * (task_package_items.sort_order — plan §7 "Agent sort_order/
     * section_label overrides win over the automatic order"), with a
     * date-based tiebreak for items the agent never reordered.
     *
     * Per-item money/payment/terms are NEVER resolved here — §9 is
     * explicit that a package shows one total, never per-line prices, so
     * each item's payload always carries template=null (no money/payment
     * leak per line) regardless of what $template says for the package
     * as a whole.
     */
    public function payloadForPackage(TaskPackage $package, int $companyId, ?VoucherTemplate $template = null, array $voucherMeta = []): array
    {
        $this->assertBelongsToCompany('task_package', $package->id, $package->company_id, $companyId);
        $this->assertTemplateBelongsToCompany($template, $companyId);

        $language = $voucherMeta['language'] ?? ($template->language ?? VoucherTemplate::LANGUAGE_EN);
        $showPrice = (bool) ($template->show_price ?? false);
        $showPaymentStatus = (bool) ($template->show_payment_status ?? false);

        // Explicit company_id re-check on the join, not just trust in the
        // package's own company_id (plan §2.4/§11.2 belt-and-braces).
        $tasks = $package->tasks()->where('tasks.company_id', $companyId)->get();

        $items = $tasks
            ->map(function (Task $task) use ($companyId) {
                $payload = $this->payloadForTask($task, $companyId);
                $payload['sort_order'] = (int) ($task->pivot->sort_order ?? 0);
                $payload['section_label'] = $task->pivot->section_label ?? null;
                $payload['itinerary_date'] = $this->bestDateForItem($payload);

                return $payload;
            })
            ->sort(function (array $a, array $b) {
                $orderCmp = $a['sort_order'] <=> $b['sort_order'];
                if ($orderCmp !== 0) {
                    return $orderCmp;
                }

                return (string) $a['itinerary_date'] <=> (string) $b['itinerary_date'];
            })
            ->values();

        return [
            'package' => [
                'id' => $package->id,
                'reference' => $package->reference,
                'name' => $package->name,
                'package_type' => $package->package_type,
                'status' => $package->status,
                'notes' => $package->notes,
                'segment_summary' => $this->segmentSummary($items),
            ],
            'company' => $this->companyBlock($companyId),
            'client' => $this->clientBlock($package->client_id, $companyId),
            'items' => $items->map(fn (array $item) => collect($item)->except(['sort_order', 'itinerary_date'])->all())->all(),
            'voucher' => $this->voucherMetaBlock($voucherMeta, $language),
            'terms' => $this->termsBlock($template, $companyId, $language),
            'money' => $showPrice ? $this->packageMoneyBlock($tasks) : null,
            'payment' => $showPaymentStatus ? $this->packagePaymentState($package, $companyId) : null,
        ];
    }

    /**
     * Paid/unpaid/partial for one task, derived exclusively from
     * `invoice_details.paid` + the parent `invoices.status`/`paid_date`
     * (plan §2.7, §9). Never touches accounts/journal_entries/
     * transactions. Neither `invoices` nor `invoice_details` carries a
     * company_id column, so the isolation boundary here IS the task_id:
     * $task must already have been resolved with company_id scoping by
     * the caller, and this method re-asserts that before doing anything.
     */
    public function paymentState(Task $task, int $companyId): array
    {
        $this->assertBelongsToCompany('task', $task->id, $task->company_id, $companyId);

        $detail = InvoiceDetail::whereNull('deleted_at')
            ->where('task_id', $task->id)
            ->latest('id')
            ->first();

        if (! $detail) {
            return $this->paymentStatePayload('not_invoiced', null, null, null);
        }

        $invoice = Invoice::whereNull('deleted_at')->find($detail->invoice_id);

        if (! $invoice) {
            return $this->paymentStatePayload('not_invoiced', null, null, null);
        }

        $linePaid = $detail->paid === null ? null : (bool) $detail->paid;
        $state = $this->normalizeInvoiceStatus($invoice->status);

        // A line explicitly marked paid is the most granular signal
        // available and wins over the invoice-level status (plan §9:
        // "plus per-line invoice_details.paid"). Verified live 2026-08-27
        // that this column is 0/NULL on every row today (never actually
        // set to 1) — this branch is dead in practice but is exactly what
        // makes the derivation correct the day something starts setting
        // it, without anyone having to revisit this method.
        if ($linePaid === true) {
            $state = 'paid';
        }

        return $this->paymentStatePayload(
            $state,
            $invoice->status,
            optional($invoice->paid_date)->toDateString(),
            $linePaid
        );
    }

    /**
     * Package = paid only if every member task is paid; unpaid only if
     * none has ever been invoiced or paid; partial otherwise (plan §9).
     */
    public function packagePaymentState(TaskPackage $package, int $companyId): array
    {
        $this->assertBelongsToCompany('task_package', $package->id, $package->company_id, $companyId);

        $tasks = $package->tasks()->where('tasks.company_id', $companyId)->get();

        if ($tasks->isEmpty()) {
            return $this->paymentStatePayload('not_invoiced', null, null, null);
        }

        $states = $tasks->map(fn (Task $task) => $this->paymentState($task, $companyId)['state']);

        if ($states->every(fn (string $s) => $s === 'paid')) {
            return $this->paymentStatePayload('paid', null, null, null);
        }

        if ($states->every(fn (string $s) => in_array($s, ['unpaid', 'not_invoiced'], true))) {
            return $this->paymentStatePayload('unpaid', null, null, null);
        }

        return $this->paymentStatePayload('partial', null, null, null);
    }

    // ------------------------------------------------------------------
    // Gallery / preview support (plan §8, §16 step 3)
    //
    // "Preview is auth-only, GET, renders HTML inline... no travel_vouchers
    // row created" (§8). These are lookups, not booking-content reads, but
    // they still touch tasks — kept here rather than in the controller so
    // every company_id-scoped read in this feature funnels through the one
    // class the plan names (§2.6), even the "which task do we preview
    // against" ones.
    // ------------------------------------------------------------------

    /**
     * The company's most recently created task of one real `tasks.type`
     * value, for the gallery's "preview against your own booking" card
     * (plan §8). Null when the company has none yet — the caller falls
     * back to a sample fixture.
     */
    public function latestTaskForType(int $companyId, string $taskType): ?Task
    {
        return Task::where('company_id', $companyId)
            ->where('type', $taskType)
            ->latest('id')
            ->first();
    }

    /**
     * The company's most recent task among the five types with no detail
     * table (plan §0/§5/§7) — what the Generic Segment catalogue card
     * previews against.
     */
    public function latestTaskForGenericTypes(int $companyId): ?Task
    {
        return Task::where('company_id', $companyId)
            ->whereIn('type', VoucherCatalogue::GENERIC_TASK_TYPES)
            ->latest('id')
            ->first();
    }

    /**
     * Company branding + voucher-meta + terms only, no task/type content —
     * for a sample-fixture preview (plan §8: "no booking of that type
     * yet... render from a shipped fixture payload"). VoucherSampleFixtures
     * merges this real company shell with its fabricated type block, so a
     * SAMPLE-watermarked card still wears the company's own logo/terms
     * rather than fake branding too. Still funnels through this class
     * (§2.6) — a fixture's company/terms data is real, only the booking
     * content is fabricated.
     */
    public function shellForCompany(int $companyId, ?VoucherTemplate $template, array $voucherMeta = []): array
    {
        $this->assertTemplateBelongsToCompany($template, $companyId);

        $language = $voucherMeta['language'] ?? ($template->language ?? VoucherTemplate::LANGUAGE_EN);

        return [
            'company' => $this->companyBlock($companyId),
            'voucher' => $this->voucherMetaBlock($voucherMeta, $language),
            'terms' => $this->termsBlock($template, $companyId, $language),
        ];
    }

    // ------------------------------------------------------------------
    // Common blocks
    // ------------------------------------------------------------------

    protected function companyBlock(int $companyId): array
    {
        $company = Company::find($companyId);

        // Voucher-only branding extras (plan §3.5, §14.4, §14.10): the
        // `companies` table is frozen to us (§2.5), so a duty/emergency
        // phone and a footer strapline live as `settings` rows instead —
        // `voucher.duty_phone` / `voucher.footer_note`. No column added
        // anywhere. No editing UI ships in this step (that is Phase C,
        // §14.4); a row set directly is honoured the moment it exists.
        $dutyPhone = $this->voucherSetting($companyId, 'voucher.duty_phone');
        $footerNote = $this->voucherSetting($companyId, 'voucher.footer_note');

        if (! $company) {
            return [
                'id' => $companyId,
                'name' => null,
                'logo' => null,
                'address' => null,
                'phone' => null,
                'email' => null,
                'whatsapp' => null,
                'socials' => ['facebook' => null, 'instagram' => null, 'snapchat' => null, 'tiktok' => null],
                'currency' => null,
                'duty_phone' => $dutyPhone,
                'footer_note' => $footerNote,
            ];
        }

        return [
            'id' => $company->id,
            'name' => $company->name,
            'logo' => $company->logo,
            'address' => $company->address,
            'phone' => $company->phone,
            'email' => $company->email,
            'whatsapp' => $company->whatsapp,
            'socials' => [
                'facebook' => $company->facebook,
                'instagram' => $company->instagram,
                'snapchat' => $company->snapchat,
                'tiktok' => $company->tiktok,
            ],
            'currency' => $company->currency,
            'duty_phone' => $dutyPhone,
            'footer_note' => $footerNote,
        ];
    }

    /**
     * A single `settings` row's value for this company, or null. The
     * column is literally named `key` (plan §3.5 — backtick it in raw
     * SQL; Eloquent's query builder already does so here).
     */
    protected function voucherSetting(int $companyId, string $key): ?string
    {
        $value = Setting::where('company_id', $companyId)->where('key', $key)->value('value');

        return $value !== null && $value !== '' ? $value : null;
    }

    /**
     * Explicit company_id scoping on the client lookup (plan §2.7 point 3:
     * "the single most likely place to leak another company's data").
     * Even though tasks.client_id should already belong to the same
     * company by application invariant, this method never trusts that —
     * a mismatched client silently renders as null, exactly like any
     * other missing-data case, never a leaked cross-tenant record.
     */
    protected function clientBlock(?int $clientId, int $companyId): ?array
    {
        if (! $clientId) {
            return null;
        }

        $client = Client::where('id', $clientId)->where('company_id', $companyId)->first();

        if (! $client) {
            return null;
        }

        $name = $client->full_name ?: $client->name;

        return [
            'id' => $client->id,
            'name' => $name !== '' ? $name : null,
            'phone' => $client->phone_number !== '' ? $client->phone_number : null,
            'email' => $client->email,
        ];
    }

    protected function agentBlock(?int $agentId): ?array
    {
        if (! $agentId) {
            return null;
        }

        $agent = Agent::find($agentId);

        if (! $agent) {
            return null;
        }

        return [
            'id' => $agent->id,
            'name' => $agent->name,
        ];
    }

    protected function taskCommonBlock(Task $task): array
    {
        return [
            'id' => $task->id,
            'type' => $task->type,
            'status' => $task->status,
            'reference' => $task->reference,
            'gds_reference' => $task->gds_reference,
            'airline_reference' => $task->airline_reference,
            'ticket_number' => $task->ticket_number,
            'passenger_name' => $task->passenger_name,
            'client_name' => $task->client_name,
            'issued_date' => optional($task->issued_date)?->toDateTimeString(),
            'expiry_date' => optional($task->expiry_date)?->toDateTimeString(),
            'duration' => $task->duration,
            'venue' => $task->venue,
            'additional_info' => $task->additional_info,
            'cancellation_policy' => $this->decodeCancellationPolicy($task->cancellation_policy),
            'cancellation_deadline' => optional($task->cancellation_deadline)?->toDateTimeString(),
        ];
    }

    protected function voucherMetaBlock(array $meta, string $language): array
    {
        return [
            'number' => $meta['number'] ?? null,
            'version' => $meta['version'] ?? null,
            'issued_at' => $meta['issued_at'] ?? null,
            'language' => $language,
            'qr_url' => $meta['qr_url'] ?? null,
        ];
    }

    /**
     * `voucher_templates.term_id` when the template points at one specific
     * term, else the company's own default term IN THE VOUCHER'S LANGUAGE
     * (plan §6). Term::getDefault() in this codebase does NOT filter by
     * language (checked 2026-08-27) — replicated here with the language
     * clause added rather than calling that method, since patching
     * Term.php is out of this step's scope.
     */
    protected function termsBlock(?VoucherTemplate $template, int $companyId, string $language): ?array
    {
        $term = null;

        if ($template && $template->term_id) {
            $term = Term::where('company_id', $companyId)->find($template->term_id);
        }

        if (! $term) {
            $term = Term::where('company_id', $companyId)
                ->where('language', $language)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();
        }

        if (! $term) {
            return null;
        }

        return [
            'title' => $term->title,
            'content' => $term->content,
            'language' => $term->language,
        ];
    }

    protected function moneyBlock(Task $task): array
    {
        return [
            'price' => $task->price,
            'total' => $task->total,
            'invoice_price' => $task->invoice_price,
            'currency' => $task->exchange_currency ?: 'KWD',
        ];
    }

    /**
     * One package total, never per-line supplier-revealing prices (plan
     * §9). $mixedCurrency is surfaced honestly rather than silently
     * summing incompatible currencies — a package template can choose to
     * warn staff on it; v1 does not attempt cross-currency conversion.
     */
    protected function packageMoneyBlock(Collection $tasks): array
    {
        $currencies = $tasks->map(fn (Task $t) => $t->exchange_currency ?: 'KWD')->unique()->values();

        return [
            'total' => (float) $tasks->sum(fn (Task $t) => (float) ($t->total ?? 0)),
            'currency' => $currencies->first() ?? 'KWD',
            'mixed_currency' => $currencies->count() > 1,
        ];
    }

    // ------------------------------------------------------------------
    // Per-type blocks (plan §5, §6)
    // ------------------------------------------------------------------

    /**
     * @return array{flight: array|null, hotel: array|null, visa: array|null, insurance: array|null, segment: array|null, resolved_type: string}
     */
    protected function resolveTypeBlocks(Task $task, int $companyId): array
    {
        $blocks = ['flight' => null, 'hotel' => null, 'visa' => null, 'insurance' => null, 'segment' => null];
        $resolvedType = 'generic';

        switch ($task->type) {
            case 'flight':
                $blocks['flight'] = $this->flightBlock($task, $companyId);
                $resolvedType = $blocks['flight'] !== null ? 'flight' : 'generic';
                break;
            case 'hotel':
                $blocks['hotel'] = $this->hotelBlock($task, $companyId);
                $resolvedType = $blocks['hotel'] !== null ? 'hotel' : 'generic';
                break;
            case 'visa':
                $blocks['visa'] = $this->visaBlock($task, $companyId);
                $resolvedType = $blocks['visa'] !== null ? 'visa' : 'generic';
                break;
            case 'insurance':
                $blocks['insurance'] = $this->insuranceBlock($task, $companyId);
                $resolvedType = $blocks['insurance'] !== null ? 'insurance' : 'generic';
                break;
        }

        // Every task, typed or not, detail row or not, always gets a
        // usable generic segment behind it (plan §7: "always, never an
        // error, never an empty hole").
        if ($resolvedType === 'generic') {
            $blocks['segment'] = $this->genericSegmentBlock($task, $companyId);
        }

        $blocks['resolved_type'] = $resolvedType;

        return $blocks;
    }

    /**
     * task_flight_details, every non-ancillary row for THIS task as one
     * itinerary leg, plus the passenger roster built from sibling tasks
     * sharing the same gds_reference (plan §6 flight row). Mirrors
     * TaskController::flightPdf's established grouping shape exactly, but
     * scoped to $companyId explicitly (that controller's version is the
     * confirmed enumeration hole this plan fixes elsewhere, §11.3).
     */
    protected function flightBlock(Task $task, int $companyId): ?array
    {
        $roster = $this->flightRoster($task, $companyId);

        // F4/R3: flightRoster() returns [] only when the anchor task
        // itself is dead (void or superseded) with no live sibling to
        // show instead. Rendering this task's OWN leg/ticket data in
        // that case would still print the dead ticket even though the
        // Passengers table is empty -- degrade the whole block to the
        // generic segment fallback (plan §7's existing degrade path,
        // resolveTypeBlocks()) instead, which carries no ticket number.
        if (empty($roster)) {
            return null;
        }

        $legs = TaskFlightDetail::where('task_id', $task->id)
            ->where('is_ancillary', false)
            ->with(['countryFrom', 'countryTo', 'airportFrom', 'airportTo', 'airline'])
            ->orderBy('departure_time')
            ->get();

        $ancillaries = TaskFlightDetail::where('task_id', $task->id)
            ->where('is_ancillary', true)
            ->get();

        if ($legs->isEmpty() && $ancillaries->isEmpty()) {
            return null;
        }

        return [
            'legs' => $legs->map(fn (TaskFlightDetail $leg) => $this->mapFlightLeg($leg))->all(),
            'ancillaries' => $ancillaries->map(fn (TaskFlightDetail $a) => [
                'description' => $a->baggage_allowed ?? $a->equipment ?? $a->class_type,
                'flight_number' => $a->flight_number,
                'ticket_number' => $a->ticket_number,
            ])->all(),
            'roster' => $roster,
        ];
    }

    protected function mapFlightLeg(TaskFlightDetail $leg): array
    {
        return [
            'departure_time' => optional($leg->departure_time)?->format('Y-m-d H:i'),
            'arrival_time' => optional($leg->arrival_time)?->format('Y-m-d H:i'),
            'duration_time' => $leg->duration_time,
            'airport_from' => $leg->airport_from,
            'airport_from_name' => optional($leg->airportFrom)->name,
            'terminal_from' => $leg->terminal_from,
            'airport_to' => $leg->airport_to,
            'airport_to_name' => optional($leg->airportTo)->name,
            'terminal_to' => $leg->terminal_to,
            'country_from' => optional($leg->countryFrom)->name,
            'country_to' => optional($leg->countryTo)->name,
            'airline' => optional($leg->airline)->name,
            'flight_number' => $leg->flight_number,
            'ticket_number' => $leg->ticket_number,
            'class_type' => $leg->class_type,
            'baggage_allowed' => $leg->baggage_allowed,
            'equipment' => $leg->equipment,
            'flight_meal' => $leg->flight_meal,
            'seat_no' => $leg->seat_no,
            'farebase' => $leg->farebase,
        ];
    }

    /**
     * Sibling tasks sharing this task's resolved gds_reference (Task's own
     * accessor, which falls back to the original task's PNR on a
     * reissue/void chain — plan §13-BIS/V9), scoped to $companyId, reduced
     * to the LIVE set via liveSiblings() (BLOCKER B1). A task with no PNR
     * at all — or whose PNR matches nothing else, which should not happen
     * but is handled honestly if it does — is its own one-row roster.
     *
     * BLOCKER B1, proven live on PNR 7NSYZS / task 19646: a naive
     * gds_reference match with no status filter and no dedupe returned 15
     * sibling rows for 5 real passengers (5 superseded originals + 5 void
     * tasks reusing the same dead ticket numbers + 5 live tickets) — and
     * status filtering alone does NOT fix it, because 10 of those 15 dead
     * rows still carry status=issued (the superseded originals were never
     * re-statused). liveSiblings() is the fix: it walks original_task_id
     * supersession + void self-exclusion instead of trusting status.
     */
    protected function flightRoster(Task $task, int $companyId): array
    {
        $gds = $task->gds_reference;

        $siblings = empty($gds)
            ? collect([$task])
            : Task::where('company_id', $companyId)->where('gds_reference', $gds)->orderBy('id')->get();

        if ($siblings->isEmpty()) {
            $siblings = collect([$task]);
        }

        $live = $this->liveSiblings($siblings);
        $live = $this->ensureAnchorPresent($task, $siblings, $live);

        // F4/R3: only fall back to the anchor when the anchor is itself
        // live. An anchor that is dead with no live sibling either must
        // resolve to an EMPTY roster, never its own dead ticket
        // (verified live on PNR 9VKQJP / task 8001, status=void, sole
        // sibling 8002 also void -- flightBlock() below turns this empty
        // roster into a full degrade to the generic segment block, so no
        // leg/ticket data from the dead task prints either).
        if ($live->isEmpty() && ! $this->isAnchorDead($task, $siblings)) {
            $live = collect([$task]);
        }

        if ($live->isEmpty()) {
            return [];
        }

        return $this->buildFlightTravellerRoster($live);
    }

    /**
     * Collapses the surviving flight siblings for a PNR into ONE row per
     * TRAVELLER, never one row per task (owner memo: "when 5 passengers
     * in a PNR there is comment information we don't need to
     * duplicate"). Grouping happens by travellerKey() first (passenger_
     * name/client_name, a trailing (CHD)/(INF) marker stripped for
     * matching only), then within each traveller the live rows are
     * reduced to their CURRENT ticket(s) by normalizeTicketKey(). Built
     * and measured against the four shapes verified live 2026-08-27:
     *
     *  - PNR 75H38H: the SAME physical ticket ingested three times (bare
     *    at 16:00, bare again at 22:00, plate-prefixed in September) --
     *    normalizeTicketKey() collapses all three rows to the one
     *    newest-task-id representative, so the ticket prints once.
     *  - PNR 78OZUB: every one of the 20 sibling rows carries
     *    status=reissued with original_task_id NULL (nothing points
     *    backward), and there are two ticket families (…2833184714-717,
     *    then …2833184817-820), each itself re-ingested two or three
     *    times. Per traveller: the same-family duplicates collapse
     *    first, then the reissue rule below keeps only the family with
     *    the higher normalised serial and drops the other.
     *  - PNR 17937089: passenger_name is NULL on every row (client_name
     *    carries the real name) and there is no void/original_task_id
     *    chain anywhere. Each traveller's ticket is stored twice as two
     *    different truncations of one 9-digit number, neither of which
     *    shares a hyphen suffix with the other, so they do not collapse
     *    into each other -- both print together on that traveller's one
     *    row, which is still correct: one row per real traveller.
     *  - PNR 72MBFY: the counter-case that must NOT collapse. One
     *    passenger, three tasks, all status=issued, no void, no
     *    original_task_id chain, three genuinely different ticket
     *    numbers on three different plate codes, three different
     *    prices. No reissued row anywhere means the "otherwise" branch
     *    applies: all three tickets are real live documents and all
     *    three print together on the one row for that passenger.
     */
    protected function buildFlightTravellerRoster(Collection $live): array
    {
        $details = TaskFlightDetail::whereIn('task_id', $live->pluck('id'))
            ->where('is_ancillary', false)
            ->get()
            ->keyBy('task_id');

        $groups = [];
        $order = [];

        foreach ($live as $sibling) {
            $key = $this->travellerKey($sibling);

            if (! isset($groups[$key])) {
                $groups[$key] = collect();
                $order[] = $key;
            }

            $groups[$key]->push($sibling);
        }

        $rows = [];
        foreach ($order as $key) {
            $rows[] = $this->buildFlightTravellerRow($groups[$key], $details);
        }

        usort($rows, fn (array $a, array $b) => $a['_min_id'] <=> $b['_min_id']);

        return array_map(function (array $row) {
            unset($row['_min_id']);

            return $row;
        }, $rows);
    }

    /**
     * One traveller's roster row from every surviving sibling task the
     * grouping in buildFlightTravellerRoster() matched to them.
     */
    protected function buildFlightTravellerRow(Collection $rows, Collection $detailsByTaskId): array
    {
        // Step 1: rows sharing a normalised ticket are the same physical
        // document (PNR 75H38H) -- collapse each such group to its
        // newest task id.
        $ticketGroups = [];
        $ticketOrder = [];

        foreach ($rows as $r) {
            $normalized = $this->normalizeTicketKey($r->ticket_number);
            $key = $normalized !== '' ? $normalized : ('__no_ticket_'.$r->id);

            if (! isset($ticketGroups[$key])) {
                $ticketGroups[$key] = collect();
                $ticketOrder[] = $key;
            }

            $ticketGroups[$key]->push($r);
        }

        $representatives = collect($ticketOrder)
            ->map(fn (string $key) => $ticketGroups[$key]->sortByDesc('id')->first())
            ->values();

        // Step 2: a reissue supersedes the family it replaced (PNR
        // 78OZUB) -- keep only the highest normalised ticket serial and
        // drop the rest. Without any reissued row, every remaining
        // distinct ticket is genuinely still held (PNR 72MBFY / 17937089)
        // and all of them stay.
        //
        // BUG 3 fix, verified live on PNR 8HUQ7U / passenger ALBUSAIRI:
        // task 15442 (reissued, ticket T-K229-9559097132) numerically
        // outranked three OTHER live tasks -- 15443/15572/15573 (issued,
        // TMCD229-1943551617/...396/...397) -- and dropped all three,
        // including 15573, the voucher's own anchor. TMCD is a different
        // document series (ancillary/EMD) from a T- air ticket with its
        // own independent numbering; comparing raw magnitude across
        // series is meaningless. The collapse below therefore only ever
        // compares representatives that share the same document
        // type-prefix (documentTypePrefix() -- the letters before the
        // digits, e.g. "T-K229-", "TMCD229-", or "" for bare digits with
        // no prefix); a reissued flag on a row only supersedes other rows
        // in ITS OWN prefix group, never a different document series, and
        // every genuinely-live document across every prefix group still
        // prints on the traveller's row (same outcome as PNR 72MBFY).
        $reissuedPrefixes = [];
        foreach ($rows as $r) {
            if ($r->status === 'reissued') {
                $reissuedPrefixes[$this->documentTypePrefix($r->ticket_number)] = true;
            }
        }

        $prefixGroups = [];
        $prefixOrder = [];
        foreach ($representatives as $rep) {
            $prefix = $this->documentTypePrefix($rep->ticket_number);

            if (! isset($prefixGroups[$prefix])) {
                $prefixGroups[$prefix] = collect();
                $prefixOrder[] = $prefix;
            }

            $prefixGroups[$prefix]->push($rep);
        }

        $kept = collect();
        foreach ($prefixOrder as $prefix) {
            $group = $prefixGroups[$prefix];

            if (($reissuedPrefixes[$prefix] ?? false) && $group->count() > 1) {
                $best = $group->sortByDesc(function (Task $t) {
                    $normalized = $this->normalizeTicketKey($t->ticket_number);

                    return is_numeric($normalized) ? (float) $normalized : -INF;
                })->first();

                $kept->push($best);
            } else {
                foreach ($group as $r) {
                    $kept->push($r);
                }
            }
        }

        $representatives = $kept->sortBy('id')->values();

        // Display name always tracks whichever row is newest overall,
        // even one whose ticket family got dropped above -- that is what
        // carries the (CHD)/(INF) marker or its absence (PNR 78OZUB:
        // ALDAIHANI/ALZAIN vs ALDAIHANI/ALZAIN(CHD)). Per-passenger detail
        // columns (seat/meal/baggage) belong to the newest KEPT row only.
        $newestOverall = $rows->sortByDesc('id')->first();
        $newestKept = $representatives->sortByDesc('id')->first();
        $detail = $detailsByTaskId->get($newestKept->id);

        $ticketNumbers = $representatives
            ->map(fn (Task $t) => $t->ticket_number)
            ->filter(fn (?string $v) => ! empty($v))
            ->values()
            ->all();

        return [
            'task_id' => $newestKept->id,
            // F3/R2: passenger_name is NULL on real data (verified live
            // on PNR 17937089) -- client_name carries the actual name
            // there. Never render blank when a real name exists.
            'passenger_name' => $newestOverall->passenger_name ?: $newestOverall->client_name,
            'ticket_number' => ! empty($ticketNumbers) ? implode(', ', $ticketNumbers) : null,
            'ticket_numbers' => $ticketNumbers,
            'seat_no' => $detail->seat_no ?? null,
            'baggage_allowed' => $detail->baggage_allowed ?? null,
            'flight_meal' => $detail->flight_meal ?? null,
            'class_type' => $detail->class_type ?? null,
            '_min_id' => $rows->min('id'),
        ];
    }

    /**
     * Groups a task into one traveller for roster collapsing (flight AND,
     * via collapseDuplicateDocuments(), visa/insurance): uppercased,
     * trimmed passenger_name (falling back to client_name), with a
     * trailing (CHD)/(INF)/(CHILD)/(INFANT) marker stripped for MATCHING
     * ONLY -- the marker itself is never part of a traveller's identity,
     * only a hint that the display name should still show whatever the
     * newest row actually carries (PNR 78OZUB: ALDAIHANI/ALZAIN vs
     * ALDAIHANI/ALZAIN(CHD) are the same person). A row with neither name
     * falls back to its own task id, which can never collide with
     * another row's key, so it can never wrongly merge with anyone.
     *
     * BUG 1 fix, verified live on PNR VMU4YN: 6 real passengers, each
     * ingested twice -- once as "Mr. Mubarak ALHAJERI" (period after the
     * honorific) and once as "Mr Mubarak ALHAJERI" (no period) -- with
     * nothing else different between the pair. A period is never
     * meaningful inside a passenger name, so every `.` is stripped from
     * the whole name before keying (not just from honorific tokens --
     * simpler and just as safe, since a stray period anywhere else in a
     * name carries no identity either). Any whitespace left behind by the
     * removal is collapsed so "MR. MUBARAK" and "MR MUBARAK" key
     * identically. This must NOT merge distinct real people: 72MBFY (one
     * person, 3 tickets, no periods in the name) and 17937089 (4 distinct
     * real people whose names differ by more than punctuation) are
     * unaffected.
     */
    protected function travellerKey(Task $task): string
    {
        $name = $task->passenger_name ?: $task->client_name;

        if (empty($name)) {
            return 'TASK#'.$task->id;
        }

        $normalized = strtoupper(trim($name));
        $normalized = trim((string) preg_replace('/\s*\((?:CHD|INF|CHILD|INFANT)\)\s*$/', '', $normalized));
        $normalized = str_replace('.', '', $normalized);
        $normalized = trim((string) preg_replace('/\s+/', ' ', $normalized));

        return $normalized !== '' ? $normalized : 'TASK#'.$task->id;
    }

    /**
     * Normalises a per-traveller document value (a flight ticket_number,
     * or a visa/insurance identifier) to the digits after its LAST hyphen
     * -- a bare ticket ('2833184813') and the same ticket re-ingested
     * with a plate-code prefix ('T-K077-2833184813') both normalise to
     * '2833184813' (verified live on PNR 75H38H). A value with no hyphen
     * at all normalises to itself (uppercased/trimmed) unchanged, which
     * is what keeps genuinely distinct identifiers (visa/insurance
     * document references, PNR 72MBFY's three unrelated ticket serials)
     * from ever colliding.
     */
    protected function normalizeTicketKey(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $trimmed = trim($raw);

        if ($trimmed === '') {
            return '';
        }

        $pos = strrpos($trimmed, '-');

        return strtoupper($pos === false ? $trimmed : substr($trimmed, $pos + 1));
    }

    /**
     * BUG 3: the document TYPE prefix -- everything up to and including
     * the LAST hyphen, uppercased -- of a per-traveller document value.
     * 'T-K229-9559097132' -> 'T-K229-'; 'TMCD229-1943551617' -> 'TMCD229-'
     * (the type-series letters/digits sit before that single hyphen); a
     * value with no hyphen at all (bare digits, or any identifier with no
     * series marker) normalises to '' so every such value groups
     * together as one series. Used ONLY to decide which documents are
     * allowed to compete in the reissue-collapse in
     * buildFlightTravellerRow() -- never to decide whether two rows are
     * the SAME document (that is normalizeTicketKey()'s job, on the
     * digits AFTER the last hyphen).
     */
    protected function documentTypePrefix(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $trimmed = trim($raw);

        if ($trimmed === '') {
            return '';
        }

        $pos = strrpos($trimmed, '-');

        return $pos === false ? '' : strtoupper(substr($trimmed, 0, $pos + 1));
    }

    /**
     * Reduce a sibling task set (same PNR, or same `reference` for a
     * non-flight booking) to the LIVE set — never dead, but NOT yet
     * collapsed down to one row per traveller; that grouping happens one
     * layer up (buildFlightTravellerRoster() for flight,
     * collapseDuplicateDocuments() for visa/insurance) because "live" and
     * "current per traveller" turned out to be two different questions.
     *
     * A task referenced by another sibling's `original_task_id` has been
     * superseded and is dead, REGARDLESS of what status the superseding
     * task itself carries — a void points backward at what it voided
     * (status=void), and a silent-update reissue does the very same thing
     * without ever touching status (verified live on hotel ref SRH44243:
     * task 13264, status=issued, carries original_task_id=13112, and
     * 13112's own status stayed 'confirmed' forever). Filtering on status
     * alone is NOT sufficient in either case.
     *
     * A sibling whose own status is `void` or `refund` is always dead
     * too, whether or not a replacement can yet be matched for it (plan
     * §13-BIS/V9: ~16% of voids have no original_task_id chain at all) —
     * a refunded ticket is not a travel document, regardless of chain.
     *
     * CORRECTED 2026-08-27 (owner measurement, second pass): the real
     * defect was never the dedupe KEY, it is that the same physical
     * ticket is present in `tasks` several times from repeated ingestion,
     * with nothing marking which row is current. Two earlier fixes each
     * chased one half of this and broke the other half:
     *
     *  - collapsing by NAME (first pass) silently dropped real tickets:
     *    PNR 72MBFY has 3 tasks, all status=issued, no void, no
     *    original_task_id chain, ONE passenger holding THREE genuinely
     *    different ticket numbers on three different plate codes — a
     *    name-based collapse suppressed 571 live tasks across 294 PNRs
     *    (17.4%) this way.
     *  - removing ALL collapsing (this method's previous state) went the
     *    other way: PNR 75H38H has 3 real passengers and 9 task rows —
     *    every ticket ingested three times (bare at 16:00, bare again at
     *    22:00, plate-prefixed in September) — with no collapse at all
     *    that is 9 rows for 3 people. PNR 78OZUB is worse: 4 real
     *    travellers, 20 rows, EVERY row status=reissued with
     *    original_task_id NULL (nothing points backward), two ticket
     *    families each re-ingested 2-3 times — 20 passenger rows for 4
     *    people, none of it flagged dead by status or supersession. PNR
     *    17937089: 4 real travellers, 8 rows, passenger_name NULL on all
     *    of them (client_name carries the name), each traveller's ticket
     *    stored twice as two different 8-character truncations of one
     *    9-digit number. Zero-collapsing suppressed nothing but printed
     *    622 extra rows across 323 PNRs.
     *
     * `ticket_number` itself is also not a safe collision key ACROSS task
     * types even once ticket duplication is handled correctly: verified
     * live on hotel ref CMT32218906820 (task 8725, 4 real guests, one
     * room, all status=issued/live), every one of the 4 sibling rows
     * shares the exact same ticket_number ('CMT32218906820' — the shared
     * booking reference, not a per-guest value) — collapsing hotel rows
     * on ticket_number would silently merge 4 live guests down to 2.
     *
     * This method therefore stays deliberately narrow — void/refund/
     * original_task_id exclusion ONLY, no ticket-level collapsing at all
     * — and every caller that needs "current ticket per traveller" (not
     * just "not dead") does that collapsing itself, scoped correctly per
     * task type: buildFlightTravellerRoster() normalises ticket_number by
     * its last-hyphen suffix and only ever compares it WITHIN one
     * traveller's own rows, never across travellers and never across a
     * hotel booking's shared reference.
     */
    protected function liveSiblings(Collection $siblings): Collection
    {
        $deadIds = $this->deadSiblingIds($siblings);

        return $siblings
            ->reject(fn (Task $s) => isset($deadIds[$s->id]))
            ->sortBy('id')
            ->values();
    }

    /**
     * The void/refund/supersession rule liveSiblings() rejects on,
     * extracted so callers can also ask "is THIS one task, specifically,
     * dead?" without re-deriving the whole live set (F2/F4).
     */
    protected function deadSiblingIds(Collection $siblings): array
    {
        $deadIds = [];

        foreach ($siblings as $sibling) {
            if (! empty($sibling->original_task_id)) {
                $deadIds[$sibling->original_task_id] = true;
            }
            if (in_array($sibling->status, ['void', 'refund'], true)) {
                $deadIds[$sibling->id] = true;
            }
        }

        return $deadIds;
    }

    /** Is $task itself void, or superseded by another sibling in $siblings pointing at it? */
    protected function isAnchorDead(Task $task, Collection $siblings): bool
    {
        return isset($this->deadSiblingIds($siblings)[$task->id]);
    }

    /**
     * F2, owner measurement on PNR 17937089 / task 5534: the voucher's
     * own subject task fell out of its own roster — 8 tasks, all
     * status=confirmed, no void/supersession chain, `passenger_name`
     * NULL on every row, rendering task 5534 produced 4 rows (5538-5541)
     * that did NOT include 5534 or its own ticket 19833251. The anchor
     * must appear in its own roster whenever it is itself live — as an
     * explicit guarantee here, not as an accident of ids/ordering/dedupe.
     */
    protected function ensureAnchorPresent(Task $task, Collection $siblings, Collection $live): Collection
    {
        if ($this->isAnchorDead($task, $siblings)) {
            return $live; // Dead anchor: F4's empty-roster fallback handles this, not this method.
        }

        if ($live->contains(fn (Task $s) => $s->id === $task->id)) {
            return $live;
        }

        return $live->push($task)->sortBy('id')->values();
    }

    /**
     * Sibling tasks sharing this task's `reference` column (the hotel/
     * visa/insurance/generic analogue of flightRoster()'s gds_reference
     * grouping — the established precedent for this is the legacy
     * TaskController::hotelPdf, which already groups hotel tasks by
     * `reference`, TaskController.php:4710), scoped to $companyId and
     * optionally to one `tasks.type` (so an accidental cross-type
     * `reference` collision never blends, say, a visa and a hotel task
     * into one roster).
     *
     * $hotelId additionally scopes to one physical hotel via
     * task_hotel_details — verified live that a bare `reference` match
     * can span two different hotels under one code (ref SRH44243: "The
     * Lodge Suites" id=2974 and "Loung Suites" id=2976) — blending those
     * would misattribute a guest to the wrong property's info block.
     */
    protected function siblingTasksByReference(Task $task, int $companyId, ?string $type = null, ?int $hotelId = null): Collection
    {
        $reference = $task->reference;

        if (empty($reference)) {
            return collect([$task]);
        }

        $query = Task::where('company_id', $companyId)->where('reference', $reference);

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($hotelId !== null) {
            $query->whereHas('hotelDetails', fn ($q) => $q->where('hotel_id', $hotelId));
        }

        $siblings = $query->orderBy('id')->get();

        return $siblings->isEmpty() ? collect([$task]) : $siblings;
    }

    /**
     * task_hotel_details + hotels (plan §6 hotel row). Decodes
     * cancellation_policy the same defensive way as taskCommonBlock (the
     * task-level column, not a hotel-detail column) and room_details JSON
     * exactly as TaskController::hotelPdf does.
     */
    protected function hotelBlock(Task $task, int $companyId): ?array
    {
        $detail = TaskHotelDetail::where('task_id', $task->id)->with('hotel')->first();

        if (! $detail) {
            return null;
        }

        $roster = $this->hotelRoster($task, $companyId, $detail->hotel_id);

        // F4/R3 analogue for hotel: an anchor that is itself dead with no
        // live sibling either must never render its own stay/room block
        // — degrade to the generic segment fallback instead, which
        // carries no room/rate/reference data from the dead booking.
        if (empty($roster)) {
            return null;
        }

        $roomDetails = $this->decodeJsonObject($detail->room_details);
        $roomName = $roomDetails['name'] ?? $detail->room_type;

        $hasStay = ! empty($detail->check_in) && ! empty($detail->check_out);

        return [
            'hotel' => $detail->hotel ? [
                'id' => $detail->hotel->id,
                'name' => $detail->hotel->name,
                'address' => $detail->hotel->address,
                'city' => $detail->hotel->city,
                'state' => $detail->hotel->state,
                'country' => $detail->hotel->country,
                'zip_code' => $detail->hotel->zip_code,
                'phone' => $detail->hotel->phone,
                'email' => $detail->hotel->email,
                'website' => $detail->hotel->website,
                'rating' => $detail->hotel->rating,
                'image' => $detail->hotel->image,
                'description' => $detail->hotel->description,
            ] : null,
            'check_in' => $detail->check_in,
            'check_out' => $detail->check_out,
            'nights' => $hasStay ? $detail->nights : null,
            'booking_time' => $detail->booking_time,
            'room_reference' => $detail->room_reference,
            'room_number' => $detail->room_number,
            'room_type' => $detail->room_type,
            'room_name' => $roomName,
            'room_amount' => $detail->room_amount,
            'room_promotion' => $detail->room_promotion,
            'rate' => $detail->rate,
            'meal_type' => $detail->meal_type,
            'meal_type_label' => self::BOARD_LABELS[$detail->meal_type] ?? $detail->meal_type,
            'is_refundable' => $detail->is_refundable === null ? null : (bool) $detail->is_refundable,
            'supplements' => $detail->supplements,
            'roster' => $roster,
        ];
    }

    /**
     * Guest roster for one hotel stay (BLOCKER B1 owner memo: "same goes
     * with hotel and other task types"). Reduced to the live set with the
     * same void/supersession logic as flightRoster() — only guest names
     * repeat per traveller, never the hotel/stay/room block above it.
     *
     * Deliberately does NOT run collapseDuplicateDocuments(): a hotel
     * task has no reliable per-guest identifier to normalise and collapse
     * on — `ticket_number` is the shared booking reference here, not a
     * per-guest value (verified live on hotel ref CMT32218906820, task
     * 8725 — 4 real guests, one room, every row sharing the exact same
     * ticket_number 'CMT32218906820'). Collapsing on it would merge those
     * 4 live guests down to 2. Every surviving sibling row stays its own
     * roster row.
     */
    protected function hotelRoster(Task $task, int $companyId, ?int $hotelId): array
    {
        $siblings = $this->siblingTasksByReference($task, $companyId, 'hotel', $hotelId);
        $live = $this->liveSiblings($siblings);
        $live = $this->ensureAnchorPresent($task, $siblings, $live);

        if ($live->isEmpty() && ! $this->isAnchorDead($task, $siblings)) {
            $live = collect([$task]);
        }

        return $live->map(fn (Task $sibling) => [
            'task_id' => $sibling->id,
            'guest_name' => $sibling->passenger_name ?: $sibling->client_name,
        ])->all();
    }

    protected function visaBlock(Task $task, int $companyId): ?array
    {
        $detail = TaskVisaDetail::where('task_id', $task->id)->first();

        if (! $detail) {
            return null;
        }

        $roster = $this->travellerRoster($task, $companyId, 'visa', 'application_number');

        // F4/R3 analogue for visa: degrade to the generic fallback rather
        // than render a dead anchor's own visa detail row.
        if (empty($roster)) {
            return null;
        }

        return [
            'visa_type' => $detail->visa_type,
            'application_number' => $detail->application_number,
            'appointment_date' => $detail->appointment_date,
            'expiry_date' => $detail->expiry_date,
            'number_of_entries' => $detail->number_of_entries,
            'stay_duration' => $detail->stay_duration,
            'issuing_country' => $detail->issuing_country,
            'roster' => $roster,
        ];
    }

    protected function insuranceBlock(Task $task, int $companyId): ?array
    {
        $detail = TaskInsuranceDetail::where('task_id', $task->id)->first();

        if (! $detail) {
            return null;
        }

        $roster = $this->travellerRoster($task, $companyId, 'insurance', 'document_reference');

        // F4/R3 analogue for insurance: degrade to the generic fallback
        // rather than render a dead anchor's own insurance detail row.
        if (empty($roster)) {
            return null;
        }

        return [
            'insurance_type' => $detail->insurance_type,
            'plan_type' => $detail->plan_type,
            'package' => $detail->package,
            'destination' => $detail->destination,
            'duration' => $detail->duration,
            'date' => $detail->date,
            'document_reference' => $detail->document_reference,
            'paid_leaves' => $detail->paid_leaves,
            'roster' => $roster,
        ];
    }

    /**
     * Traveller roster shared by visaBlock()/insuranceBlock() (BLOCKER B1
     * owner memo: "Visa / insurance / generic: same principle — shared
     * booking block once, per-person rows beneath"). Each visa/insurance
     * task still owns its own detail row (application_number, dates,
     * etc. are genuinely per-person, unlike a flight leg or a hotel
     * stay), so this only adds the sibling NAME list for context — it
     * never merges another sibling's detail row into the one rendered
     * above. $detailField names the one per-person identifier (visa's
     * application_number / insurance's document_reference) worth
     * carrying alongside each name so two travellers on the same
     * `reference` stay distinguishable.
     *
     * Same re-ingestion problem flightRoster() was built to handle can
     * happen here too — the same traveller's same application_number/
     * document_reference landing twice in `tasks` — so live rows are run
     * through collapseDuplicateDocuments() before rendering: a genuine
     * re-ingested repeat (same traveller, same normalised identifier)
     * collapses to its newest task id, while a traveller who genuinely
     * holds two DIFFERENT identifiers keeps both rows, exactly like
     * flightRoster() keeps two genuinely different tickets (PNR 72MBFY).
     * This still renders one row per surviving distinct document, not one
     * merged row per traveller — visa/insurance keep their current
     * reference-based grouping shape; only literal re-ingestion repeats
     * are removed.
     */
    protected function travellerRoster(Task $task, int $companyId, string $type, string $detailField): array
    {
        $siblings = $this->siblingTasksByReference($task, $companyId, $type);
        $live = $this->liveSiblings($siblings);
        $live = $this->ensureAnchorPresent($task, $siblings, $live);

        if ($live->isEmpty() && ! $this->isAnchorDead($task, $siblings)) {
            $live = collect([$task]);
        }

        $detailModel = $type === 'visa' ? TaskVisaDetail::class : TaskInsuranceDetail::class;

        $detailsByTaskId = $detailModel::whereIn('task_id', $live->pluck('id'))->get()->keyBy('task_id');

        $live = $this->collapseDuplicateDocuments(
            $live,
            fn (Task $sibling) => $detailsByTaskId->get($sibling->id)?->{$detailField}
        );

        return $live->map(function (Task $sibling) use ($detailsByTaskId, $detailField) {
            $detail = $detailsByTaskId->get($sibling->id);

            return [
                'task_id' => $sibling->id,
                'name' => $sibling->passenger_name ?: $sibling->client_name,
                $detailField => $detail->{$detailField} ?? null,
            ];
        })->all();
    }

    /**
     * The hotel/visa/insurance/generic analogue of the ticket-collapse
     * inside buildFlightTravellerRow() — used ONLY where a genuine
     * per-guest identifier exists ($documentResolver). Within each
     * traveller (travellerKey()), rows sharing a normalised
     * ($normalizeTicketKey()) document value are the same physical
     * document re-ingested twice and collapse to the newest task id; a
     * traveller with no document value, or two DIFFERENT document
     * values, is never merged — every distinct document keeps its own
     * row. hotelRoster() deliberately never calls this: `ticket_number`
     * on a hotel task is the shared booking reference, not a per-guest
     * value (verified live on hotel ref CMT32218906820, task 8725 — 4
     * real guests, one room, all sharing the exact same ticket_number
     * 'CMT32218906820'), so there is no safe per-guest identifier to
     * collapse on for hotel and this must not be called for it.
     */
    protected function collapseDuplicateDocuments(Collection $live, \Closure $documentResolver): Collection
    {
        $groups = [];
        $order = [];

        foreach ($live as $sibling) {
            $normalized = $this->normalizeTicketKey($documentResolver($sibling));
            $key = $normalized !== '' ? $this->travellerKey($sibling).'|'.$normalized : 'row#'.$sibling->id;

            if (! isset($groups[$key])) {
                $groups[$key] = collect();
                $order[] = $key;
            }

            $groups[$key]->push($sibling);
        }

        $result = collect();
        foreach ($order as $key) {
            $result->push($groups[$key]->sortByDesc('id')->first());
        }

        return $result->sortBy('id')->values();
    }

    /**
     * The honest fallback for every task type with no detail table (car,
     * rail, tour, esim, event — plan §0/§5) AND for a typed flight/hotel/
     * visa/insurance task whose detail row is simply missing. Always
     * returns an array, never null (plan §7: "always, never an empty
     * hole"). $task->venue/additional_info verified live to carry
     * genuinely printable free text for transfers (plan §0).
     */
    protected function genericSegmentBlock(Task $task, int $companyId): array
    {
        $siblings = $this->siblingTasksByReference($task, $companyId, $task->type);
        $live = $this->liveSiblings($siblings);
        $live = $this->ensureAnchorPresent($task, $siblings, $live);

        if ($live->isEmpty() && ! $this->isAnchorDead($task, $siblings)) {
            $live = collect([$task]);
        }

        return [
            'type_label' => $task->type ? Str::title(str_replace(['_', '-'], ' ', $task->type)) : 'Service',
            'venue' => $task->venue,
            'additional_info' => $task->additional_info,
            'date' => optional($task->issued_date)?->toDateString(),
            'roster' => $live->map(fn (Task $sibling) => [
                'task_id' => $sibling->id,
                'name' => $sibling->passenger_name ?: $sibling->client_name,
            ])->all(),
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Decodes tasks.cancellation_policy exactly as
     * TaskController::hotelPdf does (TaskController.php:4704) — the value
     * may be JSON, or a JSON string containing JSON (double-encoded).
     * Always returns an array, [] on anything unparseable, never throws.
     */
    protected function decodeCancellationPolicy(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $decoded = @json_decode($raw, true);

        if (is_string($decoded)) {
            $decoded = @json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** Same defensive shape as decodeCancellationPolicy(), for a JSON object payload (room_details). */
    protected function decodeJsonObject(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $decoded = @json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function paymentStatePayload(string $state, ?string $invoiceStatus, ?string $paidDate, ?bool $linePaid): array
    {
        return [
            'state' => $state, // not_invoiced | unpaid | partial | paid | refunded | partial_refund
            'is_paid' => $state === 'paid',
            'invoice_status' => $invoiceStatus,
            'paid_date' => $paidDate,
            'line_paid' => $linePaid,
        ];
    }

    protected function normalizeInvoiceStatus(?string $status): string
    {
        return match ($status) {
            'paid', 'paid by refund' => 'paid',
            'partial' => 'partial',
            'refunded' => 'refunded',
            'partial refund' => 'partial_refund',
            default => 'unpaid',
        };
    }

    /**
     * Best-available date for automatic itinerary ordering (plan §7:
     * flights by departure, hotels by check-in, generic segments by
     * best-available date, else issued_date) — used only as the tiebreak
     * under the agent's own sort_order.
     */
    protected function bestDateForItem(array $payload): ?string
    {
        return $payload['flight']['legs'][0]['departure_time']
            ?? $payload['hotel']['check_in']
            ?? $payload['segment']['date']
            ?? $payload['task']['issued_date']
            ?? null;
    }

    protected function segmentSummary(Collection $items): array
    {
        $counts = [];

        foreach ($items as $item) {
            $type = $item['resolved_type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        $labels = ['flight' => 'flight', 'hotel' => 'hotel', 'visa' => 'visa', 'insurance' => 'insurance', 'generic' => 'transfer/segment'];

        $parts = [];
        foreach ($counts as $type => $count) {
            $label = $labels[$type] ?? $type;
            $parts[] = $count.' '.Str::plural($label, $count);
        }

        return [
            'counts' => $counts,
            'summary_line' => implode(' · ', $parts),
        ];
    }

    protected function assertBelongsToCompany(string $subjectType, int $subjectId, ?int $subjectCompanyId, int $expectedCompanyId): void
    {
        if ((int) $subjectCompanyId !== $expectedCompanyId) {
            throw VoucherCompanyMismatchException::forSubject($subjectType, $subjectId, $subjectCompanyId, $expectedCompanyId);
        }
    }

    protected function assertTemplateBelongsToCompany(?VoucherTemplate $template, int $companyId): void
    {
        // company_id NULL = a shipped system template, visible to every
        // company (plan §3.1) — not a mismatch.
        if ($template && $template->company_id !== null && (int) $template->company_id !== $companyId) {
            throw VoucherCompanyMismatchException::forSubject('voucher_template', $template->id, $template->company_id, $companyId);
        }
    }
}
