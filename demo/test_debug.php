<?php

require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

$manifest = new \Shardevcom\Core\PackageManifest();

echo "Testing discover method step by step:\n";

$basePath = base_path("packages");
$found = [];

$vendorDirs = glob(rtrim($basePath, "/")."/*", GLOB_ONLYDIR);
echo "Vendor dirs found: " . count($vendorDirs) . "\n";

if ($vendorDirs !== false) {
    foreach ($vendorDirs as $vendorDir) {
        $packageDirs = glob(rtrim($vendorDir, "/")."/*", GLOB_ONLYDIR);
        if ($packageDirs !== false) {
            foreach ($packageDirs as $dir) {
                $manifestFile = $dir . "/composer.json";
                echo "  Checking: $manifestFile\n";

                if (! is_file($manifestFile)) {
                    echo "  NOT FOUND\n";
                    continue;
                }

                $decoded = json_decode((string) file_get_contents($manifestFile), true);
                if (! is_array($decoded)) {
                    echo "  INVALID JSON\n";
                    continue;
                }

                $provider = $decoded["extra"]["platform"]["provider"] ?? null;
                echo "  Provider: " . ($provider ?? "none") . "\n";

                $relativePath = $dir;
                $basePathR = rtrim($basePath, "/");
                $fullPath = rtrim($dir, "/");

                if (str_starts_with($fullPath, $basePathR . "/")) {
                    $relativePath = substr($fullPath, strlen($basePathR) + 1);
                    echo "  Relative path: $relativePath\n";

                    $parts = explode("/", $relativePath);
                    if (count($parts) >= 2) {
                        $vendor = $parts[0];
                        $package = $parts[1];
                        echo "  Vendor: $vendor, Package: $package\n";
                        $key = "{$vendor}/{$package}";
                        echo "  Key: $key\n";
                    }
                }
            }
        }
    }
}
