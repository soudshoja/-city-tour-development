# DESIGN/UI CHANGES: PRODUCTION vs DEVELOPMENT

## CRITICAL FINDING
**Development repo is OLDER than Production** (last commits: Dev Feb 22, Prod Mar 25).
Development needs UI changes pulled FROM production, not the reverse.

---

## WHAT'S ONLY IN PRODUCTION (NOT IN DEVELOPMENT)

### 1. LOCALIZATION INFRASTRUCTURE (HIGHEST PRIORITY)
**Status**: Complete i18n system added via commit `cc47d21d`

#### New Files - 16 translation files (1,200+ lines):
```
resources/lang/ar/
  ├── general.php (170 lines)
  ├── menu.php (67 lines)
  ├── navigation.php (33 lines)
  ├── profile.php (67 lines)
  ├── report.php (35 lines)
  ├── role.php (5 lines)
  ├── settings.php (192 lines)
  ├── suppliers.php (70 lines)
  └── doc.php (NEW - docs translations)

resources/lang/en/
  └── [Same structure as Arabic - 8 files, 1,200+ lines total]
```

#### New Middleware:
- `app/Http/Middleware/SetLocale.php` (30 lines)
  - Detects and sets locale per request
  - Registered in `bootstrap/app.php`

#### New Routes (in routes/web.php):
- `locale.switch` - POST endpoint to switch language
- 13 new lines added

#### Impact on Blade Files:
All of these were updated to use `__()` helpers instead of hardcoded English:
- `resources/views/layouts/sidebar.blade.php` - MAJOR CHANGES
- `resources/views/livewire/notification.blade.php`
- `resources/views/profile/edit.blade.php`
- `resources/views/profile/partials/update-profile-information-form.blade.php` (36 lines)
- `resources/views/profile/partials/commission-list.blade.php`
- `resources/views/profile/partials/bonus-list.blade.php`
- `resources/views/profile/partials/iata-settings-form.blade.php`
- `resources/views/profile/password/confirm-password-code.blade.php`
- `resources/views/profile/password/update-password-form.blade.php`
- `resources/views/settings/index.blade.php` (32 lines)
- `resources/views/settings/partial/agent_charges.blade.php` (130 lines)
- `resources/views/settings/partial/charges.blade.php` (206 lines)
- `resources/views/settings/partial/payment.blade.php` (10 lines)
- `resources/views/settings/partial/payment_methods.blade.php` (20 lines)
- `resources/views/settings/partial/terms_condition.blade.php` (128 lines)
- `resources/views/reports/paid-report.blade.php` (112 lines)
- `resources/views/agents/agentsShow.blade.php`

---

### 2. SIDEBAR REDESIGN (MAJOR UI CHANGE)
**File**: `resources/views/layouts/sidebar.blade.php`
**Changes**: +94 lines, major restructuring

#### Key Additions:
1. **Language Switcher Modal** (58 lines)
   - Toggle button with globe icon
   - Modal with EN/AR selection
   - Visual feedback with checkmarks and color highlighting
   - Border color changes: blue when selected
   - Smooth transitions and dark mode support

2. **DOTW Hotel API Button** (16 lines)
   - New sidebar icon for DOTW integration
   - Visible only to ADMIN and COMPANY roles
   - Uses DOTW logo image from Webbeds
   - Positioned in main sidebar navigation

3. **JavaScript Sidebar Translations Object** (NEW)
   ```javascript
   const __sidebarTranslations = {
       lastUpdated: "{{ __('menu.last_updated') }}",
       failedToConvert: "{{ __('menu.failed_to_convert') }}",
       rateCreatedRefreshing: "{{ __('menu.rate_created_refreshing') }}"
   };
   ```
   - Dynamic translation for currency exchange feature

