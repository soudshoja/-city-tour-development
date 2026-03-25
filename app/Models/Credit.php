<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Credit extends Model
{
    use HasFactory, SoftDeletes;

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
        'refund_id',
        'type',
        'description',
        'amount',
        'gateway_fee',
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

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class);
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

    public static function getAvailableBalanceByRefund($refundId)
    {
        return self::where('refund_id', $refundId)->sum('amount');
    }

    public static function getAvailablePaymentsForClient($clientId)
    {
        $availablePayments = [];

        $topupPaymentIds = self::where('client_id', $clientId)
            ->where('type', self::TOPUP)
            ->pluck('payment_id')
            ->unique()
            ->filter();

        foreach ($topupPaymentIds as $paymentId) {
            $balance = self::getAvailableBalanceByPayment($paymentId);

            if ($balance > 0) {
                $payment = Payment::with('client')->find($paymentId);

                if ($payment) {
                    $availablePayments[] = [
                        'payment' => $payment,
                        'available_balance' => $balance,
                        'reference_number' => $payment->voucher_number,
                        'date' => $payment->payment_date ?? $payment->created_at,
                        'source_type' => 'topup',
                        'credit_id' => self::where('client_id', $clientId)
                            ->where('payment_id', $paymentId)
                            ->where('type', self::TOPUP)
                            ->orderBy('id', 'desc')
                            ->value('id'),
                        'refund_id' => null,
                    ];
                }
            }
        }

        $refundIds = self::where('client_id', $clientId)
            ->where('type', self::REFUND)
            ->pluck('refund_id')
            ->unique()
            ->filter();

        foreach ($refundIds as $refundId) {
            $balance = self::getAvailableBalanceByRefund($refundId);

            if ($balance > 0) {
                $refund = Refund::find($refundId);

                if ($refund) {
                    $availablePayments[] = [
                        'payment' => (object) [
                            'voucher_number' => $refund->refund_number,
                            'payment_date' => $refund->created_at ?? now(),
                            'created_at' => $refund->created_at ?? now(),
                        ],
                        'available_balance' => $balance,
                        'reference_number' => $refund->refund_number,
                        'date' => $refund->created_at ?? now(),
                        'source_type' => 'refund',
                        'credit_id' => self::where('client_id', $clientId)
                            ->where('refund_id', $refundId)
                            ->where('type', self::REFUND)
                            ->orderBy('id', 'desc')
                            ->value('id'),
                        'refund_id' => $refundId,
                    ];
                }
            }
        }

        // Sort by payment date (FIFO - oldest first) to deduct from oldest payments first
        usort($availablePayments, function ($a, $b) {
            $dateA = $a['date'];
            $dateB = $b['date'];
            return $dateA <=> $dateB;
        });

        return $availablePayments;
    }


    // public static function getAvailablePaymentsForClient($clientId)
    // {
    //     $availablePayments = [];

    //     $topupCredits = self::where('client_id', $clientId)
    //         ->where('type', self::TOPUP)
    //         ->whereNotNull('payment_id')
    //         ->get()
    //         ->groupBy('payment_id');

    //     foreach ($topupCredits as $paymentId => $credits) {
    //         $balance = self::getAvailableBalanceByPayment($paymentId);
    //         if ($balance > 0) {
    //             $payment = Payment::find($paymentId);
    //             if ($payment) {
    //                 $availablePayments[] = [
    //                     'payment' => $payment,
    //                     'refund' => null,
    //                     'available_balance' => $balance,
    //                     'source_type' => 'topup',
    //                     'reference_number' => $payment->voucher_number,
    //                     'date' => $payment->payment_date ?? $payment->created_at,
    //                     'credit_id' => $credits->first()->id,
    //                     'is_standalone' => false,
    //                 ];
    //             }
    //         }
    //     }

    //     $refundCredits = self::where('client_id', $clientId)
    //         ->where('type', self::REFUND)
    //         ->whereNotNull('refund_id')
    //         ->where('amount', '>', 0)
    //         ->get()
    //         ->groupBy('refund_id');

    //     foreach ($refundCredits as $refundId => $credits) {
    //         $balance = self::getAvailableBalanceByRefund($refundId);
    //         if ($balance > 0) {
    //             $refund = Refund::find($refundId);
    //             if ($refund) {
    //                 $availablePayments[] = [
    //                     'payment' => null,
    //                     'refund' => $refund,
    //                     'available_balance' => $balance,
    //                     'source_type' => 'refund',
    //                     'reference_number' => $refund->refund_number,
    //                     'date' => $refund->refund_date ?? $refund->created_at,
    //                     'credit_id' => $credits->first()->id,
    //                     'refund_id' => $refundId,
    //                     'is_standalone' => false,
    //                 ];
    //             }
    //         }
    //     }

    //     $standaloneRefunds = self::where('client_id', $clientId)
    //         ->where('type', self::REFUND)
    //         ->whereNull('refund_id')
    //         ->whereNull('payment_id')
    //         ->where('amount', '>', 0)
    //         ->get();

    //     foreach ($standaloneRefunds as $refundCredit) {
    //         // Calculate used amount
    //         $usedAmount = self::where('client_id', $clientId)
    //             ->where('type', self::INVOICE)
    //             ->where(function ($query) use ($refundCredit) {
    //                 $query->where('description', 'LIKE', "%refund credit #{$refundCredit->id}%")
    //                       ->orWhere('description', 'LIKE', "%RF-{$refundCredit->id}%");
    //             })
    //             ->sum('amount');

    //         $availableBalance = $refundCredit->amount + $usedAmount;

    //         if ($availableBalance > 0) {
    //             // Try to extract refund number from description
    //             $refundNumber = $refundCredit->description;
    //             if (preg_match('/RF-\d{4}-\d{5}/', $refundCredit->description, $matches)) {
    //                 $refundNumber = $matches[0];
    //             }

    //             $availablePayments[] = [
    //                 'payment' => null,
    //                 'refund' => null,
    //                 'available_balance' => $availableBalance,
    //                 'source_type' => 'refund',
    //                 'reference_number' => $refundNumber,
    //                 'date' => $refundCredit->created_at,
    //                 'credit_id' => $refundCredit->id,
    //                 'is_standalone' => true,
    //             ];
    //         }
    //     }

    //     // Sort by date (FIFO - oldest first)
    //     usort($availablePayments, function ($a, $b) {
    //         return $a['date'] <=> $b['date'];
    //     });

    //     return $availablePayments;
    // }

    public static function hasEnoughBalance($paymentId, $amount)
    {
        return self::getAvailableBalanceByPayment($paymentId) >= $amount;
    }
}
