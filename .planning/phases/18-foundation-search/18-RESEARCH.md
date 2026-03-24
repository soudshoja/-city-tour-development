# Phase 18: Foundation + Search - Research

**Researched:** 2026-03-24
**Domain:** Laravel module scaffold, hotel static data import, fuzzy matching, REST API search endpoints, WhatsApp message formatting
**Confidence:** HIGH

## Summary

Phase 18 builds the DotwAI module foundation -- a self-contained Laravel package at `app/Modules/DotwAI/` that follows the proven ResailAI module pattern. The module wraps (never modifies) the existing `DotwService` (2,232 lines, per-company credential resolution) and existing models (`DotwPrebook`, `DotwRoom`, `CompanyDotwCredential`). It exposes REST endpoints (not GraphQL) for n8n AI agents to search hotels, browse room details, and retrieve city lists, with every response including a pre-formatted `whatsappMessage` field.

The critical finding is that **zero new composer dependencies are needed**. Maatwebsite Excel 3.1.67 is already installed for the hotel Excel/CSV import. The existing `HotelSearchService::findCompanyIdByPhone()` pattern provides the exact phone-to-agent-to-company resolution chain this phase needs. The module creates its own `dotwai_hotels` table for hotel static data (separate from the `map_data_citytour` database) and uses `LIKE` queries plus Levenshtein distance for fuzzy hotel name matching. Search results are cached per phone number via Laravel's `Cache::put()` with a 10-minute TTL.

**Primary recommendation:** Build the module following the ResailAI ServiceProvider pattern exactly (register in `bootstrap/providers.php`, own config, own routes, own middleware), add 3 new database tables (`dotwai_hotels`, `dotwai_cities`, `dotwai_countries`), and implement 3 REST endpoints (`search_hotels`, `get_hotel_details`, `get_cities`) plus the `dotwai:import-hotels` artisan command -- all behind a phone-number resolution middleware.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Self-contained at app/Modules/DotwAI/ with own ServiceProvider, config, routes, models
- Follow existing ResailAI module pattern (app/Modules/ResailAI/)
- Register in bootstrap/providers.php -- ONLY existing file touched
- Own route file: routes/dotwai.php (REST API, NOT GraphQL)
- Own config: config/dotwai.php (per-company B2B/B2C enable/disable, markup, defaults)
- 11 REST endpoints total (this phase builds 4: search_hotels, get_hotel_details, get_cities + foundation)
- All endpoints under /api/dotwai/ prefix
- Every response includes: success, data, whatsappMessage (pre-formatted Arabic/English), whatsappOptions, error with suggestedAction
- Authentication via phone number -> agent -> company -> DOTW credentials resolution
- No GraphQL -- REST is simpler for n8n AI agent tool definitions
- Import from DOTW Excel/CSV file via artisan command: php artisan dotwai:import-hotels {file}
- Store in local database table (dotwai_hotels) -- NOT in map_data_citytour MapHotel table
- Fields: dotw_hotel_id, name, city, country, star_rating, address, latitude, longitude
- Fuzzy matching via LIKE queries or full-text search for hotel name resolution
- Phone -> Agent model (existing) -> Company -> CompanyDotwCredential -> DOTW credentials
- Also resolves: B2B enabled?, B2C enabled?, markup percentage, credit line availability
- Returns a DotwAIContext object with all resolved data for the request
- After search, results stored in cache keyed by phone number (Cache::put with TTL)
- Cache TTL: 10 minutes (longer than DOTW 3-min block, gives user time to browse)
- Every endpoint returns whatsappMessage field -- pre-formatted text ready to send
- Arabic is primary language, English secondary
- Hotel search results formatted as numbered list with stars, price, meal plan
- Error messages are human-friendly, not technical
- whatsappOptions: suggested follow-up actions for the AI to present
- Module ships a default system message template at config/dotwai-system-message.md
- Bilingual Arabic/English
- Describes all available tools with parameters
- Instructs AI to have natural conversation, not rigid menus
- Can be customized per company

### Claude's Discretion
- Database migration naming and structure
- Service class organization within the module
- How to structure the phone number resolution (middleware vs service)
- Cache implementation details (Redis vs file)
- WhatsApp message formatting templates (exact layout)
- Error code taxonomy
- How many hotels to return per search (suggest 5-10)
- Fuzzy matching algorithm choice

