# Architecture

**Analysis Date:** 2026-02-12

## Pattern Overview

**Overall:** Multi-tenant SPA (Single Page Application) with layered MVC architecture augmented by specialized service layers for document processing, AI integration, and complex financial operations.

**Key Characteristics:**
- Laravel 11 backend with Blade templating and Livewire 3.5 components for frontend reactivity
- Centralized AI orchestration with pluggable provider pattern (OpenAI, OpenWebUI, AnythingLLM)
- Complex domain-driven services for document parsing, accounting, and payment processing
- Event-driven architecture for task state changes and financial operations
- Heavy use of Eloquent ORM with 112+ domain models organized by entity type
- GraphQL API layer via Lighthouse for complex query requirements
- Webhook handling for external payment gateway callbacks

## Layers

**HTTP Request Layer (Controllers):**
- Purpose: Handle incoming HTTP requests, parse input, authorize users, delegate to services
- Location: `app/Http/Controllers/`
- Contains: 50+ controllers for web routes + API routes, each handling specific domain (tasks, payments, invoices, etc.)
- Depends on: Services, Models, Requests, Auth middleware, Policies
- Used by: Routes (`routes/web.php`, `routes/api.php`), browser clients, mobile app

**Service Layer:**
- Purpose: Encapsulate complex business logic, coordinate multiple models, manage transactions
- Location: `app/Services/`
- Contains:
  - `AirFileParser.php` (1,689 lines) - Regex-based AIR file parsing for Amadeus GDS format
  - `AirFileService.php` - Batch processing, file uploads, exports
  - `FileProcessingLogger.php` - Structured logging for document processing
  - `HotelSearchService.php` (1,418 lines) - TBO and Magic Holiday hotel searches
  - `PaymentApplicationService.php` (786 lines) - Payment matching and application logic
  - `MagicHolidayService.php` (706 lines) - Magic Holiday API integration
  - `TaskRuleConfiguration.php` - Business rule engine for task validation
- Depends on: Models, AI layer, external APIs, database
- Used by: Controllers, Commands, Jobs, Events

**AI Integration Layer:**
- Purpose: Unified interface for multiple LLM providers with standardized responses
- Location: `app/AI/` (AIManager, Contracts, Services, Support)
- Contains:
  - `AIManager.php` - Factory pattern orchestrator for provider switching at runtime
  - `Services/OpenAIClient.php` (1,946 lines) - OpenAI API integration with vision capabilities
  - `Services/OpenWebUIClient.php` (761 lines) - Local LLM integration
  - `Services/AnythingLLMClient.php` - Alternative LLM provider
  - `Contracts/AIClientInterface.php` - Contract for all AI implementations
  - `Support/AIResponse.php` - Standardized response wrapper
- Depends on: HTTP client (Guzzle), file system
- Used by: `ProcessAirFiles` command, file processing services, AI extraction workflows

**Data Access Layer (Models & Eloquent):**
- Purpose: Database abstraction and relationship management
- Location: `app/Models/` (115 domain models)
- Contains: Eloquent models for:
  - Core entities: `Task.php`, `Company.php`, `Agent.php`, `Client.php`, `Branch.php`, `User.php`
  - Task details: `TaskFlightDetail.php`, `TaskHotelDetail.php`, `TaskInsuranceDetail.php`, `TaskVisaDetail.php`
  - Financial: `Invoice.php`, `Payment.php`, `JournalEntry.php`, `Account.php`, `Credit.php`
  - Transactions: `PaymentTransaction.php`, `BankPayment.php`, `ReceiptVoucher.php`
  - Suppliers: `Supplier.php`, `SupplierCompany.php`, `SupplierSurcharge.php`
  - Integrations: `TBO.php`, `HotelBooking.php`, `PaymentFatoorah.php`, `TapPayment.php`
- Depends on: Database schema (migrations)
- Used by: Controllers, Services, Commands, Jobs

**Schema & Validation Layer:**
- Purpose: Define expected data structures for AI extraction and validation rules
- Location: `app/Schema/` and `app/Http/Requests/`
- Contains:
  - `TaskSchema.php` (405 lines) - JSON schema for all task types (flight, hotel, visa, insurance, etc.)
  - `TaskFlightSchema.php` - Flight-specific extraction schema
  - `TaskHotelSchema.php` - Hotel-specific extraction schema
  - `TaskInsuranceSchema.php` - Insurance-specific extraction schema
  - `TaskVisaSchema.php` - Visa-specific extraction schema
  - Form requests in `Http/Requests/` for validation rules
