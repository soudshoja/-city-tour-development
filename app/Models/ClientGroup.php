<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientGroup extends Model
{
    use HasFactory;

    // Disable automatic timestamps if you don't want to track created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'parent_client_id',
        'child_client_id',
        'created_at',
    ];

    /**
     * Define the relationship between parent client and the group.
     */
    public function parentClient()
    {
        return $this->belongsTo(Client::class, 'parent_client_id');
    }

    /**
     * Define the relationship between child client and the group.
     */
    public function childClient()
    {
        return $this->belongsTo(Client::class, 'child_client_id');
    }
}
