<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Credit extends Model
{
    use HasFactory;

    public const INVOICE = 'Invoice';
    public const TOPUP = 'Topup';
    public const REFUND = 'Refund';
    public const INVOICE_REFUND = 'Invoice Refund';

    protected $fillable = [
        'company_id',
        'branch_id',
        'client_id',
        'invoice_id',
        'invoice_partial_id',
        'payment_id',
        'type',
        'description',
        'amount',
        'topup_by',
        'created_at',
        'updated_at',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($credit) {
            in_array($credit->type, [self::INVOICE, self::TOPUP, self::INVOICE_REFUND, self::REFUND]) or
                throw new InvalidArgumentException("Invalid credit type: {$credit->type}");
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

        public function invoicePartial()
    {
        return $this->belongsTo(invoicePartial::class);
    }

    public static function getTotalCreditsByClient($clientId)
    {
        return self::where('client_id', $clientId)->sum('amount');
    }

    public static function getTotalUtilizeCreditsByClientPartial($clientId, $partialId)
    {
        return self::where('client_id', $clientId)
            ->where('invoice_partial_id', $partialId)
            ->sum('amount');
    }

    public static function getAvailableBalanceByPayment($paymentId)
    {
        return self::where('payment_id', $paymentId)->sum('amount');
    }

    public static function getAvailablePaymentsForClient($clientId)
    {
        $topupPaymentIds = self::where('client_id', $clientId)
            ->where('type', self::TOPUP)
            ->pluck('payment_id')
            ->unique()
            ->filter();

        $availablePayments = [];

        foreach ($topupPaymentIds as $paymentId) {
            $balance = self::getAvailableBalanceByPayment($paymentId);
            if ($balance > 0) {
                $payment = Payment::with('client')->find($paymentId);
                if ($payment) {
                    $availablePayments[] = [
                        'payment' => $payment,
                        'available_balance' => $balance,
                    ];
                }
            }
        }

        // Sort by payment date (FIFO - oldest first) to deduct from oldest payments first
        usort($availablePayments, function ($a, $b) {
            $dateA = $a['payment']->payment_date ?? $a['payment']->created_at;
            $dateB = $b['payment']->payment_date ?? $b['payment']->created_at;
            return $dateA <=> $dateB;
        });

        return $availablePayments;
    }

    public static function hasEnoughBalance($paymentId, $amount)
    {
        return self::getAvailableBalanceByPayment($paymentId) >= $amount;
    }

    public function payment() {
        return $this->belongsTo(Payment::class);
    }
}
