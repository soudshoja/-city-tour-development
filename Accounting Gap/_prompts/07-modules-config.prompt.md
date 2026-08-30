# Prompt sent to Analyze-phase agent — Modules & Config

- **Model**: fable
- **Label**: analyze:07-modules-config
- **Reference file given as spec**: `C:/Users/User/OneDrive - City Travelers/soud-laravel/.claude/skills/travel-accounting-system/references/07-modules-and-config.md`
- **Codebase checked out read-only at**: `C:/Users/User/AppData/Local/Temp/claude/C--Users-User-OneDrive---City-Travelers-soud-laravel/001d2669-f340-4d6d-9568-2dee0f332d34/scratchpad/citytourv2-main-checkout`
- **Output file it was told to write**: `C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/07-modules-config.md`

## Full prompt text

```
You are auditing the accounting/back-office implementation of the "citytourv2" Laravel 11 travel-agency codebase, checked out read-only at:
C:/Users/User/AppData/Local/Temp/claude/C--Users-User-OneDrive---City-Travelers-soud-laravel/001d2669-f340-4d6d-9568-2dee0f332d34/scratchpad/citytourv2-main-checkout

against the ideal blueprint described in this reference file (part of the "travel-accounting-system" skill, distilled from a real mature production system with ~260 tables and ~860 stored procedures):
C:/Users/User/OneDrive - City Travelers/soud-laravel/.claude/skills/travel-accounting-system/references/07-modules-and-config.md

Context: citytourv2 is a full mirror (55 branches) of an upstream repo. You are analyzing the 'main' branch checkout only.
Known accounting-relevant branches NOT merged into main (do not analyze their code, just cross-reference by name if a gap you find matches):
- 'fix/payment-voucher': attempts a Payment Voucher + Reconciliation system (PaymentVoucher, PaymentReconciliation models, a data migration rewriting existing payment/JE data). Unmerged, 161 commits behind main, likely superseded/stale.
- 'fix/rv': attempts renaming invoice_receipt table/model to receipt_vouchers. Unmerged, upstream itself abandoned it ~7 weeks before main's last commit.
- 'agent-settlement': adds AgentSettlement/AgentSettlementDetail/AgentSettlementPayment models + service for agent loss recovery via profit offset or payment gateway. Unmerged into main.
If your gap analysis identifies a missing capability that one of these branches attempts, mention it as "attempted in unmerged branch X, not production-ready" rather than treating it as fully missing with no prior art.

## Task

1. Read the reference file at [ref] in full first — that is your spec for "Modules & Config".
2. Explore [checkout] for the corresponding implementation: models (app/Models), controllers (app/Http/Controllers), services (app/Services), migrations (database/migrations), config (config/*). Use Glob/Grep/Read freely — this is a real Laravel app checkout, not a diff.
3. For EACH distinct capability, rule, or invariant described in the reference file, determine one of: present_ok (fully implemented, matches the blueprint), partial (exists but incomplete/simplified vs the blueprint), buggy (exists but implemented incorrectly or violates a stated invariant — e.g. balance not maintained atomically, missing a required constraint), or missing (no equivalent implementation anywhere).
4. Be concrete: cite exact file paths and, where relevant, class/method names or line numbers. If you conclude something is "missing", you must have searched beyond the obvious location (grep the whole app/ tree for related terms) before concluding — do not hand-wave.
5. Assign a rough completeness_pct (0-100) for this whole dimension.

## Output — TWO things required

A) Call the required structured-output schema with your dimension name, completeness_pct, and the full findings array (title/status/severity/files/description/recommendation for each).

B) ALSO write a full, detailed, human-readable markdown report to this exact file path using the Write tool:
C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/07-modules-config.md

The markdown file must be MORE detailed than the structured findings — for every finding include: what the blueprint requires (quote/paraphrase the relevant section), what you found in the code (with file:line citations and short code excerpts where useful), why it's a gap/bug (or why it's fine), and a concrete recommendation. Structure it with a heading per finding. This file is a permanent audit artifact — write it as if a developer will implement fixes directly from it, without re-reading the blueprint themselves.
```
