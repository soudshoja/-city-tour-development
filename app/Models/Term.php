<?php

namespace App\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    use HasFactory;
    const LANGUAGE_EN = 'EN';
    const LANGUAGE_AR = 'ARB';

    protected $fillable = [
        'company_id',
        'created_by',
        'title',
        'content',
        'language',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function languages()
    {
        return [
            self::LANGUAGE_EN => 'English',
            self::LANGUAGE_AR => 'Arabic',
        ];
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function setAsDefault()
    {
        // Unset other defaults for this company
        self::where('company_id', $this->company_id)
            ->where('language', $this->language)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get the default template for a company
     */
    public static function getDefault($companyId)
    {
        return self::where('company_id', $companyId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}
