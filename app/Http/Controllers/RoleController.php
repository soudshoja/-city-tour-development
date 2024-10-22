<?php


namespace App\Http\Controllers;


class RoleController extends Controller
{
    public function index()
    {
        $roles = [
            [
                'id' => 1,
                'name' => 'Admin'
            ],
            [
                'id' => 2,
                'name' => 'User'
            ],
            [
                'id' => 3,
                'name' => 'Guest'
            ]
        ];
        return view('role.index', compact('roles'));
    }

    public function create()
    {
        return view('role.create');
    }
}