#### Tooltip Updates:
All hardcoded English converted to localization:
- `data-tooltip="Dashboard"` → `data-tooltip="{{ __('menu.dashboard') }}"`
- `data-tooltip="Add new user"` → `data-tooltip="{{ __('menu.add_new_user') }}"`
- `data-tooltip="Create Invoice"` → `data-tooltip="{{ __('menu.create_invoice') }}"`
- etc. (20+ tooltips updated)

---

### 3. NEW CSS DIRECTORY STRUCTURE
**Organized into focused modules** instead of monolithic files:

```
resources/css/
├── agent/
│   └── index.css (54 lines - NEW)
├── component/
│   └── ajax-searchable.css (NEW)
├── lock-management/
│   └── index.css (NEW)
├── payment-link/
│   └── index.css (NEW)
├── settings/
│   ├── agent-loss.css (NEW)
│   ├── main.css (NEW)
│   ├── notification.css (NEW)
│   └── [existing files]
├── app.css (+42 lines for agent styling)
├── guest.css
├── refund.css (+371 bytes)
└── [other existing files]
```

---

### 4. BULK INVOICE FEATURE (NEW)
**Feature**: Complete Excel-to-Invoice workflow
**Status**: Added via commit `fa808eed` + `21817de9`

#### New Blade Views (4 files):
- `resources/views/bulk-invoice/upload.blade.php` (198 lines)
  - Excel file upload form
  - Template download button
  - Instructions and validation info

- `resources/views/bulk-invoice/preview.blade.php` (376 lines)
  - Row-by-row preview before confirmation
  - Shows parsed data with task/payment relationships
  - Validation error highlighting

- `resources/views/bulk-invoice/success.blade.php` (280 lines)
  - Confirmation after bulk creation
  - Summary statistics
  - Links to created invoices

- `resources/views/bulk-invoice/pdf/bulk-invoices.blade.php` (187 lines)
  - PDF generation template
  - Multi-invoice layout

#### Email Template:
- `resources/views/email/bulk-invoices.blade.php` (NEW)

#### Sidebar Update:
- Added bulk invoice link/button to `resources/views/layouts/sidebar.blade.php`

---

### 5. SUPPLIER PAGE REDESIGN
**Status**: Refactored from 1504 lines to modular structure (commit `eae47ed9`)

#### New Partial Components:
- `resources/views/suppliers/partials/service-toggles.blade.php` (83 lines)
  - Reusable service toggle component
  - AJAX-driven without page reload

- `resources/views/suppliers/partials/surcharge-row.blade.php` (57 lines)
  - Reusable surcharge row component

#### Updated Main Files:
- `resources/views/suppliers/index.blade.php` (1504 → modular)
  - Cleaner, more maintainable structure
  - Removed duplication with partials

- `resources/views/suppliers/show.blade.php` (614 lines refactored)
  - Tab layout conversion (commit `d47defea`)
  - Better organization of supplier details

#### Authorization Changes:
- `app/Policies/SupplierPolicy.php` (31 lines modified)
- `app/Http/Controllers/SupplierController.php` (115 lines modified)

---

### 6. AGENT DETAILS PAGE REDESIGN
**Status**: Major visual overhaul (commit `ade43ae4`)
**Changes**: 668 insertions, 464 deletions

#### File:
- `resources/views/agents/agentsShow.blade.php` (1018 lines - completely redesigned)

#### Styling:
- New `resources/css/agent/index.css` (54 lines)
- Additional styling in `app.css` (+42 lines)

#### Visual Improvements:
- More polished layout
- Better typography hierarchy
- Improved responsive design
- Enhanced data presentation

---

### 7. NEW DOCUMENTATION PAGES
**Status**: 6 new documentation blade templates

#### N8n Documentation:
- `resources/views/docs/n8n-changelog.blade.php` (NEW)
- `resources/views/docs/n8n-complete-documentation.blade.php` (NEW)
- `resources/views/docs/n8n-hub.blade.php` (NEW)
- `resources/views/docs/n8n-processing.blade.php` (NEW)
- `resources/views/docs/n8n-testing-documentation.blade.php` (NEW)

