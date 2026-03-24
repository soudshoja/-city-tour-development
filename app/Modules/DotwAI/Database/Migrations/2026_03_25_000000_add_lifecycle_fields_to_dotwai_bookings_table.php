<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add lifecycle tracking fields to dotwai_bookings table.
 *
 * reminder_sent_at: Tracks when the WhatsApp deadline reminder was sent.
 *   NULL = no reminder sent yet. Set by SendReminderJob after successful dispatch.
 *   Prevents duplicate reminders on the same booking (idempotency gate).
 *
 * auto_invoiced_at: Tracks when the auto-invoice was created after deadline passed.
 *   NULL = not yet auto-invoiced. Set by AutoInvoiceDeadlineJob after completion.
 *   Prevents duplicate auto-invoicing.
 *
 * @see LIFE-02 Reminder tracking via reminder_sent_at
 * @see LIFE-03 Auto-invoice tracking via auto_invoiced_at
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dotwai_bookings', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('voucher_sent_at');
            $table->timestamp('auto_invoiced_at')->nullable()->after('reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('dotwai_bookings', function (Blueprint $table) {
            $table->dropColumn(['reminder_sent_at', 'auto_invoiced_at']);
        });
    }
};
