---
phase: quick-3
plan: "01"
subsystem: settings-ui
tags: [dotw, settings, sidebar, livewire, ui]
dependency_graph:
  requires: [quick-2]
  provides: [DOTW tab in /settings, branded sidebar icon]
  affects: [resources/views/settings/index.blade.php, app/Http/Controllers/SettingController.php, resources/views/layouts/sidebar.blade.php]
tech_stack:
  added: []
  patterns: [livewire-embed-in-settings-tab, alpine-tab-switch, role-gated-tab]
key_files:
  created: []
  modified:
    - resources/views/settings/index.blade.php
    - app/Http/Controllers/SettingController.php
    - resources/views/layouts/sidebar.blade.php
decisions:
  - "@livewire('admin.dotw-admin-index') embedded directly in settings content panel — reuses existing component with its own inner sub-tabs (credentials, audit logs, API tokens)"
  - "Role guard (ADMIN + COMPANY) applied at both sidebar nav button and content panel — BRANCH/AGENT cannot see or access the tab"
  - "saveTab() validation extended with 'dotw' — session stores active tab across page loads"
  - "Sidebar img tag (h-6 w-6 object-contain) replaces WhatsApp SVG; route('admin.dotw.index') link unchanged"
metrics:
  duration: "8 minutes"
  completed: "2026-02-21"
  tasks: 2
  files_changed: 3
---

# Quick Task 3: Move DOTW Unified Page to Project Settings Summary

**One-liner:** DOTW tab added to /settings embedding DotwAdminIndex with Pratra logo in sidebar, replacing WhatsApp SVG icon.

## What Was Built

DOTW Hotel API management is now accessible from the Project Settings page (`/settings`) as a dedicated tab in the left sidebar. The tab embeds the existing `DotwAdminIndex` Livewire component with its three inner sub-tabs (Credentials, Audit Logs, API Tokens). The standalone `/admin/dotw` route is unchanged. The sidebar icon that showed the WhatsApp SVG now shows the Pratra DOTW logo image.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add DOTW tab to Project Settings view and controller | 024486ee | resources/views/settings/index.blade.php, app/Http/Controllers/SettingController.php |
| 2 | Replace sidebar WhatsApp AI icon with Pratra DOTW image | 96a8391a | resources/views/layouts/sidebar.blade.php |

## Verification Checklist

- [x] Settings page (`/settings`) shows "DOTW / Hotel API" in left nav for ADMIN and COMPANY roles
- [x] Clicking the tab renders DotwAdminIndex with Credentials / Audit Logs / API Tokens sub-tabs
- [x] Saving the tab via Alpine `saveTab('dotw')` returns 200 (not 422) — 'dotw' added to validation allowlist
- [x] Sidebar DOTW icon shows Pratra image, tooltip "DOTW Hotel API", links to /admin/dotw
- [x] `/admin/dotw` route continues to render the standalone DOTW admin page (no route changes)
- [x] BRANCH and AGENT roles do NOT see the DOTW tab (guarded by role check at both nav and content level)

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- resources/views/settings/index.blade.php: FOUND, modified
- app/Http/Controllers/SettingController.php: FOUND, modified
- resources/views/layouts/sidebar.blade.php: FOUND, modified
- Commit 024486ee: FOUND
- Commit 96a8391a: FOUND
