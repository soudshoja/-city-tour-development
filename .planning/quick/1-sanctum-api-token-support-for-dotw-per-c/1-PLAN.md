---
phase: quick-1
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Models/User.php
  - app/Http/Livewire/Admin/DotwApiTokenIndex.php
  - resources/views/livewire/admin/dotw-api-token-index.blade.php
  - routes/web.php
autonomous: true
requirements: [SANCTUM-01, SANCTUM-02, SANCTUM-03, SANCTUM-04]

must_haves:
  truths:
    - "Super Admin can visit /admin/dotw/api-tokens and see all companies with DOTW credentials"
    - "Super Admin can generate a token per company — token shown once in full plaintext, then masked in table"
    - "Generating a new token revokes all existing dotw-n8n tokens for that company's primary user first"
    - "Super Admin can revoke a token — row returns to no-token state"
    - "Non-Super-Admin (Role::COMPANY, others) receives 403 on /admin/dotw/api-tokens"
    - "Token is associated with company->user (via company.user_id FK)"
  artifacts:
    - path: "app/Models/User.php"
      provides: "HasApiTokens trait added for Sanctum token generation"
    - path: "app/Http/Livewire/Admin/DotwApiTokenIndex.php"
      provides: "Livewire component listing companies, generate/revoke actions"
    - path: "resources/views/livewire/admin/dotw-api-token-index.blade.php"
      provides: "Blade view with table, generate button, copy modal, revoke button"
    - path: "routes/web.php"
      provides: "Route /admin/dotw/api-tokens — Super Admin only"
  key_links:
    - from: "DotwApiTokenIndex::generateToken()"
      to: "company->user->createToken()"
      via: "Company::find()->user relationship (user_id FK)"
    - from: "DotwApiTokenIndex::revokeToken()"
      to: "company->user->tokens()->where(name, dotw-n8n)->delete()"
      via: "HasApiTokens tokens() relation"
    - from: "routes/web.php"
      to: "App\\Http\\Livewire\\Admin\\DotwApiTokenIndex"
      via: "Route GET /admin/dotw/api-tokens with auth + inline abort(403) for non-ADMIN"
---

<objective>
Add Sanctum API token management UI for DOTW per-company n8n integration.

Purpose: Each company with DOTW credentials needs a long-lived API token for n8n workflows to authenticate GraphQL requests on their behalf. Super Admin generates/revokes tokens per company through a dedicated admin page.

Output: Sanctum published + migrated, User model with HasApiTokens, Livewire admin UI at /admin/dotw/api-tokens.
</objective>

<execution_context>
@/home/soudshoja/.claude/get-shit-done/workflows/execute-plan.md
@/home/soudshoja/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@app/Http/Livewire/Admin/DotwAuditLogIndex.php
@resources/views/livewire/admin/dotw-audit-log-index.blade.php
@app/Http/Middleware/DotwAuditAccess.php
@app/Models/User.php
@app/Models/CompanyDotwCredential.php
@app/Models/Company.php
@routes/web.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Publish Sanctum, add HasApiTokens to User, run migrations</name>
  <files>app/Models/User.php</files>
  <action>
Run these commands in order:

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

Then edit `app/Models/User.php`:
- Add `use Laravel\Sanctum\HasApiTokens;` to the imports
- Add `HasApiTokens` to the `use` traits line alongside `HasFactory, Notifiable, HasRoles`:

```php
use HasApiTokens, HasFactory, Notifiable, HasRoles;
```

Do NOT modify any other part of User.php. The `company()` Attribute accessor, relationships, and $fillable/$hidden arrays must remain unchanged.

Verify sanctum config was published:
```bash
ls config/sanctum.php
```
  </action>
  <verify>
```bash
php artisan migrate:status | grep personal_access_tokens
```
Expected: `Ran` status for `2019_12_14_000001_create_personal_access_tokens_table` (or equivalent Sanctum migration).

Also confirm:
```bash
php artisan tinker --execute="echo (new ReflectionClass(App\Models\User::class))->getTraitNames();" 2>/dev/null | grep HasApiTokens || php artisan tinker --execute="var_dump(in_array('Laravel\Sanctum\HasApiTokens', class_uses_recursive(App\Models\User::class)));"
```
Expected: `true` or trait name listed.
  </verify>
  <done>
`personal_access_tokens` table exists in DB. `HasApiTokens` trait is in User model's use statement. `config/sanctum.php` exists.
  </done>
</task>

<task type="auto">
  <name>Task 2: Create DotwApiTokenIndex Livewire component</name>
  <files>app/Http/Livewire/Admin/DotwApiTokenIndex.php</files>
  <action>
