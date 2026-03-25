# PRODUCTION vs DEVELOPMENT: QUICK SUMMARY

## THE SITUATION
- **Production** (`tour.citycommerce.group`): Latest commit `badb26d0` on 2026-03-25
- **Development** (`development.citycommerce.group`): Latest commit `c0d8b7a2` on 2026-03-25
- **Key insight**: Development DOES have some recent DotwAI phase commits, BUT is missing 30+ days of UI/localization work from production

## WHAT DEVELOPMENT IS MISSING (FROM PRODUCTION)

### CRITICAL - LOCALIZATION SYSTEM (1,200+ lines)
```
✗ SetLocale middleware (app/Http/Middleware/SetLocale.php)
✗ 16 Laravel translation files (resources/lang/ar|en/*.php)
✗ locale.switch route (routes/web.php)
✗ 30+ blade files converted to use __() localization helpers
```
**Impact**: Sidebar, settings, profile, reports, notifications all hardcoded in English in Dev.
**Status**: Production has complete i18n system; Dev has none.
**Risk**: CRITICAL - Cannot bring in other UI changes without localization foundation.

---

### HIGH PRIORITY - SIDEBAR REDESIGN
```
✓ Language switcher modal (58 new lines)
✓ DOTW Hotel API button (16 new lines)
✓ All tooltips use localization
✓ JavaScript translations object
```
**File**: `resources/views/layouts/sidebar.blade.php`
**Size**: +94 lines in production vs development
**Status**: Production has full redesign; Dev has old version.

---

### HIGH - BULK INVOICE FEATURE
```
✗ 4 new blade views (upload, preview, success, pdf)
✗ Email template
✗ Sidebar link integration
```
**Files**:
- `resources/views/bulk-invoice/upload.blade.php` (198 lines)
- `resources/views/bulk-invoice/preview.blade.php` (376 lines)
- `resources/views/bulk-invoice/success.blade.php` (280 lines)
- `resources/views/bulk-invoice/pdf/bulk-invoices.blade.php` (187 lines)
- `resources/views/email/bulk-invoices.blade.php`

**Status**: Complete feature in production; absent from dev.
**Note**: Requires backend code (models, controllers, services, jobs) that may also be in production.

---

### HIGH - SUPPLIER PAGE REDESIGN
```
✓ 2 new partial components (service-toggles, surcharge-row)
✓ Index page refactored from 1504 to modular structure
✓ Tab layout conversion
✓ AJAX company toggle
```
**Files**:
- `resources/views/suppliers/partials/service-toggles.blade.php` (83 lines) - NEW
- `resources/views/suppliers/partials/surcharge-row.blade.php` (57 lines) - NEW
- `resources/views/suppliers/index.blade.php` (refactored, 1504 → modular)
- `resources/views/suppliers/show.blade.php` (614 lines, refactored)

**Status**: Production has modern design; Dev has older version.

---

### MEDIUM - AGENT DETAILS PAGE
```
✓ Complete redesign (668 insertions, 464 deletions)
✓ New CSS file (resources/css/agent/index.css - 54 lines)
✓ Enhanced app.css (+42 lines)
```
**File**: `resources/views/agents/agentsShow.blade.php` (1018 lines)
**Status**: Completely overhauled in production; older version in dev.

---

### MEDIUM - CSS DIRECTORY STRUCTURE
**New organized structure in production**:
```
resources/css/agent/                    ← NEW
resources/css/component/                ← NEW
resources/css/lock-management/          ← NEW
resources/css/payment-link/             ← NEW
resources/css/settings/                 ← EXPANDED (main.css, notification.css, agent-loss.css)
```

**File sizes**:
- `app.css`: 287,240 → 288,006 bytes (+766 bytes)
- `refund.css`: 11,664 → 12,035 bytes (+371 bytes)

**Status**: Production has organized structure; Dev has monolithic/basic structure.

---

### MEDIUM - DOCUMENTATION EXPANSION
**6 new documentation pages in production**:
```
✗ resources/views/docs/n8n-changelog.blade.php
✗ resources/views/docs/n8n-complete-documentation.blade.php
✗ resources/views/docs/n8n-hub.blade.php
✗ resources/views/docs/n8n-processing.blade.php
✗ resources/views/docs/n8n-testing-documentation.blade.php
✗ resources/views/docs/dotw-page.blade.php
✗ 40+ GIF assets in public/docs/gifs/
```

**Status**: Production has comprehensive docs; Dev missing these.

---

### LOW - MINOR FEATURE ADDITIONS
**Production has, Dev missing**:
- Travel date filter for tasks report (`resources/views/reports/tasks.blade.php`)
- Task report enhancements
- Invoice creation improvements

---

## WHAT DEVELOPMENT HAS THAT PRODUCTION DOESN'T

**Development appears to have more recent DotwAI phase work**:
- Admin DOTW views (`resources/views/admin/dotw/`)
- Manual intervention views (`resources/views/admin/manual-intervention/`)
- Error dashboard views (`resources/views/admin/error-dashboard/`)
- ResailAI admin views

