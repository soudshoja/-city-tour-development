# Quick Reference: Skill Architecture Summary

## The Golden Rules for Production-Ready Skills

### 1. Organization
```
Overview → Config → Endpoints → Errors → Service Code → Testing → Security
```

### 2. Trigger Patterns
```yaml
Use 6-10 concrete triggers:
- MyFatoorah, KNET, Laravel payment (specific to generic)
- Include problem language + product language
```

### 3. API Documentation
✅ **Show everything:**
- Exact header format (Authorization: Bearer...)
- Complete request JSON/XML (not prose)
- Both success AND error responses (actual JSON)
- Field-level comments (3 decimals for KWD, etc.)

❌ **Don't write prose:**
- "The endpoint creates an invoice"
- "Check for errors in the response"

### 4. Service Code
- Production-ready (no pseudocode)
- Error handling included
- Clear method responsibilities
- Full PHPDoc comments

### 5. Test Patterns (3 tiers)
1. Unit tests (single function)
2. Integration tests (service + mocks)
3. End-to-end tests (full workflow)

### 6. Error Documentation
For each error:
- When it occurs
- Actual API response format
- How to handle (code)
- User-friendly message (for N8N)

## The Three Failure Modes

| Mode | Problem | Solution |
|------|---------|----------|
| Missing Context | API detail not documented | Document actual response JSON |
| Wrong Abstraction | Service too generic/specific | Show complete service class |
| Error Gaps | Only success path documented | Include all error responses |

## Examples Worth Studying

| Skill | Pattern | Why It Works |
|-------|---------|--------------|
| MyFatoorah | Payment flow endpoints | Concrete examples, error responses |
| Resayil | Message templates | Real bilingual examples, edge cases |
| DOTWconnect (to build) | Multi-step workflow | Stateful (allocation tokens), complex data |

## For DOTWconnect Skill

### Must Include
1. XML request/response examples (searchHotels, blockRates, confirmBooking)
2. Rate blocking mechanics (3-min allocation, state transitions)
3. Error scenarios (rate sold out, allocation expired)
4. Complete service class (300-500 lines)
5. Message tracking integration (resayil_message_id)
6. B2C markup implementation
7. Full test workflow (search → rates → block → book)

### Skill Sections (In Order)
1. Overview
2. Configuration
3. Core Operations (searchHotels, getRoomRates, blockRates, confirmBooking)
4. Data Structures
5. Error Handling
6. Service Class
7. Caching & Performance
8. Message Tracking
9. B2C Markup
10. GraphQL Integration (if applicable)
11. Testing
12. Security & Configuration

## Success Criteria

- [ ] Claude can generate complete booking workflow without asking questions
- [ ] Code includes proper error handling (not just happy path)
- [ ] Message tracking (resayil_message_id) integration is clear
- [ ] Caching strategy (2.5 min search, 3 min block) is explicit
- [ ] Test cases cover happy path + error scenarios

---

**Quick Wins:**
- Copy MyFatoorah structure, adapt for DOTW operations
- Use Resayil's message tracking as template for audit logging
- Include complete service class (helps Claude generate correct code)
- Show actual XML/JSON (not descriptions)
