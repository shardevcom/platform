<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Filesystem;

/**
 * Boundary: every filesystem side effect of the CLI goes through here.
 *
 * Commands depend on the contract, so a test can run `platform new` against an
 * in-memory filesystem and assert on the files it would have produced.
 */
interface Filesystem
{
    public function exists(string $path): bool;

    public function isDirectory(string $path): bool;

    public function isFile(string $path): bool;

    /**
     * Immediate sub-directories, absolute, sorted. Empty when the path is not a directory.
     *
     * @return list<string>
     */
    public function directories(string $path): array;

    /**
     * Files in a directory (non-recursive), absolute, sorted. Empty when the path is not a directory.
     *
     * @return list<string>
     */
    public function files(string $path): array;

    public function isWritable(string $path): bool;

    public function read(string $path): string;

    /**
     * @return array<string, string>|null null when the file is not valid JSON
     */
    public function readJson(string $path): ?array;

    public function write(string $path, string $contents): void;

    /**
     * @param array<string, mixed> $data
     */
    public function writeJson(string $path, array $data): void;

    public function createDirectory(string $path): void;

    /**
     * Copies a directory tree. Entries whose path contains any of the excluded
     * top-level segment names (e.g. `node_modules`) are skipped, so a shipped
     * template never drags build artifacts into a fresh project.
     *
     * @param list<string> $exclude
     */
    public function copyDirectory(string $from, string $to, array $exclude = []): void;

    /**
     * Moves a directory (and everything under it) to a new location.
     */
    public function moveDirectory(string $from, string $to): void;

    public function touch(string $path): void;

    public function delete(string $path): void;

    public function relativePath(string $path, string $base): string;
}