**BUT**: These are NOT UI/design changes (they're operational/backend features).

---

## FILES COMPARISON

| Category | Production | Development |
|----------|-----------|-------------|
| Localization files | 16 files (1,200+ lines) | 0 files |
| Bulk invoice views | 4 views | 0 views |
| Supplier partial components | 2 new partials | 0 partials |
| CSS directories | 8 structured dirs | 5 basic dirs |
| Agent details page | 1018 lines (redesigned) | Older version |
| Sidebar | 449 lines (with modal) | 449 lines (no modal) |
| Documentation pages | 6 new + 1 enhanced | 3 basic docs |
| **Total new/modified** | **70+ blade files** | **Baseline** |

---

## WHAT YOU NEED TO DO

### Option 1: PULL ALL CHANGES
Best approach for complete parity:
1. Cherry-pick localization commits (cc47d21d, ee8f879c)
2. Cherry-pick bulk invoice commits (fa808eed, 21817de9)
3. Cherry-pick supplier redesign (eae47ed9, d47defea)
4. Cherry-pick agent page (ade43ae4)
5. Cherry-pick CSS/docs (15+ commits)
6. Run `npm run build` to regenerate assets

**Timeline**: 2-4 hours of review and testing
**Risk**: CRITICAL (localization), MEDIUM (features), LOW (docs)

### Option 2: SELECTIVE MERGE
If you want only specific features:
1. MUST DO: Localization first (foundation for all UI)
2. THEN: Bulk invoice system (if needed)
3. THEN: Supplier redesign (if needed)
4. THEN: Agent page (if needed)
5. OPTIONAL: Documentation pages

**Timeline**: 1-2 hours per feature
**Risk**: CRITICAL for localization, MEDIUM for others

### Option 3: SKIP PRODUCTION CHANGES
Stay with development's current state:
- Keep DotwAI newer features
- Accept older UI/localization
- Make localization changes locally if needed

**Timeline**: No change
**Risk**: Users see different UI on production vs development

---

## MOST IMPORTANT COMMIT TO PULL

**`cc47d21d`** - "feat: add full Arabic/English localization with SetLocale middleware"

This is the foundation. All other UI changes depend on this:
- 30+ blade files updated to use localization
- SetLocale middleware added
- 16 translation files created
- Routes added
- Bootstrap registration updated

**Nothing else can be cleanly merged without this first.**

---

## TESTING AFTER MERGE

Must verify:
- [ ] Language switcher modal appears and functions
- [ ] Arabic text displays correctly (RTL)
- [ ] English text displays correctly (LTR)
- [ ] Locale persists across navigation
- [ ] All localization strings load (no missing key fallbacks)
- [ ] Sidebar DOTW button visible (admin/company only)
- [ ] Bulk invoice upload works with Excel
- [ ] Supplier page tabs work
- [ ] Agent details page responsive
- [ ] No CSS conflicts
- [ ] Mobile navigation intact
- [ ] Dark mode still works
- [ ] No console errors

---

## FILES TO REVIEW (CRITICAL PATH)

### Must Review (in order)
1. `app/Http/Middleware/SetLocale.php` (NEW)
2. `bootstrap/app.php` (localization registration)
3. `routes/web.php` (locale.switch route)
4. `resources/lang/ar/*` (8 files, Arabic translations)
5. `resources/lang/en/*` (8 files, English translations)
6. `resources/views/layouts/sidebar.blade.php` (+94 lines, major change)

### Should Review
7. `resources/views/bulk-invoice/*` (4 files, if using feature)
8. `resources/views/suppliers/index.blade.php` (refactored)
9. `resources/views/agents/agentsShow.blade.php` (1018 lines)
10. `public/build/manifest.json` (asset hashes)

---

## COMMITS TO CHERRY-PICK (IN SEQUENCE)

1. **cc47d21d** - Localization foundation (CRITICAL)
2. **ee8f879c** - Documentation localization support
3. **dd26a342** - Merge PR (if using git merge)
4. **fa808eed** - Bulk invoice system (if needed)
5. **21817de9** - Bulk invoice refinement (if #4 done)
6. **eae47ed9** - Supplier redesign
7. **d47defea** - Supplier tabs
8. **ade43ae4** - Agent details page
9. **a80856ec** - Settings CSS files
10. **15d99b04** - Payment link CSS

Then run: `npm run build`

---

## DECISION MATRIX

| If you need... | Priority | Risk | Effort |
|---|---|---|---|
| Localization (i18n) | CRITICAL | HIGH | 2 hrs |
| Bulk invoice feature | HIGH | MEDIUM | 4 hrs |
| Supplier UI polish | MEDIUM | MEDIUM | 2 hrs |
| Agent UI polish | MEDIUM | MEDIUM | 2 hrs |
| Documentation pages | LOW | LOW | 1 hr |
| CSS organization | LOW | LOW | 30 min |

---

## FINAL RECOMMENDATION

**Pull localization (`cc47d21d` + `ee8f879c`) as foundation.**

Then decide:
- If you're actively developing invoicing → Pull bulk invoice
- If supplier page is in use → Pull supplier redesign
- If agent pages are in use → Pull agent redesign
- Documentation → Always nice to have

**Timeline**: Start with localization (2 hrs), add features as needed.

---

## PRODUCTION REPO
Location: `/home/citycomm/repositories/city-tour-production/`
Access: `ssh ct-server "cd /home/citycomm/repositories/city-tour-production && git log ..."`

## DEVELOPMENT REPO
Location: `/home/citycomm/development.citycommerce.group/`
Access: `ssh ct-server "cd /home/citycomm/development.citycommerce.group && git log ..."`

