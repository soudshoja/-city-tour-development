# Flydubai File Upload Method Research

**Researched:** 2026-03-10
**Domain:** Document Processing, File Upload, Supplier Integration
**Confidence:** HIGH

## Summary

**Critical Finding:** There is NO separate file upload method for Flydubai. Flydubai uses the SAME file upload flow as other suppliers, but the processing path differs based on:

1. **Document type detection** (extension-based: `.air` vs `.pdf` vs `.jpg`)
2. **Supplier name matching** (`shouldUseAirFileParser()` requires supplier name "Amadeus")
3. **N8N routing by supplier_id** (routes Flydubai documents to AIR Processor which returns "deferred")

**The Key Insight:** Previous research incorrectly stated Flydubai uses AirFileParser. The actual implementation shows:
- AirFileParser is ONLY used when `supplier.name === 'Amadeus'` AND `file.extension === '.air'`
- All other suppliers (including Flydubai) use AI-based processing for non-AIR files
- N8N workflow routes Flydubai (supplier_id=2) to AIR Processor which returns "deferred" status

## Processing Methods Comparison

### Method 1: AIR File Processing (Amadeus Supplier Only)

**Applies to:** Supplier named "Amadeus" with `.air` extension files ONLY

| Property | Value |
|----------|-------|
| File extension | `.air` |
| Supplier name | Must be "Amadeus" (exact match, case-insensitive) |
| Processing | AirFileParser (regex-based) |
| N8N route | AIR Processor (deferred) |
| Code location | `ProcessAirFiles.php::shouldUseAirFileParser()` |

**Code logic:**
```php
// app/Console/Commands/ProcessAirFiles.php Lines 1668-1687
protected function shouldUseAirFileParser($supplier, array $files): bool
{
    // Use AirFileParser if:
    // 1. Supplier is Amadeus (name matches exactly)
    // 2. At least one file has .air extension

    if (!$supplier || strcasecmp($supplier->name, 'Amadeus') !== 0) {
        return false;  // Returns FALSE for Flydubai!
    }

    // Check if any file has .air extension
    foreach ($files as $file) {
        $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        if ($extension === 'air') {
            return true;
        }
    }

    return false;
}
```

**Important:** This means Flydubai files (with supplier name "Fly Dubai" or similar) will ALWAYS return `false` from `shouldUseAirFileParser()`, regardless of file extension.

### Method 2: AI-Based Processing (All Other Suppliers/File Types)

**Applies to:** All suppliers when file is NOT `.air` OR supplier is NOT "Amadeus"

| Property | Value |
|----------|-------|
| File extension | `.pdf`, `.txt`, `.jpg`, `.png`, etc. |
| Supplier | Any (including Flydubai) |
| Processing | AIManager (OpenAI/OpenWebUI) |
| N8N route | PDF: Tika, Image: Gutenberg OCR, or AIR Processor (deferred) |
| Code location | `ProcessAirFiles.php::processSingleFileWithAI()` |

**Code logic:**
```php
// app/Console/Commands/ProcessAirFiles.php Lines 594-612
$supplier = Supplier::find($supplierId);
$useAirFileParser = $this->shouldUseAirFileParser($supplier, [$file]);

$processingMethod = $useAirFileParser ? 'AirFileParser' : 'AI-based processing';
$this->info("Using {$processingMethod} for file: {$fileName}");

if ($useAirFileParser) {
    $this->processSingleFileWithAirParser(...);  // Only for Amadeus + .air
} else {
    $this->processSingleFileWithAI(...);  // Everything else, including Flydubai
}
```

## N8N Workflow Routing (supplier-document-processing.json)

| Supplier ID | Supplier Name | Processing Path | Actual Processing |
|-------------|---------------|-----------------|-------------------|
| 1 | Jazeera Airways | AIR Processor (deferred) | AI-based (not Amadeus) |
| 2 | **Flydubai** | **AIR Processor (deferred)** | **AI-based (not Amadeus)** |
| 3 | ETA UK | PDF (Tika) | AI-based |
| 4 | The Skyrooms | PDF (Tika) | AI-based |
| 5 | Air Arabia | AIR Processor (deferred) | AI-based (not Amadeus) |
| 6 | Indigo | AIR Processor (deferred) | AI-based (not Amadeus) |
| 7 | Cham Wings | AIR Processor (deferred) | AI-based (not Amadeus) |
| 8 | VFS Global | PDF (Tika) | AI-based |
| 11 | Gmail | Email processing | Email |
| 12 | Image Upload | Gutenberg OCR | AI-based |

