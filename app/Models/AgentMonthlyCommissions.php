<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentMonthlyCommissions extends Model
{
    use HasFactory;
    protected $table = 'agent_monthly_commissions';

    protected $fillable = [
        'agent_id',
        'month',
        'year',
        'salary',
        'target',
        'commission_rate',
        'total_commission',
        'total_profit',
    ];

    public function agent() {
        return $this->belongsTo(Agent::class);
    }
}
