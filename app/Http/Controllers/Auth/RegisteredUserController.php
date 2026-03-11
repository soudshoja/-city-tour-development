<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration form.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle admin registration request.
     */
    public function storeAdmin(Request $request): RedirectResponse
    {
        // Define allowed domains
        $allowedDomains = ['example.com', 'test.com', 'citytravelers.co'];

        // Validate the request
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'g-recaptcha-response' => ['required', 'recaptchav3:register,0.8'],
        ],[
            'g-recaptcha-response.required' => 'Please complete the reCAPTCHA verification.',
            'g-recaptcha-response.recaptchav3' => "The system detected suspicious activity. Please try again.",
        ]);

        // Extract the email domain
        $emailDomain = substr(strrchr($validatedData['email'], '@'), 1);

        // Check if the domain is allowed
        if (!in_array($emailDomain, $allowedDomains)) {
            return redirect()->back()->withErrors(['email' => 'The email domain is not allowed for admin registration.']);
        }

        // Create the admin user
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'role_id' => 1, // Assuming 1 is the role ID for admin
        ]);

        // Trigger the Registered event
        event(new Registered($user));

        // Redirect with success message
        return redirect()->route('login')->with('success', 'Admin registered successfully!');
    }
}
