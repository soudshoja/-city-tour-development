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

    public function payment() {
        return $this->belongsTo(Payment::class);
    }
}
