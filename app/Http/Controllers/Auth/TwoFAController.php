<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFAController extends Controller
{
    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }
    
    public function twofa(Request $request)
    {
        // $user = Auth::user();
        // $google2fa = new Google2FA();

        // $secret = $google2fa->generateSecretKey();

        // $qr_code = $google2fa->getQRCodeInline(
        //     config('app.name'),
        //     $user->email,
        //     $secret
        // );

        // session(['2fa_secret' => $secret]);

        // $this->validator($request->all())->validate();

        $google2fa = app('pragmarx.google2fa');

        $user = Auth::user();

        $two_factor_data = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password,
        ];

        $two_factor_data['two_factor_code'] = $google2fa->generateSecretKey();

        $request->session()->put('two_factor_data', $two_factor_data);

        $qr_code = $google2fa->getQRCodeInline(
            config('app.name'),
            $user->email,
            $two_factor_data['two_factor_code']
        );
        
        return view('auth.two-fa', [
            'qr_code' => $qr_code,
            'secret' => $two_factor_data['two_factor_code'],
        ]);
    }

    public function twofaEnable(Request $request)
    {
        // $google2fa = new Google2FA();

        // $secret = session('2fa_secret');
        // $authUser = Auth::user();
        // $user = User::find($authUser->id);
        
        // dd($google2fa->verify($request->input('otp'), $secret));

        // if($google2fa->verify($request->input('otp'), $secret)) {
        //     $user->two_factor_code = $secret;
        //     $user->save();

        //     session(['2fa_checked' => true]);

        //     return redirect(action('TwoFAController@twofa'));
        // }

        // throw ValidationException::withMessages([
        //     'otp' => ['Invalid OTP'],
        // ]);

        $authUser = Auth::user();
        $user = User::find($authUser->id);

        $user->two_factor_code = $request->session()->get('two_factor_data')['two_factor_code'];
        $user->fa_type_id = 1;
        $user->save();

        return redirect(route('dashboard', absolute: false));
    }
}
