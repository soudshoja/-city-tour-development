<?php

declare(strict_types=1);

namespace Dotw\Cli\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * YAML configuration loader.
 *
 * Reads ~/.dotw-cli/config.yaml, selects the active profile
 * (default_profile or $profileOverride), and exposes per-key access.
 *
 * On first run, writes a commented starter config if the file is absent.
 */
class Loader
{
    /** @var array<string,mixed> */
    private array $raw = [];

    /** @var array<string,mixed> */
    private array $profile = [];

    public function __construct(?string $profileOverride = null)
    {
        $configPath = $this->expandTilde('~/.dotw-cli/config.yaml');
        $this->ensureConfigDir($configPath);

        if (file_exists($configPath)) {
            $this->raw = Yaml::parseFile($configPath) ?? [];
        } else {
            $this->writeStarterConfig($configPath);
        }

        $profileName = $profileOverride
            ?? ($this->raw['default_profile'] ?? 'default');

        $this->profile = $this->raw['profiles'][$profileName] ?? [];
    }

    /** Get a profile-level key, with optional fallback to top-level raw config. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->profile[$key] ?? $this->raw[$key] ?? $default;
    }

    /** All profile keys for passing to Client or commands. */
    public function all(): array
    {
        return $this->profile;
    }

    private function expandTilde(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
            return $home . substr($path, 1);
        }
        return $path;
    }

    private function ensureConfigDir(string $configPath): void
    {
        $dir = dirname($configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function writeStarterConfig(string $path): void
    {
        $starter = <<<YAML
# DOTW CLI Configuration
# Generated on first run. Edit before use.

default_profile: akeed

profiles:
  akeed:
    username: "YOUR_DOTW_USERNAME"
    password: "YOUR_DOTW_PASSWORD"
    company_code: "YOUR_COMPANY_CODE"
    source: 1
    product: hotel
    endpoint: "https://xml.dotwconnect.com/2018-09-01/Dotw.asmx"
    timeout: 25
    currency: 769         # 769 = KWD
    nationality: 66       # 66 = Kuwait
    residence: 66
    myfatoorah_api_key: ""
    myfatoorah_base_url: "https://api.myfatoorah.com/v2"
    myfatoorah_payment_method_id: 2
    b2b_credit_balance: 0.0
    markup_percent: 20.0

state_db: "~/.dotw-cli/state.db"
YAML;
        file_put_contents($path, $starter);
    }
}
