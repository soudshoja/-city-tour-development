<?php
/**
 * Cache Clear Tool
 * Run this file via browser to clear all Laravel caches
 */

// Define Laravel path
$app_path = __DIR__.'/../bootstrap/app.php';

if (!file_exists($app_path)) {
    die('<h1>Error: Laravel not found at ' . $app_path . '</h1>');
}

// Bootstrap Laravel
require $app_path;

$app = require_once $app_path;
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');

// Clear all caches
echo "<h2>Clearing Laravel Caches...</h2>";

$commands = [
    'cache:clear' => 'Cache',
    'config:clear' => 'Config',
    'route:clear' => 'Routes',
    'view:clear' => 'Views',
];

echo "<ul>";
foreach ($commands as $command => $name) {
    try {
        $exitCode = $kernel->call($command);
        if ($exitCode === 0) {
            echo "<li style='color: green;'>$name cleared successfully</li>";
        } else {
            echo "<li style='color: orange;'>$name: exit code $exitCode</li>";
        }
    } catch (Exception $e) {
        echo "<li style='color: red;'>$name: " . htmlspecialchars($e->getMessage()) . "</li>";
    }
}
echo "</ul>";

echo "<h3 style='color: green;'>Done! All caches cleared.</h3>";

echo "<p><a href='https://development.citycommerce.group/admin/resailai/suppliers'>Test ResailAI Suppliers Page</a></p>";

// Display cache status
echo "<h3>Cache Status:</h3>";
$cachePath = storage_path('framework/cache/data');
if (is_dir($cachePath)) {
    $files = glob($cachePath . '/*');
    echo "<p>Cache files count: " . count($files) . "</p>";
} else {
    echo "<p>Cache directory not found</p>";
}
