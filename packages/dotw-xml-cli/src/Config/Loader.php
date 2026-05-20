<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * YAML config loader for dotw-xml-cli.
 * Reads ~/.dotw-xml-cli/config.yaml (separate from dotw-cli).
 * Writes starter config on first run; never prints credentials to stdout.
 */
class Loader
{
    /** @var array<string,mixed> */
    private array $raw = [];

    /** @var array<string,mixed> */
    private array $profile = [];

    public function __construct(?string $profileOverride = null)
    {
        $configPath = $this->expandTilde('~/.dotw-xml-cli/config.yaml');
        $this->ensureConfigDir($configPath);

        if (file_exists($configPath)) {
            $this->raw = Yaml::parseFile($configPath) ?? [];
        } else {
            $this->writeStarterConfig($configPath);
        }

        $profileName   = $profileOverride ?? ($this->raw['default_profile'] ?? 'default');
        $this->profile = $this->raw['profiles'][$profileName] ?? [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->profile[$key] ?? $this->raw[$key] ?? $default;
    }

    /** @return array<string,mixed> */
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
# DOTW XML CLI Configuration
# Generated on first run. Edit before use.
# Config path: ~/.dotw-xml-cli/config.yaml

default_profile: default

profiles:
  default:
    username: "YOUR_DOTW_USERNAME"
    password: "YOUR_DOTW_PASSWORD"
    company_code: "YOUR_COMPANY_CODE"
    source: 1
    product: hotel
    endpoint: "https://xml.dotwconnect.com/2018-09-01/Dotw.asmx"
    timeout: 25
    currency: 769         # 769 = KWD (from xml:get-salutations-ids)
    nationality: 66       # 66 = Kuwait
    residence: 66
YAML;
        file_put_contents($path, $starter);
    }
}
