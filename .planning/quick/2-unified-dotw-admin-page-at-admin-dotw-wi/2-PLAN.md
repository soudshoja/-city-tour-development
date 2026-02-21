---
phase: quick-2
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - routes/web.php
  - app/Http/Livewire/Admin/DotwAdminIndex.php
  - resources/views/livewire/admin/dotw-admin-index.blade.php
  - resources/views/admin/dotw/index.blade.php
  - resources/views/layouts/sidebar.blade.php
autonomous: true
requirements: [QUICK-2]

must_haves:
  truths:
    - "/admin/dotw loads for Role::ADMIN and Role::COMPANY (dotw_audit_access middleware)"
    - "Credentials tab shows form (dotw_username, dotw_password, dotw_company_code, markup_percent) and saves via Livewire"
    - "Audit Logs tab embeds DotwAuditLogIndex component"
    - "API Tokens tab embeds DotwApiTokenIndex component and is hidden from COMPANY role"
    - "Old /admin/dotw/audit-logs and /admin/dotw/api-tokens routes redirect to /admin/dotw"
    - "Sidebar WhatsApp AI link points to /admin/dotw"
  artifacts:
    - path: "app/Http/Livewire/Admin/DotwAdminIndex.php"
      provides: "Tabbed admin Livewire component with credentials form logic"
    - path: "resources/views/livewire/admin/dotw-admin-index.blade.php"
      provides: "Three-tab layout using Alpine.js sidebar tab pattern from settings/index"
    - path: "resources/views/admin/dotw/index.blade.php"
      provides: "Wrapper view: x-app-layout + @livewire"
  key_links:
    - from: "routes/web.php"
      to: "resources/views/admin/dotw/index.blade.php"
      via: "closure route GET /admin/dotw"
    - from: "resources/views/admin/dotw/index.blade.php"
      to: "App\\Http\\Livewire\\Admin\\DotwAdminIndex"
      via: "@livewire directive"
    - from: "DotwAdminIndex Livewire view"
      to: "DotwAuditLogIndex and DotwApiTokenIndex"
      via: "@livewire embeds inside x-show tab panels"
---

<objective>
Consolidate the three separate DOTW admin pages (credentials, audit logs, api tokens) into a single tabbed page at /admin/dotw, following the Alpine.js sidebar-tab pattern from settings/index.blade.php.

Purpose: Credentials have no UI yet (admin must use API). Audit logs and API tokens are separate pages. A unified tabbed page gives a coherent DOTW admin surface.

Output: DotwAdminIndex Livewire component, wrapper view at /admin/dotw, redirects for old routes, sidebar link update.
</objective>

<execution_context>
@/home/soudshoja/.claude/get-shit-done/workflows/execute-plan.md
@/home/soudshoja/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

Key patterns from codebase:
- Tab pattern: Alpine.js x-data with activeTab state, sidebar buttons @click="activeTab = 'tab'", content panels x-show="activeTab === 'tab'" x-cloak — see resources/views/settings/index.blade.php
- Wrapper view pattern: resources/views/admin/dotw/audit-logs.blade.php (x-app-layout + @livewire) — same for new index.blade.php
- Livewire namespace: App\Http\Livewire\Admin (matches DotwAuditLogIndex, DotwApiTokenIndex)
- Role check: Auth::user()->role_id === Role::ADMIN for super admin, Role::COMPANY for company admin
- Credentials model: CompanyDotwCredential — updateOrCreate(['company_id'], [...]) upsert pattern
- Encryption: Crypt::encrypt/Crypt::decrypt in model accessors, never log credential values
- Existing controller for reference: app/Http/Controllers/Admin/DotwCredentialController.php (store/show logic to replicate in Livewire)
- Auth::user()->company_id is accessible (User model has company accessor)
- dotw_audit_access middleware allows Role::ADMIN and Role::COMPANY
</context>

<tasks>

<task type="auto">
  <name>Task 1: Create DotwAdminIndex Livewire component (class + view)</name>
  <files>
    app/Http/Livewire/Admin/DotwAdminIndex.php
    resources/views/livewire/admin/dotw-admin-index.blade.php
  </files>
  <action>
Create `app/Http/Livewire/Admin/DotwAdminIndex.php`:

