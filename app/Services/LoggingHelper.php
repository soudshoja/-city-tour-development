<?php

namespace App\Services;

use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoggingHelper
{
    public static function log($model, $currentValue, $newValue, $remarks)
    {
        try {
            SystemLog::create([
                'user_id' => Auth::id(),
                'model' => $model,
                'current_value' => is_array($currentValue) ? json_encode($currentValue) : $currentValue,
                'new_value' => is_array($newValue) ? json_encode($newValue) : $newValue,
                'remarks' => $remarks,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SystemLog failed: ' . $e->getMessage(), [
                'model' => $model,
                'remarks' => $remarks,
            ]);
        }
    }
}
