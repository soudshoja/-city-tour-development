<?php

namespace Tests\Unit\Support;

use App\Support\Modules;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    public function test_all_lists_every_declared_module_constant(): void
    {
        $this->assertSame(
            [
                Modules::TASK_UPLOADER,
                Modules::PAYMENT_GATEWAY,
                Modules::CRM,
                Modules::AGENT_PROFIT,
                Modules::RESAYIL,
                Modules::ACCOUNTING,
            ],
            Modules::ALL
        );

        // 6 distinct string keys, no accidental duplicates/typos.
        $this->assertCount(6, array_unique(Modules::ALL));
    }

    public function test_setting_key_uses_the_module_prefix(): void
    {
        $this->assertSame('module.accounting', Modules::settingKey(Modules::ACCOUNTING));
        $this->assertSame('module.task_uploader', Modules::settingKey(Modules::TASK_UPLOADER));
    }

    public function test_package_preset_config_covers_exactly_the_known_modules(): void
    {
        $preset = config('modules.package_preset');

        $this->assertIsArray($preset);
        $this->assertEqualsCanonicalizing(Modules::ALL, array_keys($preset));

        // The one required shape: accounting off, everything else on.
        $this->assertFalse($preset[Modules::ACCOUNTING]);
        foreach (Modules::ALL as $module) {
            if ($module === Modules::ACCOUNTING) {
                continue;
            }
            $this->assertTrue($preset[$module], "Expected package_preset[{$module}] to be true.");
        }
    }
}
