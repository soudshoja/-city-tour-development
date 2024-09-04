<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFAController extends Controller
{
    public function twofa(Request $request)
    {
        $google2fa = new Google2FA();
        $user = Auth::user();
    
        $two_factor_data = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password,
        ];

        $two_factor_data['two_factor_code'] = $google2fa->generateSecretKey();
        
        $request->session()->put('two_factor_data', $two_factor_data);

        $qr_code_url = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $two_factor_data['two_factor_code']
        );

        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(400),
                new SvgImageBackEnd()
            )
        );

        $qr_code = base64_encode($writer->writeString($qr_code_url));
        
        return view('auth.two-fa', [
            'qrCode' => $qr_code,
            'secret' => $two_factor_data['two_factor_code'],
        ]);
    }

    public function twofaEnable(Request $request)
    {
        $authUser = Auth::user();
        $user = User::find($authUser->id);

        if($user->first_login){
            User::where('id', Auth::user()->id)->update(['first_login' => false]);
        }

        $user->two_factor_code = $request->session()->get('two_factor_data')['two_factor_code'];
        $user->fa_type_id = 1;
        $user->save();

        return redirect(route('dashboard', absolute: false));
    }
}
