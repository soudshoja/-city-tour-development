# Soud Laravel Application - Project Overview

## Project Summary

Soud Laravel is a comprehensive Laravel 11-based travel agency management system that provides multi-tenant functionality for managing travel operations, agents, clients, and bookings. It features a full-featured accounting system with multiple payment gateways, AI-powered document processing, and chat capabilities.

## Technology Stack

### Core Framework
- **Laravel 11** - Latest version with modern features
- **PHP** - Backend language
- **MySQL** - Primary database (two databases configured)

### Frontend
- **Blade Templates** - Laravel's templating engine
- **Livewire** - Real-time frontend components
- **Alpine.js** - Lightweight JavaScript framework
- **Tailwind CSS** - Utility-first CSS framework
- **Heroicons** - Icon library

### Key Services & Integrations
- **OpenAI Integration** - AI chat and document processing
- **AnythingLLM** - Local AI workspace
- **OpenWebUI** - Web-based AI interface
- **WhatsApp Business API** - Messaging integration
- **MyFatoorah** - Payment gateway (Kuwait)
- **TBO Holidays** - Travel booking API
- **Magic Holiday** - Alternative travel API
- **Knet** - Kuwait payment gateway
- **uPayment** - Payment processing
- **Hesabe** - Financial transaction system
- **Resend** - Email service
- **AWS SES** - Email delivery
- **Postmark** - Email service
- **N8n** - Workflow automation
- **IATA** - Airline data
- **ConvertAPI** - Document conversion

### Additional Features
- **Multi-tenancy** - Support for multiple travel agencies
- **Two-Factor Authentication** - Security feature using Google Authenticator
- **Role-based access control** - RBAC system
- **API-based architecture** - RESTful API endpoints
- **GraphQL queries & mutations** - Type-safe data access

## Architecture

### Directory Structure

```
app/
├── AI/                      # AI Integration Layer
│   ├── Contracts/
│   │   ├── AIClientInterface.php
│   │   └── WorkspaceAIInterface.php
│   ├── Services/
│   │   ├── OpenAIClient.php
│   │   ├── AnythingLLMClient.php
│   │   └── OpenWebUIClient.php
│   ├── Support/
│   │   └── AIResponse.php
│   └── AIManager.php        # Central AI orchestration
├── Enums/                   # PHP Enumerations
├── Events/                  # Laravel events
├── Exports/                 # Data export functionality
├── GraphQL/
│   ├── Mutations/           # GraphQL mutations
│   ├── Queries/             # GraphQL queries
│   └── Scalars/             # Custom GraphQL types
├── Helper/                  # Helper classes
├── Http/
│   ├── Controllers/
│   │   ├── AccountingController.php
│   │   ├── AgentController.php
│   │   ├── BankPaymentController.php
│   │   ├── ChatController.php
│   │   ├── ClientController.php
│   │   ├── CompanyController.php
│   │   ├── CreditController.php
│   │   ├── DashboardController.php
│   │   ├── ExportController.php
│   │   ├── HotelController.php
│   │   ├── InvoiceController.php
│   │   ├── JournalEntryController.php
│   │   ├── MobileController.php
│   │   ├── MyFatoorahController.php
│   │   ├── OpenAiController.php
│   │   ├── ReceiptVoucherController.php
│   │   ├── ReportController.php
│   │   ├── ResayilController.php
│   │   ├── SettingController.php
│   │   ├── SupplierCompanyController.php
│   │   ├── SupplierCredentialController.php
│   │   ├── TBOController.php
│   │   ├── WhatsappController.php
│   │   └── ...
│   ├── Requests/            # Form validation
│   ├── Middleware/          # Request middleware
│   └── Traits/              # Reusable code traits
├── Jobs/                    # Background jobs
├── Listeners/               # Event listeners
├── Mail/                    # Email templates
├── Models/                  # Eloquent models
├── Providers/               # Laravel service providers
└── Services/                # Application services
    ├── AirFileParser.php
    ├── AirFileService.php
    ├── ChargeService.php
    ├── EncryptionService.php
    ├── FileProcessingLogger.php
    ├── GatewayConfigService.php
    ├── HotelSearchService.php
    ├── IataEasyPayService.php
    ├── LoggingHelper.php
    ├── MagicHolidayService.php
    ├── OpenAIServiceEmail.php
    ├── PaymentApplicationService.php
    ├── PaymentReceiptService.php
    ├── TBOHolidayService.php
    ├── TaskRuleConfiguration.php
    └── ...
```

## Core Features

### 1. AI Integration
- **Multiple AI Providers**: OpenAI, AnythingLLM, OpenWebUI
- **Chat Completions**: GPT-3.5, GPT-4.1 support
- **Document Processing**: PDF, AIR files, passport data extraction
- **Batch Processing**: Multiple file handling
- **AI Response Standardization**: Consistent API responses

### 2. Chat System
- Real-time chat interface
- WhatsApp integration
- Conversation tracking
- Message history

### 3. Payment Gateways
- **MyFatoorah** - Kuwait payment gateway
- **Knet** - Kuwait debit/credit cards
- **uPayment** - Multi-currency payments
- **Hesabe** - Financial transactions
- **Tap** - Payment processing
- **Bank Payments** - Manual payment processing

