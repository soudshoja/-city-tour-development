<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $skipPermissionSeeder = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            !$this->skipPermissionSeeder &&
            in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive($this))
        ) {
            $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        }
    }
}
