<?php

// Simple route checker for ResailAI suppliers
// Run this on production: php check_resailai_routes.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== ResailAI Routes Check ===\n\n";

$routes = Route::getRoutes();

echo "All registered routes:\n";
echo "----------------------\n\n";

foreach ($routes as $route) {
    $uri = $route->uri();
    if (strpos($uri, 'resailai') !== false) {
        $methods = implode(', ', $route->methods());
        $name = $route->getName() ?: '(no name)';
        echo "$methods  $uri  [$name]\n";
    }
}

echo "\n=== Done ===\n";
