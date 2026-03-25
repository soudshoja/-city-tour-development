# Phase 15: ResailAI PDF Processing Integration

---

## Phase Frontmatter

```yaml
phase: 14-resailai-module
plan: 15
type: execute
wave: 3
depends_on:
  - 14-01
  - 14-02
files_modified:
  - database/migrations/2026_03_11_000002_add_auto_process_pdf_to_supplier_companies.php
  - app/Http/Controllers/TaskController.php
  - app/Http/Controllers/Api/ResailAIAdminController.php
  - app/Http/Controllers/Api/ResailAISuppliersController.php
  - app/Modules/ResailAI/Http/Controllers/CallbackController.php
  - resources/views/livewire/admin/resailai-settings-index.blade.php
autonomous: false
requirements:
  - RESAIL-11
  - RESAIL-12
  - RESAIL-13
  - RESAIL-14
  - RESAIL-15
user_setup:
  - env_var: RESAILAI_API_TOKEN
    why: "Bearer token for ResailAI n8n webhook authentication"
    value_hint: "32+ character random string generated via php artisan make:secret"
  - env_var: N8N_WEBHOOK_URL
    why: "URL of n8n webhook for PDF processing"
    value_hint: "https://n8n.example.com/webhook/resailai-process"
```

---

## Objective

Complete the ResailAI integration by:
1. Adding the `auto_process_pdf` database column for feature flags
2. Modifying TaskController to automatically trigger ResailAI for PDF files
3. Implementing CallbackController to process extraction results and create tasks
4. Creating webhook URL configuration in the settings UI

**Purpose:** Enable automatic PDF document processing via ResailAI when files are uploaded through the portal. When a supplier has `auto_process_pdf = true`, files should be sent to ResailAI n8n webhook and extraction results should automatically create tasks.

**Output:**
- Database migration for `auto_process_pdf` column
- Modified TaskController upload() to check flag and dispatch jobs
- Implemented CallbackController handle() to process results
- Webhook URL configuration in settings UI
- End-to-end test from portal upload to task creation

---

## Execution Context

@{HOME}/.claude/get-shit-done/workflows/execute-plan.md
@{HOME}/.claude/get-shit-done/templates/summary.md

---

## Context

@.planning/phases/14-resailai-module/14-RESEARCH.md

---

## Tasks

<task type="auto">
  <name>Task 1: Create auto_process_pdf database migration</name>
  <files>database/migrations/2026_03_11_000002_add_auto_process_pdf_to_supplier_companies.php</files>
  <action>Create Laravel migration to add `auto_process_pdf` boolean column to `supplier_companies` pivot table.

Requirements:
- Add `auto_process_pdf` boolean column with default `false`
- Add comment: "Auto-process PDF files via ResailAI webhook for this supplier/company combo"
- Place after `is_active` column
- Include rollback that drops the column
- Add index for performance on supplier_id + company_id + auto_process_pdf

Reference pattern from existing migrations in `database/migrations/` for style consistency.</action>
  <verify>
    <automated>php artisan migrate --pretend</automated>
  </verify>
  <done>Migration file exists and validates without errors</done>
</task>

<task type="auto">
  <name>Task 2: Modify TaskController upload() for ResailAI</name>
  <files>app/Http/Controllers/TaskController.php</files>
  <action>Modify TaskController::upload() to check auto_process_pdf flag and dispatch to ResailAI queue for PDF files.

Requirements:
- Import ProcessingAdapter and ProcessDocumentJob
- After file upload, check: ProcessingAdapter::isPdfProcessingEnabled($supplierId, $companyId)
- If TRUE and file is PDF, dispatch ProcessDocumentJob with FileUpload ID
- If FALSE, use traditional processing (existing flow)
- Update FileUpload status to 'queued' when ResailAI is triggered
- Return response indicating processing method used