### Deferred Ideas (OUT OF SCOPE)
- Booking endpoints (Phase 19)
- Payment integration (Phase 19)
- Cancellation (Phase 20)
- Accounting integration (Phase 20)
- Lifecycle automation / reminders (Phase 21)
- Booking history / vouchers (Phase 21)
- Monitoring dashboard (Phase 22)
- Multi-supplier aggregation (Future)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| FOUND-01 | Module registers as self-contained Laravel package at app/Modules/DotwAI/ with own ServiceProvider, config, routes, models | ResailAI module pattern verified -- ServiceProvider with `mergeConfigFrom` + `loadRoutesFrom`, middleware alias in `bootstrap/app.php`, provider registration in `bootstrap/providers.php` |
| FOUND-02 | Module config allows per-company enable/disable of B2B and B2C tracks independently | `CompanyDotwCredential` already has `is_active` and `markup_percent` per company; module config adds B2B/B2C toggles as defaults, with per-company override via new columns or JSON config |
| FOUND-03 | Phone number resolves to agent -> company -> DOTW credentials -> track (B2B/B2C) automatically | Existing `HotelSearchService::findCompanyIdByPhone()` pattern: `Agent::where('phone_number', $tel)->orWhere(DB::raw("CONCAT(country_code, phone_number)"), $tel)` then `$agent->branch->company_id` then `CompanyDotwCredential::forCompany($companyId)` |
| FOUND-04 | Hotel static data imported from DOTW Excel/CSV file into local database with artisan import command | Maatwebsite Excel 3.1.67 installed. Use `WithHeadingRow` import class. Store in `dotwai_hotels` table (primary DB, not `mysql_map`) |
| FOUND-05 | Hotel name fuzzy matching resolves client text ("Hilton Dubai") to DOTW hotel IDs locally | `LIKE` query as primary match, Levenshtein distance (PHP built-in `levenshtein()`) as fallback for typo correction. Filter by city when available. |
| FOUND-06 | Module provides default AI system message template (Arabic/English bilingual) with tool descriptions auto-generated from endpoint metadata | Static markdown file at `config/dotwai-system-message.md` loaded via config. Tool descriptions can be a simple array in config matching endpoint route names. |
| SRCH-01 | `search_hotels` endpoint accepts city, dates, occupancy, filters and returns flat hotel list with pre-formatted WhatsApp message | Wraps `DotwService::searchHotels()` with city code resolution, applies filters locally, formats numbered WhatsApp list |
| SRCH-02 | `get_hotel_details` endpoint returns rooms, rates, cancellation policies, meal plans, specials for a specific hotel | Wraps `DotwService::getRooms()` in browse mode (blocking=false), parses room types, meal plans, cancellation rules, specials |
| SRCH-03 | `get_cities` endpoint returns available destinations | Wraps `DotwService::getCityList()` or reads from local `dotwai_cities` cache table if populated |
| SRCH-04 | Search results cached per phone number so client can reference by option number ("book option 1") | `Cache::put("dotwai:search:{phone}", $results, 600)` -- 10 minute TTL. Results array indexed 1-N for option reference. |
| SRCH-05 | Multi-room/family scenarios supported (distribute occupancy across rooms) | Occupancy input as array of `{adults, childrenAges}` per room. Passed directly to DotwService rooms parameter. |
| SRCH-06 | Filters: star rating, meal type, price range, refundable only, hotel name | Post-search filtering on search results. Star rating and hotel name can also filter at DOTW API level for efficiency. |
| EVNT-02 | Every REST response includes `whatsappMessage` (pre-formatted) and `whatsappOptions` | Standardized response envelope via a `DotwAIResponse` helper class that wraps all controller responses |
| EVNT-03 | Error responses include WhatsApp-ready text with `suggestedAction` for AI | Error response builder with error code taxonomy and human-friendly Arabic/English messages |
</phase_requirements>

## Standard Stack

### Core (Already Installed -- Zero New Dependencies)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | 11.39.1 | Application framework, routing, caching, migrations | Already installed, fully operational |
| PHP | 8.2.12 | Runtime | Already installed |
| maatwebsite/excel | 3.1.67 | Excel/CSV hotel data import | Already in composer.json, proven for imports |
| guzzlehttp/guzzle | 7.9.x | HTTP client for DOTW API (via DotwService) | Already used by DotwService |

### Supporting (Existing Codebase Components)

| Component | Location | Purpose | When to Use |
|-----------|----------|---------|-------------|
| DotwService | `app/Services/DotwService.php` | DOTW V4 XML API wrapper -- searchHotels, getRooms, getCityList, getCountryList, getSalutationIds | Delegate all DOTW API calls to this service |
| CompanyDotwCredential | `app/Models/CompanyDotwCredential.php` | Per-company encrypted DOTW credentials with `markup_percent`, `is_active`, `scopeForCompany()` | Loaded during phone number resolution |
| DotwPrebook / DotwRoom | `app/Models/DotwPrebook.php` | Existing rate allocation storage | Used by search flow to store blocked rates |
| HotelSearchService | `app/Services/HotelSearchService.php` | `findCompanyIdByPhone()` method -- phone -> agent -> company resolution pattern | Reference pattern; module builds its own resolver |
| Agent / Branch / Company | `app/Models/Agent.php` etc. | Multi-tenant hierarchy with `phone_number`, `country_code`, `branch->company_id` | Phone number resolution chain |
| Cache facade | Laravel built-in | Search result caching per phone number | `Cache::put()` / `Cache::get()` with configurable driver |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| LIKE + Levenshtein for fuzzy matching | MySQL FULLTEXT index | FULLTEXT needs MATCH/AGAINST syntax, requires MyISAM or InnoDB fulltext support, more complex for partial name matching. LIKE + Levenshtein is simpler and sufficient for ~25K hotels. |
| Own `dotwai_hotels` table | Existing `MapHotel` in `mysql_map` | MapHotel uses a separate database connection (`mysql_map`), has different columns, and is shared with TBO/Magic. Module isolation requires own table. CONTEXT.md locks this decision. |
| REST endpoints | GraphQL extension via Lighthouse `#import` | CONTEXT.md locks REST. REST is simpler for n8n tool definitions -- one URL per tool vs. complex GraphQL query strings. |

