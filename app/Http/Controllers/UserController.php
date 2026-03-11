<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => Role::BRANCH,
                'remember_token' => Str::random(10),
                'first_login' => 1,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'data' => $user,
            ], 201);

        } catch (Exception $e) {

            logger('User creation failed with error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'User creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
