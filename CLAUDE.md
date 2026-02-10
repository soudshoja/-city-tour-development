# Claude Code Project Instructions - Soud Laravel

## This Project
Soud Laravel - Laravel 11 travel agency management platform with AI-powered document processing, multi-tenant architecture, and comprehensive accounting system.

## Key Information
- **Repository**: `git@github.com:soudshoja/-city-tour-development.git` (private)
- **Branch**: main
- **Live Site**: https://development.citycommerce.group
- **PHP**: 8.2+
- **Framework**: Laravel 11
- **Databases**:
  - Primary: `laravel_testing` (main application data)
  - Secondary: `map_data_citytour` (geographic/map data)

## Tech Stack
- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: Livewire 3.5, Alpine.js, Tailwind CSS, Blade Templates
- **AI Integration**: OpenAI (GPT-3.5/4/4o), OpenWebUI (local LLMs), AnythingLLM
- **Payment Gateways**: MyFatoorah, Knet, uPayment, Hesabe, Tap
- **Travel APIs**: TBO Holidays, Magic Holiday
- **Communication**: WhatsApp Business API, Email (Resend, AWS SES, Postmark)
- **GraphQL**: Lighthouse
- **Automation**: N8n workflows

## Key Features
1. **AI Document Processing**
   - AIR file parsing (Amadeus GDS format)
   - PDF document extraction
   - Passport image OCR (GPT-4o Vision)
   - Email attachment processing

2. **Multi-Tenant System**
   - Company → Branch → Agent hierarchy
   - Isolated data per company
   - Role-based access control

3. **Accounting System**
   - Double-entry bookkeeping
   - Chart of Accounts (COA)
   - Journal entries, invoices, receipt vouchers
   - Payment applications, credits, refunds

4. **Task Management**
   - 12 task types: flight, hotel, visa, insurance, tour, cruise, car, rail, esim, event, lounge, ferry
   - Full lifecycle: issued → reissued → refund/void
   - Multi-currency support with exchange rate tracking

## Development Workflow

### Code Quality
```bash
# ALWAYS run before committing
./vendor/bin/phpstan analyse
./vendor/bin/pint  # Laravel Pint formatter
php artisan test
```

### Common Commands
```bash
# Development server
php artisan serve

# Database
php artisan migrate
php artisan db:seed
php artisan migrate:fresh --seed  # Fresh start

# Code generation
php artisan make:controller ControllerName
php artisan make:model ModelName -m
php artisan make:migration create_table_name

# Document processing
php artisan app:process-files --batch --export-debug
php artisan emails:process

# Caching (production)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Clear caches (development)
php artisan optimize:clear
```

## Document Processing System

### AIR File Processing
```bash
# Place AIR files here:
storage/app/{company_name}/{supplier_name}/files_unprocessed/

# Run processor
php artisan app:process-files --batch --batch-size=10

# With debug exports (Excel/CSV)
php artisan app:process-files --export-debug

# Results:
# - Success: storage/app/{company}/{supplier}/files_processed/
# - Failed: storage/app/{company}/{supplier}/files_error/
# - Debug: storage/app/{company}/{supplier}/debug_exports/
# - Logs: storage/logs/air_processing.log
```

### Processing Methods
1. **AirFileParser** - For Amadeus AIR files (regex-based, fast)
2. **AI-based** - For PDFs, images, other formats (OpenAI/OpenWebUI)

### File Types Supported
- `.air` - Amadeus GDS tickets (regex parser)
- `.pdf` - Hotel bookings, invoices, visa forms (AI extraction)
- `.jpg/.png` - Passport images (GPT-4o Vision)
- `.txt` - Simple ticket receipts
- `.xlsx/.csv` - Bulk imports

## Key Rules

### Code Standards
- Follow PSR-12 coding standards
- Use type hints throughout
- Comprehensive PHPDoc comments
- Eloquent ORM (no raw SQL unless necessary)
- Request validation for all endpoints

### Security
- Never commit `.env` files
- Never bypass authentication
- Validate all inputs
- Use secure password hashing
- Protect against SQL injection, XSS, CSRF

### Database
- Always use migrations (never manual schema changes)
- Foreign keys for relationships
- Indexes on frequently queried columns
- Soft deletes for audit trail