**Installation:** No new packages to install.

## Architecture Patterns

### Recommended Module Structure

```
app/Modules/DotwAI/
  Providers/
    DotwAIServiceProvider.php       # mergeConfigFrom + loadRoutesFrom + loadMigrationsFrom + middleware alias + artisan commands
  Config/
    dotwai.php                      # Module config (B2B/B2C toggles, markup, cache TTL, search limits)
    dotwai-system-message.md        # AI system message template (bilingual)
  Routes/
    api.php                         # REST endpoints under /api/dotwai/ prefix
  Http/
    Controllers/
      SearchController.php          # search_hotels, get_hotel_details, get_cities
    Middleware/
      ResolveDotwAIContext.php      # Phone -> Agent -> Company -> Credentials -> Track resolution
    Requests/
      SearchHotelsRequest.php       # Validation rules for search input
      GetHotelDetailsRequest.php    # Validation rules for hotel details input
  Services/
    PhoneResolverService.php        # Phone number -> DotwAIContext resolution logic
    HotelSearchService.php          # Orchestrates search flow: resolve -> search DOTW -> filter -> cache -> format
    HotelImportService.php          # Excel/CSV import logic for dotwai_hotels
    FuzzyMatcherService.php         # Hotel name fuzzy matching (LIKE + Levenshtein)
    MessageBuilderService.php       # WhatsApp message formatting (pure functions)
    DotwAIResponse.php              # Standardized response envelope builder
  Models/
    DotwAIHotel.php                 # Hotel static data from Excel import
    DotwAICity.php                  # DOTW city cache (code + name + country)
    DotwAICountry.php               # DOTW country cache (code + name + nationality)
  Commands/
    ImportHotelsCommand.php         # php artisan dotwai:import-hotels {file}
    SyncStaticDataCommand.php       # php artisan dotwai:sync-static (cities/countries via DOTW API)
  Imports/
    HotelsImport.php                # Maatwebsite Excel import class
  Database/
    Migrations/
      create_dotwai_hotels_table.php
      create_dotwai_cities_table.php
      create_dotwai_countries_table.php
```

### Pattern 1: Module ServiceProvider Registration

**What:** Self-contained module with own ServiceProvider, following ResailAI pattern.
**When to use:** Always -- this is the locked module pattern.

```php
// app/Modules/DotwAI/Providers/DotwAIServiceProvider.php
namespace App\Modules\DotwAI\Providers;

use Illuminate\Support\ServiceProvider;

class DotwAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/dotwai.php', 'dotwai');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\DotwAI\Commands\ImportHotelsCommand::class,
                \App\Modules\DotwAI\Commands\SyncStaticDataCommand::class,
            ]);
        }
    }
}
```

Registration requires TWO file modifications:
1. `bootstrap/providers.php` -- add `App\Modules\DotwAI\Providers\DotwAIServiceProvider::class`
2. `bootstrap/app.php` -- add middleware alias for `dotwai.resolve` (same pattern as `verify.resailai.token`)

### Pattern 2: Phone Number Resolution via Middleware

**What:** Middleware that resolves phone number to full context (agent, company, credentials, track).
**When to use:** On all `/api/dotwai/` routes.

```php
// Middleware sets $request->attributes->set('dotwai_context', $context)
// where $context is a DTO with: agent, company, credentials, track, markup_percent
// Controllers access via $request->attributes->get('dotwai_context')

// Resolution chain (from existing HotelSearchService pattern):
// 1. Agent::where('phone_number', $phone)->orWhere(DB::raw("CONCAT(country_code, phone_number)"), $phone)
// 2. $agent->branch->company_id
// 3. CompanyDotwCredential::forCompany($companyId)->first()
// 4. new DotwService($companyId) -- uses per-company credentials
```

**Recommendation:** Implement as middleware (not inline service calls) because every endpoint needs this resolution. The middleware attaches a `DotwAIContext` DTO to the request, making controllers clean. This is the same pattern ResailAI uses with `$request->attributes->set('resailai_credential', $credential)`.

### Pattern 3: Standardized Response Envelope