#### DOTW Documentation:
- `resources/views/docs/dotw-page.blade.php` (NEW)

#### User Documentation Enhancements:
- `resources/views/docs/user-documentation.blade.php` (updated)
  - Added gif/media assets in `public/docs/gifs/` (40+ GIFs)
  - Receivable/Payable details sections
  - Lock management section

---

### 8. TASK REPORT ENHANCEMENTS
**File**: `resources/views/reports/tasks.blade.php`
**Change**: Added travel date filter (commit `07b4755e`)

---

## BUILD ASSET CHANGES

### manifest.json Updates:
Multiple asset hash regenerations from `npm run build`:
```
Before:
  "app-D89UHMud.css"
  "guest-BEKRtggv.css"
  "index-D9KYeHBy.css"

After:
  "app-CyNljHXT.css"
  "guest-CZfwVA0a.css"
  "index-6kkWx44u.css"
  "notification-DZLU-9PS.css" (NEW)
```

### File Size Changes:
- `app.css`: 287,240 bytes → 288,006 bytes (+766 bytes)
- `refund.css`: 11,664 bytes → 12,035 bytes (+371 bytes)
- `manifest.json`: Multiple entries updated

---

## RISK ASSESSMENT

### CRITICAL RISK - LOCALIZATION SYSTEM
**Complexity**: HIGH
**Testing Required**: EXTENSIVE

What could break:
1. `SetLocale` middleware might interfere with auth middleware order
2. Session locale persistence not working (user switches language, forgets after refresh)
3. Routes registered incorrectly (locale.switch endpoint)
4. Translation files not found (fallback to key showing)
5. RTL support for Arabic not implemented in CSS

Affected areas:
- All views using `__()` helpers
- All routes requiring language context
- Database queries filtered by locale (if any)

Mitigation:
- Test locale switching on every major page
- Verify Arabic text displays correctly
- Check mobile RTL compatibility
- Test logout/login locale persistence

### HIGH RISK - SIDEBAR REDESIGN
**Complexity**: MEDIUM
**Testing Required**: MODERATE

Issues:
1. Language switcher modal Alpine.js state conflicts
2. DOTW button permission check might fail if Role constants differ
3. New modal styling might clash with dark mode toggle
4. Currency exchange feature depends on JavaScript in sidebar

### MEDIUM RISK - BULK INVOICE FEATURE
**Complexity**: HIGH
**Testing Required**: EXTENSIVE

Depends on:
- Backend models: `BulkUpload`, `BulkUploadRow`
- Services: `BulkUploadValidationService`
- Jobs: `CreateBulkInvoicesJob`, `SendInvoiceEmailsJob`
- Mail: `BulkInvoicesMail`
- Migrations creating new tables

Must verify:
- Excel parsing works correctly
- Validation error reporting
- Email delivery with PDF
- Queue job processing
- Database transaction integrity

### MEDIUM RISK - SUPPLIER PAGE REDESIGN
**Complexity**: MEDIUM
**Testing Required**: MODERATE

Issues:
1. Authorization policy changes (`SupplierPolicy`)
2. AJAX toggle functionality for company services
3. Tab layout might conflict with existing CSS
4. Partial inclusion might have scoping issues

### MEDIUM RISK - AGENT DETAILS PAGE
**Complexity**: MEDIUM
**Testing Required**: MODERATE

Issues:
1. CSS changes might conflict with existing agent styles
2. Large rewrite (668 lines) likely to have edge cases
3. Responsive design needs mobile testing
4. New fields might need data population

### LOW RISK - DOCUMENTATION
**Complexity**: LOW
**Testing Required**: MINIMAL

Just content additions; no logic risk.

### LOW RISK - CSS REORGANIZATION
**Complexity**: LOW
**Testing Required**: MINIMAL

New directories don't break existing code; just organization.

---

## CHERRY-PICK SEQUENCE (IF MERGING SELECTIVELY)

