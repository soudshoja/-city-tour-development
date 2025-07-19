<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientGroup extends Model
{
    use HasFactory;

    // Define fillable columns
    protected $fillable = [
        'parent_client_id',
        'child_client_id',
        'relation'
    ];

    // Define relationships
    public function parentClient()
    {
        return $this->belongsTo(Client::class, 'parent_client_id');
    }

    public function childClient()
    {
        return $this->belongsTo(Client::class, 'child_client_id');
    }
}
