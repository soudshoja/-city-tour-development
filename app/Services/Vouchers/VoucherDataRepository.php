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
                $blocks['hotel'] = $this->hotelBlock($task);
                $resolvedType = $blocks['hotel'] !== null ? 'hotel' : 'generic';
                break;
            case 'visa':
                $blocks['visa'] = $this->visaBlock($task);
                $resolvedType = $blocks['visa'] !== null ? 'visa' : 'generic';
                break;
            case 'insurance':
                $blocks['insurance'] = $this->insuranceBlock($task);
                $resolvedType = $blocks['insurance'] !== null ? 'insurance' : 'generic';
                break;
        }

        // Every task, typed or not, detail row or not, always gets a
        // usable generic segment behind it (plan §7: "always, never an
        // error, never an empty hole").
        if ($resolvedType === 'generic') {
            $blocks['segment'] = $this->genericSegmentBlock($task);
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
            'roster' => $this->flightRoster($task, $companyId),
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
     * reissue/void chain — plan §13-BIS/V9), scoped to $companyId. A task
     * with no PNR at all — or whose PNR matches nothing else, which
     * should not happen but is handled honestly if it does — is its own
     * one-row roster.
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

        return $siblings->map(function (Task $sibling) {
            $detail = TaskFlightDetail::where('task_id', $sibling->id)->where('is_ancillary', false)->first();

            return [
                'task_id' => $sibling->id,
                'passenger_name' => $sibling->passenger_name,
                'ticket_number' => $sibling->ticket_number,
                'seat_no' => $detail->seat_no ?? null,
                'baggage_allowed' => $detail->baggage_allowed ?? null,
                'flight_meal' => $detail->flight_meal ?? null,
                'class_type' => $detail->class_type ?? null,
            ];
        })->all();
    }

    /**
     * task_hotel_details + hotels (plan §6 hotel row). Decodes
     * cancellation_policy the same defensive way as taskCommonBlock (the
     * task-level column, not a hotel-detail column) and room_details JSON
     * exactly as TaskController::hotelPdf does.
     */
    protected function hotelBlock(Task $task): ?array
    {
        $detail = TaskHotelDetail::where('task_id', $task->id)->with('hotel')->first();

        if (! $detail) {
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
        ];
    }

    protected function visaBlock(Task $task): ?array
    {
        $detail = TaskVisaDetail::where('task_id', $task->id)->first();

        if (! $detail) {
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
        ];
    }

    protected function insuranceBlock(Task $task): ?array
    {
        $detail = TaskInsuranceDetail::where('task_id', $task->id)->first();

        if (! $detail) {
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
        ];
    }

    /**
     * The honest fallback for every task type with no detail table (car,
     * rail, tour, esim, event — plan §0/§5) AND for a typed flight/hotel/
     * visa/insurance task whose detail row is simply missing. Always
     * returns an array, never null (plan §7: "always, never an empty
     * hole"). $task->venue/additional_info verified live to carry
     * genuinely printable free text for transfers (plan §0).
     */
    protected function genericSegmentBlock(Task $task): array
    {
        return [
            'type_label' => $task->type ? Str::title(str_replace(['_', '-'], ' ', $task->type)) : 'Service',
            'venue' => $task->venue,
            'additional_info' => $task->additional_info,
            'date' => optional($task->issued_date)?->toDateString(),
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
