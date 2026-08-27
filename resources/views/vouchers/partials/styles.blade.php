{{--
    Shared base styles for every shipped voucher design (plan §12/§16 step 3).

    Deliberately plain, table-based CSS — NOT Tailwind, NOT flexbox/grid.
    The two designs this replaces (tasks/pdf/flight.blade.php,
    tasks/pdf/hotel.blade.php) loaded Tailwind from cdn.tailwindcss.com,
    which renders fine in a browser but is completely dead inside dompdf
    (barryvdh/laravel-dompdf ^3.1, this app's only PDF engine — no
    browsershot/headless Chrome). dompdf's flexbox/grid support is also too
    inconsistent to build a layout on, so every structural layout below
    (the header split, label/value rows) uses plain HTML tables, exactly
    the proven pattern already in resources/views/invoice/pdf/invoice.blade.php.

    One font stack for BOTH languages: 'DejaVu Sans' is bundled with dompdf.

    BLOCKER B2 -- CORRECTED 2026-08-27, do not re-open without re-reading
    this note. An earlier pass claimed "Arabic actually renders well" in
    the dompdf PDF output and used that to overturn the original plan's
    caution ("dompdf cannot shape Arabic", plan §12). That claim was
    checked against a real rendered file and was FALSE: VCH-000011
    (hotel/ARB) contains 478 codepoints in the Arabic block
    (U+0600-U+06FF) and ZERO codepoints in Arabic Presentation Forms-B
    (U+FE70-U+FEFF) -- dompdf embedded unshaped, unjoined base letters, so
    rendered Arabic text draws as disconnected glyphs with RTL blocks
    left-aligned rather than mirrored. The plan's original caution was right;
    the "verified live" claim that overturned it was not actually checked
    against output. **dompdf cannot shape Arabic on this server, full
    stop.**

    The fix is NOT a different font here -- it is that dompdf never gets
    asked to render Arabic at all: VoucherService::renderPdf() is a
    no-op for TravelVoucher::LANGUAGE_AR (plan §12 restored: "PDF
    attachment = EN templates only in v1"), and
    PublicVoucherController::pdf() never falls back to a live dompdf
    render for one either. 'DejaVu Sans' stays the ONE font for every
    language ONLY because the HTML route (browsers shape Arabic
    correctly on their own) is the sole ARB rendering path left -- an EN
    PDF and its HTML preview still render pixel-identical typography,
    which is all this single-source-of-truth stack needs to guarantee
    once ARB has no PDF path to be identical to.
--}}
<style>
    * { box-sizing: border-box; }
    html, body {
        margin: 0;
        padding: 0;
        background: {{ ($isPdf ?? false) ? '#ffffff' : '#eef2f6' }};
        font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
        color: #1f2933;
        font-size: 12px;
        line-height: 1.5;
    }
    .voucher-page {
        max-width: 760px;
        margin: {{ ($isPdf ?? false) ? '0' : '24px auto' }};
        background: #ffffff;
        border: 1px solid #e2e8f0;
        {{ ($isPdf ?? false) ? '' : 'box-shadow: 0 1px 3px rgba(15,23,42,0.08);' }}
        position: relative;
    }

    /* ---- header ---- */
    .vh-table { width: 100%; border-collapse: collapse; background: #1d3f91; color: #ffffff; }
    .vh-table td { padding: 18px 22px; vertical-align: middle; border-bottom: 4px solid #f59e0b; }
    .vh-logo-cell { width: 80px; }
    .vh-logo { max-height: 56px; max-width: 130px; }
    .vh-company-name { font-size: 17px; font-weight: bold; margin: 0 0 2px 0; }
    .vh-company-sub { font-size: 10px; opacity: .85; margin: 0; }
    .vh-company-contact { font-size: 9.5px; opacity: .85; margin-top: 6px; }
    .vh-badge-cell { text-align: {{ ($lang ?? 'EN') === 'ARB' ? 'left' : 'right' }}; }
    .vh-ref { font-size: 19px; font-weight: bold; letter-spacing: 1px; }
    .vh-badge {
        display: inline-block; margin-top: 6px; padding: 3px 12px; border-radius: 999px;
        background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.4);
        font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px;
    }

    /* ---- status / cross-reference banner (BLOCKER B3, §13-BIS.A) ---- */
    .v-status-note { margin: 0 0 16px; padding: 11px 14px; border-radius: 6px; border: 1px solid; font-size: 11px; line-height: 1.55; }
    .v-status-note-title { margin: 0 0 3px; font-weight: bold; }
    .v-status-note-body { margin: 0; }
    .v-status-note a { color: inherit; font-weight: bold; text-decoration: underline; }
    .v-status-note-reissued { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
    .v-status-note-refunded { background: #fefce8; border-color: #fde68a; color: #854d0e; }
    .v-status-note-replaces { background: #f8fafc; border-color: #e2e8f0; color: #475569; }

    /* ---- body ---- */
    .vb { padding: 22px; }
    .v-section { margin-bottom: 18px; }
    .v-section-title {
        font-size: 12.5px; font-weight: bold; color: #12285f; margin: 0 0 8px 0;
        padding-bottom: 6px; border-bottom: 1px solid #e2e8f0;
    }
    .v-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; }
    .v-grid { width: 100%; border-collapse: collapse; }
    .v-grid td { vertical-align: top; padding: 6px 10px 6px 0; }
    .v-label { font-size: 9px; text-transform: uppercase; letter-spacing: .4px; color: #64748b; margin: 0 0 2px 0; }
    /* `font-weight: bold`, NEVER a numeric weight — verified live 2026-08-27
       on this server's dompdf v3.1.0: `font-weight: 600` (or any numeric
       value other than the literal `bold`/`700`) silently fails to find an
       Arabic-capable font for RTL text and renders it as a row of "?????",
       while the identical text at `font-weight: bold`, `700`, or unset all
       shape correctly. Latin text is unaffected either way, which is what
       made this easy to miss — it only shows up the moment a bold VALUE
       cell (client/agent/hotel name, a free-text note) happens to hold
       Arabic. Every `.v-value`, `.v-pill` and similar bold class in this
       stylesheet must stay on the keyword form for exactly this reason. */
    .v-value { font-size: 11.5px; font-weight: bold; color: #1f2933; margin: 0; }
    .v-value-muted { color: #94a3b8; font-weight: 400; }

    .v-table { width: 100%; border-collapse: collapse; }
    .v-table th, .v-table td { border: 1px solid #e2e8f0; padding: 7px 9px; font-size: 11px; text-align: {{ ($lang ?? 'EN') === 'ARB' ? 'right' : 'left' }}; }
    .v-table th { background: #f1f5f9; color: #334155; font-size: 9.5px; text-transform: uppercase; letter-spacing: .3px; }

    .v-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 9.5px; font-weight: bold; }
    .v-pill-green { background: #dcfce7; color: #166534; }
    .v-pill-red { background: #fee2e2; color: #991b1b; }
    .v-pill-blue { background: #dbeafe; color: #1e40af; }
    .v-pill-amber { background: #fef3c7; color: #92400e; }

    .v-note { font-size: 10.5px; color: #475569; background: #f8fafc; border: 1px dashed #cbd5e1; padding: 10px 12px; border-radius: 6px; }

    /* ---- footer ---- */
    .vf-table { width: 100%; border-collapse: collapse; background: #1f2933; color: #ffffff; }
    .vf-table td { padding: 12px 22px; font-size: 9.5px; opacity: .85; }

    /* ---- sample watermark ---- */
    .sample-ribbon {
        position: absolute; top: 18px; {{ ($lang ?? 'EN') === 'ARB' ? 'left' : 'right' }}: -34px;
        background: #dc2626; color: #ffffff; font-size: 10px; font-weight: bold;
        letter-spacing: 1px; padding: 4px 40px; transform: rotate({{ ($lang ?? 'EN') === 'ARB' ? '-45deg' : '45deg' }});
    }

    @page { margin: 14mm 10mm; }
</style>