- Depends on: Eloquent models
- Used by: Controllers (validation), AI extraction (prompt generation), API responses

**Command/Job Layer:**
- Purpose: Long-running background tasks and scheduled operations
- Location: `app/Console/Commands/` and `app/Jobs/`
- Contains:
  - `ProcessAirFiles.php` (1,721 lines) - Main document processor with batch/single mode, locking, debug exports
  - `ReadAndProcessEmails.php` - Email attachment processing
  - `ProcessExpiredConfirmedTasks.php` - Task lifecycle management
  - `GenerateMissingReceiptVouchers.php` - Accounting document generation
  - Queue jobs for async processing
- Depends on: Services, Models, AI layer
- Used by: Laravel scheduler (cron), queue workers, manual CLI invocation

**Event & Listener Layer:**
- Purpose: Decouple business logic through event-driven patterns
- Location: `app/Events/` and `app/Listeners/`
- Contains:
  - `CheckConfirmedOrIssuedTask.php` event → `ProcessTaskFinancials.php` listener
  - Task state change events triggering accounting operations
- Depends on: Models, Services
- Used by: Models (event firing), Service layer (event dispatching)

**View/Frontend Layer:**
- Purpose: Server-side rendered templates with Livewire reactivity and Blade components
- Location: `resources/views/` (45+ subdirectories by feature)
- Contains:
  - Layout templates: `layouts/app.blade.php`, `layouts/guest.blade.php`
  - Feature views: `tasks/`, `invoices/`, `payments/`, `accounting/`, `agents/`, etc.
  - Livewire components: `livewire/Chat.php`, `livewire/Notification.php`
  - Blade components: `components/` (custom form components, navigation, etc.)
- Depends on: Controller data, Blade directives, Alpine.js
- Used by: Route handlers for HTML responses

**GraphQL Layer:**
- Purpose: Complex data queries and nested filtering via standardized API
- Location: `app/GraphQL/` and `graphql/schema.graphql`
- Contains:
  - `GraphQL/Queries/SearchTBOHotelRooms.php` (865 lines) - Hotel search with async tokenization
  - `GraphQL/Mutations/` - Write operations
  - `graphql/schema.graphql` - Type definitions and queries
- Depends on: Lighthouse library, Eloquent models, hotel search services
- Used by: Mobile app, external clients via `/graphql` endpoint

**Webhook & Callback Layer:**
- Purpose: Receive and process external system callbacks
- Location: `app/Http/Webhooks/` and payment gateway controllers
- Contains:
  - `TaskWebhook.php` (780 lines) - General task webhooks
  - Payment gateway webhooks: MyFatoorah, Hesabe, Tap, N8n callbacks
  - Rate limiting and signature verification middleware
- Depends on: Models, Services, Logging
- Used by: External payment providers, N8n workflows

## Data Flow

**Document Processing Flow (AIR Files, PDFs, Passport Images):**

1. **Upload Phase**: File uploaded via web UI or placed in `storage/app/{company}/{supplier}/files_unprocessed/`
2. **Scanning**: `ProcessAirFiles` command scans directories (via scheduler every 5 minutes)
3. **Parsing Decision**:
   - `.air` → Direct regex parsing via `AirFileParser` (~3,000+ lines regex patterns)
   - `.pdf`/`.jpg`/`.png` → Sent to AI (`AIManager.processWithAiTool()`)
4. **AI Extraction**:
   - Prompt generated from `TaskSchema.php` with field descriptions
   - File sent to `AIManager` → selected provider (OpenAI/OpenWebUI/AnythingLLM)
   - AI returns JSON matching schema structure
5. **Task Creation**:
   - Extracted data validated against `TaskSchema`
   - Multiple tasks created for multi-passenger tickets
   - `Task` model with related detail models created (`TaskFlightDetail`, `TaskHotelDetail`, etc.)
6. **Accounting Integration**:
   - Event `CheckConfirmedOrIssuedTask` fired on task confirmation
   - Listener `ProcessTaskFinancials` creates journal entries
   - Chart of Accounts updated with supplier/expense mappings
7. **File Movement**:
   - Success: → `storage/app/{company}/{supplier}/files_processed/`
   - Error: → `storage/app/{company}/{supplier}/files_error/`
   - Debug exports (if enabled) → `storage/app/{company}/{supplier}/debug_exports/`
