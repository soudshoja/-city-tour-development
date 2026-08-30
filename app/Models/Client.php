<?php

// app/Models/Agent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'agent_id',
        'address',
        'passport_no',
        'old_passport_no',
        'status',
        'civil_no',
        'date_of_birth',
        'phone',
        'country_code',
        'company_id',
    ];

    protected $appends = ['full_name', 'phone_number'];

    public function getFullNameAttribute()
    {
        return trim(collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->join(' '));
    }

    public function getPhoneNumberAttribute()
    {
        return trim(($this->country_code ?? '').($this->phone ?? ''));
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function agents()
    {
        return $this->belongsToMany(Agent::class, 'client_agents', 'client_id', 'agent_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function credits()
    {
        return $this->hasMany(Credit::class);
    }

    /**
     * A time-limited, unauthenticated link to this client's credit-ledger
     * statement (routes/web.php's 'clients.credits.shared', served by
     * ClientController::showCreditShared()) -- e.g. for sharing over
     * WhatsApp/Resayil or email. The 'signed' route middleware verifies the
     * signature (and therefore the client id and expiry) on every request,
     * so this is the only key that needs protecting -- there is no separate
     * token to store or revoke. Expiry is controlled by
     * config('app.client_credit_link_ttl_minutes'), default 7 days.
     */
    public function creditStatementUrl(): string
    {
        return URL::temporarySignedRoute(
            'clients.credits.shared',
            now()->addMinutes((int) config('app.client_credit_link_ttl_minutes', 60 * 24 * 7)),
            ['id' => $this->id]
        );
    }

    public function subClients()
    {
        return $this->hasMany(ClientGroup::class, 'parent_client_id');
    }

    public function parentClients()
    {
        return $this->hasMany(ClientGroup::class, 'child_client_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function refunds()
    {
        return $this->hasMany(RefundClient::class);
    }

    public function getTotalCreditAttribute()
    {
        return Credit::getTotalCreditsByClient($this->id);
    }
}
