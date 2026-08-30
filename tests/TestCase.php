<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\GuardsTestDatabaseIsolation;

abstract class TestCase extends BaseTestCase
{
    use GuardsTestDatabaseIsolation;

    protected bool $skipPermissionSeeder = false;

    protected function setUp(): void
    {
        // Must run before parent::setUp() (which creates the application and therefore triggers
        // config/database.php's own env() reads) -- see GuardsTestDatabaseIsolation's class
        // docblock for why. guardTestDatabaseIsolation() derives DB_AUDIT_DATABASE internally
        // (see GuardsTestDatabaseIsolation::deriveAuditDatabaseEnvironment()) -- no separate call
        // needed here.
        $this->guardTestDatabaseIsolation();

        parent::setUp();

        if (
            !$this->skipPermissionSeeder &&
            in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive($this))
        ) {
            $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        }
    }
}
