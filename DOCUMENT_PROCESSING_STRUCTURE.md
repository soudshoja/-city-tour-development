# Soud Laravel - Document Processing System Structure

## Complete System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    DOCUMENT PROCESSING SYSTEM                    │
└─────────────────────────────────────────────────────────────────┘

┌────────────────────┐
│   FILE INPUTS      │
│ (Multiple Types)   │
└────────┬───────────┘
         │
         ├──► AIR Files (.AIR)          → AirFileParser
         ├──► PDF Documents (.pdf)      → OpenAI/OpenWebUI Vision
         ├──► Passport Images (jpg/png) → GPT-4o Vision API
         ├──► Email Attachments         → GPT-3.5 Text Extraction
         ├──► Text Files (.txt)         → TextFileProcessor
         └──► Excel/CSV (.xlsx, .csv)   → Import Services

         │
         ▼
┌────────────────────────────────────────────────────────────────┐
│                      AI PROCESSING LAYER                        │
├────────────────────────────────────────────────────────────────┤
│  AIManager.php (Central Orchestrator)                          │
│  ├─► OpenAIClient (GPT-3.5, GPT-4, GPT-4o Vision)            │
│  ├─► OpenWebUIClient (Local LLMs + RAG)                       │
│  └─► AnythingLLMClient (Local AI Workspace)                   │
└────────────────────┬───────────────────────────────────────────┘
                     │
                     ▼
┌────────────────────────────────────────────────────────────────┐
│                    PARSING & EXTRACTION                         │
├────────────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ AirFileParser.php (1,690 lines)                          │ │
│  │ • Parses Amadeus GDS format                              │ │
│  │ • Extracts ticket info, pricing, flight details          │ │
│  │ • Multi-passenger support                                │ │
│  │ • Currency conversion handling                           │ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ TextFileProcessor.php (79 lines)                         │ │
│  │ • Simple text extraction                                 │ │
│  │ • Kuwait Airways ticket receipts                         │ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ OpenAiController.php                                     │ │
│  │ • Passport image OCR                                     │ │
│  │ • PDF document extraction                                │ │
│  │ • Email content parsing                                  │ │
│  └──────────────────────────────────────────────────────────┘ │
└────────────────────┬───────────────────────────────────────────┘
                     │
                     ▼
┌────────────────────────────────────────────────────────────────┐
│                   SCHEMA NORMALIZATION                          │
├────────────────────────────────────────────────────────────────┤
│  TaskSchema.php (405 lines)                                    │
│  ├─► TaskFlightSchema.php (145 lines)                          │
│  ├─► TaskHotelSchema.php                                       │
│  ├─► TaskInsuranceSchema.php                                   │
│  └─► TaskVisaSchema.php                                        │
└────────────────────┬───────────────────────────────────────────┘
                     │
                     ▼
