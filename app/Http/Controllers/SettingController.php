<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\Request;

class SettingController extends Controller
{

    public function __construct()
    {
        SettingSeeder::run(); // Ensure settings are seeded on controller instantiation
    }

    public function index()
    {
        if(!(Auth()->user()->role_id = Role::ADMIN || Auth()->user()->role_id = Role::SUPER_ADMIN)) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to access settings.');
        }

        Setting::all()->each(function ($setting) {
            $setting->value = $setting->value; // Ensure value is cast correctly
        });

        $settings = Setting::all()->keyBy('key');

        view()->share('settings', $settings);
        view()->share('invoiceExpiryDefault', $settings->get('invoice_expiry_days', 5)->value);

        return view('settings.index');
    }

    public function updateInvoiceExpiry(Request $request)
    {

        if(!(Auth()->user()->role_id = Role::ADMIN || Auth()->user()->role_id = Role::SUPER_ADMIN)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update settings.',
            ], 403);
        }

        $request->validate([
            'invoice_expiry_default' => 'required|integer|min:1|max:365',
        ]);

        $expiryDays = (int) $request->input('invoice_expiry_default');
        $setting = Setting::updateOrCreate(
            ['key' => 'invoice_expiry_days'],
            [
                'value' => $expiryDays,
            ]
        );

        if(!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice expiry days.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invoice expiry days updated successfully.',
        ]);
    }
}
