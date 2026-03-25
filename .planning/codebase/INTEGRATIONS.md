# External Integrations

**Analysis Date:** 2026-02-12

## APIs & External Services

**AI & Document Intelligence:**
- OpenAI (GPT-3.5/4/4o Vision) - Document extraction, text understanding, image analysis
  - SDK: `openai/openai-php` (via guzzle)
  - Auth: `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_API_URL`
  - Implementation: `app/AI/Services/OpenAIClient.php`, `config/ai.php`
  - Use: Document processing, passport OCR, content extraction

- OpenWebUI (Local LLM) - Self-hosted AI alternative
  - SDK: `openwebui` (guzzle HTTP client)
  - Auth: `OPENWEBUI_API_KEY`, `OPENWEBUI_API_URL`, `OPENWEBUI_MODEL`
  - Implementation: `app/AI/Services/OpenWebUIClient.php`
  - Use: Private document processing without cloud API costs

- AnythingLLM - RAG system for intelligent document handling
  - Auth: `ANYLLM_BASE`, `ANYLLM_API_KEY`, `ANYLLM_WORKSPACE`
  - Implementation: `app/AI/Services/AnythingLLMClient.php`
  - Use: Vectorized document search and retrieval

- Google Cloud Vision API - OCR and image recognition
  - SDK: `google/cloud-vision` 1.10+
  - Auth: `GOOGLE_APPLICATION_CREDENTIALS` (service account JSON file)
  - Use: Passport image OCR, document image analysis

**Vision & OCR:**
- Tesseract OCR - Local OCR engine
  - SDK: `thiagoalessio/tesseract_ocr` 2.13+
  - Use: Extracting text from document images

**Document Format Conversion:**
- CloudConvert API - Multi-format document conversion
  - SDK: `convertapi/convertapi-php` 3.0+
  - Auth: `CONVERT_API_SECRET`
  - Use: Converting documents between formats (PDF, Word, Excel, etc.)

**Payment Gateways:**
- MyFatoorah (KWD/AED/SAR/EGP) - Primary payment processor
  - SDK: `myfatoorah/laravel-package` 2.2+
  - Auth: `MYFATOORAH_SANDBOX_KEY` (test), `MYFATOORAH_LIVE_KEY` (production)
  - URLs: Sandbox: `https://apitest.myfatoorah.com/v2`, Live: `https://api.myfatoorah.com/v2`
  - Webhook: `POST /api/payment/webhook-fatoorah`
  - Config: `config/services.php` 'myfatoorah' section
  - Implementation: `app/Http/Controllers/PaymentController.php`, `app/Models/PaymentTransaction.php`

- Knet (Kuwait National Bank) - Local payment gateway
  - URLs: Sandbox: `https://www.kpaytest.com.kw/kpg/merchant.htm`, Production: `https://kpay.com.kw/kpg/merchant.htm`
  - Auth: `KNET_SANDBOX_URL`, `KNET_PRODUCTION_URL`
  - Config: `config/services.php` 'knet' section
  - Use: Kuwait-based payment processing

- Tap (Multi-currency) - Alternative payment processor
  - Auth: `TAP_SECRET`, `TAP_PUBLIC` (sandbox/production split)
  - URL: `https://api.tap.company/v2`
  - Config: `config/services.php` 'tap' section
  - Implementation: Payment processing for multiple regions

- Hesabe - Payment gateway with encryption
  - Auth: `HESABE_SECRET_KEY`, `HESABE_MERCHANT_CODE`, `HESABE_ACCESS_CODE`, `HESABE_IV_KEY`
  - Sandbox: `https://sandbox.hesabe.com`
  - Production: `https://api.hesabe.com`
  - Webhook: `POST /api/payment/hesabe-webhook`
  - Implementation: `app/Services/HesabeCrypt.php` (encryption/decryption)
  - Config: `config/services.php` 'hesabe' section

- uPayment - Payment gateway
  - Auth: `UPAYMENT_LIVE_KEY`, `UPAYMENT_SANDBOX_KEY`
  - Sandbox: `https://sandboxapi.upayments.com/api/v1`
  - Production: `https://api.upayment.com/v1`
  - Config: `config/services.php` 'uPayment' section
  - Implementation: `app/Services/UPaymentMethodSyncService.php`

- IATA EasyPay - Airline payment system
  - Auth: `IATA_CLIENT_ID`, `IATA_CLIENT_SECRET`, `IATA_CODE`
  - URLs: `https://easypay.iata.org`, `https://login.microsoftonline.com`
  - Implementation: `app/Services/IataEasyPayService.php`
  - Use: Airline booking payments

## Data Storage

