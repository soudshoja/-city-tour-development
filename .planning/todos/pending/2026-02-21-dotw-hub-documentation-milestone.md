---
created: 2026-02-21T23:54:30.496Z
title: DOTW Hub Documentation milestone
area: docs
files:
  - docs/DOTW.md
  - docs/DOTW_API_REFERENCE.md
  - docs/DOTW_SERVICES.md
  - docs/DOTW_INTEGRATION_GUIDE.md
  - docs/DOTW_ARCHITECTURE.md
  - app/Http/Controllers/Docs/DotwDocumentationController.php
  - resources/views/docs/dotw-hub.blade.php
  - resources/views/docs/dotw-page.blade.php
  - routes/web.php
---

## Problem

DOTW v1.0 B2B milestone was completed and archived but had no developer-facing documentation site. The markdown docs existed only as files on disk with no web-accessible interface.

## Solution

Generated 5 documentation files using a 5-Haiku agent team in parallel, then built a Laravel docs viewer matching the existing `/docs/n8n` design:

**Docs generated:**
- `docs/DOTW.md` — master overview (~750 lines, quick start, cross-ref index)
- `docs/DOTW_API_REFERENCE.md` — full GraphQL API reference (all 5 operations)
- `docs/DOTW_SERVICES.md` — service layer (DotwService, Cache, CircuitBreaker, Audit)
- `docs/DOTW_INTEGRATION_GUIDE.md` — Admin UI + n8n integration guide
- `docs/DOTW_ARCHITECTURE.md` — data models, ERD, booking flow, security

**Web viewer built:**
- Route: `GET /docs/dotw` (hub), `GET /docs/dotw/{doc}` (page)
- Controller: `DotwDocumentationController` uses `league/commonmark` to render markdown
- Hub design: matches `/docs/n8n` exactly — colored-top-border cards, hero, quick start steps, system overview
- Page design: matches `/docs/n8n-changelog` — sticky sidebar (JS-built from headings), `prose prose-blue dark:prose-invert`, dark mode toggle, prev/next nav
- Deployed to: https://development.citycommerce.group/docs/dotw

**All docs committed to git and live on server via git pull.**
