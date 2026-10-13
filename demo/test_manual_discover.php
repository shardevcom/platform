<?php

require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

echo "Testing discover method step by step:\n";

$basePath = base_path("packages");
echo "Base path: $basePath\n";

$vendorDirs = glob(rtrim(base_path("packages"), "/")."/*", GLOB_ONLYDIR);
echo "Vendor dirs found: " . count($vendorDirs) . "\n";

if ($vendorDirs !== false) {
    foreach ($vendorDirs as $vendorDir) {
        $packageDirs = glob(rtrim($vendorDir, "/")."/*", GLOB_ONLYDIR);
        echo "Vendor dirs found: " . count($packageDirs) . "\n";
        
        if ($packageDirs !== false) {
            foreach ($packageDirs as $dir) {
                $manifestFile = $dir . "/composer.json";
                
                if (! is_file($manifestFile)) {
                    echo "  NOT FOUND: $manifestFile\n";
                    continue;
                }
                
                $decoded = json_decode((string) file_get_contents($manifestFile), true);
                
                if (! is_array($decoded)) {
                    echo "  INVALID JSON\n";
                    continue;
                }
                
                $provider = $decoded["extra"]["platform"]["provider"] ?? null;
                echo "  Provider: " . ($provider ?? "none") . "\n";
                
                $basePathR = rtrim(base_path("packages"), "/");
                $fullPath = rtrim($dir, "/");
                
                if (str_starts_with($fullPath, $basePath . "/")) {
                    $relativePath = substr($fullPath, strlen($basePath) + 1);
                    echo "  Relative path: $relativePath\n";
                    
                    $parts = explode("/", $relativePath);
                    if (count($parts) >= 2) {
                        $vendor = $parts[0];
                        $package = $parts[1];
                        echo "  Vendor: $vendor, Package: $package\n";
                    }
                }
            }
        }
    }
}