Keep existing AIR file processing unchanged.</action>
  <verify>
    <automated>
      php artisan test --filter TaskController::upload
      php artisan tinker --execute="echo json_encode(ProcessingAdapter::isPdfProcessingEnabled(2, 1));"
    </automated>
  </verify>
  <done>Upload method dispatches to ResailAI when flag is enabled</done>
</task>

<task type="auto">
  <name>Task 3: Implement CallbackController handle() method</name>
  <files>app/Modules/ResailAI/Http/Controllers/CallbackController.php</files>
  <action>Implement the handle() method to process extraction results from ResailAI.

Requirements:
- Validate Bearer token using VerifyResailAIToken
- Validate payload structure (document_id, status, extraction_result)
- Check feature flag via ProcessingAdapter
- Transform extraction_result via ProcessingAdapter
- Call TaskWebhookBridge::process($extractionResult)
- Update FileUpload status to 'completed' or 'error'
- Log all operations
- Return proper JSON response with status

Handle both success and error cases properly.</action>
  <verify>
    <automated>php artisan test --filter ResailAICallback</automated>
  </verify>
  <done>Callback processes extraction results and creates tasks</done>
</task>

<task type="auto">
  <name>Task 4: Add webhook URL configuration to settings</name>
  <files>resources/views/livewire/admin/resailai-settings-index.blade.php</files>
  <action>Add configuration field for N8N_WEBHOOK_URL in the settings UI.

Requirements:
- Add text input for "Webhook URL" field
- Validate URL format
- Save to resailai config or database
- Show current value when loading settings
- Include help text explaining the n8n webhook URL

Use existing Laravel config or settings table pattern.</action>
  <verify>
    <automated>php artisan view:clear && view renders without errors</automated>
  </verify>
  <done>Settings UI has webhook URL configuration field</done>
</task>

<task type="manual">
  <name>Task 5: Test end-to-end workflow</name>
  <files>None</files>
  <action>Test the complete flow from portal upload to task creation.

Test steps:
1. Enable auto_process_pdf for a test supplier (e.g., FlyDubai)
2. Upload a test PDF file via the portal
3. Verify job is dispatched to queue
4. Trigger queue worker: php artisan queue:work --once
5. Verify n8n webhook receives request
6. Verify callback is sent back to Laravel
7. Verify task is created in database
8. Check FileUpload status is 'completed'

This requires running n8n with the webhook configured.</action>
  <verify>
    <manual>Test PDF upload through portal</manual>
  </verify>
  <done>End-to-end flow works without errors</done>
</task>

<task type="manual">
  <name>Task 6: Create migration rollback script</name>
  <files>None</files>
  <action>Create documentation for rolling back the auto_process_pdf migration.

Requirements:
- Document how to run migration rollback
- Document how to restore feature flag values if needed
- Provide SQL queries for manual rollback if needed

This is for production rollback scenarios.</action>
  <verify>
    <manual>Documentation exists</manual>
  </verify>
  <done>Rollback documentation created</done>
</task>

</tasks>

---

## Verification

- [ ] Migration runs cleanly: `php artisan migrate`
- [ ] TaskController dispatches to ResailAI when enabled
- [ ] CallbackController validates token and creates tasks
- [ ] Settings UI displays webhook URL configuration
- [ ] FileUpload status updates correctly
- [ ] No syntax errors after modifications
- [ ] Existing AirFileParser flow still works for non-ResailAI suppliers

---

## Success Criteria

1. `auto_process_pdf` column exists on `supplier_companies` table
2. TaskController::upload() dispatches to ResailAI when flag is enabled
3. CallbackController processes extraction results and creates tasks via TaskWebhookBridge
4. Settings UI has webhook URL configuration field
5. End-to-end test passes with sample PDF
6. Existing manual processing (AirFileParser) still works
7. Queue processing works correctly

---

## Output

After completion, create: `.planning/phases/14-resailai-module/15-SUMMARY.md`

---

*Phase created: 2026-03-11*
*For Soud Laravel ResailAI Module - PDF Processing Integration*
