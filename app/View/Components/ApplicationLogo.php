<?php

namespace App\View\Components;

use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;
use Illuminate\View\View;

class ApplicationLogo extends Component
{
    public string $companyLogo;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->companyLogo = $this->determineCompanyLogo();
    }

    /**
     * Determine the appropriate company logo based on user role
     */
    private function determineCompanyLogo(): string
    {
        $defaultLogo = asset('images/UserPic.svg');
        $user = Auth::user();

        if (!$user || !$user->role) {
            return $defaultLogo;
        }

        $company = $this->getCompanyFromUserRole($user);

        return $company && $company->logo 
            ? asset('storage/' . $company->logo) 
            : $defaultLogo;
    }

    /**
     * Get company based on user role
     */
    private function getCompanyFromUserRole($user)
    {
        return match ($user->role->id) {
            Role::COMPANY => $user->company,
            Role::BRANCH => $user->branch?->company,
            Role::AGENT => $user->agent?->branch?->company,
            default => null,
        };
    }

    public function render(): View
    {
        return view('components.application-logo', [
            'companyLogo' => $this->companyLogo
        ]);
    }
}