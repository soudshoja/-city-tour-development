<?php

namespace App\Models;

use App\Models\Company;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'fa_type_id',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    protected $cast = [
        'role_id' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'first_login',
        'password',
        'remember_token',
    ];

    protected $dates = [
        'email_verified_at',
        'two_factor_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    // protected function twoFactorCode(): Attribute
    // {
    //     return new Attribute(
    //         function ($value) {
    //             return $value ? decrypt($value) : null;
    //         },
    //         function ($value) {
    //             return $value ? encrypt($value) : null;
    //         }
    //     );
    // }

    public function agent()
    {
        return $this->hasOne(Agent::class);
    }

    public function client()
    {
        return $this->hasOne(Client::class);
    }

    public function branch()
    {
        return $this->hasOne(Branch::class);
    }

/*     public function company()
    {
        return $this->hasOne(Company::class);
    } */

    public function notification()
    {
        return $this->hasMany(Notification::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function accountant() 
    {
        return $this->hasOne(Accountant::class);
    }

// In User.php
public function company(): Attribute
{
    return Attribute::get(function () {
        // Case 1: user directly owns a company
        if ($this->hasOne(\App\Models\Company::class)->exists()) {
            return $this->hasOne(\App\Models\Company::class)->first();
        }

        // Case 2: accountant → branch → company
        if ($this->accountant && $this->accountant->branch) {
            return $this->accountant->branch->company;
        }

        // Case 3: agent → branch → company
        if ($this->agent && $this->agent->branch) {
            return $this->agent->branch->company;
        }

        // Case 4: branch → company
        if ($this->branch) {
            return $this->branch->company;
        }

        return null;
    });
}


}