8. **Logging**: Structured logs to `storage/logs/air_processing.log` and database

**Invoice & Payment Flow:**

1. **Task Confirmation**: Agent confirms task as "issued" or "confirmed"
2. **Financial Recognition**:
   - Event fired triggering double-entry accounting
   - Journal entries created for revenue and supplier liability
   - Account balances updated via general ledger
3. **Invoice Creation**:
   - Controller creates invoice from task(s)
   - Invoice line items link to tasks
   - Totals calculated from task prices
4. **Payment Processing**:
   - Agent selects payment method (bank transfer, card, wallet, etc.)
   - Payment controller routes to appropriate gateway:
     - MyFatoorah (card) → Webhook callback
     - Hesabe (local card) → Webhook callback
     - Tap (international) → Webhook callback
     - Bank transfer → Manual reconciliation
5. **Payment Reconciliation**:
   - Webhook received and verified
   - Payment matched to invoice via reference number
   - Transaction created, journal entries finalized
   - Receipt voucher generated
6. **Refund/Credit Path**:
   - Refund request → Credit model created
   - Journal entries reversed
   - Payment refund initiated through gateway
   - Supplier payment calculated (net of charges)

**State Management:**

State flows through task status field:
- `pending` → `issued` → `confirmed` → (no further changes)
- `pending` → `reissued` (multi-leg changes) → `confirmed`
- Any → `refund` (partial or full)
- Any → `void` (cancellation)
- Special: `emd` (EMD surcharge tickets)

Original task linking preserves refund chain:
- `Task.original_task_id` points to first issued ticket
- Allows tracing refund/void/reissue lineage
- Financial reconciliation follows chain

## Key Abstractions

**Task Domain Model:**
- Purpose: Unified interface for 12 task types (flight, hotel, visa, insurance, tour, cruise, car, rail, esim, event, lounge, ferry)
- Examples: `Task.php`, `TaskFlightDetail.php`, `TaskHotelDetail.php`
- Pattern: Polymorphic model with type-specific detail tables; shared financial fields in main task
- Usage: Controllers query `Task::with(['flightDetail', 'hotelDetail', 'insuranceDetail'])`

**AI Provider Abstraction:**
- Purpose: Swap LLM providers without changing business logic
- Examples: `AIClientInterface`, `OpenAIClient`, `OpenWebUIClient`, `AnythingLLMClient`
- Pattern: Strategy pattern with factory (AIManager)
- Usage: `$aiManager->switchProvider('openwebui'); $result = $aiManager->chat($messages);`

**Account & COA Abstraction:**
- Purpose: Hierarchical chart of accounts for double-entry bookkeeping
- Examples: `Account.php` with parent/child relationships, `JournalEntry.php`
- Pattern: Tree structure with roots (Assets, Liabilities, Equity, Income, Expenses), branches, leaves
- Usage: Supplier transactions automatically find correct ledger accounts via rules

**Agent Role Hierarchy:**
- Purpose: Multi-tenant access control with role-based permissions
- Examples: `User.php` → `Role.php` → `Permission.php`, `Company` → `Branch` → `Agent`
- Pattern: Spatie Laravel-Permission package with custom hierarchy
- Usage: Gates and policies in `app/Policies/` enforce resource access

**Payment Gateway Adapter:**
- Purpose: Unified payment processing across 5+ gateways
- Examples: `MyFatoorahController.php`, `HesabePayment.php`, `TapPayment.php`
- Pattern: Each gateway has controller and model; webhooks route to payment service
- Usage: Single invoice can use different payment methods via adapter selection

## Entry Points

**Web Routes (Server-Rendered):**
- Location: `routes/web.php` (931 lines)
- Triggers: Browser requests to `/` or `/dashboard`
- Responsibilities:
  - Authentication check via `auth` middleware
  - 2FA verification via `Verify2FA` middleware
  - Role-based authorization via policies
  - Render Blade templates with Livewire components

**API Routes (JSON):**
- Location: `routes/api.php` (187 lines)
- Triggers: Mobile app, GraphQL client, external webhooks
- Responsibilities:
  - JSON response format
  - Authentication via sanctum tokens
  - Webhook signature verification
  - Rate limiting for public endpoints

