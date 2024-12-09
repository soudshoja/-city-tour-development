<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentType extends Model
{
    protected $table = 'agent_type'; // Explicitly define the table name

    // Add the 'name' field to the fillable array
    protected $fillable = ['name'];

    public function agents()
    {
        return $this->hasMany(Agent::class, 'type_id'); // 'type_id' is the foreign key in the 'agents' table
    }
}
