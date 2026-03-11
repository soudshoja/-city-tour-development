<?php

// Debug script to check if routes are loaded
ob_start();

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "<h2>ResailAI Routes</h2>";
echo "<p>Current request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "</p>";

$routes = Route::getRoutes();

$found = false;
foreach ($routes as $route) {
    $uri = $route->uri();
    if (strpos($uri, 'resailai') !== false) {
        $found = true;
        $methods = implode(', ', $route->methods());
        $name = $route->getName() ?: '(no name)';
        echo "<p><strong>$methods $uri</strong> [$name]</p>";
    }
}

if (!$found) {
    echo "<p>No ResailAI routes found!</p>";
}

echo "<hr><p>Routes loaded from: " . __FILE__ . "</p>";

$content = ob_get_clean();
echo $content;
