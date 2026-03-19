<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale 
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->has('lang')) {
            $locale = $request->get('lang');
            if (in_array($locale, config('app,available_locales', ['en', 'arb']))) {
                Session::put('locale', $locale);
                App::setlocale($locale);
            }
        } elseif (Session::has('locale')) {
            App::setLocale(Session::get('locale'));
        } elseif (auth()->check() && auth()->user()->locale) {
            //For user that has saved preference
            App::setLocale(auth()->user()->locale);
            Session::put('locale', auth()->user()->locale);
        }

        return $next($request);
    }
}