### Phase 1 - FOUNDATION (DO FIRST)
1. Add `SetLocale` middleware and bootstrap registration
2. Add localization files (resources/lang/ar|en/*)
3. Add locale.switch route
4. Test basic locale switching before proceeding

### Phase 2 - IMMEDIATE DEPENDENCIES
5. Update sidebar with language switcher and localization
6. Update all views that use localization helpers

### Phase 3 - MAJOR FEATURES
7. Add bulk invoice system (requires backend code)
8. Update supplier page redesign
9. Update agent details page

### Phase 4 - NICE-TO-HAVES
10. Add new documentation pages
11. Add CSS reorganization
12. Build assets via npm run build

---

## COMMITS TO CHERRY-PICK (IN ORDER)

```
Phase 1-2:
- cc47d21d feat: add full Arabic/English localization with SetLocale middleware
- ee8f879c feat: add Laravel localization support to user documentation page

Phase 3:
- fa808eed feat: add bulk invoice upload system with Excel validation and PDF generation
- 21817de9 refactor: rename BulkUpload to BulkInvoice, remove unused files, fix bugs
- eae47ed9 refactor: redesign supplier index and show pages with modern UI and improved authorization
- d47defea refactor: convert supplier page to tab layout
- ade43ae4 feat: agent details page

Phase 4:
- 8097a4e8 feat: add user documentation page with role-specific content
- ee87d746 feat: add receivable details, payable details, and lock management to user docs
- 07b4755e feat: add travel date filter to tasks report
```

---

## PRODUCTION COMMIT HISTORY (Since Jan 1, 2026)

**Total frontend commits**: 340
**Major categories**:
- Localization: 2 commits
- UI/View changes: 70+ commits
- CSS/Build changes: 40+ commits
- Feature additions: 20+ commits
- Bug fixes: 40+ commits
- Documentation: 15+ commits
- Refactoring: 30+ commits
- Merge commits: 80+ commits

---

## KEY FILES TO REVIEW BEFORE MERGE

### MUST REVIEW
1. `app/Http/Middleware/SetLocale.php` - New middleware
2. `bootstrap/app.php` - Middleware registration
3. `routes/web.php` - New locale routes
4. `resources/views/layouts/sidebar.blade.php` - Major UI changes
5. `resources/lang/ar/*` and `resources/lang/en/*` - 1,200+ new lines

### SHOULD REVIEW
6. `resources/views/bulk-invoice/*` - 4 new views
7. `resources/views/suppliers/index.blade.php` - Major refactor
8. `resources/views/agents/agentsShow.blade.php` - 1018 lines
9. `resources/css/agent/index.css` - New styles
10. `public/build/manifest.json` - Asset hashes

### OPTIONAL REVIEW
11. Documentation pages (low risk)
12. CSS directory structure (low risk)

---

## TESTING CHECKLIST

- [ ] Language switcher modal appears and works
- [ ] Arabic translation displays correctly (RTL text)
- [ ] English translation displays correctly (LTR text)
- [ ] Language preference persists across page navigation
- [ ] Logout/login preserves language choice
- [ ] All localized pages display without translation key fallbacks
- [ ] Sidebar DOTW button appears for admins/companies only
- [ ] Bulk invoice upload form works with Excel parsing
- [ ] Supplier page tabs function correctly
- [ ] Agent details page displays responsive layout
- [ ] CSS doesn't have conflicts with existing styles
- [ ] Mobile navigation works correctly
- [ ] Dark mode toggle still works
- [ ] Currency exchange feature works with sidebar
- [ ] No console errors related to missing translations
- [ ] Build assets generated correctly (npm run build)

---

## SUMMARY

**What changed**: Massive UI overhaul including full localization system, sidebar redesign, bulk invoice feature, supplier/agent page redesigns, and documentation expansion.

**When**: Since early February through late March 2026 (340+ commits).

**What's needed**: Careful, phased integration starting with localization foundation, followed by dependent views, then major features.

**Priority**: HIGH - Production has 30+ days of UI/UX improvements not in development.