**What:** Every response includes `success`, `data`, `whatsappMessage`, `whatsappOptions`, and on errors `error` with `suggestedAction`.
**When to use:** All DotwAI REST endpoints.

```php
// DotwAIResponse helper (static methods for consistency)
class DotwAIResponse
{
    public static function success(array $data, string $whatsappMessage, array $whatsappOptions = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'whatsappMessage' => $whatsappMessage,
            'whatsappOptions' => $whatsappOptions,
        ]);
    }

    public static function error(string $code, string $message, string $whatsappMessage, ?string $suggestedAction = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'suggestedAction' => $suggestedAction,
            ],
            'whatsappMessage' => $whatsappMessage,
            'whatsappOptions' => [],
        ], $code === 'NOT_FOUND' ? 404 : 422);
    }
}
```

### Pattern 4: Search Result Caching Per Phone Number

**What:** After search, results stored in cache keyed by phone number so user can reference "option 1".
**When to use:** Every `search_hotels` response.

```php
// Cache key: dotwai:search:{normalized_phone}
// TTL: 10 minutes (configurable via config('dotwai.search_cache_ttl'))
// Value: array of results indexed 1..N with hotel ID, name, price, prebookKey

$results = [...]; // search results
Cache::put("dotwai:search:{$phone}", $results, config('dotwai.search_cache_ttl', 600));

// Later, "book option 1":
$cached = Cache::get("dotwai:search:{$phone}");
$selected = $cached[1] ?? null;
```

**Recommendation:** Use the default cache driver (file in development, database in production based on existing config). No Redis needed -- search volume is low (< 100 searches/day expected initially).

### Pattern 5: Hotel Import via Maatwebsite Excel

**What:** Artisan command imports DOTW hotel data from Excel/CSV into `dotwai_hotels` table.
**When to use:** One-time import or periodic refresh of DOTW hotel inventory.

```php
// php artisan dotwai:import-hotels storage/app/dotw-hotels.xlsx
// Uses Maatwebsite\Excel\Concerns\WithHeadingRow for flexible column mapping
// Handles common DOTW Excel formats: hotel_id/hotelId/id, hotel_name/name, city, country, stars/star_rating
// Upserts on dotw_hotel_id (unique constraint)
```

### Anti-Patterns to Avoid

- **Modifying DotwService:** Never add methods or change signatures. Wrap it through composition in module services.
- **Modifying existing models:** DotwPrebook, DotwRoom, CompanyDotwCredential are READ-ONLY from this module's perspective.
- **Modifying existing routes:** The module's routes go in its own Routes/api.php, not in the main `routes/api.php`.
- **Modifying existing GraphQL schema:** No changes to `dotw.graphql` or `schema.graphql`. This module uses REST only.
- **Loading all hotels into memory for Levenshtein:** For ~25K hotels, load only the city-filtered subset. Never `DotwAIHotel::all()`.
- **Blocking DOTW rates during search:** Phase 18 search returns browse-mode results (no allocation). Rate blocking happens at prebook time (Phase 19). Search is informational only.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Excel/CSV parsing | Custom CSV parser | Maatwebsite Excel `WithHeadingRow` import | Already installed (3.1.67). Handles encoding, large files, column mapping. |
| Phone number resolution | Inline SQL in each controller | Middleware + `DotwAIContext` DTO | Same pattern as ResailAI `VerifyResailAIToken` middleware. DRY across all endpoints. |
| Response formatting | Ad-hoc JSON in each controller method | `DotwAIResponse` static helper class | Enforces consistent envelope structure with whatsappMessage on every response. |
| City/country code lookup | Direct DOTW API calls on every search | Local `dotwai_cities`/`dotwai_countries` tables synced from DOTW API | DOTW API call adds 2-5s latency per lookup. Local table is instant. |
| Fuzzy string matching | Custom edit-distance algorithm | PHP built-in `levenshtein()` + `similar_text()` | Built into PHP, well-tested, fast for small result sets. |
| Cache key generation | Manual string concatenation | Dedicated method in a service class | Prevents key collision bugs and makes TTL configurable. |

**Key insight:** The entire Phase 18 stack uses existing Laravel and PHP capabilities. The module adds wiring and orchestration, not new technology.

## Common Pitfalls

### Pitfall 1: Module Provider Not Loading

