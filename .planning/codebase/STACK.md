# Technology Stack

**Analysis Date:** 2026-02-12

## Languages

**Primary:**
- PHP 8.2+ (8.3.6 currently deployed) - Full backend application

**Secondary:**
- JavaScript - Frontend interactivity (Alpine.js, Vite)
- SQL - Database queries via Eloquent ORM

## Runtime

**Environment:**
- PHP 8.2-8.3 (FPM or CLI)
- Node.js + npm (frontend tooling)

**Package Manager:**
- Composer (PHP dependencies)
  - Lockfile: `composer.lock` present
- npm (JavaScript dependencies)
  - Lockfile: `package-lock.json` present

## Frameworks

**Core:**
- Laravel 11.9+ - Web framework and application foundation
  - `laravel/breeze` 2.1+ - Authentication scaffolding
  - `laravel/tinker` 2.9+ - REPL for debugging

**Frontend:**
- Livewire 3.5+ - Server-side component framework for dynamic UI
- Alpine.js 3.4.2+ - Lightweight JavaScript framework
- Tailwind CSS 3.1.0+ - Utility-first CSS framework
- Blade Templates - Laravel's templating engine

**GraphQL:**
- Lighthouse 6.63+ - GraphQL server implementation
  - Schema: `graphql/schema.graphql`
  - Resolvers in `app/GraphQL/`

**Build/Dev:**
- Vite 7.1.9+ - Fast frontend build tool
- Laravel Vite Plugin 2.0+ - Vite-Laravel integration
- PostCSS 8.4.31+ - CSS transformation tool
- Autoprefixer 10.4.2+ - Browser prefix management

## Key Dependencies

**Critical:**
- `guzzlehttp/guzzle` 7.9+ - HTTP client for API calls
- `spatie/laravel-permission` 6.10+ - Role-based access control (RBAC)
- `spatie/laravel-backup` 9.3+ - Database and file backups
- `league/oauth2-client` 2.8+ - OAuth2 authentication flows

**AI & Document Processing:**
- `google/cloud-vision` 1.10+ - Google Cloud Vision API for OCR
- `smalot/pdfparser` 2.11+ - PDF text extraction and parsing
- `thiagoalessio/tesseract_ocr` 2.13+ - OCR engine for document images
- `spatie/pdf-to-image` 1.2+ - PDF to image conversion
- `barryvdh/laravel-dompdf` 3.1+ - PDF generation from HTML
- `smalot/pdfparser` 2.11+ - PDF parsing and extraction

**File & Document Handling:**
- `maatwebsite/excel` 3.1+ - Excel/CSV import/export
- `phpoffice/phpspreadsheet` 1.30+ - Spreadsheet processing
- `setasign/fpdi` 2.6+ - PDF manipulation and merging
- `iio/libmergepdf` 4.0+ - PDF merging utility
- `tecnickcom/tcpdf` 6.8+ - PDF generation

**Document Format Conversion:**
- `convertapi/convertapi-php` 3.0+ - CloudConvert API integration for format conversion

**QR Code & 2FA:**
- `bacon/bacon-qr-code` 3.0+ - QR code generation
- `pragmarx/google2fa-laravel` 2.2+ - Google Authenticator integration
- `pragmarx/google2fa-qrcode` 3.0+ - 2FA QR code generation

**Payment Gateway Support:**
- `myfatoorah/laravel-package` 2.2+ - MyFatoorah payment gateway integration

**Email & Communication:**
- `webklex/laravel-imap` 6.1+ - IMAP client for email processing

**Security:**
- `josiasmontag/laravel-recaptchav3` 1.0+ - Google reCAPTCHA v3 integration

**UI Components:**
- `blade-ui-kit/blade-heroicons` 2.6+ - Heroicons SVG icon library

## Configuration

**Environment:**
- Configuration via `.env` file (never committed)
- Base example: `.env.example` contains all required variables
- Environment detection: `APP_ENV` (local/staging/production)
- Debug mode: `APP_DEBUG` (enabled in development, disabled in production)

**Build:**
- Vite configuration: `vite.config.js`
- PostCSS configuration: `postcss.config.js`
- Tailwind configuration: `tailwind.config.js`
- EditorConfig: `.editorconfig`

**Laravel Specific:**
- Config files in `config/` directory
- Key configs: `app.php`, `database.php`, `services.php`, `ai.php`, `webhook.php`, `mail.php`, `lighthouse.php`

## Platform Requirements

**Development:**
- PHP 8.2+ with extensions: PDO, Mysql, JSON, BCMath, Ctype, Fileinfo, Mbstring, Tokenizer, XML
- MySQL 5.7+ or MariaDB 10.2+ (dual database: primary + map database)
- Redis (optional, for caching/sessions)
- Node.js 16+ and npm 8+ (for frontend tooling)
- Composer (PHP dependency manager)

**Production:**
- PHP 8.2+ (FPM recommended)
- MySQL 5.7+ or MariaDB 10.2+
- Nginx or Apache with proper rewrite rules
- Redis (optional but recommended for performance)
- Node.js and npm (for build process only, not runtime)
- OpenAI/OpenWebUI/AnythingLLM API access (for document processing)

**External Services:**
- OpenAI API - GPT models for document intelligence
- Google Cloud Vision API - OCR and image understanding
- WhatsApp Business API - Customer communication
- Payment gateways: MyFatoorah, Knet, uPayment, Hesabe, Tap, IATA EasyPay
- Travel APIs: TBO Holidays, Magic Holiday
- n8n - Workflow automation
- OpenWebUI - Local LLM inference
- AnythingLLM - RAG system for document intelligence
- CloudConvert API - Document format conversion
- Email providers: AWS SES, Postmark, Resend, SMTP

## Key Environment Variables

### Database
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_testing
DB_USERNAME=laravel_user
DB_PASSWORD=laravel_user_password
DB_DATABASE_MAP=map_data_citytour
DB_TIMEZONE=UTC
```

### AI Providers
```env
AI_PROVIDER=openai|openwebui|anythingllm
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4
OPENAI_API_URL=https://api.openai.com/v1
OPENWEBUI_API_KEY=...
OPENWEBUI_API_URL=http://localhost:3000/api
OPENWEBUI_MODEL=llama3.1:latest
ANYLLM_BASE=...
ANYLLM_API_KEY=...
```

### Payment Gateways
```env
MYFATOORAH_LIVE_KEY=...
MYFATOORAH_LIVE_URL=https://api.myfatoorah.com
TAP_SECRET=...
TAP_PUBLIC=...
KNET_PRODUCTION_URL=https://kpay.com.kw/kpg/merchant.htm
UPAYMENT_LIVE_KEY=...
HESABE_SECRET_KEY=...
```

### Travel APIs
```env
TBO_URL=...
TBO_USERNAME=...
TBO_PASSWORD=...
MAGIC_HOLIDAY_CLIENT_ID=...
MAGIC_HOLIDAY_CLIENT_SECRET=...
```

### External Services
```env
GOOGLE_APPLICATION_CREDENTIALS=
CONVERT_API_SECRET=
WHATSAPP_TOKEN=
TWILIO_SID=
TWILIO_AUTH_TOKEN=
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
OPENWEBUI_API_KEY=
N8N_WEBHOOK_URL=
RESEND_KEY=
```

---

*Stack analysis: 2026-02-12*
