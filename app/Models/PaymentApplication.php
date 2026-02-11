<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_id',
        'credit_id',
        'invoice_id',
        'invoice_partial_id',
        'amount',
        'applied_by',
        'applied_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'applied_at' => 'datetime',
    ];

    /**
     * Get the payment that was applied
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the source credit record
     */
    public function credit()
    {
        return $this->belongsTo(Credit::class);
    }

    /**
     * Get the invoice that received the payment
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the invoice partial (if partial/split payment)
     */
    public function invoicePartial()
    {
        return $this->belongsTo(InvoicePartial::class);
    }

    /**
     * Get the user who applied the payment
     */
    public function appliedBy()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /**
     * Get total amount applied from a specific payment
     */
    public static function getTotalAppliedByPayment($paymentId)
    {
        return self::where('payment_id', $paymentId)->sum('amount');
    }

    /**
     * Get total amount applied to a specific invoice
     */
    public static function getTotalAppliedToInvoice($invoiceId)
    {
        return self::where('invoice_id', $invoiceId)->sum('amount');
    }

    /**
     * Get total amount applied to a specific invoice partial
     */
    public static function getTotalAppliedToPartial($invoicePartialId)
    {
        return self::where('invoice_partial_id', $invoicePartialId)->sum('amount');
    }

    /**
     * Get all applications for a specific invoice with payment details
     */
    public static function getApplicationsForInvoice($invoiceId)
    {
        return self::where('invoice_id', $invoiceId)
            ->with(['payment', 'credit.refund', 'appliedBy'])
            ->orderBy('applied_at', 'desc')
            ->get();
    }

    /**
     * Get all applications from a specific payment with invoice details
     */
    public static function getApplicationsFromPayment($paymentId)
    {
        return self::where('payment_id', $paymentId)
            ->with(['invoice', 'invoicePartial', 'appliedBy'])
            ->orderBy('applied_at', 'desc')
            ->get();
    }

    /**
     * Check if this application is from a refund credit (not topup)
     */
    public function isFromRefund(): bool
    {
        return $this->payment_id === null && $this->credit?->refund_id !== null;
    }

    /**
     * Check if this application is from a topup credit
     */
    public function isFromTopup(): bool
    {
        return $this->payment_id !== null;
    }

    /**
     * Get the source reference number (voucher number or refund number)
     */
    public function getSourceReferenceAttribute(): ?string
    {
        if ($this->isFromTopup()) {
            return $this->payment?->voucher_number;
        }

        if ($this->isFromRefund()) {
            return $this->credit?->refund?->refund_number ?? ('RF-' . $this->credit?->refund_id);
        }

        return null;
    }

    /**
     * Get the source type label
     */
    public function getSourceTypeAttribute(): string
    {
        if ($this->isFromTopup()) {
            return 'Topup';
        }

        if ($this->isFromRefund()) {
            return 'Refund';
        }

        return 'Unknown';
    }
}
