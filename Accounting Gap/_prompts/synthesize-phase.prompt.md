# Prompt template — Synthesize phase (final report compiler)

- **Model**: fable
- **Label**: synthesize-final-report
- **Runs**: once, after Analyze + Verify phases both complete

## Full prompt template (placeholders in `[...]` filled from prior-phase results)

```
Compile the final deliverables for an in-depth accounting gap-analysis audit of the "citytourv2" travel-agency Laravel codebase against the "travel-accounting-system" skill blueprint (a mature production IATA/BSP travel agency system with ~260 tables, ~860 stored procedures).

Per-dimension completeness scores:
[list of "- <dimension>: <completeness_pct>%" for each of the 7 dimensions]

CONFIRMED HIGH/CRITICAL FINDINGS (independently re-verified as real):
[JSON array: dimension, title, status, severity, files, description, recommendation, verification_reasoning — one entry per finding that survived the Verify phase]

MEDIUM/LOW FINDINGS (not independently re-verified — report with a caveat that they weren't adversarially checked):
[JSON array of all findings with severity medium or low, as returned by the Analyze-phase agents]

[N] additional high/critical findings were flagged initially but REFUTED on independent verification — excluded from the report entirely.

Detailed per-dimension reports already exist at:
C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/01-chart-of-accounts.md
C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/02-posting-engine.md
C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/03-transactions-ar-ap.md
C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/04-travel-industry.md
C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/05-reporting.md
C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/06-data-model.md
C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/07-modules-config.md
(these contain full file:line citations and code excerpts per finding — do not duplicate that detail here, reference them instead)

## Write exactly these files using the Write tool:

1. C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/00-executive-summary.md
   - 1-paragraph overall maturity assessment of citytourv2's accounting module vs the blueprint
   - A completeness table: dimension | % complete | most critical gap in that dimension
   - Links/references to the other files in this folder (list them with one-line descriptions)

2. C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/08-prioritized-bug-list.md
   - Every CONFIRMED finding with status=buggy, ranked critical > high > medium > low
   - For each: what's wrong, exact files, concrete fix recommendation

3. C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/09-prioritized-missing-features.md
   - Every CONFIRMED finding with status=missing or partial, ranked by how foundational it is (per the blueprint's own 5-layer dependency model: Chart of Accounts -> Posting Engine -> Transactions/feeder modules (AR/AP, travel-industry specifics) -> Reporting; also weight by severity)
   - For each: what's missing, why it matters, dependency on other missing pieces

4. C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/10-implementation-roadmap.md
   - A phased roadmap to bring citytourv2's accounting up to the blueprint standard, respecting dependency order
   - Each phase: goal, what gets built/fixed, which findings it resolves (reference by title), rough complexity (S/M/L/XL)

5. C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/verification-log.md
   - One row per high/critical finding that went through adversarial verification: title, confirmed or refuted, reasoning
   - Include the refuted ones here too (with reasoning why they were refuted) so there's a full audit trail

Be concrete and actionable throughout — a developer should be able to start implementing directly from these files. Return a short text summary (not the full file contents) when done confirming what you wrote.
```

## Note on scope vs the later technical-implementation-plan pass

This synthesis prompt asks for a roadmap (`10-implementation-roadmap.md`), which is
phase/goal-level, not schema/code-level. The user separately asked for a genuinely
buildable **technical** plan (concrete migrations, model/service skeletons, exact
build steps) — that is a distinct follow-up pass, run after this synthesis completes,
consuming its outputs rather than duplicating this prompt.