```php
<?php

namespace App\Http\Livewire\Admin;

use App\Models\CompanyDotwCredential;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DotwAdminIndex extends Component
{
    public string $activeTab = 'credentials';

    // Credentials form fields
    public string $dotw_username = '';
    public string $dotw_password = '';
    public string $dotw_company_code = '';
    public string $markup_percent = '20';

    protected array $rules = [
        'dotw_username'     => ['required', 'string', 'max:100'],
        'dotw_password'     => ['required', 'string', 'max:200'],
        'dotw_company_code' => ['required', 'string', 'max:50'],
        'markup_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
    ];

    public function isSuperAdmin(): bool
    {
        return Auth::user()->role_id === Role::ADMIN;
    }

    public function mount(string $tab = 'credentials'): void
    {
        $this->activeTab = $tab;
        $this->loadCredentials();
    }

    private function loadCredentials(): void
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId === null) {
            return;
        }

        $credential = CompanyDotwCredential::where('company_id', $companyId)->first();
        if ($credential) {
            // dotw_username and dotw_password are decrypted by model accessors
            // Do NOT pre-fill username/password — never expose credentials in form fields
            $this->dotw_company_code = $credential->dotw_company_code ?? '';
            $this->markup_percent    = (string) $credential->markup_percent;
        }
    }

    private function resolveCompanyId(): ?int
    {
        if ($this->isSuperAdmin()) {
            // Super admin: no default company — form is company-specific
            // Super admin should use the credential API or per-company pages
            // For simplicity: super admin sees an info message, not a form
            return null;
        }

        return Auth::user()->company?->id;
    }

    public function saveCredentials(): void
    {
        $this->validate();

        $companyId = $this->resolveCompanyId();

        if ($companyId === null) {
            $this->addError('dotw_username', 'Super Admin cannot save credentials from this page. Use the API endpoint.');
            return;
        }

        CompanyDotwCredential::updateOrCreate(
            ['company_id' => $companyId],
            [
                'dotw_username'     => $this->dotw_username,
                'dotw_password'     => $this->dotw_password,
                'dotw_company_code' => $this->dotw_company_code,
                'markup_percent'    => (float) ($this->markup_percent ?: 20),
                'is_active'         => true,
            ]
        );

        // Clear credential fields after save — never persist in component state
        $this->dotw_username = '';
        $this->dotw_password = '';

        session()->flash('credentials_saved', 'DOTW credentials saved successfully.');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.admin.dotw-admin-index', [
            'isSuperAdmin' => $this->isSuperAdmin(),
            'companyId'    => $this->resolveCompanyId(),
        ]);
    }
}
```

Create `resources/views/livewire/admin/dotw-admin-index.blade.php`:

Follow the exact sidebar-tab pattern from `resources/views/settings/index.blade.php`:
- Outer: `<div x-data="{ activeTab: '{{ $activeTab }}' }" class="flex min-h-[500px] bg-white dark:bg-gray-800 rounded-xl shadow-sm">`
- Left sidebar: `<div class="w-56 border-r border-gray-200 dark:border-gray-700 p-4 flex-shrink-0">` with nav buttons
- Each nav button: `@click="activeTab = 'credentials'"` and `:class="activeTab === 'credentials' ? 'bg-blue-50 ... text-blue-600' : 'text-gray-600 ... hover:bg-gray-50'"` with class `"w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all"`
- Content area: `<div class="flex-1 p-6">`

Three tabs:

**Tab 1 — Credentials** (always visible, Role::ADMIN sees info message, Role::COMPANY sees form):
- `<div x-show="activeTab === 'credentials'" x-cloak>`
- Page heading: "DOTW Credentials"
- If `$isSuperAdmin`: show info panel "To configure DOTW credentials for a company, use: POST /api/admin/companies/{id}/dotw-credentials"
- If not super admin: show credentials form with wire:submit.prevent="saveCredentials":
  - Field: DOTW Username (wire:model="dotw_username", type="text", placeholder="Leave blank to keep existing")
  - Field: DOTW Password (wire:model="dotw_password", type="password", placeholder="Leave blank to keep existing")
  - Field: DOTW Company Code (wire:model="dotw_company_code", type="text")
  - Field: Markup % (wire:model="markup_percent", type="number", step="0.01", min="0", max="100")
  - Submit button: "Save Credentials"
  - Show `@if(session('credentials_saved'))` success flash message
  - Show `@error` messages below each field

**Tab 2 — Audit Logs** (visible to both roles):
- `<div x-show="activeTab === 'audit-logs'" x-cloak>`
- `@livewire(\App\Http\Livewire\Admin\DotwAuditLogIndex::class)`

**Tab 3 — API Tokens** (hidden from COMPANY role — only ADMIN):
- Sidebar button: wrap with `@if($isSuperAdmin)` ... `@endif`
- `<div x-show="activeTab === 'api-tokens'" x-cloak>`
- Inside: `@if($isSuperAdmin) @livewire(\App\Http\Livewire\Admin\DotwApiTokenIndex::class) @endif`

Nav button tab names and icons:
- Credentials: key icon (Heroicons outline key svg)
- Audit Logs: document-text icon
- API Tokens: code-bracket icon (only shown to super admin via `@if($isSuperAdmin)`)

Use Tailwind classes matching the settings/index.blade.php style (bg-white, dark:bg-gray-800, border-gray-200, etc.).

