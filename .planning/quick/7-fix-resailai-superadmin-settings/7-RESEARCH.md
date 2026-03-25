# ResailAI Route 404 Fix - Research & Debug Log

**Date:** 2026-03-12
**Status:** IN PROGRESS - Awaiting server access

---

## Problem Statement

The route `GET /admin/resailai/suppliers` returns 404 Not Found after all code changes have been deployed to production.

---

## Deployed Changes

### 1. Routes/web.php (Line 942)
```php
require __DIR__.'/resailai-admin.php';
```
- Added to load ResailAI routes in web.php

### 2. Routes/resailai-admin.php (FIXED)
**Before:**
```php
Route::get('/admin/resailai/suppliers', [ResailAISupplierController::class, 'index'])
    ->name('admin.resailai.suppliers.index');
```

**After:**
```php
Route::prefix('admin/resailai')->middleware(['auth'])->group(function () {
    Route::get('/suppliers', [ResailAISupplierController::class, 'index'])
        ->name('admin.resailai.suppliers.index');
    Route::post('/suppliers/{supplierId}/toggle', [ResailAISuppliersController::class, 'toggle'])
        ->name('admin.resailai.suppliers.toggle');
});
```
- Wrapped in middleware group with `['auth']` instead of `['auth:api']`
- Moved `/suppliers` route inside the prefix group

### 3. App/Http/Controllers/Admin/ResailAISupplierController.php
- Created new controller for web UI
- `index()` returns view with suppliers list
- `toggle()` updates `auto_process_pdf` flag

### 4. Resources/views/resailai/suppliers.blade.php
- Fixed Alpine.js `x-data` attribute to match script definition
- Changed from `x-data="{ toggleLoading: {}, toggleModal: false }"` to `x-data="ResailAISuppliers"`

---

## Debug Steps Taken

1. **Route 404 on initial deployment** - Expected, route cache not cleared
2. **SSH attempts failed** - Password authentication not supported via pexpect/bash
3. **CPanel Terminal accessed** - Successfully logged in via Chrome browser
4. **Cache cleared** - Ran `php artisan optimize:clear`
5. **Route still 404s** - Route is registered but not being found

---

## Current Server Status

**Production Server:**
- Host: 152.53.86.223
- Username: citycomm
- Password: Alphia@2025
- Project Path: /home/citycomm/soud-laravel (actually development.citycommerce.group directory)

**Domain:** https://development.citycommerce.group

---

## Next Debug Steps

1. Check if route is registered:
   ```bash
   cd development.citycommerce.group
   php artisan route:list --path=admin/resailai
   ```

2. If route shows in list but 404s:
   - Check for opcache issues
   - Check for route caching in production
   - Verify .htaccess is allowing the route

3. If route doesn't show in list:
   - Check if resailai-admin.php is being loaded
   - Check for PHP syntax errors
   - Verify file permissions

---

## Files Modified

| File | Change |
|------|--------|
| routes/web.php | Added `require __DIR__.'/resailai-admin.php';` at line 942 |
| routes/resailai-admin.php | Wrapped routes in auth middleware group, moved /suppliers inside prefix |
| app/Http/Controllers/Admin/ResailAISupplierController.php | Created (new file) |
| resources/views/resailai/suppliers.blade.php | Fixed x-data attribute |

---

## Notes

- The server uses cPanel hosting
- SSH password authentication works interactively but not non-interactively
- Need to run commands manually in cPanel terminal for debugging
