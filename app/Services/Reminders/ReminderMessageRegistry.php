<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Models\Reminder;
use App\Services\Accounting\StatementService;
use App\Services\TaskStatusService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I): "buildMessage() becomes a template registry keyed by
 * reminder_kind (lang files, WhatsApp + email variants; email via the existing mail channel)".
 *
 * Replaces {@see \App\Console\Commands\SendReminders}'s own private buildMessage() method, which
 * this class's {@see self::render()} now backs (that command delegates to this class rather than
 * duplicating the branch-per-target_type logic inline). Every string lives in
 * resources/lang/{en,ar}/reminders.php (this app's langPath() is overridden to resources/lang,
 * not the framework default lang/ -- verified via `app()->langPath()`), keyed by reminder_kind --
 * the SAME WhatsApp-facing copy is reused
 * for the email body (plain-text; the email channel wraps it in the existing
 * {@see \App\Mail\NotificationMail} HTML shell, so no separate "email variant" string is needed
 * per the brief's own "email via the existing mail channel" instruction).
 *
 * Dispatch: a row with a known `reminder_kind` (one of {@see ReminderOptions::KINDS}, or the
 * internal 'payment_due' legacy key) renders from that key's lang entry. A row with a NULL
 * reminder_kind (every pre-P2.5.I manually-created reminder, and any bulk() row) falls back on
 * target_type alone -- 'invoice' -> the 'overdue_invoice' template (identical wording to the
 * pre-existing invoice branch), 'payment' -> the 'payment_due' template (identical wording to the
 * pre-existing payment branch). This preserves every pre-existing reminder's exact output text
 * while giving every NEW P2.5.I-generated row (which always sets reminder_kind) its own template.
 */
final class ReminderMessageRegistry
{
    /**
     * @return ?array{subject: string, client_message: ?string, agent_message: ?string} null when
     *                                                                                  no template/data resolves for this row (mirrors the old buildMessage()'s own
     *                                                                                  "return null -> caller marks failed" contract).
     */
    public function render(Reminder $reminder): ?array
    {
        $kind = $reminder->reminder_kind ?: $this->fallbackKindForTargetType($reminder->target_type);
        if ($kind === null) {
            return null;
        }

        $params = $this->paramsFor($reminder, $kind);
        if ($params === null) {
            return null;
        }

        $locale = $this->resolveLocale($reminder);

        $subject = Lang::get("reminders.{$kind}.subject", $params, $locale);
        $clientTemplate = Lang::get("reminders.{$kind}.client", $params, $locale);
        $agentTemplate = Lang::get("reminders.{$kind}.agent", $params, $locale);

        return [
            'subject' => is_string($subject) ? $subject : "reminders.{$kind}.subject",
            'client_message' => (is_string($clientTemplate) && $clientTemplate !== '') ? $clientTemplate : null,
            'agent_message' => (is_string($agentTemplate) && $agentTemplate !== '') ? $agentTemplate : null,
        ];
    }

    private function fallbackKindForTargetType(?string $targetType): ?string
    {
        return match ($targetType) {
            'invoice' => 'overdue_invoice',
            'payment' => 'payment_due',
            default => null,
        };
    }

    /** @return ?array<string,string> null when the row's required relation is missing. */
    private function paramsFor(Reminder $reminder, string $kind): ?array
    {
        $additional = $reminder->message
            ? "\n\nAdditional information regarding this {$reminder->target_type} can be found below:\n{$reminder->message}"
            : '';

        return match ($kind) {
            'overdue_invoice' => $this->invoiceParams($reminder, $additional),
            'payment_due' => $this->paymentParams($reminder, $additional),
            'ticketing_deadline' => $this->ticketingDeadlineParams($reminder, $additional),
            'statement_balance' => $this->statementBalanceParams($reminder, $additional),
            // 'message' is repurposed by CreateCommissionUnearnedReminder to carry the
            // snapshotted amount, not free-form notes -- no $additional line for this kind.
            'commission_unearned' => $this->commissionUnearnedParams($reminder),
            'payment_link_uninvoiced' => $this->paymentLinkUninvoicedParams($reminder, $additional),
            'custom' => ['message' => (string) $reminder->message],
            default => null,
        };
    }

    private function invoiceParams(Reminder $reminder, string $additional): ?array
    {
        $invoice = $reminder->invoice;
        if ($invoice === null) {
            return null;
        }

        return [
            'invoice_number' => (string) $invoice->invoice_number,
            'currency' => (string) $invoice->currency,
            'amount' => (string) $invoice->amount,
            'due_date' => $invoice->due_date ? Carbon::parse($invoice->due_date)->format('jS F Y') : '-',
            'link' => $invoice->publicUrl('show'),
            'additional' => $additional,
        ];
    }

    private function paymentParams(Reminder $reminder, string $additional): ?array
    {
        $payment = $reminder->payment;
        $client = $reminder->client;
        if ($payment === null || $client === null) {
            return null;
        }

        $companyId = $client->agent?->branch->company->id ?? 1;

        return [
            'voucher_number' => (string) $payment->voucher_number,
            'currency' => (string) $payment->currency,
            'amount' => (string) $payment->amount,
            'link' => route('payment.link.show', ['companyId' => $companyId, 'voucherNumber' => $payment->voucher_number]),
            'additional' => $additional,
        ];
    }

    /**
     * Same computed values {@see \App\Console\Commands\SendReminders}'s pre-P2.5.I task branch
     * used (deadline formatting, deposit-held text, optional client payment link) -- moved here
     * verbatim, wording unchanged.
     */
    private function ticketingDeadlineParams(Reminder $reminder, string $additional): ?array
    {
        $task = $reminder->task;
        if ($task === null) {
            return null;
        }

        $deadline = $task->deadline_at ? Carbon::parse($task->deadline_at)->format('jS F Y, h:i A') : 'its ticketing deadline';
        $deposit = app(TaskStatusService::class)->depositHeld($task);
        $depositText = $deposit > 0 ? number_format($deposit, 3).' held as deposit' : 'no deposit on file';

        return [
            'reference' => (string) $task->reference,
            'passenger_name' => (string) $task->passenger_name,
            'deadline' => $deadline,
            'deposit_text' => $depositText,
            'additional' => $additional,
        ];
    }

    private function statementBalanceParams(Reminder $reminder, string $additional): ?array
    {
        $client = $reminder->client;
        if ($client === null) {
            return null;
        }

        $companyId = $reminder->company_id ?? ($client->agent?->branch->company_id ?? 1);
        $statement = app(StatementService::class)->generate($companyId, StatementService::PARTY_CLIENT, $client->id, now());

        $link = route('accounting.statements.show', ['partyType' => 'client', 'partyId' => $client->id]);

        return [
            'currency' => (string) config('accounting.base_currency', 'KWD'),
            'amount' => number_format((float) ($statement['totals']['net_outstanding'] ?? 0), 3),
            'link' => $link,
            'additional' => $additional,
        ];
    }

    private function commissionUnearnedParams(Reminder $reminder): array
    {
        $invoice = $reminder->invoice;

        return [
            'currency' => (string) config('accounting.base_currency', 'KWD'),
            'amount' => number_format((float) $reminder->message, 3),
            'invoice_number' => $invoice?->invoice_number ?? (string) ($reminder->invoice_id ?? '-'),
            'additional' => '',
        ];
    }

    private function paymentLinkUninvoicedParams(Reminder $reminder, string $additional): ?array
    {
        $payment = $reminder->payment;
        if ($payment === null) {
            return null;
        }

        return [
            'voucher_number' => (string) $payment->voucher_number,
            'currency' => (string) $payment->currency,
            'amount' => (string) $payment->amount,
            'additional' => $additional,
        ];
    }

    private function resolveLocale(Reminder $reminder): string
    {
        $locale = Str::lower((string) ($reminder->client?->language ?? app()->getLocale() ?: 'en'));

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }
}
