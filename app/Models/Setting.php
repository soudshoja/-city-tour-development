<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_id',
        'key',
        'value',
        'type',
        'description'
    ];

    public function getValueAttribute($value)
    {
        switch ($this->type) {
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'array':
                return json_decode($value, true);
            case 'string':
            default:
                return (string) $value;
        }
    }

    public function setValueAttribute($value)
    {
        switch ($this->type) {
            case 'integer':
                $this->attributes['value'] = (string) (int) $value;
                break;
            case 'float':
                $this->attributes['value'] = (string) (float) $value;
                break;
            case 'boolean':
                $this->attributes['value'] = $value ? 'true' : 'false';
                break;
            case 'array':
                $this->attributes['value'] = json_encode($value);
                break;
            case 'string':
            default:
                $this->attributes['value'] = (string) $value;
                break;
        }
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
