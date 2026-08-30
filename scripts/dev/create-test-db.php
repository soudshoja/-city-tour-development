<?php

/**
 * Creates a pair of disposable test databases (an application DB and its
 * "_map" sibling) for the accounting test suite, so that multiple agents /
 * developers can each run `php artisan test` against their own isolated
 * databases instead of colliding on a single shared one.
 *
 * Usage:
 *   php scripts/dev/create-test-db.php city_tour_test_agent7
 *
 * This creates:
 *   - city_tour_test_agent7
 *   - city_tour_test_agent7_map
 *
 * SAFETY: the given name (and therefore both created databases) MUST start
 * with "city_tour_test". This script refuses to create anything else, so it
 * can never be pointed at laravel_testing or map_data_citytour by mistake.
 *
 * Credentials are read from the project's .env file (DB_HOST, DB_PORT,
 * DB_USERNAME, DB_PASSWORD) via a minimal parser -- this script does not
 * boot Laravel/artisan on purpose, so it has no chance of accidentally
 * running migrations.
 *
 * See phpunit.xml for how DB_TEST_DATABASE / DB_DATABASE_MAP select which
 * database a given `php artisan test` run targets.
 */

declare(strict_types=1);

const REQUIRED_PREFIX = 'city_tour_test';

function fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function loadEnvFile(string $path): array
{
    if (! is_file($path)) {
        fail("Cannot find .env file at: {$path}");
    }

    $values = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Strip matching surrounding quotes, if any.
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $values[$key] = $value;
    }

    return $values;
}

$name = $argv[1] ?? null;

if (! is_string($name) || $name === '') {
    fail('Usage: php scripts/dev/create-test-db.php <city_tour_test_NAME>');
}

if (! str_starts_with($name, REQUIRED_PREFIX)) {
    fail(
        'Refusing to create database "'.$name.'": name must start with "'.REQUIRED_PREFIX.'". '.
        'This is a hard safety rail so this script can never target laravel_testing or map_data_citytour.'
    );
}

$mapName = $name.'_map';

$envPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'.env';
$env = loadEnvFile($envPath);

$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$username = $env['DB_USERNAME'] ?? null;
$password = $env['DB_PASSWORD'] ?? '';

if ($username === null) {
    fail('DB_USERNAME not found in .env; cannot connect to MySQL to create test databases.');
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fail('Could not connect to MySQL as '.$username.'@'.$host.':'.$port.' -- '.$e->getMessage());
}

// The app DB user (.env DB_USERNAME) is commonly granted privileges scoped
// to specific existing database names only (no global CREATE), which is
// exactly the least-privilege setup you want for an app user. If CREATE
// DATABASE fails for that reason, this script used to silently fall back to
// a hardcoded, no-password local root connection and issue
// `GRANT ALL PRIVILEGES ... ; FLUSH PRIVILEGES` on the app user's behalf --
// no flag, no prompt, no way to opt out. That is a privilege escalation this
// script has no business performing unattended. It has been removed: if the
// app user cannot create the database, this script now prints the exact
// GRANT a human/admin should run and exits non-zero. Nothing here ever
// connects as root, and nothing here ever runs GRANT/FLUSH PRIVILEGES.
foreach ([$name, $mapName] as $db) {
    // Backtick-quoted identifier; $db is already constrained to the
    // city_tour_test prefix above and only ever came from argv, so this is
    // not attacker-controlled input, but escape defensively anyway.
    $safe = str_replace('`', '', $db);

    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        fwrite(STDOUT, "Created (or already existed): {$safe}".PHP_EOL);
    } catch (PDOException $e) {
        $safeUser = str_replace(["'", '\\'], '', $username);
        fail(
            "Could not create database `{$safe}` as {$username} (insufficient privileges): ".$e->getMessage().PHP_EOL.
            'RECOMMENDED (one-time fix -- covers every future city_tour_test_* name, not just this '.
            'one; note the escaped underscores, since MySQL treats a bare "_" as a single-character '.
            'wildcard in GRANT ... ON names, so it must be escaped to mean a literal underscore):'.PHP_EOL.
            "  GRANT ALL PRIVILEGES ON `city\\_tour\\_test%`.* TO '{$safeUser}'@'localhost';".PHP_EOL.
            '  FLUSH PRIVILEGES;'.PHP_EOL.
            'Or, for just this one name, ask an admin with sufficient privileges to run:'.PHP_EOL.
            "  GRANT ALL PRIVILEGES ON `{$safe}`.* TO '{$safeUser}'@'localhost';".PHP_EOL.
            '  FLUSH PRIVILEGES;'.PHP_EOL.
            'or to create the database directly:'.PHP_EOL.
            "  CREATE DATABASE IF NOT EXISTS `{$safe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        );
    }
}

fwrite(STDOUT, PHP_EOL.'Run tests against these databases with, e.g.:'.PHP_EOL);
fwrite(STDOUT, "  DB_TEST_DATABASE={$name} DB_DATABASE_MAP={$mapName} php artisan test ...".PHP_EOL);
fwrite(STDOUT, '  (PowerShell): $env:DB_TEST_DATABASE=\''.$name.'\'; $env:DB_DATABASE_MAP=\''.$mapName.'\'; php artisan test ...'.PHP_EOL);