**N8N Routing Code (Lines 78-91):**
```json
{
  "conditions": {
    "conditions": [
      {
        "leftValue": "={{ $json.supplier_id }}",
        "rightValue": 2,
        "operator": { "type": "number", "operation": "equals" }
      }
    ]
  },
  "outputKey": "FlyDubai (AIR)"
}
```

**AIR Processor Response (Lines 281-296):**
```javascript
// Returns "deferred" - actual parsing delegated to Laravel
{
  "document_id": $json.document_id,
  "extraction_status": "deferred",
  "extraction_method": "laravel-airfileparser-fallback",
  "message": "AIR file processing deferred to Laravel AirFileParser"
}
```

## How Flydubai PDF/Image Files Are Actually Processed

### Scenario 1: Flydubai PDF uploaded through N8N

```
Upload -> N8N webhook -> supplier_id=2 -> AIR Processor
     -> Returns "deferred" status -> Laravel callback
     -> Laravel ProcessAirFiles runs -> shouldUseAirFileParser() checks:
         - supplier.name !== 'Amadeus' -> returns FALSE
     -> Falls back to processSingleFileWithAI()
     -> AI (OpenAI/OpenWebUI) extracts data from PDF
```

### Scenario 2: Flydubai PDF uploaded directly to Laravel (legacy flow)

```
Upload -> TaskController::upload() -> File stored in files_unprocessed/
     -> ProcessAirFiles command (manual or scheduled)
     -> shouldUseAirFileParser() checks:
         - supplier.name !== 'Amadeus' -> returns FALSE
     -> Falls back to processSingleFileWithAI()
     -> AI (OpenAI/OpenWebUI) extracts data from PDF
```

**Result:** Both paths end up using AI-based extraction for Flydubai PDF/image files.

## Upload Endpoints (Same for All Suppliers)

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `POST /tasks/upload` | Web form | Main upload for task files |
| `POST /api/documents/process` | API | Queue document for N8N processing |
| `POST /api/webhooks/n8n/extraction` | Webhook | N8N callback |

**Upload Flow:**
```
Frontend -> POST /tasks/upload
        -> File stored in storage/app/{company}/{supplier}/files_unprocessed/
        -> FileUpload record created (status: pending)
        -> ProcessAirFiles command (manual or scheduled)
        -> shouldUseAirFileParser() check:
            - If Amadeus + .air -> AirFileParser
            - Otherwise -> AI-based extraction
        -> Task created
        -> File moved to files_processed/ or files_error/
```

## Storage Path Structure

```
storage/app/
├── {company_name}/                    # Lowercase, underscores for spaces
│   └── {supplier_name}/               # Lowercase, underscores for spaces
│       ├── files_unprocessed/         # Pending processing queue
│       ├── files_processed/           # Successfully processed
│       ├── files_error/               # Failed processing
│       └── resayil/{date}/            # WhatsApp uploads
```

**For Flydubai:**
```
storage/app/
└── city_travelers/
    └── fly_dubai/                     # supplier_name (from "Fly Dubai")
        ├── files_unprocessed/
        ├── files_processed/
        └── files_error/
```

## Database Tables

### file_uploads Table

```php
// Migration: database/migrations/2025_07_04_101603_create_file_uploads_table.php
Schema::create('file_uploads', function (Blueprint $table) {
    $table->id();
    $table->string('file_name');                   // Original filename
    $table->string('merged_file_name')->nullable(); // For merged PDFs (TBO only)
    $table->string('destination_path');            // Full filesystem path
    $table->foreignId('user_id')->constrained('users');
    $table->foreignId('supplier_id')->constrained('suppliers');  // Links to Flydubai (id=2)
    $table->foreignId('company_id')->nullable()->constrained('companies');
    $table->foreignId('task_id')->nullable()->constrained('tasks');  // Set after processing
    $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
    $table->json('source_files')->nullable();      // For merged files tracking
    $table->timestamps();
});
```

## Extracted Fields Comparison

### AIR File (Amadeus Supplier + .air extension)

