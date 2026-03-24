<?php

declare(strict_types=1);

namespace Tests\Unit\DotwAI;

use Tests\TestCase;

/**
 * Unit tests for DotwAI configuration values.
 *
 * Verifies all required config keys exist with correct types
 * and that the system message file is readable.
 *
 * @covers dotwai.php config
 * @see FOUND-02
 * @see FOUND-06
 */
class DotwAIConfigTest extends TestCase
{
    protected bool $skipPermissionSeeder = true;

    public function test_b2b_enabled_config_is_boolean(): void
    {
        $value = config('dotwai.b2b_enabled');

        $this->assertIsBool($value);
    }

    public function test_b2c_enabled_config_is_boolean(): void
    {
        $value = config('dotwai.b2c_enabled');

        $this->assertIsBool($value);
    }

    public function test_system_message_path_resolves_to_readable_file(): void
    {
        $path = config('dotwai.system_message_path');

        $this->assertNotNull($path, 'system_message_path config should be set');
        $this->assertFileExists($path, "System message file should exist at: {$path}");
        $this->assertFileIsReadable($path, "System message file should be readable at: {$path}");
    }

    public function test_config_has_required_keys(): void
    {
        $requiredKeys = [
            'b2b_enabled',
            'b2c_enabled',
            'default_markup_percent',
            'search_results_limit',
            'search_cache_ttl',
            'fuzzy_match_threshold',
            'system_message_path',
            'default_currency',
            'default_nationality',
            'default_residence',
            'display_currency',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertNotNull(
                config("dotwai.{$key}"),
                "Config key 'dotwai.{$key}' should exist and not be null"
            );
        }
    }
}
