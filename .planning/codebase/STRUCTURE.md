# Codebase Structure

**Analysis Date:** 2026-02-12

## Directory Layout

```
/home/soudshoja/soud-laravel/
├── app/                        # Application source code
│   ├── AI/                      # AI integration layer
│   │   ├── Contracts/           # AIClientInterface
│   │   ├── Services/            # OpenAI, OpenWebUI, AnythingLLM clients
│   │   ├── Support/             # AIResponse wrapper
│   │   └── AIManager.php        # Provider orchestrator
│   ├── Console/
│   │   ├── Commands/            # Artisan commands (ProcessAirFiles, etc.)
│   │   └── Kernel.php
│   ├── Enums/                   # PHP enums (TaskType, etc.)
│   ├── Events/                  # Event classes (CheckConfirmedOrIssuedTask)
│   ├── Exports/                 # Excel exports (Maatwebsite package)
│   ├── GraphQL/
│   │   ├── Mutations/           # GraphQL write operations
│   │   ├── Queries/             # GraphQL read operations (SearchTBOHotelRooms)
│   │   ├── Scalars/             # Custom GraphQL scalars
│   │   └── Lighthouse config
│   ├── Helper/                  # Global helper functions (helper.php)
│   ├── Http/
│   │   ├── Controllers/         # 50+ controllers by domain
│   │   │   ├── Admin/           # Admin-specific controllers
│   │   │   ├── Api/             # API controllers (DocumentProcessing, etc.)
│   │   │   ├── Auth/            # Authentication controllers
│   │   │   ├── Docs/            # Documentation controllers
│   │   │   └── [Domain]Controller.php  # e.g., TaskController, PaymentController
│   │   ├── Middleware/          # Request middleware
│   │   │   ├── Verify2FA.php
│   │   │   ├── VerifyWebhookSignature.php
│   │   │   ├── WebhookRateLimiter.php
│   │   │   └── AccountantView.php
│   │   ├── Requests/            # Form request validation classes
│   │   ├── Traits/              # Reusable mixins (Converter, CurrencyExchangeTrait, NotificationTrait)
│   │   ├── Webhooks/            # Webhook handlers (TaskWebhook.php, etc.)
│   │   └── [Controller].php     # e.g., Controller.php (base)
│   ├── Imports/                 # Excel imports (Maatwebsite package)
│   ├── Jobs/                    # Queueable jobs
│   ├── Listeners/               # Event listeners (ProcessTaskFinancials, etc.)
│   ├── Livewire/                # Livewire components (Chat, Notification)
│   ├── Mail/                    # Mailables (email templates)
│   ├── Models/                  # 115 Eloquent models
│   │   ├── Task.php             # Core task model
│   │   ├── Company.php
│   │   ├── Agent.php
│   │   ├── User.php
│   │   ├── Invoice.php
│   │   ├── Payment.php
│   │   ├── Account.php          # Chart of accounts
│   │   ├── JournalEntry.php     # Double-entry bookkeeping
│   │   ├── TaskFlightDetail.php
│   │   ├── TaskHotelDetail.php
│   │   ├── TaskInsuranceDetail.php
│   │   ├── TaskVisaDetail.php
│   │   └── [Domain]Model.php    # One per entity
│   ├── Policies/                # Authorization policies
│   ├── Providers/               # Service providers
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php
│   │   └── etc.
│   ├── Schema/                  # Data extraction schemas (JSON structure for AI)
│   │   ├── TaskSchema.php       # Main schema with all fields (405 lines)
│   │   ├── TaskFlightSchema.php
│   │   ├── TaskHotelSchema.php
│   │   ├── TaskInsuranceSchema.php
│   │   └── TaskVisaSchema.php
│   ├── Services/                # Business logic services
│   │   ├── AirFileParser.php    # Amadeus AIR parsing (1,689 lines, regex-based)
│   │   ├── AirFileService.php
│   │   ├── FileProcessingLogger.php
│   │   ├── HotelSearchService.php
│   │   ├── MagicHolidayService.php
│   │   ├── PaymentApplicationService.php
│   │   ├── TaskRuleConfiguration.php
│   │   └── [Domain]Service.php
│   ├── Support/                 # Support utilities
│   │   └── PaymentGateway/      # Payment gateway adapters
│   ├── View/
│   │   └── Components/          # Blade view components
│   └── [Folder]/                # Other PSR-4 namespaced directories
├── bootstrap/                   # Bootstrap files (autoload, cache)
├── config/                      # Configuration files
│   ├── ai.php                   # AI provider settings
│   ├── app.php
│   ├── database.php
│   ├── lighthouse.php           # GraphQL config
│   ├── permission.php           # Spatie permissions
│   ├── services.php             # External service credentials
│   ├── webhook.php              # Webhook signing secrets
│   └── [config].php
├── database/
│   ├── factories/               # Model factories for testing
│   ├── migrations/              # 367 migration files (schema versions)
│   ├── seeders/                 # Database seeders (MasterSeeder, RolePermissionSeeder, etc.)
│   └── database.sqlite          # SQLite DB (dev only)
├── docs/                        # Project documentation
│   ├── graphql/                 # GraphQL API documentation
│   └── [doc].md
├── graphql/                     # GraphQL schema definition
│   └── schema.graphql           # Lighthouse GraphQL schema (12KB)
├── n8n/                         # N8n workflow automation (separate project)
│   ├── workflows/               # Workflow JSON files
│   ├── nodes/                   # Custom node definitions
│   ├── credentials/             # API credential configs
│   └── README.md
├── public/                      # Web-accessible files
│   ├── css/                     # Compiled Tailwind CSS
│   ├── js/                      # Compiled JavaScript
│   ├── images/
│   ├── index.php                # Web entry point
│   └── favicon.ico
├── resources/
│   ├── css/
│   │   └── app.css              # Tailwind directives
│   ├── js/
│   │   ├── app.js               # Alpine.js initialization
│   │   └── bootstrap.js         # Echo/Livewire setup
│   └── views/                   # Blade templates (45+ subdirectories)
│       ├── accounting/
│       ├── agents/
│       ├── auth/                # Login, register, password reset
│       ├── clients/
│       ├── companies/
│       ├── components/          # Reusable Blade components
│       ├── invoices/
│       ├── layouts/
│       │   ├── app.blade.php    # Main authenticated layout
│       │   └── guest.blade.php  # Auth layout
│       ├── livewire/            # Livewire component views
│       ├── payments/
│       ├── tasks/
│       ├── transactions/
│       └── [feature]/
├── routes/                      # Route definitions
│   ├── web.php                  # Web routes (931 lines, main application)
│   ├── api.php                  # API routes (187 lines, mobile/external)
│   ├── console.php              # Console route definitions
│   └── auth.php                 # Breeze auth routes (75 lines)
├── storage/
│   ├── app/
│   │   ├── {company}/{supplier}/files_unprocessed/   # Input files
│   │   ├── {company}/{supplier}/files_processed/     # Success outputs
│   │   ├── {company}/{supplier}/files_error/         # Failed files
│   │   └── {company}/{supplier}/debug_exports/       # Debug Excel/CSV
│   ├── logs/
│   │   ├── air_processing.log   # Document processing logs
│   │   ├── laravel.log          # Application logs
│   │   └── ai.log               # AI integration logs
│   └── framework/               # Cache, sessions, views cache
├── tests/                       # Test files
│   ├── Feature/                 # Feature/integration tests
│   ├── Unit/                    # Unit tests
│   └── TestCase.php
├── .env                         # Environment variables (DO NOT COMMIT)
├── .env.example                 # Example env template
├── .editorconfig                # Editor settings
├── .cpanel.yml                  # cPanel deployment config
├── artisan                      # Artisan CLI entry point
├── composer.json                # PHP dependencies (Laravel 11, 35+ packages)
├── composer.lock                # Locked versions
├── package.json                 # Node.js dependencies (npm/webpack)
├── package-lock.json
├── phpunit.xml                  # PHPUnit test config
├── tailwind.config.js           # Tailwind CSS configuration
├── postcss.config.js            # PostCSS config for Tailwind
├── README.md                    # Basic readme
├── CLAUDE.md                    # Project instructions and conventions
└── [documentation].md           # Various docs (PROJECT_OVERVIEW.md, etc.)
```

