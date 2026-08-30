# Prompt template — Verify phase (adversarial verification)

- **Model**: fable
- **Trigger**: one agent spawned per finding where `severity` is `critical` or `high` (findings with `severity` medium/low were NOT independently verified — flagged as such in the final report)
- **Label pattern**: `verify:<finding title, truncated to 50 chars>`
- **Codebase checked read-only at**: `C:/Users/User/AppData/Local/Temp/claude/C--Users-User-OneDrive---City-Travelers-soud-laravel/001d2669-f340-4d6d-9568-2dee0f332d34/scratchpad/citytourv2-main-checkout`

## Structured output schema

```json
{
  "type": "object",
  "properties": {
    "confirmed": { "type": "boolean" },
    "reasoning": { "type": "string" }
  },
  "required": ["confirmed", "reasoning"]
}
```

## Full prompt template (placeholders in `[...]` filled per finding)

```
Adversarially verify this accounting gap-analysis finding against the actual codebase, checked out read-only at:
C:/Users/User/AppData/Local/Temp/claude/C--Users-User-OneDrive---City-Travelers-soud-laravel/001d2669-f340-4d6d-9568-2dee0f332d34/scratchpad/citytourv2-main-checkout

Try to REFUTE it. Actively search for evidence the finding is wrong: the capability might exist elsewhere, under a different name/pattern, in a related file, trait, or observer not originally checked. Use Glob/Grep/Read to check independently — do not just trust the original claim.

Finding under review:
- Dimension: [dimension key, e.g. 02-posting-engine]
- Title: [finding title]
- Claimed status: [missing|partial|buggy|present_ok], claimed severity: [critical|high]
- Description: [finding description as written by the Analyze-phase agent]
- Cited files: [files cited, or "none cited"]

Return confirmed=true only if, after your own independent check, the gap/bug genuinely exists as described. Return confirmed=false if you find evidence it's wrong, already handled elsewhere, or overstated.
```

This is a per-finding template — the actual number of verify agents spawned equals the count
of critical+high findings across all 7 Analyze-phase agents (see `verification-log.md` in the
parent folder for the literal finding text each instance received and its verdict).