| Field | Extraction Method | Confidence |
|-------|-------------------|------------|
| `ticket_number` | Regex from T-K line | HIGH |
| `gds_reference` | Regex from MUC1A | HIGH |
| `status` | Pattern detection | HIGH |
| `price/total/currency` | K line parsing | HIGH |
| `client_name` | I line passenger | HIGH |
| `flight_details` | H line segments | HIGH |

### PDF/Image (Flydubai, AI Extraction)

| Field | Extraction Method | Confidence |
|-------|-------------------|------------|
| `reference_number` | AI extraction (PNR pattern) | MEDIUM |
| `passenger_name` | AI extraction (name pattern) | MEDIUM |
| `amount/currency` | AI extraction (price pattern) | MEDIUM |
| `dates` | AI extraction (date parsing) | MEDIUM |
| `flight_details` | AI extraction (itinerary) | LOW-MEDIUM |

**Key Difference:** AIR files use structured regex extraction with HIGH confidence. PDF/images use AI extraction with variable confidence based on document quality.

## How to Differentiate in Code

### Check Processing Method

```php
// In ProcessAirFiles.php
$supplier = Supplier::find($supplierId);
$useAirFileParser = $this->shouldUseAirFileParser($supplier, $files);

if ($useAirFileParser) {
    // Method 1: AIR file processing (Amadeus supplier + .air extension)
    // Only executes for supplier named "Amadeus" with .air files
    $this->processBatchFilesWithAirParser(...);
} else {
    // Method 2: AI-based processing (all other cases)
    // Executes for Flydubai, Jazeera, Air Arabia, Indigo, Cham Wings, etc.
    // Also executes for PDF/image files even for Amadeus supplier
    $this->processBatchFilesWithAI(...);
}
```

### Check File Type

```php
// File extension check
$extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));

if ($extension === 'air' && strcasecmp($supplier->name, 'Amadeus') === 0) {
    // Use AirFileParser (regex-based, deterministic)
} else {
    // Use AI-based extraction (OpenAI/OpenWebUI)
}
```

### Check N8N Routing

```javascript
// In N8N workflow - Route by Supplier switch
if ($json.supplier_id === 2) {
    // Flydubai route -> AIR Processor -> returns "deferred"
}
```

## Common Pitfalls

### Pitfall 1: Assuming Flydubai Uses AirFileParser

**What goes wrong:** Developer assumes supplier_id=2 (Flydubai) uses AirFileParser for all files because N8N workflow routes to "AIR Processor".

**Why it happens:** N8N workflow output key is "FlyDubai (AIR)" but the AIR Processor node only returns "deferred" status. The actual processing happens in Laravel via `ProcessAirFiles` command.

**How Laravel actually processes:**
```php
// shouldUseAirFileParser() returns FALSE for Flydubai because:
// strcasecmp($supplier->name, 'Amadeus') !== 0  <-- TRUE, so returns FALSE
```

**How to avoid:** Understand the two-stage flow:
1. N8N routes by supplier_id but returns "deferred"
2. Laravel's `shouldUseAirFileParser()` checks supplier NAME (not ID) for "Amadeus"

**Warning signs:** PDF files for Flydubai not extracting expected flight fields.

### Pitfall 2: Supplier Name Mismatch

**What goes wrong:** Database has "Fly Dubai" (or variations) but code checks for "Amadeus".

**Why it happens:** Supplier seeder creates specific suppliers, but production may have different names:
```php
// From SupplierSeeder.php
Supplier::updateOrCreate(['name' => 'Amadeus', ...]);
Supplier::updateOrCreate(['name' => 'Magic Holiday', ...]);
// Flydubai may be named differently in production
```

**How to avoid:** Check actual supplier names in database:
```php
$supplier = Supplier::find(2);
echo $supplier->name;  // Verify actual name
```

### Pitfall 3: N8N "Deferred" Not Handled

**What goes wrong:** N8N returns "deferred" status but Laravel doesn't properly handle deferred processing.

**Why it happens:** Callback handler may not check for `extraction_status: 'deferred'`.

**How to avoid:** Ensure N8nCallbackController handles deferred status:
```php
if ($validated['status'] === 'deferred') {
    // Trigger Laravel processing via artisan command
    Artisan::call('app:process-files', [
        '--company' => $validated['company_id'],
        '--supplier' => $validated['supplier_id']
    ]);
}
```

