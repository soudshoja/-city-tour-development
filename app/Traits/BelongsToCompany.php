<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    protected static ?int $resolvedCompanyId = null;
    protected static ?int $resolvedForUserId = null;

    public static function resolveCompanyId(): ?int
    {
        if (!Auth::check()) {
            static::$resolvedCompanyId = null;
            static::$resolvedForUserId = null;
            return null;
        }

        $currentUserId = Auth::id();

        if (static::$resolvedCompanyId !== null && static::$resolvedForUserId === $currentUserId) {
            return static::$resolvedCompanyId;
        }

        static::$resolvedForUserId = $currentUserId;
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
