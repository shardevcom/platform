<?php

require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

$manifest = new \Shardevcom\Core\PackageManifest();

echo "Testing discover method with reflection:\n";

$reflection = new ReflectionClass($manifest);
$method = $reflection->getMethod("discover");
$method->setAccessible(true);
$packages = $method->invoke($manifest, base_path("packages"));

echo "Found packages:\n";
foreach ($packages as $key => $pkg) {
    echo "  $key => " . ($pkg["provider"] ?? "no provider") . "\n";
}