### Pitfall 4: File Path Mismatch Between Storage and N8N

**What goes wrong:** N8N cannot read files from Laravel storage path.

**Why it happens:** Docker volume mount differences between Laravel and N8N containers.

**How to avoid:** Use absolute paths: `/var/www/storage/app/{company}/{supplier}/files_unprocessed/`

## Architecture Recommendation

For Flydubai PDF/image file uploads, consider:

1. **Add Flydubai-specific PDF route in N8N:**
```json
{
  "conditions": [
    { "leftValue": "={{ $json.supplier_id }}", "rightValue": 2, "operator": "equals" },
    { "leftValue": "={{ $json.document_type }}", "rightValue": "pdf", "operator": "equals" }
  ],
  "outputKey": "FlyDubai (PDF)"
}
```

2. **Add document_type detection in Laravel:**
```php
protected function getDocumentType($file): string
{
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    return match($extension) {
        'air' => 'air',
        'pdf' => 'pdf',
        'jpg', 'jpeg', 'png' => 'image',
        default => 'unknown'
    };
}
```

3. **Route Flydubai PDFs to Tika processor in N8N:**
- Similar to supplier_id 3, 4, 8 (PDF processors)
- Extract text with Tika
- Apply Flydubai-specific parsing rules

## Supplier Seeding

```php
// database/seeders/SupplierSeeder.php
Supplier::updateOrCreate(['name' => 'Amadeus', 'country_id' => $kuwaitId], ['has_hotel' => true, 'has_flight' => true]);
Supplier::updateOrCreate(['name' => 'Magic Holiday', 'country_id' => $kuwaitId], ['has_hotel' => true]);
Supplier::updateOrCreate(['name' => 'TBO Holiday', 'country_id' => $kuwaitId], ['has_hotel' => true]);
Supplier::updateOrCreate(['name' => 'DOTW', 'country_id' => $kuwaitId], ['has_hotel' => true]);
Supplier::updateOrCreate(['name' => 'Rate Hawk', 'country_id' => $kuwaitId], ['has_hotel' => true]);
```

**Note:** Flydubai (supplier_id=2) may be added manually or via different seeder. The production database determines actual supplier names.

## Sources

### Primary (HIGH confidence)
- `app/Console/Commands/ProcessAirFiles.php` - Lines 1668-1687 (shouldUseAirFileParser)
- `app/Console/Commands/ProcessAirFiles.php` - Lines 724+ (processSingleFileWithAI)
- `n8n/workflows/supplier-document-processing.json` - Lines 78-91 (Flydubai routing)
- `database/seeders/SupplierSeeder.php` - Supplier name seeding
- `app/Http/Controllers/TaskController.php` - Lines 3000-3334 (upload method)

### Secondary (MEDIUM confidence)
- `.planning/quick/flydubai-supplier-research.md` - Previous research (contains inaccuracies about AirFileParser usage)
- `.planning/quick/01-n8n-flydubai-extraction-research.md` - N8N integration research
- `.planning/quick/pdf-upload-flow-research.md` - PDF upload flow documentation

### Tertiary (Context)
- `app/Services/AirFileParser.php` - AIR parsing implementation
- `app/Services/AirFileService.php` - AIR service wrapper
- `app/AI/AIManager.php` - AI-based extraction
- `app/Models/Supplier.php` - Supplier model

## Metadata

**Confidence breakdown:**
- Processing methods: HIGH - Based on code inspection of `shouldUseAirFileParser()`
- N8N routing: HIGH - Based on workflow JSON analysis
- Upload flow: HIGH - Based on TaskController and ProcessAirFiles analysis
- Supplier configuration: MEDIUM - Seeder shows limited suppliers, production may differ
- AIR parsing limitation: HIGH - Code clearly shows Amadeus-only check

**Research date:** 2026-03-10
**Valid until:** 2026-04-10 (30 days for stable codebase)

## Related Research

- `.planning/quick/flydubai-supplier-research.md` - Flydubai AIR file processing (note: contains inaccuracies)
- `.planning/quick/01-n8n-flydubai-extraction-research.md` - N8N integration
- `.planning/quick/pdf-upload-flow-research.md` - PDF upload flow
- `.planning/quick/webhook-urls-research.md` - N8N webhook configuration