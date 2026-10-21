#!/usr/bin/env bash
#
# Runs the Dart test suite of every package in the mobile monorepo.
# `flutter test` only looks at the current package's `test/`, so the app root
# has no tests of its own: we fan out to each package.
set -euo pipefail

cd "$(dirname "$0")/.."

flutter pub get

for package in packages/plugin_kit packages/core plugins/product; do
  echo "==> $package"
  # Path packages keep their own package_config; resolve the dependency graph
  # there too so core's passkeys plugin is visible to the plugin tests.
  (cd "$package" && flutter pub get && flutter test)
done