**What goes wrong:** Module is built but routes return 404, config returns null defaults, migrations never run.
**Why it happens:** ResailAI module has a `composer.json` with `extra.laravel.providers` but is NOT discovered automatically (main `composer.json` doesn't reference it as a package). The ResailAI routes actually work because they're duplicated in `routes/api.php` directly. The ServiceProvider may not be loading at all.
**How to avoid:** Register the DotwAI provider directly in `bootstrap/providers.php` (the only reliable method). Also register the middleware alias in `bootstrap/app.php`. Do NOT rely on the module's own `composer.json` for auto-discovery.
**Warning signs:** `config('dotwai.xxx')` returns null. Artisan commands not appearing in `php artisan list`. Routes 404.

### Pitfall 2: Phone Number Format Mismatch

**What goes wrong:** WhatsApp sends `+96599800027`, database stores `99800027` with `country_code` = `+965`. Direct match fails.
**Why it happens:** Agent phone numbers are stored as `phone_number` (local format) + `country_code` (prefix). WhatsApp sends the full international format. The existing `HotelSearchService::findCompanyIdByPhone()` handles this with `CONCAT(country_code, phone_number)` but the `+` prefix may or may not be stored.
**How to avoid:** Normalize the incoming phone number: strip leading `+`, then try exact match, then try `CONCAT(country_code, phone_number)` both with and without `+`.
**Warning signs:** Searches always fall back to default credentials instead of per-company credentials.

### Pitfall 3: Hotel Import Column Name Variance

**What goes wrong:** DOTW Excel file has column headers like `HotelId` or `hotel_id` or `Hotel ID` or `productId`. Import fails because it expects one exact format.
**Why it happens:** DOTW provides data exports in various formats over time. Column naming is inconsistent.
**How to avoid:** Build the import with a column mapping strategy that normalizes headers. Use `WithHeadingRow` from Maatwebsite and map common variations: `['hotel_id', 'hotelid', 'id', 'productid', 'product_id']` all resolve to `dotw_hotel_id`.
**Warning signs:** Import runs but all fields are null. Import throws "undefined index" errors.

### Pitfall 4: Levenshtein Memory Explosion on Large Hotel Sets

**What goes wrong:** Fuzzy matching loads all 25,000+ hotels and computes Levenshtein distance for each. Memory exhaustion or extreme slowness.
**Why it happens:** `DotwAIHotel::all()->sortBy(fn($h) => levenshtein(...))` loads the entire table.
**How to avoid:** Pre-filter by city when city is known (reduces set to ~200-500 hotels). Use `LIKE` first to narrow to 50 candidates, then Levenshtein only on those 50. Never load all hotels for fuzzy matching.
**Warning signs:** Search endpoint takes > 5 seconds. PHP memory limit errors.

### Pitfall 5: Cache Key Collision Across Phone Numbers

**What goes wrong:** Two users with similar phone numbers get each other's cached search results.
**Why it happens:** Phone number normalization creates identical keys, or the key format doesn't include enough specificity.
**How to avoid:** Use full normalized phone number (digits only) as cache key: `dotwai:search:96599800027`. Include a hash of the search parameters if you want per-search-criteria caching.
**Warning signs:** User sees results they didn't search for. "Book option 1" references wrong hotel.

### Pitfall 6: DOTW API Down During City/Country Sync

**What goes wrong:** `dotwai:sync-static` command runs but DOTW API is unreachable. The sync truncates old data before inserting new data, leaving empty tables. All searches fail because city resolution returns null.
**Why it happens:** Truncate-before-insert is a common sync anti-pattern. Network issues or DOTW maintenance windows happen.
**How to avoid:** Use upsert pattern (`updateOrCreate`), never truncate. If the API call fails, abort the sync and keep existing data. Log the failure, retry on next schedule run.
**Warning signs:** `dotwai_cities` table is empty after a sync run. All city resolutions return null.

### Pitfall 7: Search Returns Too Many Results for WhatsApp

**What goes wrong:** DOTW search returns 200+ hotels for "Dubai". WhatsApp message with 200 hotels is unreadable.
**Why it happens:** No result limit applied. City-only search without hotel name filter returns everything.
**How to avoid:** Limit search results to configurable top N (recommend 5-10, configurable in `dotwai.php`). Sort by star rating then price. Include "showing top N of M results" in the WhatsApp message. When hotel name is provided, fuzzy match locally first, then search DOTW with specific hotel IDs (batches of 50).
**Warning signs:** WhatsApp messages exceeding 4096 character limit (WhatsApp max). Very long API response times.

## Code Examples

### Phone Number Resolution (based on existing HotelSearchService)

```php
// Source: app/Services/HotelSearchService.php lines 71-101 (existing pattern)
use App\Models\Agent;
use App\Models\CompanyDotwCredential;
use Illuminate\Support\Facades\DB;

class PhoneResolverService
{
    public function resolve(string $phone): ?DotwAIContext
    {
        // Normalize phone: strip + and leading zeros
        $normalized = ltrim(preg_replace('/[^0-9]/', '', $phone), '0');

        $agent = Agent::where('phone_number', $phone)
            ->orWhere('phone_number', $normalized)
            ->orWhere(DB::raw("CONCAT(country_code, phone_number)"), $phone)
            ->orWhere(DB::raw("CONCAT(country_code, phone_number)"), $normalized)
            ->first();

        if (!$agent) return null;

        $companyId = $agent->branch?->company_id;
        if (!$companyId) return null;

        $credentials = CompanyDotwCredential::forCompany($companyId)->first();
        if (!$credentials) return null;

        return new DotwAIContext(
            agent: $agent,
            companyId: $companyId,
            credentials: $credentials,
            track: $credentials->markup_percent > 0 ? 'b2c' : 'b2b',
            markupPercent: $credentials->markup_percent,
        );
    }
}
```

### Hotel Excel Import (Maatwebsite Excel)

```php
// Source: Maatwebsite Excel 3.1 documentation + existing AgentsImport pattern
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use App\Modules\DotwAI\Models\DotwAIHotel;

class HotelsImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    private array $columnMap;

    public function __construct()
    {
        // Map common DOTW Excel column name variations
        $this->columnMap = [
            'dotw_hotel_id' => ['hotel_id', 'hotelid', 'id', 'productid', 'product_id'],
            'name' => ['hotel_name', 'hotelname', 'name'],
            'city' => ['city', 'city_name', 'cityname'],
            'country' => ['country', 'country_name', 'countryname'],
            'star_rating' => ['stars', 'star_rating', 'starrating', 'classification'],
            'address' => ['address', 'hotel_address'],
            'latitude' => ['latitude', 'lat'],
            'longitude' => ['longitude', 'lng', 'lon'],
        ];
    }

    public function model(array $row): ?DotwAIHotel
    {
        $mapped = $this->mapRow($row);
        if (!$mapped['dotw_hotel_id']) return null;

        return DotwAIHotel::updateOrCreate(
            ['dotw_hotel_id' => $mapped['dotw_hotel_id']],
            $mapped
        );
    }

    public function batchSize(): int { return 500; }
    public function chunkSize(): int { return 1000; }
}
```

### Fuzzy Hotel Name Matching

```php
// Source: Existing DotwCity/DotwCountry resolution patterns in SKILL.md
public function findHotels(string $query, ?string $city = null, int $limit = 10): Collection
{
    $normalizedQuery = strtolower(trim($query));

    // Step 1: LIKE match (fast, uses index)
    $builder = DotwAIHotel::query();
    if ($city) {
        $builder->where('city', 'LIKE', "%{$city}%");
    }

    $likeResults = $builder->where('name', 'LIKE', "%{$normalizedQuery}%")
        ->limit($limit)
        ->get();

    if ($likeResults->isNotEmpty()) {
        return $likeResults;
    }

    // Step 2: Levenshtein fallback on city-filtered subset (never all hotels)
    $candidates = DotwAIHotel::query()
        ->when($city, fn($q) => $q->where('city', 'LIKE', "%{$city}%"))
        ->limit(500) // Safety limit
        ->get();

    return $candidates->sortBy(function ($hotel) use ($normalizedQuery) {
        return levenshtein($normalizedQuery, strtolower($hotel->name));
    })->take($limit);
}
```

### WhatsApp Message Formatting

```php
// Pure function: takes search results, returns formatted Arabic/English text
public static function formatSearchResults(array $hotels, string $currency): string
{
    $lines = [];
    $lines[] = "نتائج البحث | Search Results";
    $lines[] = str_repeat('─', 30);

    foreach ($hotels as $i => $hotel) {
        $stars = str_repeat('*', (int) ($hotel['star_rating'] ?? 0)); // Unicode stars not supported in all phones
        $number = $i + 1;
        $lines[] = "{$number}. {$hotel['name']}";
        $lines[] = "   {$stars} | {$hotel['city']}";
        $lines[] = "   {$currency} {$hotel['price_from']} - {$hotel['meal_plan'] ?? 'Room Only'}";
        if (!empty($hotel['is_refundable'])) {
            $lines[] = "   قابل للاسترداد | Refundable";
        }
        $lines[] = "";
    }

    $lines[] = "للتفاصيل اكتب رقم الفندق | For details, type the hotel number";

    return implode("\n", $lines);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| GraphQL for n8n tools | REST endpoints | Phase 18 decision | Simpler n8n tool definitions, one URL per tool |
| Search in `map_data_citytour` MapHotel | Own `dotwai_hotels` table in primary DB | Phase 18 decision | Module isolation, no cross-database dependency |
| Phone resolution inline in controllers | Middleware + DTO pattern | Phase 18 | DRY, consistent auth across all endpoints |
| TBO-style single-call search | DOTW 3-step search (search -> browse -> block) | DOTW V4 certification | More complex but mandatory per DOTW spec |

**Deprecated/outdated:**
- The existing `SearchDotwHotelRooms` GraphQL query mentioned in the SKILL.md is a reference design only -- Phase 18 implements REST endpoints instead.
- The SKILL.md `laravel-files.md` DotwService reference code differs from the actual `DotwService.php` (actual uses `wrapRequest()` method, per-company credential resolution, and audit logging). Always reference the real code.

## Open Questions

1. **DOTW Hotel Excel File Format**
   - What we know: CONTEXT.md says "import from DOTW Excel/CSV file" with fields `dotw_hotel_id, name, city, country, star_rating, address, latitude, longitude`.
   - What's unclear: The actual Excel file from DOTW has not been provided yet. Column names and format are unknown.
   - Recommendation: Build flexible importer with column name normalization (multiple name variants per field). Include a `--preview` flag on the artisan command to display first 5 rows for verification before import.

2. **B2B vs B2C Track Determination**
   - What we know: `CompanyDotwCredential` has `markup_percent` (0 for B2B, 20 for B2C). CONTEXT.md says track should be resolved.
   - What's unclear: Is track determined per-company (company is always B2B or always B2C), or can it vary per request? Is there a `booking_type` field planned for credentials?
   - Recommendation: Add `b2b_enabled` and `b2c_enabled` boolean columns to `CompanyDotwCredential` or create a new `dotwai_company_settings` table. For Phase 18, the search endpoint receives `bookingType` in the request (from n8n) and validates it against company settings.

3. **Browse-Only vs Block in Phase 18 Search**
   - What we know: The SKILL.md describes the full search->browse->block 3-step flow in the search query. Phase 18 builds search endpoints. Phase 19 builds booking.
   - What's unclear: Should Phase 18 search block rates (creating prebook records), or only browse (returning informational results)?
   - Recommendation: Phase 18 search should do browse-only (no rate blocking, no prebook creation). Return informational prices and room details. Rate blocking happens in Phase 19's `prebook_hotel` endpoint. This simplifies Phase 18 and avoids wasting DOTW allocations on browsing users.

4. **Search By Hotel Name: Local-First vs DOTW-First**
   - What we know: CONTEXT.md says "Search by hotel name: fuzzy match locally -> get DOTW hotel IDs -> call DotwService::searchHotels with hotelId filter (batch 50)".
   - What's unclear: What if the hotel isn't in the local `dotwai_hotels` table yet (import hasn't happened or is outdated)?
   - Recommendation: Local fuzzy match as primary path. If no local match found and city is known, fall back to DOTW city search and filter results by hotel name string match. This ensures the system works even before the first Excel import.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Runtime | Yes | 8.2.12 | -- |
| Laravel | Framework | Yes | 11.39.1 | -- |
| Composer | Dependencies | Yes | 2.9.5 | -- |
| MySQL (laravel_testing) | Primary DB | Yes (assumed) | -- | -- |
| maatwebsite/excel | Hotel import | Yes | 3.1.67 | -- |
| DOTW API (xmldev) | Search, city list | Yes (external) | V4 | -- |
| Levenshtein (PHP built-in) | Fuzzy matching | Yes | Built-in | -- |
| Laravel Cache | Result caching | Yes | Built-in (file driver) | -- |

**Missing dependencies with no fallback:** None.

**Missing dependencies with fallback:** None.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit (via `php artisan test`) |
| Config file | `phpunit.xml` (exists, uses `pgsql_testing` connection) |
| Quick run command | `php artisan test --filter DotwAI` |
| Full suite command | `php artisan test` |

### Phase Requirements -> Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| FOUND-01 | DotwAI ServiceProvider registers config + routes + migrations | unit | `php artisan test --filter DotwAIServiceProviderTest -x` | Wave 0 |
| FOUND-02 | Config has B2B/B2C toggle defaults | unit | `php artisan test --filter DotwAIConfigTest -x` | Wave 0 |
| FOUND-03 | Phone number resolves to agent/company/credentials | unit | `php artisan test --filter PhoneResolverServiceTest -x` | Wave 0 |
| FOUND-04 | Hotel import from Excel creates records | unit | `php artisan test --filter HotelImportTest -x` | Wave 0 |
| FOUND-05 | Fuzzy matching resolves "Hilton Dubai" to hotel IDs | unit | `php artisan test --filter FuzzyMatcherServiceTest -x` | Wave 0 |
| FOUND-06 | System message template loads from config | unit | `php artisan test --filter DotwAIConfigTest::testSystemMessageTemplate -x` | Wave 0 |
| SRCH-01 | search_hotels returns hotel list with whatsappMessage | feature | `php artisan test --filter SearchHotelsEndpointTest -x` | Wave 0 |
| SRCH-02 | get_hotel_details returns rooms/rates/policies | feature | `php artisan test --filter GetHotelDetailsEndpointTest -x` | Wave 0 |
| SRCH-03 | get_cities returns city list | feature | `php artisan test --filter GetCitiesEndpointTest -x` | Wave 0 |
| SRCH-04 | Search results cached per phone number | unit | `php artisan test --filter SearchCacheTest -x` | Wave 0 |
| SRCH-05 | Multi-room occupancy passed correctly | unit | `php artisan test --filter OccupancyHandlingTest -x` | Wave 0 |
| SRCH-06 | Filters (star, meal, price, refundable, name) work | unit | `php artisan test --filter SearchFiltersTest -x` | Wave 0 |
| EVNT-02 | Every response has whatsappMessage field | feature | `php artisan test --filter ResponseEnvelopeTest -x` | Wave 0 |
| EVNT-03 | Error responses have suggestedAction | feature | `php artisan test --filter ErrorResponseTest -x` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter DotwAI`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before verification

### Wave 0 Gaps
- [ ] `tests/Feature/DotwAI/SearchHotelsEndpointTest.php` -- covers SRCH-01, SRCH-04, SRCH-05, SRCH-06, EVNT-02, EVNT-03
- [ ] `tests/Feature/DotwAI/GetHotelDetailsEndpointTest.php` -- covers SRCH-02
- [ ] `tests/Feature/DotwAI/GetCitiesEndpointTest.php` -- covers SRCH-03
- [ ] `tests/Unit/DotwAI/PhoneResolverServiceTest.php` -- covers FOUND-03
- [ ] `tests/Unit/DotwAI/FuzzyMatcherServiceTest.php` -- covers FOUND-05
- [ ] `tests/Unit/DotwAI/HotelImportTest.php` -- covers FOUND-04
- [ ] `tests/Unit/DotwAI/MessageBuilderServiceTest.php` -- covers EVNT-02 formatting
- [ ] `tests/Unit/DotwAI/DotwAIResponseTest.php` -- covers EVNT-02, EVNT-03 envelope
- [ ] Test database seeding for agents, companies, credentials (shared fixture)

**Note:** Feature tests for search endpoints will need to mock `DotwService` since tests cannot call the real DOTW API. Use Laravel's service container to bind a mock in tests.

## Project Constraints (from CLAUDE.md)

- Follow PSR-12 coding standards
- Use type hints throughout
- Comprehensive PHPDoc comments
- Eloquent ORM (no raw SQL unless necessary)
- Request validation for all endpoints
- Always use migrations (never manual schema changes)
- Foreign keys for relationships (but module uses soft FKs to maintain isolation)
- Indexes on frequently queried columns
- Run `./vendor/bin/phpstan analyse` and `./vendor/bin/pint` before committing
- Run `php artisan test` before committing
- Never commit `.env` files
- Validate all inputs
- Git branch naming: `feature/` prefix for new features

## Sources

### Primary (HIGH confidence)
- `app/Modules/ResailAI/Providers/ResailAIServiceProvider.php` -- exact module registration pattern (mergeConfigFrom, loadRoutesFrom)
- `app/Modules/ResailAI/Middleware/VerifyResailAIToken.php` -- middleware pattern for request auth + context attachment
- `bootstrap/app.php` -- middleware alias registration pattern (`'verify.resailai.token' => VerifyResailAIToken::class`)
- `bootstrap/providers.php` -- provider registration (currently has 3 providers)
- `app/Services/DotwService.php` (2,232 lines) -- DOTW API wrapper with per-company credentials, searchHotels, getRooms, getCityList, getCountryList, getSalutationIds
- `app/Services/HotelSearchService.php` lines 71-101 -- `findCompanyIdByPhone()` phone resolution pattern
- `app/Models/CompanyDotwCredential.php` -- encrypted credentials, `scopeForCompany()`, `markup_percent`, `is_active`
- `app/Models/Agent.php` -- `phone_number`, `country_code`, `branch->company_id` chain
- `app/Models/DotwPrebook.php` -- existing prebook model (will not modify, but search stores results here in future phases)
- `config/dotw.php` -- existing DOTW config (will not modify)
- `composer.json` -- confirms maatwebsite/excel 3.1.x installed
- `.planning/research/STACK.md` -- zero new dependencies confirmed
- `.planning/research/ARCHITECTURE.md` -- module structure, bridge patterns, 39 new files
- `.planning/research/PITFALLS.md` -- 20 pitfalls with prevention strategies

### Secondary (MEDIUM confidence)
- `.claude/skills/dotwai/SKILL.md` -- module architecture reference design
- `.claude/skills/dotwai/references/laravel-files.md` -- migration schemas, model code (reference, not actual)
- `.claude/skills/dotwai/references/n8n-tools.md` -- n8n tool definitions
- `.claude/skills/dotwai/references/dotw-mapping.md` -- meal type, special promotion, changed occupancy mapping

### Tertiary (LOW confidence)
- DOTW hotel Excel file format -- not yet available, assumed based on CONTEXT.md description

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- zero new dependencies, all capabilities verified from existing codebase
- Architecture: HIGH -- ResailAI module pattern proven, DotwService wrapper pattern proven, phone resolution pattern exists
- Pitfalls: HIGH -- based on direct codebase analysis of ResailAI loading mechanism, DotwService API signatures, Agent model schema

**Research date:** 2026-03-24
**Valid until:** 2026-04-24 (stable -- no external dependency version changes expected)
