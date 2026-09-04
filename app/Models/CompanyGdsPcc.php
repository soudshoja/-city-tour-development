<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyGdsPcc extends Model
{
    protected $table = 'company_gds_pccs';

    protected $fillable = [
        'company_id',
        'gds',
        'pcc',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