**Databases:**
- Primary Database: MySQL 5.7+
  - Connection: `mysql` (default connection)
  - Database: Configured via `DB_DATABASE` env (typically `laravel_testing`)
  - Client: Eloquent ORM (Laravel built-in)
  - Config: `config/database.php`
  - Use: All application data (tasks, users, payments, accounting)

- Map/Geographic Database: MySQL 5.7+
  - Connection: `mysql_map`
  - Database: `map_data_citytour` (separate database)
  - Client: Eloquent ORM with custom connection
  - Config: `config/database.php` 'mysql_map' section
  - Use: Hotel locations, geographic data, map overlays

- Test Database: MySQL 5.7+
  - Connection: `mysql_testing`
  - Database: `city_tour_test`
  - Use: Unit/integration testing with isolated data

**Caching:**
- Database Cache - Cache stored in DB tables
  - Config: `CACHE_STORE=database`
  - Use: Configuration caching, query result caching

- Redis (Optional)
  - Connection: `redis`
  - Config: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`
  - Use: High-performance caching, session storage, queue processing

**File Storage:**
- Local Filesystem - Primarily local disk storage
  - Location: `storage/app/` directory
  - Document paths: `storage/app/{company_name}/{supplier_name}/files_unprocessed/`
  - Processed files: `storage/app/{company}/{supplier}/files_processed/`
  - Failed files: `storage/app/{company}/{supplier}/files_error/`
  - Debug exports: `storage/app/{company}/{supplier}/debug_exports/`

- AWS S3 (Configured but optional)
  - Auth: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`
  - Region: `AWS_DEFAULT_REGION` (default: us-east-1)
  - Bucket: `AWS_BUCKET`
  - Config: `config/filesystems.php`
  - Enable with `FILESYSTEM_DISK=s3`

## Authentication & Identity

**Auth Provider:**
- Custom Laravel Authentication
  - Implementation: Laravel Breeze scaffolding
  - Driver: Session-based (not token-based)
  - Config: `config/auth.php`, `app/Models/User.php`

- Role-Based Access Control (RBAC)
  - Package: `spatie/laravel-permission` 6.10+
  - Use: Company → Branch → Agent hierarchy
  - Implementation: `app/Models/User.php` with permission traits

- 2FA (Two-Factor Authentication)
  - Package: `pragmarx/google2fa-laravel` 2.2+
  - Implementation: Google Authenticator integration
  - Config: `config/google2fa.php`

- OAuth2 Support
  - Package: `league/oauth2-client` 2.8+
  - Use: Third-party API authentication (Travel APIs, etc.)

- reCAPTCHA v3
  - Package: `josiasmontag/laravel-recaptchav3` 1.0+
  - Auth: `RECAPTCHAV3_SITEKEY`, `RECAPTCHAV3_SECRET`
  - Use: Bot protection on registration/payment forms

## Communication

**WhatsApp Business API:**
- Resayil Custom Integration
  - Endpoint: `https://api.resayil.io/v1/messages`
  - Auth: `RESAYIL_API_TOKEN`
  - Webhook: `POST /api/webhook/resayil/media` (incoming messages)
  - Implementation: `app/Http/Controllers/IncomingMediaController.php`, `app/Http/Controllers/WhatsappController.php`
  - Use: Customer notifications, booking confirmations

- Official Facebook Graph API
  - URL: `https://graph.facebook.com/v22.0`
  - Auth: `WHATSAPP_TOKEN`
  - Phone Number ID: `WHATSAPP_PHONE_NUMBER_ID`
  - Alternative endpoint: `WHATSAPP_GRAPH_API_URL`

**Email:**
- SMTP (Primary)
  - Config: `MAIL_HOST`, `MAIL_PORT` (587 for TLS), `MAIL_USERNAME`, `MAIL_PASSWORD`
  - Encryption: TLS (default)
  - From: Configured via `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`

