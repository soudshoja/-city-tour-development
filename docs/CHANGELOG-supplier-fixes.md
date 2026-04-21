# Supplier Fix Log

Cumulative log of supplier-specific fixes and hotfixes to the PDF processing pipeline.

---

## 2026-04-20 — Sky Rooms: `room_details` array-to-string fix

**Requested by:** Hashemi
**Supplier:** Sky Rooms Holidays
**Severity:** Medium (data loss — PDFs not becoming tasks)

**Issue:**
Sky Rooms PDFs (example: `SRH48046-.pdf` uploaded 2026-04-19) were moving to `files_error/` with fatal error `Array to string conversion` at `ProcessAirFiles.php:958`. AI correctly extracted data but `task_hotel_details.room_details` was returned as nested object/array; storage attempted string coercion → crash. 4 files stuck in `storage/app/city_travelers/sky_rooms/files_error/`.

**Root cause:**
`TaskHotelSchema::normalize()` did not JSON-serialize `room_details` when it came back as an array. All other writers in the codebase (`PaymentController`, `TaskController`, `getMagicHolidayReservationList`, `WhatsAppHotelController`) already `json_encode` before save. Missing normalization was the only gap.

**Fix:**
Added 3 lines to `app/Schema/TaskHotelSchema.php::normalize()` — if `room_details` is array, `json_encode` it before return. Zero structural change: column stays TEXT, no model cast, no migration, no AI prompt change.

**Files changed:**
- `app/Schema/TaskHotelSchema.php` (+3 lines)

**Deployment:**
- Commit: `e03db9a6`
- Deployed to production: `tour.citycommerce.group` at `2026-04-20 11:42:06 UTC` via `scp` (server is not a git checkout; single-file scp + `php artisan optimize:clear`)
- Replayed 3 stuck `.pdf` files moved from `files_error/` → `files_unprocessed/`:
  - `INV2 (2) (1).pdf`
  - `SKYR-2510071425-b01.pdf`
  - `SRH48046-.pdf`
  - (1 non-PDF file `download` (JPEG) left in `files_error/` — not a PDF, not moved.)
- Verified: 2 new tasks created after replay (IDs 14464, 14465) — `SRH48046-.pdf` produced task ID 14465 (reference `2026/03145`, passenger Mrs Anoud Alkandari). `SKYR-2510071425-b01.pdf` likely deduped against the existing Oct 2025 processed copy. No new `Array to string conversion` errors in `storage/logs/laravel.log` post-deploy.

**Permanent fix deferred:**
Column type change to JSON + Eloquent Attribute cast pending as part of Phase 25 (PDF AI Processing Fallback). See `.planning/phases/25-pdf-ai-fallback-chain/25-RESEARCH.md`.

---