┌────────────────────────────────────────────────────────────────┐
│                    DATABASE STORAGE                             │
├────────────────────────────────────────────────────────────────┤
│  Tasks Table (Main)                                             │
│  ├─► task_flight_details (Flights)                             │
│  ├─► task_hotel_details (Hotels)                               │
│  ├─► task_insurance_details (Insurance)                        │
│  ├─► task_visa_details (Visas)                                 │
│  ├─► task_emails (Email correspondence)                        │
│  └─► Related: invoices, payments, refunds, journal_entries     │
└─────────────────────────────────────────────────────────────────┘
```

---

## File Types & Processing Methods

### 1. **AIR Files (.AIR)** - Amadeus GDS Format
**Processor:** `AirFileParser.php`
**Method:** Regex pattern matching + line parsing

**Supported Ticket Types:**
- ✅ Issued tickets
- ✅ Refunded tickets
- ✅ Voided tickets
- ✅ Reissued/Exchanged tickets
- ✅ EMD (Electronic Miscellaneous Documents)

**Extracted Data:**
- Ticket numbers (T-K, R-, FO, TMCD formats)
- GDS references (MUC1A lines)
- Passenger information (I- lines)
- Flight segments (H-/U- lines)
- Pricing (K-, KN-, KS- lines)
- Taxes (KFTF, KNTI, KRF lines)
- Seat assignments (S- lines)
- Date/time information
- Currency conversions

**Key Features:**
- Multi-passenger file support (creates task per passenger)
- Multi-segment flights (connecting flights)
- Currency exchange detection (USD→KWD, AED→KWD)
- IATA airport code mapping (75+ airports)
- Tax breakdown parsing (YQ, YR, YX, etc.)

---

### 2. **PDF Documents (.pdf)**
**Processor:** AI-powered extraction (OpenAI/OpenWebUI)
**Method:** Vision API + RAG (Retrieval-Augmented Generation)

**Document Types:**
- Hotel booking confirmations
- Airline tickets
- Visa application forms (VFS Global)
- Insurance certificates
- Travel vouchers
- Invoice documents

**AI Process:**
1. Upload PDF to AI service
2. Wait for RAG indexing (OpenWebUI only)
3. Send extraction prompt with document context
4. Parse JSON response using TaskSchema
5. Validate and normalize data

---

### 3. **Passport Images** (JPG, PNG)
**Processor:** `ChatController::handleFileUpload()`
**Method:** GPT-4o Vision API

**Extracted Data:**
- Full name
- Passport number
- Nationality
- Date of birth
- Expiry date
- Issuing country
- MRZ (Machine Readable Zone) data

**Upload Path:** `storage/app/public/uploads/`

---

### 4. **Email Attachments**
**Processor:** `OpenAIServiceEmail.php`
**Method:** GPT-3.5-turbo text completion

**Use Cases:**
- Email content summarization
- Booking confirmation extraction
- Customer inquiry parsing
- Automated task creation from emails

**Route:** `POST /api/task-from-email`

---

### 5. **Text Files (.txt)**
**Processor:** `TextFileProcessor.php`
**Method:** Regex pattern extraction

**Format:** Kuwait Airways receipt text format
**Extracted Data:**
- Agent ID
- Customer name
- Ticket number (ETKT format)
- Booking reference (Amadeus)
- Flight details (outbound/return)
- Fare and total cost

---

### 6. **Excel/CSV Files** (.xlsx, .csv)
**Processors:** Import controllers
**Types:**
- Agent bulk import
- Company bulk import
- Client bulk import

**Routes:**
- `POST /agents/upload`
- `POST /companiesupload`
- `POST /clients/upload`

---

## Database Schema

### **Tasks Table** (Main Entity)
```sql
CREATE TABLE tasks (
    id BIGINT PRIMARY KEY,

    -- Relations
    client_id BIGINT,
    agent_id BIGINT,
    company_id BIGINT,
    supplier_id BIGINT,

    -- Core Info
    type ENUM('flight','hotel','visa','insurance','tour','cruise','car','rail','esim','event','lounge','ferry'),
    status ENUM('issued','reissued','void','refund','emd','on_hold','confirmed'),
    supplier_status VARCHAR,

    -- References
    ticket_number VARCHAR,
    original_ticket_number VARCHAR,
    reference VARCHAR,
    original_reference VARCHAR,
    gds_reference VARCHAR,
    airline_reference VARCHAR,

    -- Pricing (KWD)
    price DECIMAL(12,3),
    tax DECIMAL(12,3),
    surcharge DECIMAL(12,3),
    penalty_fee DECIMAL(12,3),
    total DECIMAL(12,3),

    -- Original Currency
    original_price DECIMAL(12,3),
    original_tax DECIMAL(12,3),
    original_surcharge DECIMAL(12,3),
    original_total DECIMAL(12,3),
    original_currency VARCHAR(3),
    exchange_currency VARCHAR(3),
    exchange_rate DECIMAL(10,8),

    -- Tax Details
    taxes_record TEXT,
    refund_charge DECIMAL(10,3),

    -- Metadata
    client_name VARCHAR,
    passenger_name VARCHAR,
    venue VARCHAR,
    additional_info TEXT,
    cancellation_policy TEXT,

    -- Dates
    issued_date DATETIME,
    refund_date DATETIME,
    expiry_date DATETIME,
    cancellation_deadline DATETIME,
    supplier_pay_date DATETIME,

    -- GDS Info
    created_by VARCHAR,
    issued_by VARCHAR,
    iata_number VARCHAR,

    -- System
    file_name VARCHAR,
    is_n8n_booking BOOLEAN,
    enabled BOOLEAN,
    deleted_at TIMESTAMP,

    INDEXES:
    - PRIMARY KEY (id)
    - INDEX (reference)
    - INDEX (ticket_number)
    - INDEX (gds_reference)
    - INDEX (client_id, agent_id, company_id, supplier_id)
    - INDEX (status, type)
    - INDEX (issued_date)
);
```

### **Task Flight Details** (Child Table)
```sql
CREATE TABLE task_flight_details (
    id BIGINT PRIMARY KEY,
    task_id BIGINT,

    -- Flight Info
    flight_number VARCHAR,
    airline_id BIGINT,  -- FK to airlines table
    class_type VARCHAR,
    equipment VARCHAR,  -- Aircraft type

    -- Departure
    departure_time DATETIME,
    airport_from VARCHAR(3),  -- FK to airports table
    country_id_from BIGINT,
    terminal_from VARCHAR,

    -- Arrival
    arrival_time DATETIME,
    airport_to VARCHAR(3),  -- FK to airports table
    country_id_to BIGINT,
    terminal_to VARCHAR,
    duration_time VARCHAR,

    -- Passenger Details
    seat_no VARCHAR,
    baggage_allowed VARCHAR,
    flight_meal VARCHAR,
    ticket_number VARCHAR,

    -- Pricing
    farebase DECIMAL(10,3),

    -- Flags
    is_ancillary BOOLEAN,

    FOREIGN KEY (task_id) REFERENCES tasks(id)
);
```

### **Other Task Detail Tables**
- `task_hotel_details` - Hotel bookings
- `task_insurance_details` - Travel insurance
- `task_visa_details` - Visa applications
- `task_emails` - Email correspondence

---

## Processing Workflows

### **AIR File Processing Workflow**
```
1. File Upload
   ↓
