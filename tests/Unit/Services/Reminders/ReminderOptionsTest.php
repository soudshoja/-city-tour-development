<?php

namespace Tests\Unit\Services\Reminders;

use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Reminders\ReminderOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) required test: "quiet-hours shift". Also covers the
 * enabled/channel/offsets resolvers' company-override-vs-config-default behaviour.
 */
class ReminderOptionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $owner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create(['user_id' => $owner->id, 'country_id' => $country->id]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    public function test_quiet_hours_disabled_by_default_does_not_shift(): void
    {
        $at = Carbon::parse('2026-09-01 14:00:00');

        $shifted = ReminderOptions::shiftForQuietHours($this->company->id, $at->copy());

        $this->assertTrue($shifted->equalTo($at));
    }

    public function test_quiet_hours_shifts_a_same_day_window_to_the_window_end(): void
    {
        Setting::updateOrCreate(
            ['key' => 'accounting.reminders.quiet_hours', 'company_id' => $this->company->id],
            ['value' => '13:00-15:00', 'type' => 'string']
        );

        $at = Carbon::parse('2026-09-01 14:00:00');
        $shifted = ReminderOptions::shiftForQuietHours($this->company->id, $at->copy());

        $this->assertSame('2026-09-01 15:00:00', $shifted->toDateTimeString());
    }

    public function test_quiet_hours_leaves_a_time_outside_the_window_unchanged(): void
    {
        Setting::updateOrCreate(
            ['key' => 'accounting.reminders.quiet_hours', 'company_id' => $this->company->id],
            ['value' => '13:00-15:00', 'type' => 'string']
        );

        $at = Carbon::parse('2026-09-01 09:00:00');
        $shifted = ReminderOptions::shiftForQuietHours($this->company->id, $at->copy());

        $this->assertTrue($shifted->equalTo($at));
    }

    public function test_quiet_hours_shifts_an_overnight_window_across_midnight(): void
    {
        Setting::updateOrCreate(
            ['key' => 'accounting.reminders.quiet_hours', 'company_id' => $this->company->id],
            ['value' => '22:00-07:00', 'type' => 'string']
        );

        // Late-evening side of the window -> pushed to 07:00 the NEXT day.
        $late = Carbon::parse('2026-09-01 23:30:00');
        $shiftedLate = ReminderOptions::shiftForQuietHours($this->company->id, $late->copy());
        $this->assertSame('2026-09-02 07:00:00', $shiftedLate->toDateTimeString());

        // Early-morning side of the window (before 07:00) -> pushed to 07:00 the SAME day.
        $early = Carbon::parse('2026-09-02 03:00:00');
        $shiftedEarly = ReminderOptions::shiftForQuietHours($this->company->id, $early->copy());
        $this->assertSame('2026-09-02 07:00:00', $shiftedEarly->toDateTimeString());

        // Outside the window entirely -> unchanged.
        $daytime = Carbon::parse('2026-09-02 12:00:00');
        $shiftedDaytime = ReminderOptions::shiftForQuietHours($this->company->id, $daytime->copy());
        $this->assertTrue($shiftedDaytime->equalTo($daytime));
    }

    public function test_enabled_defaults_true_and_respects_a_company_override(): void
    {
        $this->assertTrue(ReminderOptions::enabled($this->company->id, 'overdue_invoice'));

        Setting::updateOrCreate(
            ['key' => 'accounting.reminders.overdue_invoice.enabled', 'company_id' => $this->company->id],
            ['value' => false, 'type' => 'boolean']
        );

        $this->assertFalse(ReminderOptions::enabled($this->company->id, 'overdue_invoice'));
    }

    public function test_channel_defaults_to_whatsapp_and_respects_a_company_override(): void
    {
        $this->assertSame('whatsapp', ReminderOptions::channel($this->company->id, 'overdue_invoice'));

        Setting::updateOrCreate(
            ['key' => 'accounting.reminders.overdue_invoice.channel', 'company_id' => $this->company->id],
            ['value' => 'both', 'type' => 'string']
        );

        $this->assertSame('both', ReminderOptions::channel($this->company->id, 'overdue_invoice'));
    }

    public function test_overdue_invoice_offsets_days_parses_a_company_override(): void
    {
        $this->assertSame([1, 3, 7, 14, 30], ReminderOptions::overdueInvoiceOffsetsDays($this->company->id));

        Setting::updateOrCreate(
            ['key' => 'accounting.reminders.overdue_invoice.offsets_days', 'company_id' => $this->company->id],
            ['value' => '2, 5,10', 'type' => 'string']
        );

        $this->assertSame([2, 5, 10], ReminderOptions::overdueInvoiceOffsetsDays($this->company->id));
    }
}
