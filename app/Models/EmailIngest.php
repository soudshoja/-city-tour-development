<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailIngest extends Model
{
    protected $table = 'email_ingests';

    protected $fillable = [
        'company_id',
        'agent_id',
        'mailbox',
        'message_id',
        'from_address',
        'supplier_slug',
        'file_name',
        'pnr',
        'status',
        'note',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
