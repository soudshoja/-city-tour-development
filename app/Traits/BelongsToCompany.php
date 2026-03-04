<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    protected static ?int $resolvedCompanyId = null;

    public static function resolveCompanyId(): ?int
    {
        if (!Auth::check()) {
            static::$resolvedCompanyId = null;
            return null;
        }

        if (static::$resolvedCompanyId !== null) {
            return static::$resolvedCompanyId;
        }

        static::$resolvedCompanyId = getCompanyId(Auth::user());

        return static::$resolvedCompanyId;
    }

    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $q) {
            if (Auth::check()) {
                $id = static::resolveCompanyId();
                if ($id !== null) {
                    $q->where($q->qualifyColumn('company_id'), $id);
                }
            }
        });

        static::creating(function (Model $model) {
            if ($model->company_id === null && Auth::check()) {
                $id = static::resolveCompanyId();
                if ($id !== null) {
                    $model->company_id = $id;
                }
            }
        });
    }
}