## Directory Purposes

**app/AI/**
- Purpose: Unified interface for multiple LLM providers
- Contains: AIManager orchestrator, client implementations, response standards
- Key files: `AIManager.php` (factory pattern), `Services/OpenAIClient.php` (1,946 lines)

**app/Http/Controllers/**
- Purpose: Handle HTTP requests, validate input, call services, return responses
- Contains: 50+ controller classes organized by domain (Task, Payment, Invoice, etc.)
- Key files: `TaskController.php` (6,392 lines), `PaymentController.php` (6,828 lines), `InvoiceController.php` (5,309 lines)
- Pattern: Each controller handles one domain entity with CRUD + custom actions

**app/Models/**
- Purpose: Database abstraction and relationship definitions
- Contains: 115 Eloquent models covering all entities
- Key files: `Task.php` (core), `Company.php`, `Invoice.php`, `Account.php`, `JournalEntry.php`
- Pattern: Each model has relationships, fillable properties, casts, scopes, and business logic

**app/Services/**
- Purpose: Encapsulate complex business logic, database transactions, external API calls
- Contains: Domain-specific services organized by feature area
- Key files: `AirFileParser.php` (1,689 lines), `HotelSearchService.php` (1,418 lines), `PaymentApplicationService.php` (786 lines)
- Pattern: Services are stateless, accept parameters, return results; called from controllers/commands

**app/Console/Commands/**
- Purpose: Long-running background tasks and CLI utilities
- Contains: 20+ command classes
- Key files: `ProcessAirFiles.php` (1,721 lines - main processor), `ReadAndProcessEmails.php`
- Pattern: Each command is isolated, handles own locking/logging, used by scheduler or manual run

**app/Schema/**
- Purpose: Define expected data structures for AI extraction
- Contains: JSON schemas with field descriptions, types, examples for all task types
- Key files: `TaskSchema.php` (405 lines - master schema), type-specific schemas
- Pattern: Schemas used as prompt instructions and response contracts

**database/migrations/**
- Purpose: Version-controlled schema changes
- Contains: 367 migration files tracking database evolution
- Pattern: Timestamped files with up/down methods; alphabetically ordered for idempotency

**database/seeders/**
- Purpose: Populate database with initial/test data
- Contains: MasterSeeder orchestrates all others; entity-specific seeders
- Key files: `MasterSeeder.php`, `RolePermissionSeeder.php`, `TaskRuleSeeder.php`
- Pattern: Seeders are re-runnable; use firstOrCreate for idempotency

**resources/views/**
- Purpose: Server-rendered HTML templates using Blade
- Contains: 45+ subdirectories organized by feature (tasks, invoices, payments, etc.)
- Key files: `layouts/app.blade.php` (main layout), feature-specific views
- Pattern: Views mirror controller structure; components extracted to `components/`

**storage/app/{company}/{supplier}/**
- Purpose: File storage for document processing
- Contains: Input, output, error, debug directories per company/supplier pair
- Pattern: Automatic file movement: `files_unprocessed/` → `files_processed/` or `files_error/`
- Cleanup: Old files archived; `files_error/` retains failed attempts for debugging

**config/**
- Purpose: Application-wide configuration
- Contains: 20+ config files for services, databases, logging, queues, etc.
- Key files: `ai.php` (LLM providers), `lighthouse.php` (GraphQL), `webhook.php` (signing)
- Pattern: Read from `.env` via `env()` helper; cached in production

**routes/**
- Purpose: HTTP endpoint definitions
- Contains: Web routes (server-rendered), API routes (JSON), auth routes (Breeze scaffolding)
- Key files: `web.php` (931 lines - main UI), `api.php` (187 lines - mobile/external)
- Pattern: Routes grouped by middleware/prefix; names used for URL generation

## Key File Locations

**Entry Points:**
- `public/index.php`: Web server entry point (front controller)
- `artisan`: Artisan CLI entry point (commands, migrations, tinker)
- `routes/web.php`: Web route definitions (authenticated users)
- `routes/api.php`: API route definitions (mobile, webhooks)
- `app/Providers/AppServiceProvider.php`: Service container bootstrap

**Configuration:**
- `.env`: Environment variables (secrets, API keys, database credentials)
- `config/ai.php`: AI provider selection and credentials
- `config/database.php`: Database connections (MySQL, SQLite)
- `config/lighthouse.php`: GraphQL schema and resolver mappings
- `config/permission.php`: Spatie role/permission settings

**Core Logic:**
- `app/Services/AirFileParser.php`: Amadeus AIR file parsing (main document processor)
- `app/AI/AIManager.php`: LLM provider orchestration
- `app/Models/Task.php`: Core task domain model (70+ fields)
- `app/Schema/TaskSchema.php`: Data extraction contract (405 lines)
- `app/Console/Commands/ProcessAirFiles.php`: Batch document processing (1,721 lines)

**Testing:**
- `tests/Feature/`: Integration tests for features/workflows
- `tests/Unit/`: Unit tests for services/models
- `phpunit.xml`: Test configuration and paths
- `database/factories/`: Model factories for test data generation

**Documentation:**
- `CLAUDE.md`: Project instructions, key features, deployment
- `PROJECT_OVERVIEW.md`: System architecture overview
- `DOCUMENT_PROCESSING_DEEP_DIVE.md`: File processing detailed guide
- `docs/graphql/`: GraphQL API documentation
- `n8n/README.md`: N8n workflow automation guide

## Naming Conventions

**Files:**
- Controllers: `{Entity}Controller.php` (e.g., `TaskController.php`, `PaymentController.php`)
- Models: `{Entity}.php` (e.g., `Task.php`, `Invoice.php`)
- Migrations: `YYYY_MM_DD_HHMMSS_{action}_{table}.php` (e.g., `2025_03_17_161405_update_foreign_in_general_ledgers_table.php`)
- Commands: `{ActionName}Command.php` or `{action_name}` (e.g., `ProcessAirFiles.php`)
- Services: `{Entity}Service.php` (e.g., `AirFileService.php`, `HotelSearchService.php`)
- Traits: `{Concern}Trait.php` (e.g., `CurrencyExchangeTrait.php`, `NotificationTrait.php`)
- Events: `{Action}{Entity}.php` (e.g., `CheckConfirmedOrIssuedTask.php`)
- Listeners: `{Action}` (e.g., `ProcessTaskFinancials.php`)

**Classes:**
- Controllers: PascalCase, singular noun (e.g., `TaskController`)
- Models: PascalCase, singular noun (e.g., `Task`, `TaskFlightDetail`)
- Services: PascalCase, service suffix (e.g., `AirFileService`)
- Interfaces: PascalCase, Interface suffix (e.g., `AIClientInterface`)
- Traits: PascalCase, Trait suffix (e.g., `NotificationTrait`)
- Enums: PascalCase (e.g., `TaskType`)

**Methods:**
- Controllers: camelCase, verb+noun (e.g., `getTasks()`, `createTask()`, `storeTask()`)
- RESTful: `index`, `show`, `create`, `store`, `edit`, `update`, `destroy`
- Custom: `processFile()`, `handleWebhook()`, `syncWithSupplier()`

**Directories:**
- Plural for collections: `app/Models/`, `resources/views/`
- Feature-based: `resources/views/tasks/`, `resources/views/invoices/`
- Functional: `app/Http/Controllers/`, `app/Console/Commands/`

**Database:**
- Tables: snake_case, plural (e.g., `tasks`, `invoice_details`, `payment_methods`)
- Columns: snake_case (e.g., `created_at`, `user_id`, `original_ticket_number`)
- Foreign keys: `{entity}_id` (e.g., `task_id`, `supplier_id`, `company_id`)
- Pivot tables: `{entity1}_{entity2}` alphabetically (e.g., `agent_client`)

## Where to Add New Code

**New Feature (e.g., Hotel Booking Feature):**
- Controller: `app/Http/Controllers/HotelController.php`
- Model: `app/Models/HotelBooking.php`
- Service: `app/Services/HotelBookingService.php`
- Schema: `app/Schema/TaskHotelSchema.php` (if extraction needed)
- Routes: Add to `routes/web.php` under appropriate group
- Views: Create `resources/views/hotel-bookings/` subdirectory
- Tests: `tests/Feature/HotelBookingTest.php`
- Migration: `database/migrations/YYYY_MM_DD_HHMMSS_create_hotel_bookings_table.php`

**New API Endpoint (e.g., Mobile App Integration):**
- Controller: `app/Http/Controllers/Api/{Feature}Controller.php` (under Api subdirectory)
- Route: Add to `routes/api.php` in appropriate group/middleware
- Request validation: `app/Http/Requests/Api/{Feature}Request.php`
- Response: Use consistent JSON format with metadata
- Authentication: Sanctum token validation via middleware
- Tests: `tests/Feature/Api/{Feature}Test.php`

**New Service/Utility:**
- Location: `app/Services/{Name}Service.php`
- Pattern: Stateless class with public methods
- Dependencies: Injected via constructor
- Return: Structured array or object (not mixed types)
- Logging: Use `Log::info/warning/error()` for important steps
- Example: `PaymentApplicationService` matches payments to invoices

**New Model/Database Entity:**
- Model: `app/Models/{Entity}.php` with relationships
- Migration: `database/migrations/YYYY_MM_DD_HHMMSS_create_{table}_table.php`
- Factory: `database/factories/{Entity}Factory.php` (for testing)
- Policy: `app/Policies/{Entity}Policy.php` (for authorization)
- Seeder: Add to `database/seeders/MasterSeeder.php`
- Test: `tests/Feature/{Entity}Test.php`

**New Blade Component:**
- Location: `app/View/Components/{ComponentName}.php` + `resources/views/components/{component-name}.blade.php`
- Pattern: Single responsibility (form input, card, button, etc.)
- Props: Define public properties for component variables
- Usage: `<x-component-name :prop="value" />`

**New GraphQL Query/Mutation:**
- Query: `app/GraphQL/Queries/{QueryName}.php`
- Mutation: `app/GraphQL/Mutations/{MutationName}.php`
- Schema: Update `graphql/schema.graphql` with type definition
- Authorization: Use `Gate::authorize()` in resolver
- Resolver pattern: Return `Illuminate\Support\Collection`

**New Document Processing Type:**
- Schema: Create `app/Schema/Task{Type}Schema.php` (e.g., `TaskCarSchema.php`)
- Model: Create `app/Models/Task{Type}Detail.php`
- Migration: Add detail table with foreign key to tasks
- Parser: Extend parsing logic in `AirFileService` or AI extraction
- Controller: Update `TaskController.php` to handle new type
- Views: Create `resources/views/tasks/{type}/` views

## Special Directories

**storage/app/{company}/{supplier}/**
- Purpose: Document processing file system
- Generated: Yes, automatically on first file upload
- Committed: No, ignored via `.gitignore`
- Cleanup: Old files retained; manual cleanup recommended
- Subdirectories:
  - `files_unprocessed/`: Drop files here for processing
  - `files_processed/`: Successfully extracted files
  - `files_error/`: Failed files with error details
  - `debug_exports/`: Excel/CSV exports of extracted data (when `--export-debug` used)

**storage/logs/**
- Purpose: Application logging
- Generated: Yes, automatically on first log entry
- Committed: No, ignored via `.gitignore`
- Key files:
  - `air_processing.log`: Document processing events
  - `laravel.log`: Application errors and warnings
  - `ai.log`: AI provider calls and responses
- Retention: Configure in `config/logging.php` (default: 14 days)

**bootstrap/cache/**
- Purpose: Runtime cache files
- Generated: Yes, by commands like `php artisan config:cache`
- Committed: No, regenerated in production
- Files:
  - `config.php`: Cached configuration
  - `routes.php`: Cached route definitions
  - `services.php`: Cached service bindings

**public/css/, public/js/**
- Purpose: Compiled frontend assets
- Generated: Yes, by `npm run build` (webpack/Vite)
- Committed: No, regenerated on deployment
- Source: `resources/css/app.css` and `resources/js/app.js`
- Build commands:
  - Dev: `npm run dev` (watch mode)
  - Production: `npm run build` (minified)

**n8n/**
- Purpose: N8n workflow automation configurations
- Generated: Manual creation + export from N8n UI
- Committed: Yes, version control for workflows
- Structure:
  - `workflows/`: JSON workflow definitions
  - `nodes/`: Custom node implementations
  - `credentials/`: Encrypted credential references
- Deployment: Import via N8n UI or API

---

*Structure analysis: 2026-02-12*
