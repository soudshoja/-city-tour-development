<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoBilling extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'created_by',
        'agent_id',
        'issued_by',
        'client_id',
        'add_amount',
        'gateway_id',
        'method_id',
        'invoice_time_company',
        'invoice_time_system',
        'timezone',
        'auto_send_whatsapp',
        'is_active',
    ];

    protected $casts = [
        'auto_send_whatsapp' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function gateway()
    {
        return $this->belongsTo(Charge::class, 'gateway_id');
    }

    public function method()
    {
        return $this->belongsTo(PaymentMethod::class, 'method_id');
    }
}