IMPORTANT: The credentials form sends dotw_username and dotw_password to the server via Livewire wire:model. On saveCredentials(), the model mutator encrypts them before storing. After save, clear the fields in PHP (`$this->dotw_username = ''; $this->dotw_password = '';`). Never render these fields with existing values — always empty on load.
  </action>
  <verify>
    php artisan livewire:discover 2>&1 | grep -i error
    php artisan route:list | grep "admin/dotw"
    ./vendor/bin/phpstan analyse app/Http/Livewire/Admin/DotwAdminIndex.php --level=5
  </verify>
  <done>
    DotwAdminIndex.php exists with no PHPStan errors. Livewire discovers the component without errors. The view file exists at resources/views/livewire/admin/dotw-admin-index.blade.php.
  </done>
</task>

<task type="auto">
  <name>Task 2: Wire routes, wrapper view, and sidebar</name>
  <files>
    routes/web.php
    resources/views/admin/dotw/index.blade.php
    resources/views/layouts/sidebar.blade.php
  </files>
  <action>
**1. Create `resources/views/admin/dotw/index.blade.php`:**

```blade
<x-app-layout>
    @livewire(\App\Http\Livewire\Admin\DotwAdminIndex::class)
</x-app-layout>
```

This follows the identical pattern of audit-logs.blade.php and api-tokens.blade.php.

**2. Update `routes/web.php`:**

Replace the two existing DOTW route groups (lines ~929-945) with a single consolidated group:

```php
// DOTW Admin (unified tabbed page) — Phase 2 Plan 3 + Quick-2
Route::middleware(['auth', 'dotw_audit_access'])
    ->prefix('admin/dotw')
    ->name('admin.dotw.')
    ->group(function () {
        Route::get('/', fn () => view('admin.dotw.index'))->name('index');
        Route::redirect('audit-logs', '/admin/dotw', 301)->name('audit-logs');
        Route::redirect('api-tokens', '/admin/dotw', 301)->name('api-tokens');
    });
```

IMPORTANT: `Route::redirect` creates named routes — the existing sidebar reference to `route('admin.dotw.audit-logs')` will continue to work and redirect to /admin/dotw. The `api-tokens` redirect uses 301 (permanent). Both old named routes are preserved so no broken links in sidebar.

**3. Update `resources/views/layouts/sidebar.blade.php`:**

Change the existing anchor href from `route('admin.dotw.audit-logs')` to `route('admin.dotw.index')`:

```blade
{{-- Before --}}
<a href="{{ route('admin.dotw.audit-logs') }}">

{{-- After --}}
<a href="{{ route('admin.dotw.index') }}">
```

Only this one href needs changing — everything else in the sidebar block stays identical.
  </action>
  <verify>
    php artisan route:list --name="admin.dotw" 2>&1
    php artisan route:list | grep "admin/dotw"
    # Confirm three routes: GET /admin/dotw (index), GET /admin/dotw/audit-logs (redirect), GET /admin/dotw/api-tokens (redirect)
  </verify>
  <done>
    `php artisan route:list` shows admin.dotw.index at GET /admin/dotw, admin.dotw.audit-logs as redirect to /admin/dotw, admin.dotw.api-tokens as redirect to /admin/dotw. Sidebar file updated. Wrapper view exists.
  </done>
</task>

</tasks>

<verification>
1. `php artisan route:list --name=admin.dotw` — three routes listed
2. `./vendor/bin/phpstan analyse app/Http/Livewire/Admin/DotwAdminIndex.php` — no errors
3. Visit /admin/dotw as ADMIN role: see all three tab buttons in sidebar, credentials tab shows API info panel, audit-logs tab embeds DotwAuditLogIndex, api-tokens tab embeds DotwApiTokenIndex
4. Visit /admin/dotw as COMPANY role: see only two tab buttons (Credentials + Audit Logs), API Tokens button absent, credentials form is visible with dotw_company_code and markup_percent pre-filled
5. Visit /admin/dotw/audit-logs — redirected to /admin/dotw (301)
6. Visit /admin/dotw/api-tokens — redirected to /admin/dotw (301)
</verification>

<success_criteria>
- /admin/dotw loads the three-tab DOTW admin page
- Credentials tab: COMPANY role can enter and save credentials via Livewire form; ADMIN role sees API endpoint info
- Audit Logs tab: renders the existing DotwAuditLogIndex component with full filter/pagination
- API Tokens tab: renders the existing DotwApiTokenIndex component; tab button hidden for COMPANY role
- Old routes /admin/dotw/audit-logs and /admin/dotw/api-tokens redirect 301 to /admin/dotw
- Sidebar WhatsApp AI icon links to /admin/dotw (via admin.dotw.index route name)
- No PHPStan errors on DotwAdminIndex.php
</success_criteria>

<output>
After completion, create `.planning/quick/2-unified-dotw-admin-page-at-admin-dotw-wi/2-SUMMARY.md`
</output>
