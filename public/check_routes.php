<?php

// Quick route checker
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

header('Content-Type: text/plain');

echo "ResailAI Routes:\n";
echo "================\n\n";

$routes = Route::getRoutes();

foreach ($routes as $route) {
    $uri = $route->uri();
    if (strpos($uri, 'resailai') !== false) {
        $methods = implode(', ', $route->methods());
        $name = $route->getName() ?: '(no name)';
        echo sprintf("%-10s %-50s [%s]\n", $methods, $uri, $name);
    }
}

echo "\nDone.\n";