Create `app/Http/Livewire/Admin/DotwApiTokenIndex.php`:

```php
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
```

Key design notes:
- `abort_unless($this->isSuperAdmin(), 403)` in `mount()` — gate at component load, consistent with task spec (Super Admin only, no Role::COMPANY access unlike audit-logs)
- `company.user.tokens` eager-loaded with name='dotw-n8n' filter — each row can show hasToken boolean without N+1
- `$newTokenPlaintext` stored on component — Livewire re-renders blade with token visible in modal; after `dismissToken()` it's null and never shown again
- No pagination needed — number of DOTW-enabled companies is small (admin-managed)
  </action>
  <verify>
```bash
php artisan livewire:discover 2>/dev/null || true
grep -r "DotwApiTokenIndex" app/Http/Livewire/
```
Expected: file found at `app/Http/Livewire/Admin/DotwApiTokenIndex.php`.

Also check for syntax errors:
```bash
php -l app/Http/Livewire/Admin/DotwApiTokenIndex.php
```
Expected: `No syntax errors detected`.
  </verify>
  <done>
File exists, `php -l` passes, class is in `App\Http\Livewire\Admin` namespace.
  </done>
</task>

<task type="auto">
  <name>Task 3: Create blade view + add route</name>
  <files>
    resources/views/livewire/admin/dotw-api-token-index.blade.php
    routes/web.php
  </files>
  <action>
**3a. Create the blade view** at `resources/views/livewire/admin/dotw-api-token-index.blade.php`:

```blade
<x-app-layout>
    <div class="container mx-auto px-4 py-8">

        {{-- Page Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">WhatsApp AI &mdash; DOTW API Tokens</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
                Manage per-company Sanctum tokens for n8n GraphQL integration. Tokens are generated per company's primary user account.
            </p>
        </div>

        {{-- One-time token reveal modal --}}
        @if($newTokenPlaintext)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60"
             x-data="{ copied: false }">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-lg w-full mx-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    Token Generated — Company #{{ $newTokenCompanyId }}
                </h2>
                <p class="text-sm text-amber-600 dark:text-amber-400 mb-4">
                    Copy this token now. It will not be shown again after you close this dialog.
                </p>
                <div class="flex items-center gap-2 mb-6">
                    <input id="token-plaintext"
                           type="text"
                           readonly
                           value="{{ $newTokenPlaintext }}"
                           class="flex-1 border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 text-sm font-mono bg-gray-50 dark:bg-gray-900 dark:text-white focus:outline-none" />
                    <button
                        @click="
                            navigator.clipboard.writeText('{{ $newTokenPlaintext }}');
                            copied = true;
                            setTimeout(() => copied = false, 2500);
                        "
                        class="px-3 py-2 text-sm rounded-md bg-blue-600 hover:bg-blue-700 text-white transition-colors whitespace-nowrap">
                        <span x-show="!copied">Copy</span>
                        <span x-show="copied" x-cloak>Copied!</span>
                    </button>
                </div>
                <div class="flex justify-end">
                    <button wire:click="dismissToken"
                            class="px-4 py-2 text-sm rounded-md bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">
                        I've copied it — Close
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- Token Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Primary User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Token (masked)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($credentials as $cred)
                    @php
                        $user = $cred->company?->user;
                        $existingToken = $user?->tokens->first(); {{-- eager-loaded, filtered to dotw-n8n --}}
                        $hasToken = !is_null($existingToken);
                    @endphp
                    <tr>
                        {{-- Company --}}
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $cred->company?->name ?? '—' }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">ID: {{ $cred->company_id }}</div>
                        </td>

                        {{-- DOTW Active Status --}}
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($cred->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                    Inactive
                                </span>
                            @endif
                        </td>

                        {{-- Primary User --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                            {{ $user?->email ?? '<span class="text-red-500">No user</span>' }}
                        </td>

                        {{-- Token (masked) --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500 dark:text-gray-400">
                            @if($hasToken)
                                {{ Str::mask($existingToken->token, '*', 4) }}
                            @else
                                <span class="text-gray-400 italic">No token</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-2">
                                <button wire:click="generateToken({{ $cred->company_id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="generateToken({{ $cred->company_id }})"
                                        class="px-3 py-1.5 rounded-md text-xs font-medium bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white transition-colors">
                                    <span wire:loading.remove wire:target="generateToken({{ $cred->company_id }})">
                                        {{ $hasToken ? 'Regenerate' : 'Generate' }}
                                    </span>
                                    <span wire:loading wire:target="generateToken({{ $cred->company_id }})">
                                        Generating...
                                    </span>
                                </button>

                                @if($hasToken)
                                <button wire:click="revokeToken({{ $cred->company_id }})"
                                        wire:confirm="Revoke dotw-n8n token for {{ $cred->company?->name }}? n8n workflows using this token will stop working immediately."
                                        wire:loading.attr="disabled"
                                        wire:target="revokeToken({{ $cred->company_id }})"
                                        class="px-3 py-1.5 rounded-md text-xs font-medium bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white transition-colors">
                                    Revoke
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500 text-sm italic">
                            No companies have DOTW credentials configured yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-app-layout>
```

