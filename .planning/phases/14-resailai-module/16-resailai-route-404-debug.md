# Phase 16: ResailAI Route 404 Debug

---

## Phase Frontmatter

```yaml
phase: 14-resailai-module
plan: 16
type: debug
wave: 3
depends_on:
  - 15-resailai-pdf-integration
files_modified: []
autonomous: false
requirements:
  - Route /admin/resailai/suppliers returns 200
  - Page renders with suppliers list
  - Toggle switch functional for auto_process_pdf
```

---

## Objective

Fix the 404 error on `/admin/resailai/suppliers` route after all code changes have been deployed to production server.

---

## Context

### Previous Work (Phase 15)
- ResailAI module created with controllers, views, and routes
- Database migration added `auto_process_pdf` column
- TaskController modified to dispatch to ResailAI for PDF files
- CallbackController implemented to process extraction results
- Webhook URL configuration added to settings UI

### Deployed Changes
| File | Change |
|------|--------|
| routes/web.php | Added `require __DIR__.'/resailai-admin.php';` at line 942 |
| routes/resailai-admin.php | Wrapped routes in auth middleware group |
| app/Http/Controllers/Admin/ResailAISupplierController.php | Created (new file) |
| resources/views/resailai/suppliers.blade.php | Fixed x-data attribute |

### Current Status
- Production server: 152.53.86.223 (cPanel hosting)
- Domain: https://development.citycommerce.group
- Project path: /home/citycomm/soud-laravel (actual directory)
- Cache cleared: `php artisan optimize:clear` executed via cPanel terminal
- Route still returns 404

---

## Debug Steps Performed

1. ✅ SSH connection attempted (password auth failed via non-interactive)
2. ✅ CPanel terminal accessed via Chrome browser
3. ✅ Cache cleared: `php artisan optimize:clear`
4. ✅ Routes file checked and fixed (middleware group issue)
5. ❌ Route still returns 404 after cache clear

---

## Next Debug Steps

### Step 1: Check if route is registered
Run in cPanel terminal:
```bash
cd development.citycommerce.group
php artisan route:list --path=admin/resailai
```

### Step 2: If route shows but 404s
- Check opcache: `php -i | grep opcache`
- Check for route cache files: `ls -la bootstrap/cache/routes_v*.php`
- Check .htaccess is not blocking the route

### Step 3: If route doesn't show
- Verify resailai-admin.php file exists on server
- Check for PHP syntax errors: `php -l routes/resailai-admin.php`
- Verify file permissions

---

## Server Access

**Production Server:**
- Host: 152.53.86.223
- Username: citycomm
- Password: Alphia@2025
- Project Path: /home/citycomm/soud-laravel

**Domain:** https://development.citycommerce.group

**CPanel Terminal Access:** Already logged in via Chrome browser

---

## Notes

- User has cPanel access via Chrome browser
- SSH password authentication works interactively
- May need to run debug commands manually in cPanel terminal

---

*Debug session started: 2026-03-12*
*For Soud Laravel ResailAI Module - Route 404 Fix*