2. AirFileParser initialized with file path
   ↓
3. Parse task schema data (parseTaskSchema)
   │  ├─► Extract all passengers (extractAllPassengers)
   │  ├─► Extract ticket info per passenger
   │  ├─► Extract pricing data
   │  ├─► Extract flight segments (extractFlightSegments)
   │  └─► Build task array for each passenger
   ↓
4. Normalize data with TaskSchema::normalize()
   ↓
5. Normalize flight details with TaskFlightSchema::normalize()
   ↓
6. Create Task records in database
   ↓
7. Create TaskFlightDetail records (one per segment)
   ↓
8. Log processing results
   ↓
9. Move file to processed directory
```

### **AI Document Processing Workflow (OpenWebUI)**
```
1. Upload document to OpenWebUI
   ↓
2. Wait for RAG indexing (up to 2 minutes)
   ↓
3. Send extraction prompt with schema
   ↓
4. AI extracts structured JSON data
   ↓
5. Parse and validate response
   ↓
6. Normalize with TaskSchema
   ↓
7. Create database records
   ↓
8. Cleanup uploaded file from AI service
```

### **Passport Image Processing Workflow**
```
1. Upload image to storage/app/public/uploads/
   ↓
2. Call AIManager::extractPassportData()
   ↓
3. Encode image to base64
   ↓
4. Send to GPT-4o Vision API with prompt
   ↓
5. Parse passport data from response
   ↓
6. Return structured JSON
   │  ├─► Full name
   │  ├─► Passport number
   │  ├─► Nationality
   │  ├─► DOB, Expiry date
   │  └─► MRZ data
   ↓
7. Frontend populates form fields
```

---

## Task Types Supported

| Type | Schema | Details Table | AI Extraction |
|------|--------|---------------|---------------|
| **flight** | TaskSchema + TaskFlightSchema | task_flight_details | ✅ AIR + AI |
| **hotel** | TaskSchema + TaskHotelSchema | task_hotel_details | ✅ AI Only |
| **visa** | TaskSchema + TaskVisaSchema | task_visa_details | ✅ AI Only |
| **insurance** | TaskSchema + TaskInsuranceSchema | task_insurance_details | ✅ AI Only |
| **tour** | TaskSchema | tasks | ✅ AI Only |
| **cruise** | TaskSchema | tasks | ✅ AI Only |
| **car** | TaskSchema | tasks | ✅ AI Only |
| **rail** | TaskSchema | tasks | ✅ AI Only |
| **esim** | TaskSchema | tasks | ✅ AI Only |
| **event** | TaskSchema | tasks | ✅ AI Only |
| **lounge** | TaskSchema | tasks | ✅ AI Only |
| **ferry** | TaskSchema | tasks | ✅ AI Only |

---

## AI Provider Options

### **OpenAI (Cloud)**
- Models: GPT-3.5, GPT-4, GPT-4o (Vision)
- Cost: Per-token pricing
- Speed: Fast (< 5 seconds)
- Vision: ✅ Yes (GPT-4o)

### **OpenWebUI (Local)**
- Models: Llama 3.1, Mixtral, custom models
- Cost: Free (self-hosted)
- Speed: Medium (5-30 seconds + RAG)
- Vision: ❌ No (text only)
- **RAG:** ✅ Yes (document indexing)

### **AnythingLLM (Local)**
- Models: Various local LLMs
- Cost: Free (self-hosted)
- Speed: Medium
- Vision: Depends on model

---

## File Storage Structure

```
storage/app/
├── public/
│   ├── uploads/               # Passport images, temp uploads
│   └── passports/             # Processed passport files
│
├── {company_id}/
│   └── {supplier_id}/
│       ├── files_unprocessed/  # Pending AIR files
│       ├── files_processed/    # Successfully processed
│       └── files_failed/       # Failed processing
│
└── logs/
    └── air_processing.log     # Processing logs
