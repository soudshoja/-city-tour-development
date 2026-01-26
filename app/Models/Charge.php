<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class Charge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        // 'auth_type',
        'api_key',
        'tran_portal_id',
        'tran_portal_password',
        'terminal_resource_key',
        'paid_by',
        'amount',
        'extra_charge',
        'self_charge',
        'is_active',
        'can_generate_link',
        'charge_type',
        'company_id',
        'branch_id',
        'acc_bank_id',
        'acc_fee_id',
        'acc_fee_income_id',
        'acc_fee_bank_id',
        'is_auto_paid',
        'has_url',
        'can_charge_invoice',
        'is_system_default',
        'can_be_deleted',
        'enabled_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_generate_link' => 'boolean',
        'is_auto_paid' => 'boolean',
        'has_url' => 'boolean',
        'can_charge_invoice' => 'boolean',
        'is_system_default' => 'boolean',
        'can_be_deleted' => 'boolean',
    ];

    public function getAmountAttribute($value)
    {
        return number_format($value, 2);
    }

    public function setAmountAttribute($value)
    {
        $this->attributes['amount'] = str_replace(',', '', $value);
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function accFee()
    {
        return $this->belongsTo(Account::class, 'acc_fee_id');
    }

    public function accIncome()
    {
        return $this->belongsTo(Account::class, 'acc_fee_income_id');
    }

    public function accBank()
    {
        return $this->belongsTo(Account::class, 'acc_bank_id');
    }

    public function accBankFee()
    {
        return $this->belongsTo(Account::class, 'acc_fee_bank_id');
    }

    public function methods()
    {
        return $this->hasMany(PaymentMethod::class, 'charge_id');
    }

    /**
     * Check if this gateway has API implementation in code
     * 
     * This is a TECHNICAL CHECK (not business logic):
     * - Returns TRUE if code exists in app/Support/PaymentGateway/ or app/Services/
     * - Returns FALSE if gateway is custom/not implemented
     * 
     * Use Cases:
     * 1. Before attempting to generate payment links
     * 2. Validating if ChargeService methods exist for this gateway
     * 3. Showing/hiding API settings buttons in UI
     * 
     * Combined with can_generate_link (database field):
     * - hasApiImplementation() = Technical capability (code exists?)
     * - can_generate_link = Business permission (enabled by admin?)
     * 
     * Implementation locations to check:
     * - InvoiceController@show (line ~1926-1972) - Check before link generation
     * - InvoiceController@split (line ~2146-2175) - Validate before ChargeService call
     * - PaymentController@paymentShowLink (line ~1771-1794) - Recalculate fees safely
     * - PaymentController@paymentStoreLinkProcess (line ~1617-1630) - Validate before processing
     * - charges/index.blade.php - Conditionally show API settings button
     * 
     * @return bool True if API implementation exists in code
     */
    public function hasApiImplementation(): bool
    {
        $implementedGateways = ['Tap', 'MyFatoorah', 'Hesabe', 'UPayment'];
        return in_array($this->name, $implementedGateways, true);
    }

    /**
     * Check if payment link can be generated (combined validation)
     * 
     * This combines both technical and business checks:
     * - Technical: Does code implementation exist?
     * - Business: Is link generation enabled for this gateway?
     * 
     * Recommended usage pattern:
     * ```php
     * if (!$charge->canGeneratePaymentLink()) {
     *     if (!$charge->hasApiImplementation()) {
     *         return "Gateway not supported by system";
     *     }
     *     return "Link generation disabled for this gateway";
     * }
     * // Proceed with link generation
     * ```
     * 
     * @return bool True if both technical capability exists AND business permission granted
     */
    public function canGeneratePaymentLink(): bool
    {
        return $this->hasApiImplementation() && $this->can_generate_link;
    }

    protected static ?int $resolvedCompanyId = null;

    protected static function resolveCompanyId(): ?int
    {
        if (static::$resolvedCompanyId !== null) {
            return static::$resolvedCompanyId;
        }

        $user = Auth::user();

        if (!$user) {
            return null;
        }

        static::$resolvedCompanyId = getCompanyId($user);

        return static::$resolvedCompanyId;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $q) {
            $id = static::resolveCompanyId();
            if ($id !== null) {
                $q->where($q->qualifyColumn('company_id'), $id);
            }
        });
    }
}
