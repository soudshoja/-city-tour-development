---
phase: quick-3
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/settings/index.blade.php
  - app/Http/Controllers/SettingController.php
  - resources/views/layouts/sidebar.blade.php
autonomous: true
requirements: [QUICK-3]

must_haves:
  truths:
    - "Settings page has a 'DOTW / Hotel API' tab in the left sidebar"
    - "Clicking the DOTW tab embeds DotwAdminIndex (credentials, audit logs, API tokens sub-tabs)"
    - "Sidebar icon that was WhatsApp AI now shows the Pratra DOTW logo image linking to /admin/dotw"
    - "/admin/dotw route continues to work"
  artifacts:
    - path: "resources/views/settings/index.blade.php"
      provides: "DOTW tab button in sidebar nav + content panel embedding @livewire(DotwAdminIndex)"
    - path: "app/Http/Controllers/SettingController.php"
      provides: "dotw added to saveTab validation allowlist and init activeTab default"
    - path: "resources/views/layouts/sidebar.blade.php"
      provides: "Pratra DOTW img tag replacing WhatsApp SVG icon"
  key_links:
    - from: "settings/index.blade.php tab button"
      to: "content panel x-show dotw"
      via: "Alpine activeTab === 'dotw'"
    - from: "content panel"
      to: "App\\Http\\Livewire\\Admin\\DotwAdminIndex"
      via: "@livewire('admin.dotw-admin-index')"
---

<objective>
Add a "DOTW / Hotel API" tab to the Project Settings page that embeds the existing DotwAdminIndex Livewire component. Replace the WhatsApp AI sidebar icon with the Pratra DOTW logo image linking to /admin/dotw. The /admin/dotw route stays active.

Purpose: Consolidate DOTW hotel API management inside Project Settings alongside other system-level config, replacing the awkward standalone admin page link with a branded image.
Output: Settings page gains a DOTW tab; sidebar icon becomes a branded Pratra image.
</objective>

<execution_context>
@/home/soudshoja/.claude/get-shit-done/workflows/execute-plan.md
@/home/soudshoja/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add DOTW tab to Project Settings view and controller</name>
  <files>resources/views/settings/index.blade.php, app/Http/Controllers/SettingController.php</files>
  <action>
**In `resources/views/settings/index.blade.php`:**

1. Add a new sidebar button after the "Agent Charges" button (around line 92, before closing `</nav>`):

```blade
<!-- DOTW / Hotel API Tab -->
@if(in_array(auth()->user()->role_id, [\App\Models\Role::ADMIN, \App\Models\Role::COMPANY]))
<button
    @click="saveTab('dotw')"
    :class="activeTab === 'dotw'
    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600'
    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
    class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
    <img src="https://www.pratra.com/assets/img/page/api-sub-dotw-webbeds.png"
         alt="DOTW"
         class="h-5 w-5 object-contain">
    DOTW / Hotel API
</button>
@endif
```

2. Add a content panel in the `<!-- Content Area -->` div, after the `agent-charges` panel (around line 117):

```blade
@if(in_array(auth()->user()->role_id, [\App\Models\Role::ADMIN, \App\Models\Role::COMPANY]))
<div x-show="activeTab === 'dotw'" x-cloak>
    @livewire('admin.dotw-admin-index')
</div>
@endif
```

**In `app/Http/Controllers/SettingController.php`:**

In `saveTab()` method, the validation rule is:
```php
'tab' => 'required|in:invoice,payment,terms,charges,payment-methods,agent-charges',
```
Add `dotw` to the allowlist:
```php
'tab' => 'required|in:invoice,payment,terms,charges,payment-methods,agent-charges,dotw',
```

No changes needed to `index()` — the session default remains `'payment'` which is fine. The `dotw` tab is selectable once the user clicks it.
  </action>
  <verify>
    1. Visit `/settings` in browser (as ADMIN or COMPANY role)
    2. Confirm "DOTW / Hotel API" tab appears in left sidebar with Pratra logo
    3. Click the tab — confirm DotwAdminIndex renders (credentials form / audit logs / API tokens sub-tabs visible)
    4. Click another tab, then click DOTW again — confirm `saveTab` POST succeeds (no 422 validation error in network tab)
  </verify>
  <done>
    Settings page shows DOTW tab. DotwAdminIndex embeds correctly with its own inner tabs. saveTab accepts 'dotw' without validation error.
  </done>
</task>

<task type="auto">
  <name>Task 2: Replace sidebar WhatsApp AI icon with Pratra DOTW image</name>
  <files>resources/views/layouts/sidebar.blade.php</files>
  <action>
Locate the sidebar block (around lines 89-103) that currently renders the WhatsApp icon linking to `admin.dotw.index`:

```blade
@if(in_array(auth()->user()->role_id, [\App\Models\Role::ADMIN, \App\Models\Role::COMPANY]))
<div class="flex flex-col items-center">
    <a href="{{ route('admin.dotw.index') }}">
        <div class="relative">
            <div data-tooltip="WhatsApp AI"
                class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md ...">
                {{-- WhatsApp icon --}}
                <svg class="w-5 h-5" ...>...</svg>
            </div>
        </div>
    </a>
</div>
@endif
```

Replace the inner content (keep the wrapper `@if`, `<div class="flex flex-col items-center">`, and `<a href>` intact — only change `data-tooltip` and swap the `<svg>` for an `<img>`):

```blade
@if(in_array(auth()->user()->role_id, [\App\Models\Role::ADMIN, \App\Models\Role::COMPANY]))
<div class="flex flex-col items-center">
    <a href="{{ route('admin.dotw.index') }}">
        <div class="relative">
            <div data-tooltip="DOTW Hotel API"
                class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                <img src="https://www.pratra.com/assets/img/page/api-sub-dotw-webbeds.png"
                     alt="DOTW Hotel API"
                     class="h-6 w-6 object-contain">
            </div>
        </div>
    </a>
</div>
@endif
```

The link target remains `route('admin.dotw.index')` — /admin/dotw route is unchanged.
  </action>
  <verify>
    1. Reload any page with the sidebar visible (e.g. `/dashboard`)
    2. Confirm the sidebar icon that was the WhatsApp SVG now shows the Pratra DOTW image
    3. Hover the icon — tooltip should read "DOTW Hotel API"
    4. Click the icon — confirm it navigates to `/admin/dotw`
  </verify>
  <done>
    Sidebar shows Pratra DOTW image (not WhatsApp SVG). Tooltip updated. Link still goes to /admin/dotw.
  </done>
</task>

</tasks>

<verification>
- [ ] Settings page (`/settings`) shows "DOTW / Hotel API" in left nav for ADMIN and COMPANY roles
- [ ] Clicking the tab renders DotwAdminIndex with Credentials / Audit Logs / API Tokens sub-tabs
- [ ] Saving the tab via Alpine `saveTab('dotw')` returns 200 (not 422)
- [ ] Sidebar DOTW icon shows Pratra image, tooltip "DOTW Hotel API", links to /admin/dotw
- [ ] `/admin/dotw` route continues to render the standalone DOTW admin page
- [ ] BRANCH and AGENT roles do NOT see the DOTW tab (guarded by role check)
</verification>

<success_criteria>
DOTW Hotel API management is accessible from Project Settings as a tab, and the sidebar icon is branded with the Pratra DOTW logo. The standalone /admin/dotw URL continues to work.
</success_criteria>

<output>
After completion, create `.planning/quick/3-move-dotw-unified-page-to-project-settin/3-SUMMARY.md`
</output>
