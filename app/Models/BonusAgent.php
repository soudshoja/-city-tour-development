<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;

class BonusAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'agent_id',
        'amount',
        'created_by',
        'company_id',
        'branch_id',
        'description',
    ];

    protected $casts = [
        'transaction_id' => 'integer',
        'agent_id' => 'integer',
        'amount' => 'decimal:2',
        'created_by' => 'integer',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}