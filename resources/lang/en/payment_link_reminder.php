<?php

/**
 * P2.5.I prod-drift port (verbatim from /home/citycomm/tour.citycommerce.group
 * resources/lang/en/payment_link_reminder.php, 2026-08-31) -- feeds
 * App\Console\Commands\SendAgentUninvoicedPaymentLinkReminders /
 * App\Mail\UninvoicedPaymentLinkReminderMail / notifications.pdf.uninvoiced-payment-links.
 */

return [
    'subject' => 'Uninvoiced Payment Link Reminder - :count payment(s) pending',
    'whatsapp_caption' => "*Uninvoiced Payment Link Reminder*\n\n"
        . "Dear :name,\n\n"
        . "You have *:count payment link(s)* that have been paid but not yet invoiced.\n\n"
        . "Please close them against an invoice as soon as possible.\n\n"
        . "Attached is the full list for your reference.\n\n"
        . "_:company_",

    'pdf' => [
        'title' => 'Uninvoiced Payment Links',
        'agent' => 'Agent',
        'window' => 'Period',
        'count' => 'Total uninvoiced',
        'voucher' => 'Voucher',
        'client' => 'Client',
        'amount' => 'Amount',
        'currency' => 'Currency',
        'gateway' => 'Gateway',
        'paid_at' => 'Paid At',
        'reference' => 'Reference',
        'note' => 'Please review each payment and create the corresponding invoice.',
    ],

    'email' => [
        'greeting' => 'Dear :name,',
        'intro' => 'You have :count payment link(s) that have been paid but not yet closed against an invoice.',
        'action' => 'Please review the attached list and create the matching invoices as soon as possible.',
        'footer' => 'This is an automated reminder from :company.',
    ],
];