### Git Workflow
- **main** - Production code
- **dev** - Development branch
- **feature/** - New features
- **fix/** - Bug fixes
- **enhance/** - Enhancements
- **docs/** - Documentation

## Important Files

### Documentation
- `PROJECT_OVERVIEW.md` - Complete system architecture
- `DOCUMENT_PROCESSING_STRUCTURE.md` - File processing breakdown
- `DOCUMENT_PROCESSING_DEEP_DIVE.md` - Detailed processing guide
- `OPENWEBUI_INTEGRATION.md` - AI integration guide
- `REPOSITORY_SETUP_COMPLETE.md` - GitHub setup guide

### Core Services
- `app/Services/AirFileParser.php` - AIR file parsing (1,690 lines)
- `app/Services/AirFileService.php` - Batch processing & exports
- `app/Services/FileProcessingLogger.php` - Logging
- `app/AI/AIManager.php` - AI orchestration
- `app/AI/Services/OpenAIClient.php` - OpenAI integration
- `app/AI/Services/OpenWebUIClient.php` - Local LLM integration

### Commands
- `app/Console/Commands/ProcessAirFiles.php` - Main document processor
- `app/Console/Commands/ReadAndProcessEmails.php` - Email processor

### Schemas
- `app/Schema/TaskSchema.php` - Main task schema (405 lines)
- `app/Schema/TaskFlightSchema.php` - Flight details
- `app/Schema/TaskHotelSchema.php` - Hotel details
- `app/Schema/TaskInsuranceSchema.php` - Insurance
- `app/Schema/TaskVisaSchema.php` - Visa details

## Environment Variables

### Required for Document Processing
```env
# AI Provider
AI_PROVIDER=openwebui
OPENWEBUI_API_KEY=your_key
OPENWEBUI_API_URL=http://localhost:3000
OPENWEBUI_MODEL=llama3.1:latest

# Fallback OpenAI
OPENAI_API_KEY=your_openai_key

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=laravel_testing
DB_USERNAME=your_user
DB_PASSWORD=your_password

# Map Database
DB_CONNECTION_MAP=mysql
DB_HOST_MAP=127.0.0.1
DB_DATABASE_MAP=map_data_citytour
```

## Testing

### Run Tests
```bash
# All tests
php artisan test

# Specific test
php artisan test --filter TestName

# With coverage
php artisan test --coverage
```

### Test Document Processing
```bash
# Create test AIR file
mkdir -p storage/app/test_company/amadeus/files_unprocessed

cat > storage/app/test_company/amadeus/files_unprocessed/test.air << 'EOF'
AIR-BLK1;IS;001
MUC1A 8DROXL0101;1234567;KWIKT2619;
T-K229-2833133219
I-001;001TEST/USER MR;
A-KUWAIT AIRWAYS;KU
K-FKWD100.000;;;;;;;;;;;;KWD130.000;;;
EOF

# Process
php artisan app:process-files --export-debug

# Check results
ls -la storage/app/test_company/amadeus/files_processed/
cat storage/logs/air_processing.log
```

## Deployment

### Production Deployment
```bash
# On server (development.citycommerce.group)
cd /path/to/soud-laravel

# Pull latest code
git pull origin main

# Install dependencies (production)
composer install --no-dev --optimize-autoloader
npm install --production
npm run build

# Run migrations
php artisan migrate --force

# Clear and cache
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

### Automated Processing
```bash
# Add to crontab for automated processing
* * * * * cd /path/to/soud-laravel && php artisan schedule:run >> /dev/null 2>&1

# In app/Console/Kernel.php:
$schedule->command('app:process-files --batch')->everyFiveMinutes();
$schedule->command('emails:process')->hourly();
```

## Troubleshooting

### Document Processing Issues
```bash
# Check logs
tail -f storage/logs/air_processing.log
tail -f storage/logs/ai.log

# Test parser directly
php artisan tinker
>>> $parser = new \App\Services\AirFileParser('path/to/file.air');
>>> $data = $parser->parseTaskSchema();
>>> print_r($data);

# Check AI connection
>>> $ai = app(\App\AI\AIManager::class);
>>> $result = $ai->chat([['role' => 'user', 'content' => 'test']]);
```

### Database Issues
```bash
# Check connections
php artisan migrate:status

# Reset database (CAUTION: destroys data)
php artisan migrate:fresh --seed

# Check specific table
php artisan tinker
>>> \DB::table('tasks')->count();
```

### Permission Issues
```bash
# Fix storage permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## Project Status

### Completed Features
- ✅ AI document processing (AIR, PDF, passport, email)
- ✅ Multi-tenant system
- ✅ Accounting system
- ✅ Payment gateway integration
- ✅ Task management (12 types)
- ✅ WhatsApp Business API
- ✅ Travel API integration (TBO, Magic)
- ✅ GraphQL API
- ✅ N8n workflow automation

### Active Development
- [ ] Mobile app integration
- [ ] Advanced analytics dashboard
- [ ] Multi-language support
- [ ] Automated reconciliation
- [ ] Performance optimization

## Support & Resources

### Documentation
- Read `PROJECT_OVERVIEW.md` for system architecture
- Read `DOCUMENT_PROCESSING_DEEP_DIVE.md` for processing details
- Check `storage/logs/` for debugging

### Useful Queries
```sql
-- Recent tasks
SELECT * FROM tasks ORDER BY created_at DESC LIMIT 10;

-- Tasks by status
SELECT status, COUNT(*) FROM tasks GROUP BY status;

-- Today's processing
SELECT COUNT(*) FROM tasks WHERE DATE(created_at) = CURDATE();

-- Failed file processing
SELECT * FROM file_uploads WHERE status = 'error';
```

## Notes
- Always test document processing with `--export-debug` first
- Review Excel exports to validate parsing accuracy
- Check agent matching logic for your specific agency
- Currency conversion is automatic with exchange rate tracking
- Multi-passenger AIR files create separate tasks per passenger
- Original task linking works for refund/void/reissued tickets

## Contact
- **Repository**: https://github.com/soudshoja/-city-tour-development
- **Owner**: @soudshoja
- **Live Site**: https://development.citycommerce.group
