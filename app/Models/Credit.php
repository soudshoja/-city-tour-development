<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Credit extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'client_id',
        'task_id',
        'type',
        'description',
        'amount',
        'created_at',
        'updated_at',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public static function getTotalCreditsByClient($clientId)
    {
        return self::where('client_id', $clientId)->sum('amount');
    }
}
