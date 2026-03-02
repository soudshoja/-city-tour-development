<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

class ApplicationLogo extends Component
{
    public string $companyLogo;

    /**
     * Create a new component instance.
     */
    public function __construct(string $companyLogo = '')
    {
        if ($companyLogo) {
            if (file_exists(public_path('storage/' . $companyLogo))) {
                $this->companyLogo = asset('storage/' . $companyLogo);
            } else {
                $this->companyLogo = asset('images/userPic.svg');
            }
        } else {
            $this->companyLogo = $this->determineCompanyLogo();
        }
    }

    /**
     * Determine the appropriate company logo based on user role
     */
    private function determineCompanyLogo(): string
    {
        $defaultLogo = asset('images/userPic.svg');
        $user = Auth::user();

        if (!$user || !$user->role) {
            return $defaultLogo;
        }

        $company = $this->getCompanyFromUserRole($user);

        return $company && $company->logo
            && file_exists(public_path('storage/' . $company->logo))
            ? asset('storage/' . $company->logo)
            : $defaultLogo;
    }

    /**
     * Get company based on user role
     */
    private function getCompanyFromUserRole($user)
    {
        $companyId = getCompanyId($user);

        return $companyId ? \App\Models\Company::find($companyId) : null;
    }

    public function render(): View
    {
        return view('components.application-logo', [
            'companyLogo' => $this->companyLogo
        ]);
    }
}
