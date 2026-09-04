<?php

use App\Services\Parsers\AccelyaNdcParser;
use App\Services\Parsers\AiraloParser;
use App\Services\Parsers\AlmavivaItalyVisaParser;
use App\Services\Parsers\AirArabiaParser;
use App\Services\Parsers\CebuPacificParser;
use App\Services\Parsers\ChamWingsParser;
use App\Services\Parsers\FlyDubaiParser;
use App\Services\Parsers\IndigoParser;
use App\Services\Parsers\JazeeraAirwaysParser;
use App\Services\Parsers\LondonVisaParser;
use App\Services\Parsers\MagicHolidaysParser;
use App\Services\Parsers\RateHawkParser;
use App\Services\Parsers\SkyRoomsParser;
use App\Services\Parsers\SmileHolidaysParser;
use App\Services\Parsers\TurkishNdcParser;
use App\Services\Parsers\VfsGlobalParser;
use App\Services\Parsers\VietnamAirlinesParser;
use App\Services\Parsers\TboHotelParser;

return [
    /*
    | Master switch + transport. See docs/design_cpanel_per_agent_email_ingestion.md
    | mode: 'imap' (dev1 validation, Mode A) | 'pipe' (go-live push, Mode B)
    */
    'enabled' => (bool) env('MAIL_INGEST_ENABLED', false),
    'mode'    => env('MAIL_INGEST_MODE', 'imap'),

    // Attribute the task to the mailbox-owner agent. OFF for now: every agent
    // currently receives the same booking, so the mailbox != the booking agent.
    // Flip on (per-agent routing in place) to auto-assign agent_id on ingest.
    'attribute_agent' => (bool) env('MAIL_INGEST_ATTRIBUTE_AGENT', false),

    /*
    | Umbrella mail domain. dev1 build uses citytravelers.citycommerce.group
    | (umbrella citycommerce.group on the citycomm cPanel); prod identical.
    */
    'umbrella'     => env('MAIL_INGEST_UMBRELLA', 'citycommerce.group'),
    'company_id'   => (int) env('MAIL_INGEST_COMPANY_ID', 1),
    // Storage slug for the landing path; must match ProcessAirFiles' company loop
    // (strtolower(name) spaces->underscores). "City Travelers" -> city_travelers.
    'company_slug' => env('MAIL_INGEST_COMPANY_SLUG', 'city_travelers'),

    /*
    | Mode A (IMAP) mailboxes to poll. dev1 validates ONE test mailbox. Each
    | 'address' MUST equal the matching agents.ingest_email. Per-mailbox creds
    | don't scale — that's why pipe (Mode B) is the go-live transport.
    */
    'mailboxes' => (function () {
        $host = env('MAIL_INGEST_IMAP_HOST', 'mail.citycommerce.group');
        $port = (int) env('MAIL_INGEST_IMAP_PORT', 993);
        $enc  = env('MAIL_INGEST_IMAP_ENCRYPTION', 'ssl');
        $vc   = (bool) env('MAIL_INGEST_IMAP_VALIDATE_CERT', false);
        $mk = fn ($addr, $pass) => [
            'address' => $addr, 'host' => $host, 'port' => $port, 'encryption' => $enc,
            'validate_cert' => $vc, 'username' => $addr, 'password' => $pass,
        ];
        $list = [];
        // Preferred: MAIL_INGEST_MAILBOXES = JSON (or base64-of-JSON to dodge .env quoting)
        // list of {"address":"x@..","password":".."}.
        $raw = (string) env('MAIL_INGEST_MAILBOXES');
        $decoded = json_decode($raw, true);
        if ($decoded === null && $raw !== '') {
            $decoded = json_decode((string) base64_decode($raw, true), true);
        }
        foreach ((array) $decoded as $m) {
            if (!empty($m['address']) && !empty($m['password'])) {
                $list[] = $mk($m['address'], $m['password']);
            }
        }
        // Legacy single-mailbox fallback.
        if (!$list && env('MAIL_INGEST_IMAP_USERNAME')) {
            $list[] = $mk(env('MAIL_INGEST_IMAP_USERNAME'), env('MAIL_INGEST_IMAP_PASSWORD'));
        }
        return $list;
    })(),

    // Attachment names to ignore (case-insensitive substring).
    'skip_attachments' => ['termsandconditions', 'terms_and_conditions', 'terms-and-conditions', 'terms and conditions', 'important information', 'important_information'],

    // Sender substrings to ignore entirely (no ingest, no task). Amadeus tickets
    // already flow through the dedicated AIR-file pipeline, so its document-mail
    // PDFs are duplicates — drop them.
    'ignore_senders' => ['doc.mail.amadeus.com'],

    /*
    | Sender -> supplier slug (folder). Fast primary router; the folder is the
    | supplier scope ProcessAirFiles reads. Extend as suppliers come online.
    */
    // Ported from the n8n DEV1-Multi-Supplier Switch (2026-06-01). Slugs match
    // dev1 active supplier names. Smile Holidays is subject-matched in n8n, not
    // by sender — covered here by the SupplierPdfDetector fallback instead.
    'sender_supplier_map' => [
        'no-reply@gtsbeds.com'                                          => 'gts_beds',
        'gtsbeds.com'                                                   => 'gts_beds', // domain catch-all
        'itinerary@fly.jazeeraairways.com'                              => 'jazeera_airways',
        'confirmation@flydubai.com'                                     => 'fly_dubai',
        'ops@theskyrooms.com'                                           => 'sky_rooms',
        'reservations@airarabia.com'                                    => 'air_arabia',
        'uk.visas.and.immigration.home.office@notifications.service.gov.uk' => 'london_visa',
        'reservations@chamwings.com'                                    => 'cham_wings_airlines',
        'reservations@customer.goindigo.in'                             => 'indigo',
        'donotreply@vfsglobal.com'                                      => 'vfs_global',
        'sa.info@almaviva-visa.it'                                      => 'vfs_global', // Almaviva Italian visa (Consortium) - filed under VFS Global
        'noreply@airalo.com'                                            => 'airalo', // Airalo per-order eSIM invoice
        'no-reply@accelya.com'                                          => 'emirates_ndc', // Accelya = Emirates + Oman NDC
        'no-reply@email.mycebupacific.com'                              => 'cebu_pacific',
        'b2b@news.ratehawk.com'                                         => 'rate_hawk',
        'b2b@email.ratehawk.com'                                        => 'rate_hawk',
        'ratehawk.com'                                                  => 'rate_hawk', // domain catch-all (resolveSupplier matches by domain)
        'no-reply@service.vietnamairlines.com'                          => 'vietnam_airlines',
        'vietnamairlines.com'                                           => 'vietnam_airlines', // domain catch-all
        'noreply@tbo.com'                                               => 'tbo_holiday',
        'no-reply@salamair.com'                                         => 'salam_air',
        'onlineticket@mail.ajet.com'                                    => 'ajet_airline',
        'salamair.com'                                                  => 'salam_air', // domain catch-all
    ],

    /*
    | Fallback router: SupplierPdfDetector parser class -> supplier slug, used
    | when the sender isn't mapped. (Accelya covers Emirates+Oman NDC; defaults
    | to emirates_ndc — distinguish via sender_supplier_map when needed.)
    */
    'parser_supplier_map' => [
        \App\Services\Parsers\GtsBedsParser::class => 'gts_beds',
        JazeeraAirwaysParser::class => 'jazeera_airways',
        FlyDubaiParser::class       => 'fly_dubai',
        AirArabiaParser::class      => 'air_arabia',
        IndigoParser::class         => 'indigo',
        AccelyaNdcParser::class     => 'emirates_ndc',
        TurkishNdcParser::class     => 'turkish_airline_ndc',
        ChamWingsParser::class      => 'cham_wings_airlines',
        SkyRoomsParser::class       => 'sky_rooms',
        MagicHolidaysParser::class  => 'magic_holiday',
        VfsGlobalParser::class      => 'vfs_global',
        AlmavivaItalyVisaParser::class => 'vfs_global',
        AiraloParser::class         => 'airalo',
        LondonVisaParser::class     => 'london_visa',
        CebuPacificParser::class    => 'cebu_pacific',
        SmileHolidaysParser::class  => 'smile_holidays',
        RateHawkParser::class       => 'rate_hawk',
        VietnamAirlinesParser::class => 'vietnam_airlines',
        TboHotelParser::class       => 'tbo_holiday',
        \App\Services\Parsers\HeysamParser::class => 'heysam_group',
        \App\Services\Parsers\SalamAirParser::class => 'salam_air',
        \App\Services\Parsers\AjetParser::class => 'ajet_airline',
    ],

    // Unidentified-supplier PDFs land here for triage (never silently dropped).
    'unrouted_slug' => 'unrouted',
];
