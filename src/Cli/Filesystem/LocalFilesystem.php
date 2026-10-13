<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Filesystem;

use Shardevcom\Cli\Exception\CommandFailed;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Boundary: the real filesystem implementation (plain PHP, no Laravel).
 *
 * Recursive copy replaces the previous `cp -a` shell call, so `platform new`
 * behaves the same on hosts without a POSIX `cp`.
 */
final class LocalFilesystem implements Filesystem
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    public function directories(string $path): array
    {
        $entries = glob(rtrim($path, '/').'/*', GLOB_ONLYDIR);

        if ($entries === false) {
            return [];
        }

        sort($entries);

        return array_values($entries);
    }

    public function files(string $path): array
    {
        $entries = glob(rtrim($path, '/').'/*', GLOB_NOSORT);

        if ($entries === false) {
            return [];
        }

        $files = array_filter($entries, 'is_file');
        sort($files);

        return array_values($files);
    }

    public function read(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw CommandFailed::create("Unable to read file: {$path}");
        }

        return $contents;
    }

    public function readJson(string $path): ?array
    {
        if (! $this->isFile($path)) {
            return null;
        }

        $decoded = json_decode($this->read($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function write(string $path, string $contents): void
    {
        $this->createDirectory(dirname($path));

        if (@file_put_contents($path, $contents) === false) {
            throw CommandFailed::create("Unable to write file: {$path}");
        }
    }

    public function writeJson(string $path, array $data): void
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw CommandFailed::create("Unable to encode JSON for: {$path}");
        }

        $this->write($path, $encoded.PHP_EOL);
    }

    public function createDirectory(string $path): void
    {
        if ($this->isDirectory($path)) {
            return;
        }

        if (! @mkdir($path, 0755, true) && ! $this->isDirectory($path)) {
            throw CommandFailed::create("Unable to create directory: {$path}");
        }
    }

    public function moveDirectory(string $from, string $to): void
    {
        if (! is_dir($from)) {
            throw new \RuntimeException("Cannot move missing directory: {$from}");
        }

        $this->createDirectory(dirname($to));

        if (! rename($from, $to)) {
            throw new \RuntimeException("Unable to move {$from} to {$to}");
        }
    }

    public function copyDirectory(string $from, string $to): void
    {
        if (! $this->isDirectory($from)) {
            throw CommandFailed::create("Source directory not found: {$from}");
        }

        $this->createDirectory($to);

        /** @var SplFileInfo $item */
        foreach ($this->walk($from) as $item) {
            $relative = substr($item->getPathname(), strlen($from) + 1);
            $target = $to.DIRECTORY_SEPARATOR.$relative;

            if ($item->isDir()) {
                $this->createDirectory($target);

                continue;
            }

            $this->createDirectory(dirname($target));
            copy($item->getPathname(), $target);
        }
    }

    public function touch(string $path): void
    {
        $this->createDirectory(dirname($path));

        if (! $this->isFile($path) && @touch($path) === false) {
            throw CommandFailed::create("Unable to create file: {$path}");
        }
    }

    public function delete(string $path): void
    {
        if ($this->isDirectory($path)) {
            foreach (new FilesystemIterator($path) as $item) {
                /** @var SplFileInfo $item */
                $this->delete($item->getPathname());
            }

            @rmdir($path);

            return;
        }

        if ($this->exists($path)) {
            @unlink($path);
        }
    }

    public function relativePath(string $path, string $base): string
    {
        $path = rtrim($path, '/');
        $base = rtrim($base, '/');

        if ($path === $base) {
            return '';
        }

        if (str_starts_with($path, $base.'/')) {
            return substr($path, strlen($base) + 1);
        }

        return $path;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function walk(string $directory): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            yield $item;
        }
    }
}