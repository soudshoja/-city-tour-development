<?php


// app/Models/Agent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_id',
        'country_id',
        'gds_office_id',
        'status',
        'code',
        'email',
        'logo',
        'address',
        'phone',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function agents()
    {
        return $this->hasManyThrough(Agent::class, Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function nationality()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'supplier_companies')
            ->using(SupplierCompany::class)
            ->withPivot('is_active');
    }

    /**
     * Get the main/default branch for this company
     * Returns the first branch or creates one if none exists
     */
    public function getMainBranch()
    {
        $mainBranch = $this->branches()->first();
        
        if (!$mainBranch) {
            // Create a default main branch if none exists
            $mainBranch = $this->branches()->create([
                'name' => $this->name . ' - Main Branch',
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
                'user_id' => $this->user_id,
            ]);
        }
        
        return $mainBranch;
    }
}
