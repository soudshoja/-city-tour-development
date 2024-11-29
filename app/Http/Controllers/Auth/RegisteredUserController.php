<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Country;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $countries = Country::all(['id', 'name']); // Fetch all countries
        return view('auth.register', compact('countries'));
    }

    /**
     * Handle admin registration request.
     */
    public function storeAdmin(Request $request): RedirectResponse
    {

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => 1, // Assuming 1 is the role ID for admin
        ]);

        event(new Registered($user));

        return redirect()->route('login')->with('success', 'Admin registered successfully!');
    }

    /**
     * Handle company registration request.
     */
    public function storeCompany(Request $request): RedirectResponse
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'code' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'nationality_id' => ['required', 'exists:countries,id'],
            'status' => ['required', 'in:1,0'],
        ]);

        DB::transaction(function () use ($validatedData) {
            // Create the user
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'role_id' => 2, // Assuming 2 is the role ID for company
            ]);

            // Create the company
            Company::create([
                'name' => $validatedData['name'], // Use the same name as the user
                'email' => $validatedData['email'], // Use the same email as the user
                'code' => $validatedData['code'],
                'address' => $validatedData['address'],
                'nationality_id' => $validatedData['nationality_id'],
                'status' => $validatedData['status'],
                'user_id' => $user->id, // Link the company to the created user
            ]);
        });

        return redirect()->route('login')->with('success', 'Company registered successfully!');
    }
}
