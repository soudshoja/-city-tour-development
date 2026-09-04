<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappIngest extends Model
{
    protected $table = 'whatsapp_ingests';

    protected $fillable = [
        'company_id', 'agent_id', 'from_phone', 'country_code', 'message_id',
        'supplier_slug', 'file_name', 'pnr', 'confidence', 'status', 'task_id',
        'note', 'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
