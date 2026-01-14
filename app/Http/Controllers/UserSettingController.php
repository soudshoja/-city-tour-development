<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class UserSettingController extends Controller
{

    public function getSetting(Request $request)
    {
        $request->validate([
            'keys' => 'required|array|in:' . implode(',', UserSetting::KEYS),
            'keys.*' => 'string|in:' . implode(',', UserSetting::KEYS),
        ]);

        $userId = auth()->id();
        $keys = $request->input('keys');
        $settings = [];

        foreach ($keys as $key) {
            $settings[$key] = UserSetting::getValue($userId, $key);
        }

        Log::info("[USER SETTING] Retrieved settings", ['user_id' => $userId, 'keys' => $keys]);

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    public function show($userId)
    {
        $userSetting = UserSetting::where('user_id', $userId)->get();

        return;
    }

    public function update(Request $request)
    {
        Log::info("[USER SETTING] Update request received", ['request' => $request->all()]);

        $request->validate([
            'key' => 'required|string|in:' . implode(',', UserSetting::KEYS),
            'value' => 'required', // Can be boolean, string, etc.
        ]);

        $userId = auth()->id();
        $key = $request->input('key');
        $value = $request->input('value');

        $type = is_bool($value) ? 'boolean' : 'string';

        try {
            UserSetting::setValue($userId, $key, $value, $type);

            Log::info("[USER SETTING] Successfully updated setting", ['user_id' => $userId, 'key' => $key, 'value' => $value]);

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully.',
            ]);
        } catch (Exception $e) {

            Log::error("[USER SETTING] Failed to update setting", [
                'user_id' => $userId,
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting.',
            ], 500);
        }
    }
}
