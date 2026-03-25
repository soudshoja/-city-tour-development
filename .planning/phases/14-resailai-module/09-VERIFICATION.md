# Phase 09: ResailAI Module — Verification Plan

**Phase Date:** 2026-03-11
**Status:** PRE-IMPLEMENTATION VERIFICATION

---

## 1. Wave 1 Verification (Foundation)

### 1.1 Migration Verification

| Test | Command | Expected | Notes |
|------|---------|----------|-------|
| Migration exists | `ls database/migrations/*_add_auto_process_pdf_to_supplier_companies.php` | File exists | Exact filename pattern |
| Migration syntax | `php artisan migrate --pretend` | No errors | Validates PHP syntax |
| Column added | `php artisan migrate` then `php artisan tinker --execute="print_r(Schema::getColumnListing('supplier_companies'));"` | `auto_process_pdf` in list | Column present |
| Column properties | Check migration and verify | Default: false, after: is_active | Correct placement |

### 1.2 Service Provider Verification

| Test | Command | Expected | Notes |
|------|---------|----------|-------|
| Provider loads | `php artisan config:clear && php artisan config:cache` | No errors | Provider registers |
| Config merged | `php artisan tinker --execute="print_r(array_keys(config('resailai')));"` | Contains all keys | api_token, n8n_webhook_url, etc. |
| Routes registered | `php artisan route:list --path=modules/resailai` | callback route shown | Route name and method correct |

### 1.3 Config Verification

| Test | Command | Expected | Notes |
|------|---------|----------|-------|
| Config loads | `php artisan config:clear` | No errors | Config parses |
| Env vars work | `php artisan tinker --execute="echo config('resailai.api_token');"` | Matches .env value | If RESAILAI_API_TOKEN set |

---

## 2. Wave 2 Verification (Middleware & Job)

### 2.1 Middleware Verification

| Test | Command | Expected | Notes |
|------|---------|----------|-------|
| Middleware class exists | `ls app/Http/Middleware/VerifyResailAIToken.php` | File exists | Correct namespace |
| Token validation | `php artisan test --filter VerifyResailAIToken` | Tests pass | Valid token passes, invalid fails |
| Missing token handling | Manual test with curl | Returns 401 | Unauthenticated request rejected |

### 2.2 Job Verification

| Test | Command | Expected | Notes |
|------|---------|----------|-------|
| Job exists | `ls app/Jobs/ProcessDocumentJob.php` | File exists | Correct namespace |
| Queue dispatch | `php artisan test --filter ProcessDocumentJob` | Tests pass | Job dispatches correctly |
| Failed job handling | `php artisan queue:failed` | Shows job | Failed jobs captured |

---

## 3. Wave 3 Verification (Admin UI)

### 3.1 API Key Generation

| Test | Command | Expected | Notes |
|------|---------|----------|-------|
| Controller exists | `ls app/Http/Controllers/Api/AdminController.php` | File exists | Correct namespace |
| Key generation | `curl -X POST /api/modules/resailai/admin/generate-key -H "Authorization: Bearer {admin_token}"` | Returns encrypted key | Key stored in database |
| Key display once | Check database directly | Key visible only once | Not re-displayed |

---

## 4. Integration Verification

### 4.1 End-to-End Flow

| Scenario | Steps | Expected | Notes |
|----------|-------|----------|-------|
| PDF upload with auto_process_pdf=enabled | 1. Set flag on supplier_companies<br>2. Upload PDF<br>3. Check DocumentProcessingLog<br>4. Trigger job manually | Job dispatched, callback received, task created | Full flow works |
| PDF upload with auto_process_pdf=disabled | 1. Disable flag<br>2. Upload PDF<br>3. Check DocumentProcessingLog | Job NOT dispatched, traditional flow | Feature flag works |
| Invalid callback token | POST to callback with wrong token | Returns 401 | Security works |

---

## 5. Security Verification

| Test | Method | Expected | Notes |
|------|--------|----------|-------|
| Bearer token auth | POST callback without token | Returns 401 | Unauthenticated rejected |
| Invalid token | POST callback with wrong token | Returns 401 | Wrong token rejected |
| Token in logs | Check storage/logs/laravel.log | No token present | Security best practice |

---

## 6. Performance Verification

| Test | Command | Expected | Notes |
|------|---------|----------|-------|
| Queue processing | `php artisan queue:work --once` | Job completes | Async processing works |
| Concurrent uploads | 10 parallel uploads | All processed | No race conditions |
| UUID naming | Check storage/app files | UUID prefix | No filename collision |

---

## 7. Code Quality Verification

| Check | Command | Pass/Fail | Notes |
|-------|---------|-----------|-------|
| PHPStan | `./vendor/bin/phpstan analyse` | No errors | Static analysis |
| Code Sniffer | `./vendor/bin/pint --test` | No changes needed | Code style |
| Tests pass | `php artisan test` | All tests pass | Integration tests |

---

## 8. Failed Verification Actions

If any verification fails:

1. **Check logs**: `tail -f storage/logs/laravel.log`
2. **Re-run migration**: `php artisan migrate:rollback && php artisan migrate`
3. **Clear cache**: `php artisan config:clear && php artisan route:clear && php artisan cache:clear`
4. **Check .env**: Verify `RESAILAI_API_TOKEN` and `N8N_WEBHOOK_URL` are set

---

*Verification plan created: 2026-03-11*
*Reference: Phase 09 - ResailAI Module Implementation*
