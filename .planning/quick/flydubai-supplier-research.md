# Flydubai Supplier Research

**Researched:** 2026-03-10
**Domain:** Document Processing, AIR File Parsing, Supplier Integration
**Confidence:** HIGH

## Summary

Flydubai (supplier_id=2) is integrated into the Soud Laravel system as an AIR file processor using the shared `AirFileParser` service. Flydubai files are processed identically to other Amadeus GDS-format AIR files (Jazeera Airways, Air Arabia, Indigo, Cham Wings). The processing flow uses the N8n webhook orchestration with deferred processing - N8n routes Flydubai documents to the AIR processor, which immediately returns a `deferred` status, and Laravel's `AirFileParser` handles the actual extraction.

**Primary Finding:** Flydubai has no supplier-specific code. All AIR file suppliers (IDs 1, 2, 5, 6, 7) use the same `AirFileParser.php` regex-based parser. The only Flydubai-specific logic is in `TaskWebhook.php` and `TaskController.php` for IATA wallet processing when `supplier_id == 2`.

## Standard Stack

### Core
| Library/Service | Version | Purpose | Why Standard |
|----------------|---------|---------|--------------|
| AirFileParser | Local (1,690+ lines) | Parse Amadeus GDS AIR files | Handles all AIR file variations for flight suppliers |
| AirFileService | Local | Business logic wrapper for parsing | Normalizes data using TaskSchema |
| N8n Workflow | Current | Document processing orchestration | Routes documents by supplier_id |
| TaskSchema | Local | Data normalization schema | Standard output format for all task types |

### Supporting
| Library | Purpose | When to Use |
|---------|---------|-------------|
| TaskFlightSchema | Flight-specific fields normalization | All flight tasks from AIR files |
| DocumentProcessingLog | Processing state tracking | All document processing via N8n |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Shared AirFileParser | Supplier-specific parsers | Higher maintenance, code duplication |
| Deferred N8n processing | Native N8n AIR parsing | Complex regex patterns would need porting |

## Code Locations

### Flydubai-Specific Code
| File | Lines | Purpose |
|------|-------|---------|
| `app/Http/Webhooks/TaskWebhook.php` | 587-590 | IATA wallet processing for supplier_id=2 |
| `app/Http/Controllers/TaskController.php` | 1264-1357 | IATA wallet automation for Flydubai (Amadeus) |

### Shared AIR Processing Code
| File | Lines | Purpose |
|------|-------|---------|
| `app/Services/AirFileParser.php` | 1-1700+ | AIR file parsing (regex-based) |
| `app/Services/AirFileService.php` | 1-337 | Business logic wrapper |
| `app/Schema/TaskSchema.php` | 1-600+ | Task data normalization schema |
| `app/Schema/TaskFlightSchema.php` | 1-145 | Flight details schema |

### N8n Integration
| File | Lines | Purpose |
|------|-------|---------|
| `n8n/workflows/supplier-document-processing.json` | 1-522 | Main N8n workflow |
| `n8n/nodes/air-processor.json` | 1-24 | AIR fallback handler |

## Data Fields Extracted from Flydubai Files

### Primary Task Fields (from AirFileParser)
| Field | Type | Extraction Method | AIR Line Pattern |
|-------|------|-------------------|------------------|
| `ticket_number` | string | Regex from T-K line | `/^(T-[KE]\d+-\d+)/` |
| `gds_reference` | string | 6 chars after MUC1A | `/^MUC1A\s+([A-Z0-9]{6})/` |
| `airline_reference` | string | Last 6 chars from GDS line | `/^MUC1A\s+[A-Z0-9]+.*\s+([A-Z0-9]{6})/` |
| `status` | string | Status detection | VOID, RF, FO, EMD indicators |
| `price` | float | K line parsing | `/^K-[RF]([A-Z]{3})([\d.]+)/` |
| `currency` | string | K line exchange | Exchange currency from K line |
| `total` | float | K line total | Total amount from K line |
| `tax` | float | Tax extraction | Tax from pricing breakdown |
| `taxes_record` | string | KRF line parsing | Tax breakdown format |
| `client_name` | string | I line passenger | `/^I-\d+;\d+([^;]+);/` |
| `agent_name` | string | A line agent | Agent name from A line |
| `agent_amadeus_id` | string | MUC1A line | Agent ID from GDS line |
| `issued_date` | datetime | D line | Date parsing from D line |
| `refund_date` | date | D line for refunds | `/\bD-(\d{6});(\d{6});\d{6}\b/` |
| `void_date` | date | VOID pattern | `/VOID(\d{2}(?:JAN|FEB|...))/` |

### Flight Details Fields (from AirFileParser::parseFlightDetails)
| Field | Type | Extraction Method |
|-------|------|-------------------|
| `departure_time` | datetime | H line parsing |
| `arrival_time` | datetime | H line parsing |
| `airport_from` | string | H line airport code |
| `airport_to` | string | H line airport code |
| `flight_number` | string | H line flight number |
| `airline` | string | H line carrier code |
| `class_type` | string | H line booking class |
| `terminal_from` | string | H line terminal |
| `terminal_to` | string | H line terminal |
| `baggage_allowed` | string | H line baggage |
| `equipment` | string | H line aircraft type |
| `seat_no` | string | S line per passenger |

## How Flydubai Differs from Other Suppliers

### Same Processing (All AIR Suppliers)
- Jazeera Airways (supplier_id=1)
- Flydubai (supplier_id=2)
- Air Arabia (supplier_id=5)
- Indigo (supplier_id=6)
- Cham Wings (supplier_id=7)