- AWS SES (Optional)
  - Auth: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`
  - Region: `AWS_DEFAULT_REGION`
  - Config: `config/services.php` 'ses' section

- Postmark (Optional)
  - Auth: `POSTMARK_TOKEN`
  - Config: `config/services.php` 'postmark' section

- Resend (Optional)
  - Auth: `RESEND_KEY`
  - Config: `config/services.php` 'resend' section

**SMS/Messaging:**
- Twilio WhatsApp
  - Auth: `TWILIO_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_WHATSAPP_FROM`

## Travel APIs

**TBO Holidays:**
- Endpoint: Sandbox or Production URL
  - Sandbox: `TBO_SANDBOX_URL`
  - Production: `TBO_URL`
  - Auth: `TBO_USERNAME`, `TBO_PASSWORD`
  - Config: `config/services.php` 'tbo' section
  - Implementation: `app/Services/TBOHolidayService.php`
  - Use: Hotel availability, pricing, booking integration

**Magic Holiday:**
- OAuth2 Flow
  - Auth URL: `MAGIC_HOLIDAY_AUTHORIZATION_URL`
  - Token URL: `MAGIC_HOLIDAY_TOKEN_URL`
  - Client: `MAGIC_HOLIDAY_CLIENT_ID`, `MAGIC_HOLIDAY_CLIENT_SECRET`
  - Base URL: `MAGIC_HOLIDAY_URL`
  - Webhook: `POST /magic-webhook-callback` (booking updates)
  - Implementation: `app/Services/MagicHolidayService.php`
  - Use: Tour packages, availability, pricing

## Monitoring & Observability

**Error Tracking:**
- Not detected (rely on Laravel logging)

**Logs:**
- Laravel Stack Logging
  - Channels: `stack`, `single`, `daily`
  - Location: `storage/logs/`
  - Config: `config/logging.php`
  - Rotation: Daily logs with 360 day retention

- Application-Specific Logs
  - AIR Processing: `storage/logs/air_processing.log`
  - AI Requests: `storage/logs/ai.log`
  - N8n Tracking: `app/Services/N8nErrorLogger.php`

**Backup:**
- Spatie Laravel Backup
  - Package: `spatie/laravel-backup` 9.3+
  - Config: `config/backup.php`
  - Database dump: Configured with `MYSQLDUMP_BINARY_PATH`
  - Archive password: `BACKUP_ARCHIVE_PASSWORD`
  - Email notifications: `MAIL_TO_DATA_ONLY_BACKUP`

## Workflow Automation

**n8n Integration:**
- Webhook Endpoint: `N8N_WEBHOOK_URL` (full URL to n8n instance)
- Webhook Signing
  - Package: `app/Services/WebhookSigningService.php`
  - HMAC Algorithm: SHA256
  - Headers: `X-Signature-SHA256`, `X-Signature-Timestamp`
  - Signature verification in: `config/webhook.php`

- Laravel Callback
  - Route: `POST /api/webhooks/n8n/callback` (document processing results)
  - Implementation: `app/Http/Controllers/Api/DocumentProcessingController.php`
  - Tracking: `app/Services/N8nExecutionTracker.php`

- Rate Limiting
  - Global limit: 100 requests/minute (configurable)
  - Per-client limit: 60 requests/minute (configurable)

- File Validation
  - Max size: 10MB (configurable)
  - Allowed types: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, EML
  - Config: `config/webhook.php` 'file_validation' section

## Currency & Exchange Rates

**Currency API:**
- CurrencyAPI
  - URL: `https://api.currencyapi.com/v3/`
  - Auth: `CURRENCY_API_KEY`
  - Default Currency: `CURRENCY_DEFAULT` (KWD)
  - Use: Multi-currency conversion, exchange rate tracking

## Environment Configuration

**Required env vars:**
- `APP_KEY` - Laravel encryption key
- `APP_ENV` - Environment mode (local/staging/production)
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` - Database
- `OPENAI_API_KEY` or `OPENWEBUI_API_KEY` - AI provider
- `MAIL_FROM_ADDRESS` - Email sender
- Payment gateway keys (MyFatoorah, Knet, etc.)

**Secrets location:**
- Environment file: `.env` (never committed)
- Template: `.env.example` (safe, check-in tracked)
- No secrets in `.env.*` files (blocked by `.gitignore`)

## Webhooks & Callbacks

**Incoming Webhooks (Public API):**
- `POST /api/payment/webhook-fatoorah` - MyFatoorah payment callbacks
- `POST /api/payment/hesabe-webhook` - Hesabe payment callbacks
- `POST /api/webhook/resayil/media` - Resayil WhatsApp message webhooks
- `POST /magic-webhook-callback` - Magic Holiday booking updates
- `POST /api/webhooks/n8n/callback` - n8n document processing results
- HMAC signature verification required for n8n webhooks

**Outgoing Webhooks (To N8n):**
- Document processing trigger
  - Endpoint: `N8N_WEBHOOK_URL` (from n8n configuration)
  - Signing: HMAC-SHA256 with timestamp
  - Payload: File metadata, processing type, callback URL
  - Callback: Results sent to `POST /api/webhooks/n8n/callback`

## Integration Health & Monitoring

**Webhook Configuration:**
- Rate limiting enabled (configurable thresholds)
- Deduplication enabled (1-hour cache TTL, configurable)
- Error alerting based on error rate thresholds
- Audit logging with 90-day retention
- Automatic cleanup of old audit records

**Critical Integration Points:**
- Payment processing (4 gateways + Tap) - Revenue dependent
- Document processing (OpenAI/OpenWebUI/AnythingLLM) - Core feature
- Travel APIs (TBO/Magic) - Booking dependent
- WhatsApp/Email - Customer communication
- n8n - Automation backbone

---

*Integration audit: 2026-02-12*
