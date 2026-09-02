<?php

namespace Tests\Feature\Accounting\Reminders;

use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use App\Services\Reminders\ReminderOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) UI sub-scope: "Reminders settings tab (per-kind on/off,
 * offsets, channel, quiet hours)" -- folded into the existing Accounting settings tab endpoint
 * (SettingController::getAccountingSettings/storeAccountingSettings) rather than a second
 * screen; see that controller's own P2.5.I comments. This proves the round trip: what the form
 * posts is what ReminderOptions (the class every generator/listener actually reads) reads back.
 */
class ReminderAccountingSettingsRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_reminder_settings_via_the_shared_form_is_read_back_by_reminder_options(): void
    {
        Company::forgetModuleCache();
        $country = Country::factory()->create();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $company = Company::factory()->create(['user_id' => $admin->id, 'country_id' => $country->id]);
        // Gate::authorize('manageAccountingSettings') resolves the acting company via
        // getCompanyId(), which for an ADMIN falls back to session('company_id') — without
        // this the POST below 403s against whatever company_id happens to be in session
        // (see SettingControllerAccountingSettingsTest::makeCompanyWithAdmin for the same
        // established pattern this test previously omitted).
        session(['company_id' => $company->id]);

        $response = $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), [
            'invoice_overpay_cancel_policy' => 'credit',
            'unclaimed_writeback_months' => 12,
            'refund_send_on_post' => true,
            'agent_unearn_notice' => true,
            'reminders' => [
                'enabled' => ['overdue_invoice' => false, 'statement_balance' => true],
                'channel' => ['overdue_invoice' => 'both'],
                'overdue_invoice_offsets_days' => '2,5,10',
                'daily_run_time' => '10:30',
                'quiet_start' => '22:00',
                'quiet_end' => '07:00',
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertFalse(ReminderOptions::enabled($company->id, 'overdue_invoice'));
        $this->assertTrue(ReminderOptions::enabled($company->id, 'statement_balance'));
        $this->assertSame('both', ReminderOptions::channel($company->id, 'overdue_invoice'));
        $this->assertSame([2, 5, 10], ReminderOptions::overdueInvoiceOffsetsDays($company->id));
        $this->assertSame('10:30', ReminderOptions::dailyRunTime($company->id));
        $this->assertSame(['start' => '22:00', 'end' => '07:00'], ReminderOptions::quietHours($company->id));

        // GET reflects the same values back to the form.
        $get = $this->actingAs($admin)->getJson(route('settings.accounting-settings').'?company_id='.$company->id);
        $get->assertOk();
        $get->assertJsonPath('settings.reminders.enabled.overdue_invoice', false);
        $get->assertJsonPath('settings.reminders.overdue_invoice_offsets_days', '2,5,10');
        $get->assertJsonPath('settings.reminders.quiet_start', '22:00');

        Company::forgetModuleCache();
    }

    public function test_clearing_quiet_hours_disables_them(): void
    {
        Company::forgetModuleCache();
        $country = Country::factory()->create();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $company = Company::factory()->create(['user_id' => $admin->id, 'country_id' => $country->id]);
        session(['company_id' => $company->id]);

        $set = $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), [
            'invoice_overpay_cancel_policy' => 'credit',
            'unclaimed_writeback_months' => 12,
            'refund_send_on_post' => true,
            'agent_unearn_notice' => true,
            'reminders' => ['quiet_start' => '22:00', 'quiet_end' => '07:00'],
        ]);
        $set->assertOk();
        $set->assertJson(['success' => true]);

        // Prove the POST actually took effect before clearing it, so the final assertion
        // below cannot pass vacuously off the untouched default.
        $this->assertSame(['start' => '22:00', 'end' => '07:00'], ReminderOptions::quietHours($company->id));

        $clear = $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), [
            'invoice_overpay_cancel_policy' => 'credit',
            'unclaimed_writeback_months' => 12,
            'refund_send_on_post' => true,
            'agent_unearn_notice' => true,
            'reminders' => ['quiet_start' => '', 'quiet_end' => ''],
        ]);
        $clear->assertOk();
        $clear->assertJson(['success' => true]);

        $this->assertSame(['start' => null, 'end' => null], ReminderOptions::quietHours($company->id));

        Company::forgetModuleCache();
    }
}
