<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingMedia extends Model
{
    protected $table = 'incoming_media';

    protected $fillable = [
        'phone',
        'media_id',
        'mime_type',
        'caption',
        'received_at',
    ];

    protected $dates = [
        'received_at',
        'created_at',
        'updated_at',
    ];
}
