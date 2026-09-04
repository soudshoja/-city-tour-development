<?php
// config/wa_pdf_ingest.php — WhatsApp supplier-PDF → task ingestion.
// Mirrors config/mail_ingest.php so dev1 and prod differ only by env values.
return [
    'enabled'      => env('WA_PDF_INGEST_ENABLED', false),
    'company_id'   => (int) env('WA_PDF_INGEST_COMPANY_ID', 1),
    'company_slug' => env('WA_PDF_INGEST_COMPANY_SLUG', 'city_travelers'),

    // How long the bot waits for an agent to reply with a missing field (Level 2).
    'field_ttl_minutes' => (int) env('WA_PDF_FIELD_TTL_MINUTES', 30),

    // How long the dispatcher waits for app:process-files to produce a task
    // before declaring the file unparseable and replying "saved for review".
    'parse_grace_minutes' => (int) env('WA_PDF_PARSE_GRACE_MINUTES', 6),

    // Reuse the same parser-class → supplier-slug map the email pipeline uses.
    'parser_supplier_map' => config('mail_ingest.parser_supplier_map', []),
];
