<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientGroup extends Model
{
    use HasFactory;

    // Define the table name if it's different from the plural of the model name
    protected $table = 'client_groups';

    // Auto incrementing primary key (it's handled by MySQL auto_increment)
    protected $primaryKey = 'id';
    public $incrementing = true;  // Allow auto-incrementing of the primary key

    // Disable timestamps if not used
    public $timestamps = false;

    // Define fillable columns
    protected $fillable = [
        'parent_client_id',
        'child_client_id',
        'relation',
        'created_at'
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
