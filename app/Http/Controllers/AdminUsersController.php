<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminUsersController extends Controller
{
    public function index()
    {
        $NumberOfAdmins = User::where('role', 'admin')->count();
        $adminUsers = User::where('role', 'admin')->get();

        return view('adminsList', compact('adminUsers', 'NumberOfAdmins'));
    }
}