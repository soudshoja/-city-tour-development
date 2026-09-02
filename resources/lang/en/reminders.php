<?php

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) -- template registry, English. Keyed by reminder_kind; each
 * kind carries a 'subject' (email) plus 'client'/'agent' bodies (shared verbatim between the
 * WhatsApp and email channel -- only the wrapper differs, per
 * App\Services\Reminders\ReminderMessageRegistry). Placeholders use Laravel's :placeholder
 * convention (trans()/__() with a replace array).
 *
 * 'ticketing_deadline' bodies are the same wording SendReminders::buildMessage() already sent
 * (W6.U) -- moved here verbatim, not reworded, so this refactor changes WHERE the copy lives, not
 * what it says.
 */
return [

    'overdue_invoice' => [
        'subject' => 'Overdue invoice :invoice_number',
        'client' => "Please be reminded that you have an outstanding payment to invoice :invoice_number of :currency :amount that was past due on :due_date.:additional\n\nPlease click the following link to make the payment to the invoice:\n:link\n\nShould you require further assistance, feel free to reach out our support team.",
        'agent' => "This is a reminder that your client has an outstanding payment to invoice :invoice_number of :currency :amount that was past due on :due_date.:additional\n\nInvoice link:\n:link\n\nPlease follow up with your client regarding this payment.",
    ],

    'statement_balance' => [
        'subject' => 'Outstanding statement balance',
        'client' => "You currently have an outstanding statement balance of :currency :amount.:additional\n\nShould you require further assistance or a copy of your statement, feel free to reach out to our support team.",
        'agent' => "This is a reminder that your client has an outstanding statement balance of :currency :amount.:additional\n\nStatement link:\n:link\n\nPlease follow up with your client regarding this balance.",
    ],

    'ticketing_deadline' => [
        'subject' => 'Ticketing deadline approaching -- :reference',
        'agent' => "Reminder: booking reference :reference for passenger :passenger_name must be ticketed before :deadline (:deposit_text).:additional\n\nPlease action this booking before the deadline to avoid losing the reservation.",
        'client' => "Please be reminded that your booking (ref. :reference) is due for ticketing before :deadline.:additional\n\nPlease contact us to complete your booking before the deadline.",
    ],

    'commission_unearned' => [
        'subject' => 'Commission un-earned on a refunded sale',
        'agent' => "A commission of :currency :amount previously earned on invoice :invoice_number has been un-earned following a refund on this booking.:additional\n\nThis amount has been reversed from your commission balance.",
        'client' => '',
    ],

    'payment_link_uninvoiced' => [
        'subject' => 'Payment link :voucher_number is still uninvoiced',
        'agent' => "Payment voucher :voucher_number of :currency :amount has been paid but has not yet been converted into an invoice.:additional\n\nPlease raise the invoice for this payment as soon as possible.",
        'client' => '',
    ],

    'custom' => [
        'subject' => 'Reminder',
        'client' => ':message',
        'agent' => ':message',
    ],

    /**
     * Internal fallback ONLY -- used when reminder_kind is null and target_type='payment' (the
     * legacy manually-created reminder, unchanged from pre-P2.5.I SendReminders::buildMessage()).
     * Deliberately distinct wording from 'payment_link_uninvoiced' above: this one asks the
     * CLIENT to pay an unpaid voucher; 'payment_link_uninvoiced' asks the AGENT to invoice an
     * already-paid one. Not a member of ReminderOptions::KINDS -- never selectable in the
     * settings tab.
     */
    'payment_due' => [
        'subject' => 'Outstanding payment -- voucher :voucher_number',
        'client' => "Please be reminded that you have an outstanding payment to voucher :voucher_number of :currency :amount.:additional\n\nPlease click the following link to make the payment:\n:link\n\nShould you require further assistance, feel free to reach out our support team.",
        'agent' => "This is a reminder that your client has an outstanding payment to voucher :voucher_number of :currency :amount.:additional\n\nPayment link:\n:link\n\nPlease follow up with your client regarding this payment.",
    ],

];
