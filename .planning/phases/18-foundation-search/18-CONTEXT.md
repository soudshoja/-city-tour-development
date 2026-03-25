# Phase 18: Foundation + Search - Context

**Gathered:** 2026-03-24
**Status:** Ready for planning
**Source:** Milestone v2.0 discussion + /dotwai skill + research

<domain>
## Phase Boundary

Build the DotwAI module foundation and search endpoints. After this phase: n8n AI agents can resolve phone numbers to companies, search hotels by city/name/filters, browse room details, and receive WhatsApp-ready formatted responses. Hotel static data is importable from DOTW Excel files.

Requirements: FOUND-01 through FOUND-06, SRCH-01 through SRCH-06, EVNT-02, EVNT-03 (14 total)

</domain>

<decisions>
## Implementation Decisions

### Module Structure
- Self-contained at app/Modules/DotwAI/ with own ServiceProvider, config, routes, models
- Follow existing ResailAI module pattern (app/Modules/ResailAI/)
- Register in bootstrap/providers.php and bootstrap/app.php (middleware alias) — only 2 existing files touched
- Own route file: app/Modules/DotwAI/Routes/api.php (REST API, NOT GraphQL)
- Own config: config/dotwai.php (per-company B2B/B2C enable/disable, markup, defaults)

### REST API Design (NOT GraphQL)
- 11 REST endpoints total (this phase builds 4: search_hotels, get_hotel_details, get_cities + foundation)
- All endpoints under /api/dotwai/ prefix
- Every response includes: success, data, whatsappMessage (pre-formatted Arabic/English), whatsappOptions, error with suggestedAction
- Authentication via phone number → agent → company → DOTW credentials resolution
- No GraphQL — REST is simpler for n8n AI agent tool definitions

### Hotel Static Data
- Import from DOTW Excel/CSV file via artisan command: php artisan dotwai:import-hotels {file}
- Store in local database table (dotwai_hotels) — NOT in map_data_citytour MapHotel table
- Fields: dotw_hotel_id, name, city, country, star_rating, address, latitude, longitude
- Fuzzy matching via LIKE queries or full-text search for hotel name resolution
- "Hilton Dubai" → find matching hotels → return DOTW hotel IDs

### Phone Number Resolution
- Phone → Agent model (existing) → Company → CompanyDotwCredential → DOTW credentials
- Also resolves: B2B enabled?, B2C enabled?, markup percentage, credit line availability
- Returns a DotwAIContext object with all resolved data for the request

### Search Results Caching
- After search, results stored in cache keyed by phone number (Cache::put with TTL)
- Client says "book option 1" → system retrieves cached results, picks option 1
- Cache TTL: 10 minutes (longer than DOTW 3-min block, gives user time to browse)

### WhatsApp Message Formatting
- Every endpoint returns whatsappMessage field — pre-formatted text ready to send
- Arabic is primary language, English secondary
- Hotel search results formatted as numbered list with stars, price, meal plan
- Error messages are human-friendly, not technical
- whatsappOptions: suggested follow-up actions for the AI to present

### AI System Message Template
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

</decisions>

<canonical_refs>
## Canonical References

### DotwAI Skill (Application Architecture)
- `.claude/skills/dotwai/SKILL.md` — Module architecture, booking tracks, file structure
- `.claude/skills/dotwai/references/laravel-files.md` — Complete Laravel file blueprints
- `.claude/skills/dotwai/references/n8n-tools.md` — n8n tool definitions for all 11 endpoints
- `.claude/skills/dotwai/references/graphql-schema.md` — GraphQL schema (reference only — we use REST)
- `.claude/skills/dotwai/references/dotw-mapping.md` — DOTW XML response → output field mapping

### DOTW API Skill (XML API)
- `.claude/skills/dotw-api/SKILL.md` — DOTW V4 XML API overview, mandatory elements
- `.claude/skills/dotw-api/references/api-methods.md` — Complete XML templates for all methods
- `.claude/skills/dotw-api/references/best-practices.md` — Certification requirements

### Existing Code (wrap, don't modify)
- `app/Services/DotwService.php` — DOTW XML API wrapper (2,232 lines) — call this, don't duplicate
- `app/Models/CompanyDotwCredential.php` — Per-company DOTW credentials
- `app/Models/DotwPrebook.php` — Existing prebook model (may extend or create new)
- `app/Modules/ResailAI/` — Module pattern to follow (ServiceProvider, config, routes)
- `config/dotw.php` — Existing DOTW config (don't modify, create own config/dotwai.php)

### Research
- `.planning/research/STACK.md` — Zero new dependencies needed
- `.planning/research/FEATURES.md` — Feature landscape, table stakes
- `.planning/research/ARCHITECTURE.md` — Integration architecture, 7 new tables
- `.planning/research/PITFALLS.md` — 20 pitfalls with prevention strategies
- `.planning/research/SUMMARY.md` — Executive summary

</canonical_refs>

<specifics>
## Specific Ideas

- n8n calls /api/dotwai/search with phone number, city, dates, occupancy — Laravel does ALL heavy lifting
- Search by hotel name: fuzzy match locally → get DOTW hotel IDs → call DotwService::searchHotels with hotelId filter (batch 50)
- Search by city: call DotwService::searchHotels with city code → return top results
- get_hotel_details: calls DotwService::getRooms (browse mode) for a specific hotel
- get_cities: wraps DotwService::getCityList with country code
- AI model: Qwen3-Next:80B on Ollama cloud — system message template provided by module
- DOTW hotel Excel file format TBD — build flexible importer that handles common column layouts

</specifics>

<deferred>
## Deferred Ideas

- Booking endpoints (Phase 19)
- Payment integration (Phase 19)
- Cancellation (Phase 20)
- Accounting integration (Phase 20)
- Lifecycle automation / reminders (Phase 21)
- Booking history / vouchers (Phase 21)
- Monitoring dashboard (Phase 22)
- Multi-supplier aggregation (Future)

</deferred>

---

*Phase: 18-foundation-search*
*Context gathered: 2026-03-24 via milestone discussion + research*
