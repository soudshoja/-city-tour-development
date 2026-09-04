<?php

/**
 * Notification text for cross-agent refund/void/reissue acknowledgment requests.
 *
 * Placeholders are Laravel-style :variables. Available everywhere:
 *   :actor       — actor agent name (who did the refund/reissue)
 *   :owner       — owner agent name (original task's agent)
 *   :ticket      — ticket number
 *   :client      — client full name
 *   :action      — refund | reissue | void
 *   :tickets     — comma-separated list (bundled cases)
 *   :tickets_count — number of bundled tickets
 *   :reason      — (deprecated; reason field removed but kept here for future)
 *   :decision_link — URL to web Approve/Deny page (token-bearing)
 *   :submitted_at  — request created_at, formatted
 */
return [

    'action_label' => [
        'refund' => 'refund',
        'void' => 'void',
        'reissue' => 'reissue',
    ],

    /* ---------------- Owner — Assigned Agent variant (info only) ----------------- */
    'owner_notify_only_title' => ':actor processed a :action on your client\'s ticket',
    'owner_notify_only_body' => ':actor did a [:action] on ticket [:ticket] for your client [:client]. Sale credited to :actor. No action needed.',
    'owner_notify_only_body_bundled' => ':actor did :tickets_count [:action] tasks for your client [:client] (tickets: :tickets). Sale credited to :actor. No action needed.',
    'owner_notify_only_whatsapp' => 'Hi :owner — :actor processed a :action on ticket :ticket (:client). Sale goes to :actor. FYI only.',
    'owner_notify_only_whatsapp_bundled' => 'Hi :owner — :actor processed :tickets_count :action tasks for :client (tickets: :tickets). Sale goes to :actor. FYI only.',

    /* ---------------- Actor — courtesy info when notify_only --------------------- */
    'actor_notify_only_title' => 'Owner notified about your :action',
    'actor_notify_only_body' => ':owner has been notified about your [:action] on ticket [:ticket] (:client). No approval needed — you are an Assigned Agent. Sale credited to you.',
    'actor_notify_only_body_bundled' => ':owner has been notified about your :tickets_count [:action] tasks for :client (tickets: :tickets). No approval needed — you are an Assigned Agent. Sale credited to you.',
    'actor_notify_only_whatsapp' => 'Hi :actor — :owner has been notified about your :action on ticket :ticket (:client). No approval needed. Sale credited to you.',
    'actor_notify_only_whatsapp_bundled' => 'Hi :actor — :owner has been notified about your :tickets_count :action tasks for :client (tickets: :tickets). No approval needed. Sale credited to you.',

    /* ---------------- Owner — Approve/Deny variant ------------------------------- */
    'owner_decision_title' => ':action acknowledgment needed — ticket :ticket',
    'owner_decision_title_bundled' => ':action acknowledgment needed — :tickets_count tickets for :client',
    'owner_decision_body' => ':actor did a [:action] on ticket [:ticket] for your client [:client]. Approve to credit the sale to :actor, or Deny to keep it on your side. Admin and accounting will be notified if no action in 2 days.',
    'owner_decision_body_bundled' => ':actor did :tickets_count [:action] tasks for your client [:client] (tickets: :tickets). Approve to credit all :tickets_count sales to :actor, or Deny to keep them on your side. One decision applies to all. Admin and accounting will be notified if no action in 2 days.',
    'owner_decision_whatsapp' => 'Hi :owner — :actor did a :action on ticket :ticket (:client). Reply 1 to Approve (sale to :actor) or 2 to Deny: :decision_link',
    'owner_decision_whatsapp_bundled' => 'Hi :owner — :actor did :tickets_count :action tasks for :client (tickets: :tickets). Reply 1 to Approve all (sale to :actor) or 2 to Deny: :decision_link',

    /* ---------------- 2-day escalation to admin + accountant --------------------- */
    'escalation_title' => 'Pending :action acknowledgment >2 days — :client',
    'escalation_body' => 'A :action acknowledgment request has been pending for over 2 days. Owner :owner has not Approved/Denied :actor\'s :action on ticket :ticket (client :client). Submitted :submitted_at. Please decide on their behalf.',
    'escalation_body_bundled' => 'A :action acknowledgment request has been pending for over 2 days. Owner :owner has not Approved/Denied :actor\'s :tickets_count :action tasks for client :client (tickets: :tickets). Submitted :submitted_at. Please decide on their behalf.',

    /* ---------------- Approve outcome notifications ------------------------------ */
    'approve_notify_actor_title' => 'Your :action was approved',
    'approve_notify_actor_body' => ':owner approved your [:action] on ticket [:ticket] (:client). Sale credited to you.',
    'approve_notify_actor_body_bundled' => ':owner approved your :tickets_count [:action] tasks for :client (tickets: :tickets). Sale credited to you.',
    'approve_notify_actor_whatsapp' => 'Hi :actor — :owner approved your :action on ticket :ticket (:client). Sale credited to you.',
    'approve_notify_actor_whatsapp_bundled' => 'Hi :actor — :owner approved your :tickets_count :action tasks for :client (tickets: :tickets). Sale credited to you.',
    'approve_notify_admin_title' => ':action approved by owner — :client',
    'approve_notify_admin_body' => ':owner approved :actor\'s [:action] on ticket [:ticket] (:client). Sale credited to :actor. Logged for review.',
    'approve_notify_admin_body_bundled' => ':owner approved :actor\'s :tickets_count [:action] tasks for :client (tickets: :tickets). Sale credited to :actor. Logged for review.',

    /* ---------------- Deny outcome notifications --------------------------------- */
    'deny_notify_actor_title' => 'Your :action was denied',
    'deny_notify_actor_body' => ':owner denied your [:action] on ticket [:ticket] (:client). Sale stays with :owner. Admin and accounting notified.',
    'deny_notify_actor_body_bundled' => ':owner denied your :tickets_count [:action] tasks for :client (tickets: :tickets). Sale stays with :owner. Admin and accounting notified.',
    'deny_notify_actor_whatsapp' => 'Hi :actor — :owner denied your :action on ticket :ticket (:client). Sale stays with :owner. Admin & accounting notified.',
    'deny_notify_actor_whatsapp_bundled' => 'Hi :actor — :owner denied your :tickets_count :action tasks for :client (tickets: :tickets). Sale stays with :owner. Admin & accounting notified.',
    'deny_notify_admin_title' => ':action denied by owner — :client',
    'deny_notify_admin_body' => ':owner denied :actor\'s [:action] on ticket [:ticket] (:client). Sale remains with :owner. Logged for review.',
    'deny_notify_admin_body_bundled' => ':owner denied :actor\'s :tickets_count [:action] tasks for :client (tickets: :tickets). Sale remains with :owner. Logged for review.',

    /* ---------------- Auto-approve (owner inactive) ------------------------------ */
    'auto_approve_admin_title' => 'Auto-approved :action — owner inactive',
    'auto_approve_admin_body' => 'Auto-approved: :actor\'s [:action] on ticket [:ticket] (:client). Original agent :owner is inactive or has no user account. Sale credited to :actor.',

    /* ---------------- WhatsApp inbound reply confirmations ----------------------- */
    'whatsapp_invalid_reply' => 'Sorry, I didn\'t recognize that. Reply 1 to Approve or 2 to Deny.',
    'whatsapp_no_pending' => 'No pending refund/reissue requests for you right now.',
    'whatsapp_approved' => 'Approved. :action sale on ticket :ticket (:client) credited to :actor.',
    'whatsapp_denied' => 'Denied. :action sale on ticket :ticket (:client) stays with you.',
    'whatsapp_multiple_pending' => 'You have :count pending requests. Reply with the action followed by the request ID, e.g. "1 :sample_token" to Approve.',
];
