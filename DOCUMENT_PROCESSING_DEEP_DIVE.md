# Document Processing System - Deep Dive Guide

## 📚 Table of Contents
1. [System Overview](#system-overview)
2. [AIR File Processing (Detailed)](#air-file-processing-detailed)
3. [Email Processing](#email-processing)
4. [PDF & Passport Processing](#pdf--passport-processing)
5. [Testing the System](#testing-the-system)
6. [Adding New Document Types](#adding-new-document-types)
7. [Troubleshooting](#troubleshooting)

---

## System Overview

### **Processing Command**
```bash
php artisan app:process-files
```

### **Options:**
```bash
--batch           # Process files in batches (DEFAULT)
--single          # Process files one by one
--batch-size=10   # Files per batch (default: 10)
--export-debug    # Export parsed data to Excel/CSV for debugging
--test-export     # Test export functionality
```

### **Directory Structure**
```
storage/app/
└── {company_name}/
    └── {supplier_name}/
        ├── files_unprocessed/  # Drop AIR files here
        ├── files_processed/    # Successfully processed
        ├── files_error/        # Failed processing
        └── debug_exports/      # Debug Excel/CSV files (with --export-debug)
```

---

## AIR File Processing (Detailed)

### **Step 1: Understanding AIR File Format**

AIR files are **plain text files** from Amadeus GDS containing airline ticket data.

#### **Sample AIR File Structure:**
```
AIR-BLK1;IS;001
MUC1A 8DROXL0101;1234567;KWIKT2619;45678;KWIKT2619;23456789;42230215
T-K229-2833133219
A-KUWAIT AIRWAYS;KU
I-001;001SMITH/JOHN MR;
H-001;003OKWI;KUWAIT;DOH;DOHA;KU 1077 Y Y 30JUL0435 0605 30JUL;OK01;HK01;M;0;77W;;;30K;1;;ET;0130;N;351;
K-FKWD135.000    ;;;;;;;;;;;;KWD172.850    ;;;
KFTF; KWD24.000   YQ AC; KWD4.000    YR VB; KWD9.850   XT ;
S-01/B12F.N;/B11B.N
```

#### **Line-by-Line Breakdown:**

| Line | Format | Meaning | Example | Parser Method |
|------|--------|---------|---------|---------------|
| **AIR-BLK** | `AIR-BLK{n};{type};{count}` | File header | `AIR-BLK1;IS;001` | Status indicator |
| **MUC1A** | `MUC1A {pnr};...;{office};...` | GDS reference | `MUC1A 8DROXL` | `extractGdsReference()` |
| **T-K** | `T-K{airline}-{ticket}` | Ticket number | `T-K229-2833133219` | `extractTicketNumber()` |
| **A-** | `A-{airline_name};{code}` | Airline | `A-KUWAIT AIRWAYS;KU` | `extractAirlineName()` |
| **I-** | `I-{seq};{seq2}{name};` | Passenger | `I-001;001SMITH/JOHN MR;` | `extractClientName()` |
| **H-** | `H-{seg};{from};{city};{to};{city};{flight_info}` | Flight segment | See below | `extractFlightSegments()` |
| **K-** | `K-F{currency}{base}...{currency}{total}` | Pricing | `K-FKWD135.000...KWD172.850` | `extractPrice()` |
| **KFTF** | `KFTF; {currency}{amount} {code}...` | Taxes | `KFTF; KWD24.000 YQ AC` | `extractTax()` |
| **S-** | `S-{pax}/{seat}.{status}` | Seat assignment | `S-01/B12F.N` | `extractSeatNumber()` |

---

### **Step 2: Processing Flow Diagram**

```
┌─────────────────────────────────────────────────────────────────┐
│  ProcessAirFiles Command (app:process-files)                    │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 1. Scan Directory Structure                                     │
│    storage/app/{company}/{supplier}/files_unprocessed/          │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. Determine Processing Method                                  │
│    ┌─────────────────────────────────────────────────────┐     │
│    │ shouldUseAirFileParser()                            │     │
│    │ • Supplier = "Amadeus" AND file extension = .air    │     │
│    │ → Use AirFileParser                                 │     │
│    │                                                       │     │
│    │ • Otherwise                                          │     │
│    │ → Use AI-based processing (OpenAI/OpenWebUI)       │     │
│    └─────────────────────────────────────────────────────┘     │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ├─► AirFileParser Path
                         │   ↓
                         │   ┌──────────────────────────────────┐
                         │   │ AirFileParser::parseTaskSchema() │
                         │   └──────────────────────────────────┘
                         │   ↓
                         │   ┌──────────────────────────────────┐
                         │   │ Extract passenger data           │
                         │   │ • extractAllPassengers()         │
                         │   │ • extractTicketNumber()          │
                         │   │ • extractGdsReference()          │
                         │   └──────────────────────────────────┘
                         │   ↓
                         │   ┌──────────────────────────────────┐
                         │   │ Extract pricing data             │
                         │   │ • extractPrice()                 │
                         │   │ • extractTax()                   │
                         │   │ • extractTotal()                 │
                         │   │ • extractExchangeCurrency()      │
                         │   └──────────────────────────────────┘
                         │   ↓
                         │   ┌──────────────────────────────────┐
                         │   │ Extract flight segments          │
                         │   │ • extractFlightSegments()        │
                         │   │ • Parse H- lines                 │
                         │   │ • Extract airport codes          │
                         │   │ • Parse date/time formats        │
                         │   └──────────────────────────────────┘
                         │   ↓
                         │   Returns: Array of task data (1 per passenger)
                         │
                         └─► AI-based Path
                             ↓
                             ┌──────────────────────────────────┐
                             │ AIManager::processWithAiTool()   │
                             └──────────────────────────────────┘
                             ↓
                             ┌──────────────────────────────────┐
                             │ OpenWebUIClient::extractWithAI() │
                             │ OR                               │
                             │ OpenAIClient::extractDocument()  │
                             └──────────────────────────────────┘
                             ↓
                             Send TaskSchema to AI as prompt
                             ↓
                             AI returns JSON structured data
                             ↓
                             Returns: Extracted task data
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. Normalize Data                                               │
│    ┌─────────────────────────────────────────────────────┐     │
│    │ TaskSchema::normalize($taskData)                    │     │
│    │ • Validate all fields exist                         │     │
│    │ • Set defaults for missing fields                   │     │
│    │ • Convert data types                                │     │
│    └─────────────────────────────────────────────────────┘     │
│    ↓                                                             │
│    ┌─────────────────────────────────────────────────────┐     │
│    │ TaskFlightSchema::normalize($flightDetails)         │     │
│    │ • Normalize each flight segment                     │     │
│    │ • Validate airport codes                            │     │
│    │ • Convert datetime formats                          │     │
│    └─────────────────────────────────────────────────────┘     │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. Process Task Data                                            │
│    ┌─────────────────────────────────────────────────────┐     │
│    │ processTaskData()                                   │     │
│    │ • Find/create agent                                 │     │
│    │ • Find/create client                                │     │
│    │ • Handle refund/void/reissued logic                 │     │
│    │ • Link to original task (if applicable)             │     │
│    │ • Apply task rules                                  │     │
│    └─────────────────────────────────────────────────────┘     │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. Save to Database                                             │
│    ┌─────────────────────────────────────────────────────┐     │
│    │ TaskController::store()                             │     │
│    │ • Create Task record                                │     │
│    │ • Create TaskFlightDetail records (for flights)     │     │
│    │ • Create TaskHotelDetail records (for hotels)       │     │
│    │ • Link to invoices/payments if applicable           │     │
│    │ • Enable task (enabled = true)                      │     │
│    └─────────────────────────────────────────────────────┘     │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. Move File & Log Results                                      │
│    ┌─────────────────────────────────────────────────────┐     │
│    │ Success → files_processed/                          │     │
│    │ Failure → files_error/                              │     │
│    │ Log to storage/logs/air_processing.log              │     │
│    └─────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────────┘
```

---

### **Step 3: Real Example - Multi-Passenger Processing**

#### **Input: AIR File with 2 Passengers**
```
AIR-BLK1;IS;002
MUC1A 8DROXL0101;1234567;KWIKT2619;45678;KWIKT2619;23456789;42230215
T-K229-2833133219
T-K229-2833133220
I-001;001SMITH/JOHN MR;
I-002;002SMITH/JANE MS;
A-KUWAIT AIRWAYS;KU
H-001;003OKWI;KUWAIT;DOH;DOHA;KU 1077 Y Y 30JUL0435 0605 30JUL;OK01;HK01;M;0;77W;;;30K;1;;ET;0130;N;351;
K-FKWD135.000    ;;;;;;;;;;;;KWD172.850    ;;;
KFTF; KWD24.000   YQ AC; KWD4.000    YR VB; KWD9.850   XT ;
S-01/B12F.N
S-02/B12G.N
```

#### **Processing Steps:**

1. **AirFileParser reads file**
   ```php
   $parser = new AirFileParser($filePath);
   $tasksData = $parser->parseTaskSchema();
   // Returns: Array with 2 tasks (one per passenger)
   ```

2. **Extracted Data Structure:**
   ```json
   [
     {
       "client_name": "SMITH/JOHN MR",
       "ticket_number": "T-K229-2833133219",
       "reference": "2833133219",
       "gds_reference": "8DROXL",
       "status": "issued",
       "price": 135.000,
       "tax": 37.850,
       "total": 172.850,
       "currency": "KWD",
       "task_flight_details": [
         {
           "flight_number": "KU1077",
           "departure_time": "2025-07-30 04:35:00",
           "arrival_time": "2025-07-30 06:05:00",
           "airport_from": "KWI",
           "airport_to": "DOH",
           "seat_no": "12F"
         }
       ]
     },
     {
       "client_name": "SMITH/JANE MS",
       "ticket_number": "T-K229-2833133220",
       "reference": "2833133220",
       "gds_reference": "8DROXL",
       "status": "issued",
       "price": 135.000,
       "tax": 37.850,
       "total": 172.850,
       "currency": "KWD",
       "task_flight_details": [
         {
           "flight_number": "KU1077",
           "departure_time": "2025-07-30 04:35:00",
           "arrival_time": "2025-07-30 06:05:00",
           "airport_from": "KWI",
           "airport_to": "DOH",
           "seat_no": "12G"
         }
       ]
     }
   ]
   ```

3. **Database Inserts:**
   ```sql
   -- Task 1
   INSERT INTO tasks (client_name, ticket_number, reference, ...)
   VALUES ('SMITH/JOHN MR', 'T-K229-2833133219', '2833133219', ...);

   INSERT INTO task_flight_details (task_id, flight_number, seat_no, ...)
   VALUES (1, 'KU1077', '12F', ...);

   -- Task 2
   INSERT INTO tasks (client_name, ticket_number, reference, ...)
   VALUES ('SMITH/JANE MS', 'T-K229-2833133220', '2833133220', ...);

   INSERT INTO task_flight_details (task_id, flight_number, seat_no, ...)
   VALUES (2, 'KU1077', '12G', ...);
   ```

---

### **Step 4: Currency Conversion Handling**

#### **Example: USD → KWD Conversion**

**AIR File Line:**
```
K-FUSD100.00 ;KWD30.655 ;;;;;;;;;;;KWD35.655 ;0.30655 ;;
```

**Parsing Logic:**
```php
// AirFileParser::extractPrice()
$match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');

// Extracted:
// $match[1] = 'USD'      (original_currency)
// $match[2] = '100.00'   (original_price)
// $match[3] = 'KWD'      (exchange_currency - intermediate)
// $match[4] = '30.655'   (price - exchanged base fare)
// $match[5] = 'KWD'      (exchange_currency - final)
// $match[6] = '35.655'   (total - with taxes)
```

**Result:**
```json
{
  "original_currency": "USD",
  "original_price": 100.00,
  "original_total": 116.32,
  "exchange_currency": "KWD",
  "price": 30.655,
  "tax": 5.000,
  "total": 35.655,
  "exchange_rate": 0.30655,
  "is_exchanged": true
}
```

---

### **Step 5: Ticket Status Types**

| Status | AIR Indicator | Description | Original Task Link |
|--------|---------------|-------------|-------------------|
| **issued** | Default | New ticket issued | No |
| **reissued** | `FO{airline}-{ticket}` | Ticket exchanged/changed | Yes (original ticket) |
| **refund** | `AIR-BLK1;RF;` | Ticket refunded | Yes (original ticket) |
| **void** | `;VOID{date};` | Ticket voided (cancelled before use) | Yes (original ticket) |
| **emd** | `EMD{digits};` | Electronic Miscellaneous Document | Yes (if linked) |
| **on_hold** | N/A | Hotel booking pending confirmation | No |
| **confirmed** | N/A | Hotel booking confirmed, payment pending | No |

#### **Refund/Void Processing Logic:**
```php
// Find original task
$originalTask = Task::where('reference', $taskData['original_reference'])
    ->orWhere('reference', $taskData['reference'])
    ->whereIn('status', ['issued', 'reissued'])
    ->first();

if ($originalTask) {
    // Link to original
    $taskData['original_task_id'] = $originalTask->id;

    // Copy agent/client from original
    $taskData['agent_id'] = $originalTask->agent_id;
    $taskData['client_id'] = $originalTask->client_id;

    // For refund/void: Copy flight details from original
    if (in_array($taskData['status'], ['refund', 'void'])) {
        $flightDetails = TaskFlightDetail::where('task_id', $originalTask->id)->get();
        $taskData['task_flight_details'] = $flightDetails->toArray();
    }
}
```

---

### **Step 6: Agent Matching Logic**

**Find agent by Amadeus ID, name, or email:**

```php
protected function findAgent($amadeusId, $name, $email, $companyId): ?Agent
{
    $agentQuery = Agent::query();

    // Priority 1: Match by Amadeus ID
    if ($amadeusId) {
        $query->where('amadeus_id', 'like', $amadeusId);
    }

    // Priority 2: Match by name (fuzzy match)
    if ($name) {
        $words = explode(' ', $name);
        foreach ($words as $word) {
            $query->where('name', 'like', '%' . $word . '%');
        }
    }

    // Priority 3: Match by email
    if ($email) {
        $query->orWhere('email', 'like', $email);
    }

    // Filter by company
    return $agentQuery
        ->whereHas('branch', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })
        ->first();
}
```

**Example:**
```
AIR File: C-7906/ 0070MBAS-0002ABAS-I-0
           └─ Extract: 0002AB (agent_amadeus_id)

Agent Record: amadeus_id = '0002AB'
              ↓
              Match found!
```

---

## Email Processing

### **Command**
```bash
php artisan emails:process
```

### **Flow Diagram**
```
┌──────────────────────────────────────────────────┐
│ ReadAndProcessEmails Command                     │
└────────────────────┬─────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────┐
│ Connect to Gmail via IMAP                        │
│ • Account: default (from config/imap.php)       │
│ • Labels: ['magic', 'tbo', 'webbeds']           │
└────────────────────┬─────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────┐
│ For each email in label:                         │
│ 1. Get email ID (unique identifier)             │
│ 2. Check if already processed                   │
│ 3. Extract text body                            │
└────────────────────┬─────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────┐
│ OpenAIServiceEmail::extractHotelData()           │
│ • Send email text to GPT-3.5-turbo              │
│ • Prompt includes TaskSchema definition         │
│ • AI extracts structured data                   │
└────────────────────┬─────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────┐
│ Parse AI Response                                 │
│ • Hotel name, check-in/out dates                │
│ • Client name, reference                        │
│ • Pricing information                           │
└────────────────────┬─────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────┐
│ Save to task_emails table                        │
│ • Mark as 'pending' status                      │
│ • Link to supplier if matched                   │
└────────────────────┬─────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────┐
│ Admin reviews and approves                       │
│ → Creates Task from task_emails record          │
└──────────────────────────────────────────────────┘
```

### **Example Email Processing**

**Input Email (Magic Holiday):**
```
From: reservations@magicholiday.com
Subject: Booking Confirmation - Reference MH123456

Dear Agent,

Your hotel booking has been confirmed:

Guest Name: Ahmed Al-Salem
Hotel: Hilton Kuwait Resort
Check-in: 2025-08-15
Check-out: 2025-08-20
Rooms: 2 Deluxe Rooms
Total: KWD 450.00

Booking Reference: MH123456
Confirmation Code: HIL-789-XYZ

Best regards,
Magic Holiday Team
```

**AI Extraction Request:**
```php
$messages = [
    [
        'role' => 'system',
        'content' => 'Extract hotel booking data from the email. Return JSON with fields: hotel_name, client_name, reference, check_in_date, check_out_date, price, currency'
    ],
    [
        'role' => 'user',
        'content' => $emailText
    ]
];

$response = $openAIService->getChatResponse($messages);
```

**AI Response:**
```json
{
  "data": {
    "type": "hotel",
    "hotel_name": "Hilton Kuwait Resort",
    "client_name": "Ahmed Al-Salem",
    "reference": "MH123456",
    "check_in_date": "2025-08-15",
    "check_out_date": "2025-08-20",
    "price": 450.00,
    "currency": "KWD",
    "status": "confirmed",
    "supplier_name": "Magic Holiday"
  }
}
```

**Database Insert:**
```sql
INSERT INTO task_emails (
    email_id,
    client_name,
    type,
    status,
    reference,
    vendor_name,
    destination,
    created_at
) VALUES (
    '<email-id@gmail.com>',
    'Ahmed Al-Salem',
    'hotel',
    'pending',
    'MH123456',
    'Magic Holiday',
    'Kuwait',
    NOW()
);
```

---

## PDF & Passport Processing

### **Passport Image Processing**

**Endpoint:** `POST /chat/upload`

**Flow:**
```
User uploads passport image (JPG/PNG)
    ↓
ChatController::handleFileUpload()
    ↓
Store in storage/app/public/uploads/
    ↓
AIManager::extractPassportData()
    ↓
GPT-4o Vision API
    ↓
Return structured JSON:
{
  "full_name": "AHMED MOHAMMED AL-SALEM",
  "passport_number": "N12345678",
  "nationality": "Kuwait",
  "date_of_birth": "1985-03-15",
  "expiry_date": "2030-03-14",
  "issuing_country": "Kuwait",
  "mrz_line1": "P<KWTALSALEM<<AHMED<MOHAMMED<<<<<<<<<<<<<<<",
  "mrz_line2": "N123456788KWT8503153M3003149<<<<<<<<<<<<<<<4"
}
```

**Frontend Auto-Fill:**
```javascript
fetch('/chat/upload', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        document.getElementById('full_name').value = data.data.full_name;
        document.getElementById('passport_number').value = data.data.passport_number;
        document.getElementById('nationality').value = data.data.nationality;
        document.getElementById('dob').value = data.data.date_of_birth;
        document.getElementById('expiry').value = data.data.expiry_date;
    }
});
```

---

## Testing the System

### **1. Test AIR File Processing**

**Create test AIR file:**
```bash
mkdir -p storage/app/test_company/amadeus/files_unprocessed

cat > storage/app/test_company/amadeus/files_unprocessed/test_ticket.air << 'EOF'
AIR-BLK1;IS;001
MUC1A 8DROXL0101;1234567;KWIKT2619;45678;KWIKT2619;23456789;42230215
T-K229-2833133219
I-001;001TESTUSER/JOHN MR;
A-KUWAIT AIRWAYS;KU
H-001;003OKWI;KUWAIT;DOH;DOHA;KU 1077 Y Y 30JUL0435 0605 30JUL;OK01;HK01;M;0;77W;;;30K;1;;ET;0130;N;351;
K-FKWD100.000    ;;;;;;;;;;;;KWD130.000    ;;;
KFTF; KWD20.000   YQ AC; KWD10.000   XT ;
S-01/B12F.N
EOF
```

**Run processor:**
```bash
php artisan app:process-files --export-debug
```

**Check output:**
```bash
# View logs
tail -f storage/logs/air_processing.log

# Check processed file
ls -la storage/app/test_company/amadeus/files_processed/

# View debug export (if --export-debug used)
ls -la storage/app/test_company/amadeus/debug_exports/
```

---

### **2. Test with Export Debug Mode**

```bash
# Process with debug exports
php artisan app:process-files --export-debug

# Check exports
cd storage/app/{company}/{supplier}/debug_exports/

# Files created:
# - parsed_data_{timestamp}_file.xlsx       # Main data
# - task_save_summary_{timestamp}.xlsx      # Save results
```

**Excel Export Structure:**

| source_file | item_index | client_name | ticket_number | price | tax | total | currency | status |
|-------------|------------|-------------|---------------|-------|-----|-------|----------|--------|
| test_ticket.air | 0 | TESTUSER/JOHN MR | T-K229-2833133219 | 100.00 | 30.00 | 130.00 | KWD | issued |

---

### **3. Test Batch Processing**

```bash
# Create multiple test files
for i in {1..5}; do
    cp storage/app/test_company/amadeus/files_unprocessed/test_ticket.air \
       storage/app/test_company/amadeus/files_unprocessed/test_ticket_$i.air
done

# Process in batches of 2
php artisan app:process-files --batch --batch-size=2

# Output:
# Processing batch 1/3 (2 files)
# Processing batch 2/3 (2 files)
# Processing batch 3/3 (1 file)
```

---

### **4. Test Email Processing**

```bash
# Configure IMAP in config/imap.php
# Then run:
php artisan emails:process

# Check results:
mysql -u root -p -e "SELECT * FROM task_emails ORDER BY created_at DESC LIMIT 10"
```

---

## Adding New Document Types

### **Example: Add Insurance Certificate Processing**

**Step 1: Create Schema**
```php
// app/Schema/TaskInsuranceSchema.php
class TaskInsuranceSchema
{
    public static function getSchema()
    {
        return [
            'insurance_type' => [
                'type' => 'string',
                'desc' => 'Type of insurance (Travel, Health, etc.)',
                'example' => 'Travel Insurance',
            ],
            'policy_number' => [
                'type' => 'string',
                'desc' => 'Insurance policy number',
                'example' => 'INS-12345',
            ],
            'coverage_amount' => [
                'type' => 'float',
                'desc' => 'Coverage amount',
                'example' => 5000.00,
            ],
            'start_date' => [
                'type' => 'datetime',
                'desc' => 'Coverage start date',
                'example' => '2025-08-01',
            ],
            'end_date' => [
                'type' => 'datetime',
                'desc' => 'Coverage end date',
                'example' => '2025-08-31',
            ],
        ];
    }

    public static function normalize(array $input)
    {
        // Same pattern as TaskFlightSchema
    }
}
```

**Step 2: Add to TaskSchema**
```php
// In TaskSchema::getSchema()
'task_insurance_details' => [
    'type' => 'object',
    'desc' => 'Insurance details associated with the task',
    'example' => TaskInsuranceSchema::example(),
],
```

**Step 3: Create Migration**
```php
php artisan make:migration create_task_insurance_details_table

// In migration:
Schema::create('task_insurance_details', function (Blueprint $table) {
    $table->id();
    $table->foreignId('task_id')->constrained()->onDelete('cascade');
    $table->string('insurance_type');
    $table->string('policy_number');
    $table->decimal('coverage_amount', 12, 3);
    $table->date('start_date');
    $table->date('end_date');
    $table->timestamps();
});
```

**Step 4: Create Model**
```php
php artisan make:model TaskInsuranceDetail

// In model:
class TaskInsuranceDetail extends Model
{
    protected $fillable = [
        'task_id', 'insurance_type', 'policy_number',
        'coverage_amount', 'start_date', 'end_date'
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
```

**Step 5: Update TaskController::store()**
```php
// In TaskController::store()
if (isset($taskData['task_insurance_details'])) {
    foreach ($taskData['task_insurance_details'] as $insuranceData) {
        TaskInsuranceDetail::create([
            'task_id' => $task->id,
            ...$insuranceData
        ]);
    }
}
```

**Step 6: Create Parser (Optional)**
```php
// app/Services/InsuranceCertificateParser.php
class InsuranceCertificateParser
{
    public function parseInsuranceCertificate($filePath)
    {
        // Parse PDF or text format insurance certificates
        // Return normalized data matching TaskInsuranceSchema
    }
}
```

**Step 7: Test**
```bash
# Create test insurance PDF
# Process via AI
php artisan app:process-files

# Or process via API
curl -X POST http://localhost/api/tasks \
  -H "Content-Type: application/json" \
  -d '{
    "type": "insurance",
    "task_insurance_details": {
      "insurance_type": "Travel Insurance",
      "policy_number": "INS-12345",
      "coverage_amount": 5000.00,
      "start_date": "2025-08-01",
      "end_date": "2025-08-31"
    }
  }'
```

---

## Troubleshooting

### **Common Issues**

#### **1. AIR File Not Processing**

**Symptom:** File stays in `files_unprocessed/`

**Checks:**
```bash
# 1. Check file permissions
ls -la storage/app/{company}/{supplier}/files_unprocessed/

# 2. Check logs
tail -f storage/logs/air_processing.log

# 3. Verify supplier is "Amadeus"
mysql -e "SELECT * FROM suppliers WHERE name = 'Amadeus'"

# 4. Verify company-supplier relationship
mysql -e "SELECT * FROM company_supplier WHERE supplier_id = X"

# 5. Test parser directly
php artisan tinker
>>> $parser = new \App\Services\AirFileParser('/path/to/file.air');
>>> $data = $parser->parseTaskSchema();
>>> print_r($data);
```

#### **2. Agent Not Found**

**Symptom:** Task created with `enabled = false`

**Solution:**
```bash
# Check agent exists
mysql -e "SELECT * FROM agents WHERE amadeus_id LIKE '%{id}%'"

# Or by name
mysql -e "SELECT * FROM agents WHERE name LIKE '%{name}%'"

# Create agent if missing
php artisan tinker
>>> Agent::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'amadeus_id' => '0002AB',
    'branch_id' => 1
]);
```

#### **3. Task Already Exists (Duplicate)**

**Symptom:** Error "Task already exists"

**Check:**
```sql
SELECT * FROM tasks
WHERE reference = '{reference}'
AND supplier_id = {supplier_id}
ORDER BY created_at DESC;
```

**Fix:**
```sql
-- If truly duplicate, file was already processed
-- Check files_processed directory

-- If not duplicate, check unique constraint:
SHOW CREATE TABLE tasks;

-- May need to adjust reference or ticket_number
```

#### **4. Flight Details Missing**

**Symptom:** Task created but no flight details

**Checks:**
```bash
# 1. Verify H- lines in AIR file
cat storage/app/.../file.air | grep "^H-"

# 2. Check parser output
php artisan tinker
>>> $parser = new \App\Services\AirFileParser('/path/to/file.air');
>>> $data = $parser->parseTaskSchema();
>>> print_r($data[0]['task_flight_details']);

# 3. Check database
mysql -e "SELECT * FROM task_flight_details WHERE task_id = X"
```

#### **5. AI Processing Fails**

**Symptom:** File moved to `files_error/`

**Checks:**
```bash
# 1. Check AI provider configuration
cat .env | grep -E "AI_PROVIDER|OPENAI|OPENWEBUI"

# 2. Test AI connection
php artisan tinker
>>> $ai = app(\App\AI\AIManager::class);
>>> $result = $ai->chat([['role' => 'user', 'content' => 'test']]);
>>> print_r($result);

# 3. Check logs
tail -f storage/logs/ai.log
```

---

## Performance Optimization

### **Batch Processing**
```bash
# Process 10 files at a time
php artisan app:process-files --batch --batch-size=10

# Monitor memory usage
watch -n 1 'ps aux | grep php'
```

### **Database Indexes**
```sql
-- Add indexes for faster lookups
CREATE INDEX idx_reference ON tasks(reference);
CREATE INDEX idx_ticket_number ON tasks(ticket_number);
CREATE INDEX idx_gds_reference ON tasks(gds_reference);
CREATE INDEX idx_agent_amadeus ON agents(amadeus_id);
```

### **Queue Processing (Advanced)**
```php
// Dispatch to queue for async processing
dispatch(new ProcessAirFile($filePath, $companyId, $supplierId));
```

---

## Summary

The document processing system is production-ready and handles:

✅ **6+ file types** (AIR, PDF, images, text, Excel, email)
✅ **Multi-passenger bookings** (1 file → N tasks)
✅ **Currency conversion** (automatic rate tracking)
✅ **Ticket lifecycle** (issued → reissued → refund/void)
✅ **AI-powered extraction** (3 providers: OpenAI, OpenWebUI, AnythingLLM)
✅ **Batch processing** (configurable batch sizes)
✅ **Debug exports** (Excel/CSV for validation)
✅ **Comprehensive logging** (air_processing.log)

**Next Steps:**
1. Test with your actual AIR files
2. Review debug exports to validate accuracy
3. Tune agent matching logic for your agency
4. Add new document types as needed
5. Set up automated scheduling (cron jobs)