**3b. Add route** to `routes/web.php`. Find the existing DOTW route group (lines 929-936):

```php
// DOTW Audit Log Viewer (Phase 2 Plan 3)
Route::middleware(['auth', 'dotw_audit_access'])
    ->prefix('admin/dotw')
    ->name('admin.dotw.')
    ->group(function () {
        Route::get('audit-logs', \App\Http\Livewire\Admin\DotwAuditLogIndex::class)
            ->name('audit-logs');
    });
```

Add the new route INSIDE the same group AND add a second protected group for Super Admin only. The existing group uses `dotw_audit_access` (allows Role::ADMIN + Role::COMPANY). The new API tokens page is Super Admin (Role::ADMIN) only. Add it as a SEPARATE group immediately after the existing one, right before `require __DIR__.'/auth.php';`:

```php
// DOTW API Token Manager — Super Admin only
Route::middleware(['auth'])
    ->prefix('admin/dotw')
    ->name('admin.dotw.')
    ->group(function () {
        Route::get('api-tokens', \App\Http\Livewire\Admin\DotwApiTokenIndex::class)
            ->middleware(function ($request, $next) {
                if (auth()->user()->role_id !== \App\Models\Role::ADMIN) {
                    abort(403, 'Super Admin only.');
                }
                return $next($request);
            })
            ->name('api-tokens');
    });
```

Note: The 403 guard is also enforced in `DotwApiTokenIndex::mount()` — this is double-protection (route layer + component layer). The `dotw_audit_access` middleware is intentionally NOT used here because that middleware allows Role::COMPANY which must be excluded from this page.
  </action>
  <verify>
Check blade syntax (no parse errors):
```bash
php artisan view:cache 2>&1 | grep -i error | head -5 || echo "no view errors"
php artisan optimize:clear
```

Check route is registered:
```bash
php artisan route:list | grep api-tokens
```
Expected: `admin/dotw/api-tokens` listed with name `admin.dotw.api-tokens`.

Check PHP lint on Livewire component:
```bash
php -l app/Http/Livewire/Admin/DotwApiTokenIndex.php
```
  </verify>
  <done>
Route `admin.dotw.api-tokens` exists. Blade file is at `resources/views/livewire/admin/dotw-api-token-index.blade.php`. `php artisan route:list | grep api-tokens` shows the route. A non-ADMIN user hitting the route gets 403.
  </done>
</task>

</tasks>

<verification>
End-to-end verification checklist:

1. `personal_access_tokens` table exists:
   ```bash
   php artisan migrate:status | grep personal_access_tokens
   ```

2. Route registered:
   ```bash
   php artisan route:list | grep api-tokens
   ```

3. No PHP syntax errors:
   ```bash
   php -l app/Models/User.php
   php -l app/Http/Livewire/Admin/DotwApiTokenIndex.php
   ```

4. PHPStan passes:
   ```bash
   ./vendor/bin/phpstan analyse app/Models/User.php app/Http/Livewire/Admin/DotwApiTokenIndex.php --level=5
   ```

5. Pint formatting:
   ```bash
   ./vendor/bin/pint app/Models/User.php app/Http/Livewire/Admin/DotwApiTokenIndex.php
   ```
</verification>

<success_criteria>
- `php artisan migrate:status` shows `personal_access_tokens` as Ran
- `app/Models/User.php` contains `HasApiTokens` in its use traits
- `php artisan route:list | grep api-tokens` shows `admin/dotw/api-tokens`
- Blade view exists at `resources/views/livewire/admin/dotw-api-token-index.blade.php`
- Livewire component exists at `app/Http/Livewire/Admin/DotwApiTokenIndex.php`
- `php -l` passes on both PHP files
</success_criteria>

<output>
After completion, create `.planning/quick/1-sanctum-api-token-support-for-dotw-per-c/1-SUMMARY.md` using the standard summary template.
</output>
