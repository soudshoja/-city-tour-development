<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFAController extends Controller
{
    public function twofa()
    {
        $user = Auth::user();
        $google2fa = new Google2FA();

        $secret = $google2fa->generateSecretKey();

        $qr_code = $google2fa->getQRCodeInline(
            config('app.name'),
            $user->email,
            $secret
        );

        session(['2fa_secret' => $secret]);

        return view('auth.two-fa', compact('qr_code'))->render();
    }

    public function twofaEnable(Request $request)
    {
        $google2fa = new Google2FA();

        $secret = session('2fa_secret');
        $authUser = Auth::user();
        $user = User::find($authUser->id);
        
        dd($google2fa->verify($request->input('otp'), $secret));

        if($google2fa->verify($request->input('otp'), $secret)) {
            $user->two_factor_code = $secret;
            $user->save();

            session(['2fa_checked' => true]);

            return redirect(action('TwoFAController@twofa'));
        }

        throw ValidationException::withMessages([
            'otp' => ['Invalid OTP'],
        ]);
    }
}
