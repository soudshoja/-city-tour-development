<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Http\Traits\Lockable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

class Invoice extends Model
{
    use HasFactory, Lockable, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'client_id',
        'agent_id',
        'currency',
        'sub_amount',
        'invoice_charge',
        'amount',
        'status',
        'invoice_date',
        'paid_date',
        'due_date',
        'label',
        'account_number',
        'bank_name',
        'swift_no',
        'iban_no',
        'country_id',
        'tax',
        'discount',
        'shipping',
        'accept_payment',
        'payment_type',
        'is_client_credit',
        'external_url',
        'is_locked',
        'locked_by',
        'locked_at',
        'agent_loss',
        'company_loss',
        'proforma_sent_at',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'agent_loss' => 'decimal:2',
        'company_loss' => 'decimal:2',
        'proforma_sent_at' => 'datetime',
    ];

    /**
     * W3a PROFORMA LOCK (owner decision, 2026-08-27): the amount-bearing columns named in
     * PROFORMA_LOCKED_COLUMNS below become immutable the instant `proforma_sent_at` is first set
     * — a proforma is shown to the client as a binding quote, so once it has gone out, this
     * codebase must never silently overwrite the numbers the client already saw. Any legitimate
     * amount change after that point has to go through a reverse + re-send flow (a NEW document/
     * amount, not an in-place edit of this one) — enforced centrally here, at the model layer,
     * rather than scattered across every controller method that can touch these columns, so no
     * future write path can accidentally bypass it.
     */
    private const PROFORMA_LOCKED_COLUMNS = ['amount', 'sub_amount', 'currency'];

    public static function boot()
    {
        parent::boot();

        static::saving(function ($invoice) {
            $validStatuses = array_column(InvoiceStatus::cases(), 'value');

            if (! in_array($invoice->status, $validStatuses, true)) {
                throw new InvalidArgumentException("Invalid invoice status: {$invoice->status}");
            }

            // The proforma lock only starts protecting a row once `proforma_sent_at` was ALREADY
            // non-null before this save — i.e. this is not the very save that is setting it for
            // the first time (that transition itself, and the amounts it carries at that exact
            // moment, are exactly what "sent as a proforma" means and must be allowed through).
            $wasAlreadySentAsProforma = $invoice->getOriginal('proforma_sent_at') !== null;

            if (! $wasAlreadySentAsProforma) {
                return;
            }

            foreach (self::PROFORMA_LOCKED_COLUMNS as $column) {
                if ($invoice->isDirty($column)) {
                    throw new \App\Exceptions\Accounting\ProformaAmountLockedException(
                        $invoice->getKey(),
                        $column,
                        $invoice->getOriginal($column),
                        $invoice->getAttribute($column)
                    );
                }
            }
        });
    }

    /**
     * When an invoice is locked, also lock:
     * - All transactions where invoice_id = this invoice
     * - All journal entries where invoice_id = this invoice
     */
    public static function getLockCascadeMap(): array
    {
        return [
            [Transaction::class,  'invoice_id'],
            [JournalEntry::class, 'invoice_id'],
        ];
    }

    /**
     * P2.5.E (p2_5-brief.md §P2.5.E): the one {@see \App\Http\Traits\Lockable::unlockBlockers()}
     * override this wave ships. Full chain-walk logic lives in
     * {@see \App\Services\Accounting\UnlockDependencyResolver} (a plain service, not a model
     * method) so it stays independently unit-testable and so {@see \App\Models\Transaction} /
     * {@see \App\Models\JournalEntry} -- the other two `Lockable` models -- never pull in
     * Invoice-specific traversal they cannot use.
     */
    public function unlockBlockers(): array
    {
        return app(\App\Services\Accounting\UnlockDependencyResolver::class)->blockersForInvoice($this);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function invoiceDetails()
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function invoicePartials()
    {
        return $this->hasMany(InvoicePartial::class);
    }

    public function JournalEntrys()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function originalRefunds()
    {
        // Refunds that refer to this invoice as the *original invoice*
        // → one invoice can have many refunds
        return $this->hasMany(Refund::class, 'invoice_id');
    }

    public function refund()
    {
        // Refund that uses this invoice as the *refund invoice*
        // → one refund invoice is linked to one refund record only
        return $this->hasOne(Refund::class, 'refund_invoice_id');
    }

    public function recalculateTotal()
    {
        $this->amount = $this->invoiceDetails()->sum('task_price');
        $this->sub_amount = $this->invoiceDetails()->sum('task_price');
        $this->save();
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class, 'invoice_id');
    }

    /**
     * Get all payment applications (payments applied to this invoice)
     */
    public function paymentApplications()
    {
        return $this->hasMany(PaymentApplication::class, 'invoice_id');
    }

    /**
     * Get total amount paid via payment applications
     */
    public function getTotalPaidViaApplicationsAttribute()
    {
        return PaymentApplication::getTotalAppliedToInvoice($this->id);
    }

    /**
     * Get remaining balance on this invoice
     */
    public function getRemainingBalanceAttribute()
    {
        return $this->amount - $this->total_paid_via_applications;
    }

    /**
     * Check if invoice is fully paid via payment applications
     */
    public function isFullyPaidViaApplications()
    {
        return $this->remaining_balance <= 0;
    }

    /**
     * Get effective loss settings for this invoice.
     * Returns invoice-level override if set, otherwise falls back to agent_loss table default.
     *
     * Bearer is derived from percentages:
     *   agent_loss=100 → agent bears | company_loss=100 → company bears | otherwise → split
     */
    public function getEffectiveLossSettings(): AgentLoss
    {
        if ($this->agent_loss !== null) {
            $agentPct = (float) $this->agent_loss;
            $companyPct = (float) $this->company_loss;

            if ($agentPct >= 100) {
                $bearer = AgentLoss::BEARER_AGENT;
            } elseif ($companyPct >= 100) {
                $bearer = AgentLoss::BEARER_COMPANY;
            } else {
                $bearer = AgentLoss::BEARER_SPLIT;
            }

            return new AgentLoss([
                'agent_id' => $this->agent_id,
                'company_id' => $this->agent?->branch?->company_id ?? 0,
                'loss_bearer' => $bearer,
                'agent_percentage' => $agentPct,
                'company_percentage' => $companyPct,
            ]);
        }

        $companyId = $this->agent?->branch?->company_id;
        if ($companyId && $this->agent_id) {
            return AgentLoss::getForAgent($this->agent_id, $companyId);
        }

        return new AgentLoss([
            'loss_bearer' => AgentLoss::BEARER_COMPANY,
            'agent_percentage' => 0,
            'company_percentage' => 100,
        ]);
    }

    public function hasLossBearerOverride(): bool
    {
        return $this->agent_loss !== null;
    }

    public function hasLoss(): bool
    {
        return $this->invoiceDetails->contains(fn ($d) => $d->profit < 0);
    }

    /**
     * A time-limited, unauthenticated link to a public-facing invoice
     * document -- e.g. for sharing over WhatsApp/Resayil/email or for a
     * payment-gateway redirect back to the client's browser. Reachable
     * ONLY via the matching 'invoice.<variant>.public' route (routes/web.php),
     * which is guarded by the 'signed' route middleware -- the signature
     * (and therefore the companyId/invoiceNumber, or clientId/partialId for
     * the 'split' variant, plus expiry) is verified on every request, so
     * this is the only key that needs protecting. Those routes also strip
     * internal pricing fields from what they hand to the view -- see
     * InvoiceController::scrubInvoiceDetailsForPublicView(). Expiry is
     * controlled by config('app.invoice_link_ttl_minutes'), default 7 days.
     *
     * @param  string  $variant  One of: show, pdf, proforma, proforma-pdf, split.
     * @param  array  $params  Extra/override route parameters. Required for
     *                         'split' (clientId, partialId); optional
     *                         overrides for companyId/invoiceNumber otherwise.
     * @param  string|null  $locale  When it resolves to Arabic (starts with
     *                               "ar"), routes to the '-arabic' twin for
     *                               variants that have one (show, split).
     *                               Defaults to the current app locale.
     */
    public function publicUrl(string $variant, array $params = [], ?string $locale = null): string
    {
        $arabic = str_starts_with($locale ?? app()->getLocale(), 'ar');

        $routes = [
            'show' => [
                'name' => $arabic ? 'invoice.show-arabic.public' : 'invoice.show.public',
                'keys' => ['companyId', 'invoiceNumber'],
            ],
            'pdf' => [
                'name' => 'invoice.pdf.public',
                'keys' => ['companyId', 'invoiceNumber'],
            ],
            'proforma' => [
                'name' => 'invoice.proforma.public',
                'keys' => ['companyId', 'invoiceNumber'],
            ],
            'proforma-pdf' => [
                'name' => 'invoice.proforma.pdf.public',
                'keys' => ['companyId', 'invoiceNumber'],
            ],
            'split' => [
                'name' => $arabic ? 'invoice.split-arabic.public' : 'invoice.split.public',
                'keys' => ['invoiceNumber', 'clientId', 'partialId'],
            ],
        ];

        if (! isset($routes[$variant])) {
            throw new InvalidArgumentException("Unknown invoice public URL variant: {$variant}");
        }

        $defaults = [
            'companyId' => $this->agent?->branch?->company_id,
            'invoiceNumber' => $this->invoice_number,
        ];

        $routeParams = [];
        foreach ($routes[$variant]['keys'] as $key) {
            $routeParams[$key] = $params[$key] ?? $defaults[$key] ?? null;
        }

        return URL::temporarySignedRoute(
            $routes[$variant]['name'],
            now()->addMinutes((int) config('app.invoice_link_ttl_minutes', 60 * 24 * 7)),
            $routeParams
        );
    }
}
