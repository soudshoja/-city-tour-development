<?php

namespace App\View\Components;

use App\Models\Company;
use App\Models\Role;
use Illuminate\View\Component;

class AdminCard extends Component
{
    public function render()
    {
        $isAdmin = auth()->user()->role_id == Role::ADMIN && auth()->user()->hasRole('admin');
        $companies = Company::all();

        return view('components.admin-card', compact(
            'isAdmin',
            'companies'
        ));
    }
}