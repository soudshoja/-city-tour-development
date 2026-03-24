<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-company DOTW API credential record.
 *
 * Stores encrypted DOTW username and password for each company in the
 * multi-tenant B2B hotel booking system. One row per company (unique
 * constraint on company_id in the database).
 *
 * Security design:
 * - dotw_username and dotw_password are stored as Laravel-encrypted blobs
 *   via Crypt::encrypt() / Crypt::decrypt(). The raw encrypted blobs are
 *   never exposed in JSON serialization ($hidden array prevents this).
 * - Only the decrypted values are available through the Attribute accessors,
 *   and only to internal PHP code — never in API responses or logs.
 *
 * @property int $id
 * @property int $company_id
 * @property string $dotw_username Decrypted plaintext username (via accessor)
 * @property string $dotw_password Decrypted plaintext password (via accessor)
 * @property string $dotw_company_code DOTW company code (not sensitive)
 * @property float $markup_percent B2C markup percentage (default 20.00)
 * @property bool $is_active Whether DOTW access is enabled for this company
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class CompanyDotwCredential extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'company_dotw_credentials';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'dotw_username',
        'dotw_password',
        'dotw_company_code',
        'markup_percent',
        'is_active',
        'b2b_enabled',
        'b2c_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * Prevents encrypted credential blobs from appearing in API responses,
     * JSON exports, or log output.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'dotw_username',
        'dotw_password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'markup_percent' => 'float',
        'is_active' => 'boolean',
        'b2b_enabled' => 'boolean',
        'b2c_enabled' => 'boolean',
    ];

    /**
     * DOTW username accessor and mutator.
     *
     * Getter: decrypts the stored blob and returns plaintext.
     * Setter: encrypts the plaintext before storing.
     */
    protected function dotwUsername(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Crypt::decrypt($value),
            set: fn (string $value) => Crypt::encrypt($value),
        );
    }

    /**
     * DOTW password accessor and mutator.
     *
     * Getter: decrypts the stored blob and returns plaintext.
     * Setter: encrypts the plaintext before storing.
     */
    protected function dotwPassword(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Crypt::decrypt($value),
            set: fn (string $value) => Crypt::encrypt($value),
        );
    }

    /**
     * Get the company that owns these credentials.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope: filter by company_id and active status.
     *
     * Used by DotwService to load credentials for a specific company while
     * ensuring disabled companies cannot access the DOTW API.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId)->where('is_active', true);
    }

    /**
     * Calculate the markup multiplier for fare calculation.
     *
     * Example: 20% markup returns 1.20, so $100 * 1.20 = $120 final fare.
     *
     * @return float The multiplier (1 + markup_percent / 100)
     */
    public function getMarkupMultiplier(): float
    {
        return 1 + ($this->markup_percent / 100);
    }
}