### 4. Travel Services
- **TBO Holidays** - Official travel booking API
- **Magic Holiday** - Alternative booking source
- **Hotel Search** - Multiple hotel search capabilities
- **Airline Management** - Airline data
- **Airport Management** - Airport data

### 5. Accounting System
- **Chart of Accounts** - Complete COA structure
- **Journal Entries** - Double-entry bookkeeping
- **Invoice Management** - Create, view, manage invoices
- **Payment Receipts** - Receipt voucher handling
- **Credit Management** - Credit tracking and management
- **Bank Payments** - Bank transaction tracking
- **Reports** - Financial reporting

### 6. User Management
- **Multi-tenancy** - Per-tenant user management
- **Role-Based Access** - RBAC with permissions
- **Agent Management** - Agent registration and management
- **Client Management** - Client registration and tracking
- **Company Management** - Company hierarchy

### 7. Document Processing
- **File Upload** - Multiple file types
- **Document Parsing** - AIR, PDF files
- **Passport Data Extraction** - AI-powered extraction
- **Batch File Processing** - Multiple files at once

### 8. Notifications
- Email notifications
- WhatsApp notifications
- Push notifications
- In-app notifications

### 9. Reports
- Financial reports
- Agent performance reports
- Client activity reports
- Payment reports
- Tax reports

## Database Structure

### Primary Database (laravel_testing)
- Users and authentication
- Agent management
- Client management
- Company structure
- Travel services
- Payment transactions
- Accounting records
- Chat messages
- Document files

### Map Database (map_data_citytour)
- Location data
- Map coordinates
- Geographic information

## API Structure

### GraphQL Endpoints
- Custom queries and mutations
- Type-safe data access
- Custom scalars for complex types

### REST API
- Comprehensive controller-based endpoints
- Authentication required for most endpoints
- Support for CRUD operations
- File upload endpoints
- Payment processing endpoints

## Security Features

1. **Two-Factor Authentication** - Google Authenticator
2. **Role-Based Access Control** - Granular permissions
3. **API Key Management** - Secure key handling
4. **Environment-Specific Configs** - Separate configs for dev/staging/prod
5. **Request Validation** - Input validation for all endpoints
6. **CSRF Protection** - Standard Laravel CSRF
7. **SQL Injection Protection** - Eloquent ORM
8. **XSS Protection** - Blade auto-escaping

## Configuration

### Environment Variables
- Database connections (primary and map)
- AI provider configuration
- Payment gateway credentials
- Email service settings
- WhatsApp API configuration
- File storage settings
- Queue configuration

### Key Config Files
- `config/ai.php` - AI provider settings
- `config/services.php` - Third-party services
- `config/app.php` - Application configuration
- `config/auth.php` - Authentication configuration
- `config/permission.php` - Role-based permissions

## Key Models

### User & Authentication
- User, Agent, Client, Company
- Account, AccountTransaction, AccountType
- Role, Permission

### Travel
- Hotel, Airline, Airport, City, Country
- Supplier, SupplierCompany, SupplierCredential

### Finance
- Charge, AgentCharge, AutoBilling
- Invoice, Payment, ReceiptVoucher
- Credit, JournalEntry, CoaCategory

### Chat & Communication
- Conversation, ChatCompletion

### Files & Documents
- Asset, IncomingMedia

## Deployment

### Prerequisites
- PHP 8.x
- MySQL 8.x
- Composer
- Node.js & npm
- Redis (optional, for caching)

### Deployment Process
1. Configure environment variables in `.env`
2. Run database migrations
3. Generate application key
4. Set proper permissions
5. Configure queue workers
6. Set up cron jobs for scheduled tasks
7. Configure production services (email, payments, etc.)

## Development Notes

### Code Style
- Laravel conventions followed
- PSR-12 coding standards
- Type hints throughout
- Comprehensive documentation in code comments

### AI Integration Pattern
```php
// Use AIManager for consistent AI access
$aiManager = app(App\AI\AIManager::class);
$result = $aiManager->chat($messages);
```

### Service Layer
All external integrations use service classes:
- TBOHolidayService for TBO API
- MagicHolidayService for alternative booking
- PaymentApplicationService for payments
- OpenAIClient for AI operations

## Supported Regions

- Kuwait (primary market)
- Regional travel services
- Multiple currencies supported
- Local payment gateways

## Project Status

This is an active Laravel 11 project with:
- Complete core functionality
- Multiple payment gateways integrated
- AI capabilities for document processing
- Full accounting system
- Multi-tenant architecture
- Mobile-responsive interface

## Documentation

For detailed documentation, see:
- Laravel official documentation
- Project-specific documentation in `storage/logs` and code comments
- Configuration files as inline documentation
- GraphQL schema for API documentation

## Dependencies

Key dependencies:
- Laravel 11 framework
- OpenAI PHP SDK
- Facebook Graph API SDK (WhatsApp)
- AWS SDK for PHP
- Postmark PHP SDK
- Lighthouse GraphQL server
- Livewire
- Alpine.js
- Tailwind CSS
- MySQL driver for PHP