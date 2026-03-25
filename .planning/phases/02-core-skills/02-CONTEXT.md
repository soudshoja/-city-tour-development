# Phase 2: Core Skills - Context

**Gathered:** 2026-03-09
**Status:** Ready for planning
**Source:** Project files and Phase 1 API research

---

## Phase Boundary

Create production-ready hotel search and booking skills using the DOTWconnect API. These skills will be packaged as Claude skills (.skill format) that generate correct, testable PHP/Laravel code for integration into Soud Laravel.

**Deliverables:**
- `~/.claude/skills/dotwconnect-hotel-search/` — Search skill with test cases
- `~/.claude/skills/dotwconnect-booking/` — Booking skill with test cases
- Skill documentation and integration examples

---

## Implementation Decisions

### API Integration
- **Source:** DOTWconnect v4 XML API (https://xmldev.dotwconnect.com)
- **Auth:** MD5-encrypted password in XML requests
- **Protocol:** HTTPS POST only

### Core Workflows to Support

#### 1. Hotel Search (SEARCH-01, SEARCH-02, SEARCH-03, SEARCH-04)
- Search hotels by city/country, check-in/out dates
- Filter by room count, guest counts
- Support pagination and filtering
- Extract room details, pricing, amenities

#### 2. Booking Management (BOOK-01, BOOK-02)
- **Immediate Flow:** searchHotels → getRooms (preview) → getRooms (blocking) → confirmBooking
- **Deferred Flow:** searchHotels → getRooms → savebooking → bookItinerary
- Handle 3-minute rate lock requirement
- Generate confirmation numbers

### Skill Generation Requirements
- **Output:** Production-ready PHP/Laravel code snippets
- **Code patterns:** Match Soud Laravel standards (Eloquent ORM, Models, Services)
- **Error handling:** Timeout handling, XML parsing errors, validation
- **Testing:** Include realistic test cases with mock data

### Critical v4 Changes (from Phase 1 research)
1. getRooms is now MANDATORY (was optional in v3)
2. Blocking step is MANDATORY — must lock rates before confirming
3. allocationDetails token required for confirmBooking/savebooking
4. 3-minute rate lock is a hard limit

### Security & Performance
- **Security:** No plain-text passwords in logs/debug, secure token handling
- **Performance:** Pagination for large hotel lists, caching for reference data, connection pooling
- **Integration:** Should work with existing Soud Laravel authentication and multi-tenant system

---

## Claude's Discretion

### Skill Architecture
- How to structure skill prompts for maximum code generation accuracy
- Test case design and realistic mock data
- Error handling strategies for rate locks and timeouts
- Response parsing and validation patterns

### Code Generation
- PHP client library abstraction (should skills generate raw XML or wrapper classes?)
- Laravel model/service integration patterns
- Database schema for booking persistence
- Queue job strategies for async confirmations

### Testing Strategy
- Unit test vs integration test split
- Mock vs sandbox API usage
- Test data generation for different hotel scenarios
- Edge case coverage (expired rates, invalid guests, etc.)

---

## Specific Ideas

### Skill File Structure
```
~/.claude/skills/dotwconnect-hotel-search/
  SKILL.md                    # Skill definition
  examples/                   # Usage examples
    basic-search.md
    advanced-filters.md
    pagination.md
  tests/
    search.test.md           # Test cases
  resources/
    API_REFERENCE.md         # Copy from Phase 1 research
```

### Integration with Soud Laravel
- Use existing `Company` → `Branch` → `Agent` multi-tenant structure
- Store booking references in existing `tasks` table
- Use existing payment gateway integrations for booking confirmation
- Leverage existing WhatsApp/Email systems for confirmations

### Certification & Compliance
- Skills should note XML Certification Test Plan requirements (from Phase 1)
- Include security checklist (MD5 hashing, HTTPS, token handling)
- Reference Web Services Certificate requirements

---

## Deferred Ideas

### Phase 3 & Beyond
- Itinerary updates and deletions (BOOK-03, BOOK-04)
- Reference data operations (REF-01-04)
- Advanced features: Rate caching, multi-property groups, real-time polling
- Mobile app integration

### Out of Scope (v1)
- Direct payment processing (handled by Laravel app)
- Real-time rate updates
- Multi-language processing

---

*Phase: 02-core-skills*
*Context created: 2026-03-09*
*Ready for technical research and planning*
