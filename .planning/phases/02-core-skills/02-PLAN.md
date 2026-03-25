---
wave: 1
depends_on: []
files_modified:
  - ~/.claude/skills/dotwv4/SKILL.md
  - ~/.claude/skills/dotwv4/evals/evals.json
  - ~/.claude/skills/dotwv4/references/
  - ~/.claude/skills/dotwv4-booking/SKILL.md
autonomous: true
---

# Phase 2: Core Skills

## Objective

Create production-ready Claude skills for DOTWconnect v4 hotel booking operations. Skills must reference actual DotwService implementation and generate correct, testable Laravel code.

**Requirements:** SEARCH-01, SEARCH-02, SEARCH-03, SEARCH-04, BOOK-01, BOOK-02

---

## Tasks

### Wave 1 - Parallel Skill Creation & Testing

<task>
<id>02-01</id>
<name>Complete and test dotwv4 skill</name>
<requirements>SEARCH-01, SEARCH-02, SEARCH-03, SEARCH-04, BOOK-01, BOOK-02</requirements>

**Objective:** Complete the dotwv4 skill, run test cases, validate code generation, iterate based on results.

**Deliverables:**
- Tested and working dotwv4 skill
- 4 test cases with validated outputs
- Documentation with examples
- Ready for packaging

**Success Criteria:**
- ✓ All 4 test cases execute
- ✓ Generated code references exact DotwService signatures
- ✓ Handles all workflows (search, browse, lock, confirm immediate, save & confirm later)
- ✓ Error handling for rate expiration documented
- ✓ Multi-tenant support demonstrated
- ✓ Code compiles and follows Laravel patterns
</task>

<task>
<id>02-02</id>
<name>Create dotwv4-booking skill for advanced workflows</name>
<requirements>BOOK-01, BOOK-02</requirements>

**Objective:** Create complementary booking skill focusing on advanced booking workflows and error recovery.

**Deliverables:**
- dotwv4-booking skill with SKILL.md
- Test cases for deferred booking flow
- Error handling patterns (rate expiration, timeouts)
- Examples for rate lock management

**Success Criteria:**
- ✓ Skill generates correct saveBooking/bookItinerary code
- ✓ Rate lock timing and expiration handling clear
- ✓ Error scenarios documented with recovery patterns
- ✓ Test cases validate multi-step workflows
</task>

<task>
<id>02-03</id>
<name>Package and validate skills</name>
<requirements>SEARCH-01, SEARCH-02, SEARCH-03, SEARCH-04, BOOK-01, BOOK-02</requirements>

**Objective:** Package both skills, validate they work together, create final documentation.

**Deliverables:**
- dotwv4.skill package
- dotwv4-booking.skill package
- Integration guide (when to use each skill)
- README with examples
- Ready for distribution

**Success Criteria:**
- ✓ Both .skill files created
- ✓ Skills load without errors
- ✓ Integration guide shows complete workflows
- ✓ All requirements covered
- ✓ Skills are production-ready
</task>

---

## Verification Criteria

### must_haves (Goal-Backward)

1. **dotwv4 skill exists and is tested**
   - Skill references actual DotwService methods
   - Test cases pass and generate correct code
   - Handles dual getRooms pattern (blocking/non-blocking)
   - Rate lock 3-minute requirement documented

2. **dotwv4-booking skill created**
   - Complements dotwv4 with advanced workflows
   - saveBooking → bookItinerary flow documented
   - Error recovery patterns included

3. **Both skills packaged**
   - .skill files ready for distribution
   - Integration examples show complete workflows
   - Multi-tenant usage documented

### Coverage Check

- SEARCH-01 (Search hotels) ✓ dotwv4
- SEARCH-02 (Room availability) ✓ dotwv4
- SEARCH-03 (Filtering/pagination) ✓ dotwv4
- SEARCH-04 (Room details/pricing) ✓ dotwv4
- BOOK-01 (Save itinerary) ✓ dotwv4-booking
- BOOK-02 (Confirm/complete) ✓ dotwv4, dotwv4-booking

---

*Phase 2 plan: Core Skills creation with parallel skill development*