**Console Commands (CLI):**
- Location: `app/Console/Commands/ProcessAirFiles.php` (main entry)
- Triggers:
  - Scheduled: `* * * * * cd /path && php artisan schedule:run` (every minute)
  - Manual: `php artisan app:process-files --batch --export-debug`
- Responsibilities:
  - Scan file directories
  - Process with locking (cache or DB lock)
  - Orchestrate AI extraction
  - Generate debug exports
  - Return exit codes for monitoring

**GraphQL Endpoint:**
- Location: `/graphql` via Lighthouse
- Triggers: POST requests with GraphQL queries
- Responsibilities:
  - Parse GraphQL query
  - Route to `GraphQL/Queries/` or `GraphQL/Mutations/`
  - Authorize via policies
  - Return JSON result

**Webhook Endpoints:**
- Locations:
  - `POST /api/payment/webhook-fatoorah`
  - `POST /api/payment/hesabe-webhook`
  - `POST /api/task-webhooks` (N8n callbacks)
- Triggers: External payment providers or N8n workflows
- Responsibilities:
  - Verify webhook signature
  - Parse payload
  - Update related models
  - Return 200 OK
  - Log events for debugging

## Error Handling

**Strategy:** Layered exception handling with logging at each layer.

**Patterns:**

**Controller Layer:**
```php
// routes/web.php and controllers use try/catch with Grace::handle()
try {
    $result = $this->service->processTask($task);
} catch (ValidationException $e) {
    return back()->withErrors($e->errors());
} catch (Exception $e) {
    Log::error('Task processing failed', ['error' => $e->getMessage()]);
    return redirect()->route('dashboard')->with('error', 'Operation failed');
}
```

**Service Layer:**
```php
// Services throw typed exceptions, caught by controllers
// FileProcessingLogger logs at warning/error/critical levels
$this->logger->error('Parse failed', ['file' => $file, 'reason' => $e->getMessage()]);
```

**AI Layer:**
```php
// AIManager catches all provider exceptions and returns standardized error array
// Support/AIResponse::error('Chat failed: ' . $e->getMessage())
// Prevents cascading failures in batch processing
```

**Command Layer:**
```php
// ProcessAirFiles uses cache/DB locking to prevent parallel runs
// Logs every step to file and database for audit trail
// Returns exit codes (0=success, 1=failure) for scheduler monitoring
```

## Cross-Cutting Concerns

**Logging:**
- Framework: Laravel's logging facade via Monolog
- Patterns:
  - `FileProcessingLogger` for document processing (custom handler)
  - `Log::info()`, `Log::warning()`, `Log::error()` in services
  - Stack trace logging for exceptions
  - All payment operations logged to database transactions table
- Files: `storage/logs/air_processing.log`, `storage/logs/laravel.log`

**Validation:**
- Input: Form requests in `app/Http/Requests/` define validation rules (Laravel validate())
- Schema: `TaskSchema.php` provides AI extraction contract
- Database: Foreign key constraints, unique indexes via migrations
- Business rules: `TaskRuleConfiguration.php` enforces supplier/account matching
- Example: `$request->validate(['email' => 'required|email|unique:users'])`

**Authentication:**
- Mechanism: Laravel Sanctum for API, session-based for web
- Two-Factor: Custom 2FA via `Verify2FA` middleware + Google Authenticator
- Role-based: `User.role_id` joined with permissions table
- Tenant isolation: `getCompanyId($user)` helper scopes all queries to company
- Policies: `app/Policies/` define resource authorization (create, edit, delete)
- Example: `Gate::authorize('viewAny', Task::class)` in controller

**Multi-tenancy:**
- Approach: Company-based isolation via company_id foreign key
- Hierarchy: Company → Branch → Agent → User
- Scoping: Helper functions like `getCompanyId($user)` automatically apply tenant filter
- Data isolation: All queries filtered by company automatically
- Payment flows: Tenant-aware (each company has separate accounts, invoices)

**Caching:**
- Framework: Laravel Cache facade with multiple backends (Redis, file, array)
- Patterns:
  - Command locking via `Cache::lock('process_air_files_lock', 600)` (10 min TTL)
  - Fallback to DB lock via `GET_LOCK()` if cache unavailable
  - Config cache for production: `php artisan config:cache`
  - Route cache for performance: `php artisan route:cache`
- Usage: Prevents concurrent document processing, improves auth performance

---

*Architecture analysis: 2026-02-12*