All use identical `AirFileParser.php` regex patterns for Amadeus GDS format.

### Flydubai-Specific Logic
1. **IATA Wallet Processing**: When `supplier_id == 2` and `iata_number == '42230215'`, the system uses City Travelers IATA wallet for payment automation
2. **Agent ID Detection**: Specific agent IDs like `KWIKT211N` trigger City Travelers branch processing

### File Format
Flydubai AIR files follow standard Amadeus GDS format:
```
AIR-BLK1;IS;001
MUC1A 8DROXL0101;1234567;KWIKT2619;
T-K229-2833133219
I-001;001TEST/USER MR;
A-KUWAIT AIRWAYS;KU
K-FKWD100.000;;;;;;;;;;;;KWD130.000;;;
H-003OKWI;KUWAIT;DOH;DOHA;QR1077 S S 30JUL0435 0605 30JUL;OK02;HK02;M;0;77W;30K;1;
```

## Integration Points

### N8n Workflow Routing
```json
// supplier-document-processing.json - Route by Supplier switch
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

### Database Tables
| Table | Purpose | Flydubai Usage |
|-------|---------|----------------|
| `suppliers` | Supplier master | supplier_id=2 for Flydubai |
| `tasks` | Task records | All Flydubai flights stored here |
| `task_flight_details` | Flight segments | Multi-segment flights |
| `document_processing_logs` | N8n tracking | Processing state for each document |

### API Endpoints
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/documents/process` | POST | Queue document for N8n processing |
| `/api/webhooks/n8n/callback` | POST | Receive N8n processing results |

## Common Pitfalls

### Pitfall 1: Assuming Supplier-Specific Parsing
**What goes wrong:** Developers may try to add Flydubai-specific parsing logic
**Why it happens:** Different suppliers may have slightly different AIR file variations
**How to avoid:** All AIR suppliers use shared `AirFileParser.php`. Add variations to the existing parser, not new supplier-specific parsers.
**Warning signs:** Multiple AIR parser files, supplier_id checks in parsing logic

### Pitfall 2: Missing IATA Wallet Configuration
**What goes wrong:** Flydubai tasks don't auto-process payments through IATA wallet
**Why it happens:** Missing IATA account configuration for agent IDs
**How to avoid:** Ensure `supplier_id=2` and `iata_number='42230215'` are linked to correct accounts
**Warning signs:** Tasks created without payment_method_account_id

### Pitfall 3: File Path Mismatch
**What goes wrong:** N8n cannot read files from Laravel storage
**Why it happens:** Docker volume mount differences between environments
**How to avoid:** Use absolute paths: `/var/www/storage/app/{company}/{supplier}/files_unprocessed/`
**Warning signs:** `ERR_FILE_NOT_FOUND` in N8n logs

### Pitfall 4: Deferred Processing Not Triggered
**What goes wrong:** AIR files processed by N8n return empty extraction_result
**Why it happens:** Laravel callback handler not recognizing `deferred` status
**How to avoid:** Ensure `N8nCallbackController` handles `extraction_status='deferred'`
**Warning signs:** Tasks not created after document processing

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (Laravel default) |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter AirFileParser` |
| Full suite command | `php artisan test` |

### Test Coverage
| Test File | Coverage | Status |
|-----------|----------|--------|
| `tests/Feature/Staging/StagingSupplierTest.php` | supplier_id=2 tests | Existing |
| `tests/Feature/Integration/N8nDocumentProcessingTest.php` | AIR processing | Existing |

### Sampling Rate
- **Per task commit:** `php artisan test --filter AirFileParser`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

## Open Questions

1. **What specific Flydubai AIR file variations exist?**
   - What we know: Flydubai uses Amadeus GDS AIR format
   - What's unclear: Are there Flydubai-specific field variations?
   - Recommendation: Sample Flydubai AIR files to identify any unique patterns

2. **IATA Wallet Multi-Currency Support?**
   - What we know: Flydubai uses IATA wallet for KWD payments
   - What's unclear: How multi-currency bookings are handled
   - Recommendation: Check currency exchange handling in TaskWebhook

## Sources

### Primary (HIGH confidence)
- `app/Services/AirFileParser.php` - AIR file parsing logic (1,690+ lines)
- `app/Services/AirFileService.php` - Business logic wrapper
- `app/Schema/TaskSchema.php` - Task data schema
- `app/Schema/TaskFlightSchema.php` - Flight details schema
- `n8n/workflows/supplier-document-processing.json` - N8n workflow (Flydubai routing)
- `n8n/README.md` - N8n documentation

### Secondary (MEDIUM confidence)
- `app/Http/Webhooks/TaskWebhook.php` - supplier_id=2 IATA processing
- `app/Http/Controllers/TaskController.php` - IATA wallet automation
- `app/Models/Supplier.php` - Supplier model structure
- `app/Models/Task.php` - Task model structure
- `.planning/quick/01-n8n-flydubai-extraction-research.md` - Previous N8n research

### Tertiary (LOW confidence)
- Database seeder (no Flydubai entry found - may be added manually)

## Metadata

**Confidence breakdown:**
- Standard Stack: HIGH - Based on code inspection of AirFileParser, AirFileService, N8n workflow
- Architecture: HIGH - Clear routing via supplier_id in N8n, shared AIR parser
- Pitfalls: HIGH - Common integration issues documented

**Research date:** 2026-03-10
**Valid until:** 2026-04-10 (30 days for stable Laravel/N8n)