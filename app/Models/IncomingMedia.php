<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingMedia extends Model
{
    protected $table = 'incoming_media';

    protected $fillable = [
        'phone',
        'company_id',
        'media_id',
        'mime_type',
        'caption',
        'received_at',
        'file_path',
        'agent_phone',
        'agent_email',
        'agent_id',
        'media_type',
        'client_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    protected $dates = [
        'received_at',
        'created_at',
        'updated_at',
    ];
}
