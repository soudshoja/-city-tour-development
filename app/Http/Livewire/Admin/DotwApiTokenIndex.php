<?php

namespace App\Http\Livewire\Admin;

use App\Models\CompanyDotwCredential;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DotwApiTokenIndex extends Component
{
    /** Full plaintext token shown once after generation, then cleared. */
    public ?string $newTokenPlaintext = null;

    /** company_id for which the new token was just generated (for modal heading). */
    public ?int $newTokenCompanyId = null;

    public function isSuperAdmin(): bool
    {
        return Auth::user()->role_id === Role::ADMIN;
    }

    public function mount(): void
    {
        abort_unless($this->isSuperAdmin(), 403, 'Super Admin only.');
    }

    /**
     * Generate a dotw-n8n token for the given company.
     *
     * Steps:
     * 1. Load company via CompanyDotwCredential (only companies with DOTW credentials appear)
     * 2. Load company->user via user_id FK (Company belongsTo User via user_id)
     * 3. Revoke all existing tokens named "dotw-n8n" for that user
     * 4. Create new token named "dotw-n8n" with no abilities (wildcard)
     * 5. Store plaintext in $newTokenPlaintext for one-time display in blade modal
     */
    public function generateToken(int $companyId): void
    {
        abort_unless($this->isSuperAdmin(), 403);

        $credential = CompanyDotwCredential::with('company.user')
            ->where('company_id', $companyId)
            ->firstOrFail();

        $user = $credential->company->user;

        abort_if(is_null($user), 422, 'Company has no primary user.');

        // Revoke all existing dotw-n8n tokens for this user
        $user->tokens()->where('name', 'dotw-n8n')->delete();

        // Generate new token — no specific abilities (n8n uses it as Bearer for GraphQL)
        $newToken = $user->createToken('dotw-n8n');

        $this->newTokenPlaintext = $newToken->plainTextToken;
        $this->newTokenCompanyId = $companyId;
    }

    /**
     * Revoke all dotw-n8n tokens for the given company's primary user.
     */
    public function revokeToken(int $companyId): void
    {
        abort_unless($this->isSuperAdmin(), 403);

        $credential = CompanyDotwCredential::with('company.user')
            ->where('company_id', $companyId)
            ->firstOrFail();

        $user = $credential->company->user;

        if ($user) {
            $user->tokens()->where('name', 'dotw-n8n')->delete();
        }

        $this->newTokenPlaintext = null;
        $this->newTokenCompanyId = null;
    }

    /**
     * Dismiss the one-time token modal (user has copied the token).
     */
    public function dismissToken(): void
    {
        $this->newTokenPlaintext = null;
        $this->newTokenCompanyId = null;
    }

    public function render(): \Illuminate\View\View
    {
        // Load all companies that have DOTW credentials configured
        // Eager-load company and company->user so we can check token existence
        $credentials = CompanyDotwCredential::with(['company', 'company.user.tokens' => function ($q) {
            $q->where('name', 'dotw-n8n');
        }])
            ->orderBy('company_id')
            ->get();

        return view('livewire.admin.dotw-api-token-index', [
            'credentials' => $credentials,
        ]);
    }
}
