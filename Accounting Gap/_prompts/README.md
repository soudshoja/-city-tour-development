# Agent Prompts — Audit Trail

This folder records the exact prompt sent to each agent in the citytourv2 accounting
gap-analysis workflow (`wf_ce18523e-8c7`), so the depth and scope of each check is
verifiable after the fact — not just the conclusions.

## Pipeline structure

1. **Analyze phase** — 7 parallel agents, one per blueprint dimension (see files in
   this folder), model `fable`. Each reads its assigned reference file from the
   `travel-accounting-system` skill in full, then explores a read-only checkout of
   `citytourv2/main` (real Laravel app tree, not a diff) via Glob/Grep/Read.
   Required to return BOTH a structured findings object (schema below) AND write its
   own full detailed markdown report.

2. **Verify phase** — every finding with `severity` = `critical` or `high` gets its
   own independent adversarial-verification agent (model `sonnet` — switched from
   `fable` mid-run for token efficiency; the run was stopped after the Analyze phase
   completed and resumed from cache with verify on sonnet), instructed to try
   to REFUTE the finding by re-searching the codebase, not just re-read the original
   claim. Only findings that survive get marked `confirmed`.

3. **Synthesize phase** — one final agent (model `opus`, switched from `fable` in the
   same token-efficiency re-plan) compiles the confirmed
   high/critical findings + the unverified medium/low findings into the executive
   summary, prioritized bug list, prioritized missing-features list, implementation
   roadmap, and verification log.

## Structured output schema every Analyze-phase agent had to satisfy

```json
{
  "type": "object",
  "properties": {
    "dimension": { "type": "string" },
    "completeness_pct": { "type": "number" },
    "findings": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "title": { "type": "string" },
          "status": { "type": "string", "enum": ["missing", "partial", "buggy", "present_ok"] },
          "severity": { "type": "string", "enum": ["critical", "high", "medium", "low"] },
          "files": { "type": "array", "items": { "type": "string" } },
          "description": { "type": "string" },
          "recommendation": { "type": "string" }
        },
        "required": ["title", "status", "severity", "description"]
      }
    }
  },
  "required": ["dimension", "completeness_pct", "findings"]
}
```

## Shared context block injected into every Analyze-phase prompt

```
Context: citytourv2 is a full mirror (55 branches) of an upstream repo. You are analyzing the 'main' branch checkout only.
Known accounting-relevant branches NOT merged into main (do not analyze their code, just cross-reference by name if a gap you find matches):
- 'fix/payment-voucher': attempts a Payment Voucher + Reconciliation system (PaymentVoucher, PaymentReconciliation models, a data migration rewriting existing payment/JE data). Unmerged, 161 commits behind main, likely superseded/stale.
- 'fix/rv': attempts renaming invoice_receipt table/model to receipt_vouchers. Unmerged, upstream itself abandoned it ~7 weeks before main's last commit.
- 'agent-settlement': adds AgentSettlement/AgentSettlementDetail/AgentSettlementPayment models + service for agent loss recovery via profit offset or payment gateway. Unmerged into main.
If your gap analysis identifies a missing capability that one of these branches attempts, mention it as "attempted in unmerged branch X, not production-ready" rather than treating it as fully missing with no prior art.
```

Per-dimension prompt files: `01-chart-of-accounts.prompt.md` ... `07-modules-config.prompt.md`.
Verify-phase and synthesis-phase prompt templates: `verify-phase.prompt.md`, `synthesize-phase.prompt.md`.

## Supplementary agent (added after coverage review)

A coverage review of the 7-dimension fan-out against the full skill found one blind spot:
the SKILL.md top page's 8 "golden rules" are system-wide invariants that cross dimension
boundaries (especially rule 7 — "is there ONE shared posting engine, or does every
controller hand-roll its own journal entries?"), and no per-dimension agent audits them
end-to-end. An 8th agent was launched to close this:

- `golden-rules-integration.prompt.md` — cross-cutting integration audit
  → writes `../golden-rules-integration.md`

With this addition, every topic in the skill (top page + all 7 references) has an
assigned auditor. Verify-phase caveat still applies: only critical/high findings get
independent adversarial re-verification; medium/low findings are reported as-found.
