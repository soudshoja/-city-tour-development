# Prompt sent to supplementary agent — Cross-Cutting Golden-Rules Integration Audit

- **Model**: fable
- **Why this agent exists**: coverage review of the original 7-dimension fan-out found one
  structural blind spot — the 7 agents each audit ONE reference file, so nobody audits the
  SKILL.md top page's 8 "golden rules" as END-TO-END system-wide invariants. Rule 7 in
  particular ("feeders emit documents, never invent accounting" — i.e. is there ONE shared
  posting engine, or does every controller hand-roll journal entries?) is only visible by
  enumerating ALL journal-entry-creating call sites across the whole codebase, which no
  per-dimension agent does.
- **Spec given**: `C:/Users/User/OneDrive - City Travelers/soud-laravel/.claude/skills/travel-accounting-system/SKILL.md` (top page, golden rules section)
- **Codebase checked read-only at**: `C:/Users/User/AppData/Local/Temp/claude/C--Users-User-OneDrive---City-Travelers-soud-laravel/001d2669-f340-4d6d-9568-2dee0f332d34/scratchpad/citytourv2-main-checkout`
- **Output file it was told to write**: `C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/golden-rules-integration.md`

## Full prompt text

```
You are performing the CROSS-CUTTING integration audit of the accounting implementation in the "citytourv2" Laravel 11 travel-agency codebase, checked out read-only at:
C:/Users/User/AppData/Local/Temp/claude/C--Users-User-OneDrive---City-Travelers-soud-laravel/001d2669-f340-4d6d-9568-2dee0f332d34/scratchpad/citytourv2-main-checkout

Seven parallel agents are each auditing ONE reference file of the "travel-accounting-system" skill (COA, posting engine, transactions/AR-AP, travel-industry, reporting, data model, modules/config). YOUR job is different: audit the system-wide invariants that no per-dimension agent can see — the "golden rules" from the skill's top page at:
C:/Users/User/OneDrive - City Travelers/soud-laravel/.claude/skills/travel-accounting-system/SKILL.md

Read that SKILL.md in full first. Then verify each of the 8 golden rules END-TO-END across the whole codebase:

1. **One tree, post only to leaves** — is there any enforcement anywhere that group/header accounts never receive journal entries? Is account type derived from tree position or hand-typed?
2. **Every transaction is one header + N balanced lines** — is there a document/transaction header model at all, or do journal entries float free? Is Σdebit=Σcredit enforced anywhere (DB constraint, service, observer, validation)?
3. **Base AND original currency on every line** — do journal entry lines store both foreign amount + rate + base amount? Are rates effective-dated or a single mutable number?
4. **Eager balance maintenance + reverse-before-reapply** — are balances maintained on write or recomputed at read? On edit/delete of financial documents, is the old posting reversed before the new one applies — check EVERY edit/delete path: invoice update, payment update, refund edit, invoice delete, payment delete. This is where real systems silently corrupt balances.
5. **Posted-only + opening journal** — is there a draft/posted distinction? Period locking? A year-end opening journal mechanism?
6. **Open-item AR/AP via apply** — is outstanding always derived as invoice − applied, or are there parallel "balance" fields that can drift? Look for double bookkeeping between Credit, PaymentApplication, Invoice.paid_amount-style fields, and journal entries.
7. **Feeders emit documents, never invent accounting** — THE critical integration check. Enumerate EVERY code path that creates journal entries (grep for JournalEntry::create, ->journalEntries()->create, insert into journal_entries, etc). Is there ONE shared posting service they all call, or does each controller/job hand-roll its own debit/credit logic? List every distinct call site and classify: goes through shared engine vs hand-rolled. Hand-rolled sites are where books diverge.
8. **Everything dimensioned and audited** — do journal lines carry branch/cost-center? Is there any audit/log table mirroring financial mutations?

For each rule: verdict (enforced / partially enforced / not enforced / violated with evidence), concrete file:line citations, code excerpts for violations, and severity (critical/high/medium/low) + concrete recommendation.

Write your full detailed report using the Write tool to EXACTLY this path:
C:/Users/User/OneDrive - City Travelers/soud-laravel/Accounting Gap/golden-rules-integration.md

Structure: one section per golden rule. Same audit-artifact standard as the other reports: a developer must be able to implement fixes directly from it. At the top include a summary table (rule | verdict | severity of worst finding).

Your final text reply: a compact summary — per-rule verdicts, count of critical/high findings, and the single worst integration violation you found.
```