```

---

## Key Controllers & Routes

### **Task Upload Routes**
```php
POST /tasks/upload                    # Upload and process AIR files
POST /tasks/agent/upload              # Agent-specific upload
POST /chat/upload                     # Passport image upload
POST /api/task-from-email             # Email-based task creation
```

### **Document Processing Routes**
```php
GET  /tasks/{id}                      # View task details
PUT  /tasks/update/{id}               # Update task
POST /tasks/bulk-update               # Bulk operations
DELETE /tasks/{id}                    # Delete task
GET  /tasks/pdf/flight/{taskId}       # Generate flight PDF
GET  /tasks/pdf/hotel/{taskId}        # Generate hotel PDF
GET  /export-tasks                    # Export to CSV/Excel
```

---

## Error Handling & Logging

### **FileProcessingLogger**
Dedicated logger for document processing:

```php
$logger = new FileProcessingLogger('air_processing');
$logger->fileProcessingStart($filename);
$logger->taskSaveError($taskId, $errorMessage, $taskData);
$logger->fileProcessingComplete($filename, $results);
```

**Log Channel:** `storage/logs/air_processing.log`

**Logged Events:**
- File processing start/complete
- Task save errors
- Batch processing events
- API call failures
- Validation errors

---

## Export Formats

### **AirFileService Export Methods**
```php
// Export processed data
$service->exportData($processedFiles, 'json', 'output.json');
$service->exportData($processedFiles, 'csv', 'output.csv');
$service->exportData($processedFiles, 'xml', 'output.xml');

// Summary statistics
$stats = $service->getSummaryStats($processedFiles);
// Returns: total_files, successful, failed, success_rate,
//          status_breakdown, total_amount, currency_breakdown
```

---

## Currency Handling

### **Supported Currencies**
- **Primary:** KWD (Kuwait Dinar)
- **Common:** USD, EUR, GBP, AED, SAR, QAR, BHD
- **Exchange Rate Storage:** Automatic conversion tracking

### **Currency Fields**
```json
{
  "exchange_currency": "KWD",      // Default/display currency
  "original_currency": "USD",      // Document's original currency
  "exchange_rate": 0.30655,        // Conversion rate

  "price": 30.655,                 // In KWD
  "original_price": 100.00,        // In USD

  "tax": 5.00,                     // In KWD
  "original_tax": 16.32,           // In USD

  "total": 35.655,                 // In KWD
  "original_total": 116.32,        // In USD

  "is_exchanged": true             // Currency conversion occurred
}
```

---

## Multi-Passenger Handling

For AIR files with multiple passengers:

1. **Single file** → **Multiple tasks**
   - One task per passenger
   - Same flight segments for all
   - Individual seat assignments
   - Separate ticket numbers

2. **Flight details shared**
   - Same departure/arrival times
   - Same airline/flight number
   - Different seat numbers

3. **Example:**
   ```
   AIR file: 3 passengers (KWI → SIN → KWI)

   Output:
   ├─► Task 1: Passenger SMITH/JOHN MR - Ticket T-K229-1111111111 - Seat 12A
   ├─► Task 2: Passenger SMITH/JANE MS - Ticket T-K229-2222222222 - Seat 12B
   └─► Task 3: Passenger SMITH/JACK MR - Ticket T-K229-3333333333 - Seat 12C

   All share same 2 flight segments:
   └─► KWI → SIN (Flight KU123)
   └─► SIN → KWI (Flight KU124)
   ```

---

## Performance Considerations

### **Batch Processing**
- Process multiple files in parallel
- Queue-based processing for large volumes
- Background jobs for heavy AI operations

### **Caching**
- Airline/airport data cached
- Supplier configurations cached
- Exchange rates cached

### **Optimization**
- Database indexes on reference fields
- Soft deletes for audit trail
- Eager loading for related models

---

## Summary

The Soud Laravel document processing system is a comprehensive, AI-powered automation platform that:

1. **Processes 6+ file types** (AIR, PDF, images, text, Excel, email)
2. **Supports 12+ task types** (flight, hotel, visa, insurance, etc.)
3. **Uses 3 AI providers** (OpenAI, OpenWebUI, AnythingLLM)
4. **Handles multi-currency** transactions with automatic conversion
5. **Supports multi-passenger** bookings with individual task creation
6. **Provides comprehensive logging** for debugging and auditing
7. **Exports in 3 formats** (JSON, CSV, XML)
8. **Integrates with accounting** (invoices, payments, journal entries)

The system is production-ready and actively used for travel agency operations.
